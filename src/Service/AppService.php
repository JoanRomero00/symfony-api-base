<?php

/**
 * Utilidades generales de aplicación: CSV, fechas, dinero, archivos ZIP, sanitización y notificaciones.
 */

namespace App\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Exception\ExceptionInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Servicio utilizado para realizar acciones generales (no referido a persistencia) de los sistemas symfony del PJ.
 *
 * @author Martín Maglianesi <mmaglianesi@justiciasantafe.gov.ar>
 */
class AppService
{
    private $_release;

    /**
     * Construct.
     */
    public function __construct(
        private MailerInterface $mailer,
        private Security $security,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
        private JWTImpersonationService $jwtImpersonationService,
    ) {
        $this->_release = $this->getGitInformation();
    }

    public function getRelease()
    {
        return $this->_release;
    }

    public function setRelease($release)
    {
        $this->_release = $release;
    }

    /**
     * Obtiene la ultima versión del software desde git.
     *
     * @param string PATH la raíz donde corre el sistema
     */
    public function getGitInformation(): string
    {
        // Obtengo la raíz donde corre el sistema
        $path = $_ENV['PATH_SISTEMA'];

        // Creo el proceso de git para obtener el ultimo tags
        $process = Process::fromShellCommandline('git describe --tags', $path);
        // Corro el proceso
        $process->run();

        // Si hay alguna falla, logueo el error y retorno la palabra ERROR
        if (!$process->isSuccessful()) {
            $excepcion = new ProcessFailedException($process);
            $this->logger->error($excepcion->getMessage());

            return 'error';
        }

        return $process->getOutput();
    }

    /**
     * Exporta un array de strings a un archivo csv.
     * Retorna true si fue exitoso, o false caso contrario.
     *
     * @param array  $arrayDeStrings array de strings
     * @param string $pathArchivo    path del archivo de salida
     * @param array  $encabezado     Encabezado del archivo csv (opcional)
     *
     * @return bool $Retorna true si fue exitoso, o false caso contrario
     */
    public function exportarArrayACSV(
        array $arrayDeStrings,
        string $pathArchivo,
        ?array $encabezado,
    ): bool {
        try {
            // Si no es null, se agrega el encabezado al resultado de la consulta
            if (!is_null($encabezado)) {
                array_unshift($arrayDeStrings, $encabezado);
            }

            // Se crea un serializador para codificar en formato CSV
            $serializer = new Serializer(
                [new ObjectNormalizer()],
                [new CsvEncoder()]
            );
            // Se serializa el array resultado de la consulta de BD pasado como parametro, a formato CSV
            $csvData = $serializer->encode($arrayDeStrings, 'csv', [
                'csv_delimiter' => ';', // Se setea el punto y coma como delimitador del archivo csv
                'no_headers' => true, // Se setea para que no incluya el header por defecto, que son las keys del array
            ]);

            // Se escribe en el archivo pasado como parametro
            $filesystem = new Filesystem();
            $filesystem->dumpFile($pathArchivo, $csvData);

            return true;
        } catch (ExceptionInterface|IOException $e) {
            $this->logger->error(
                'Error al serializar los datos, o al querer escribir en el sistema de archivos.'
            );
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            return false;
        }
    }

    /**
     * Convierte un array asociativo a formato csv, y lo devuelve como string.
     *
     * @param array $array asociativo
     *
     * @return string devuelve el csv en tipo string
     */
    public function arrayToCsv(array $array, bool $encabezado = true): string
    {
        $output = fopen('php://temp', 'r+');

        // Si se pide encabezado, escribir
        if ($encabezado) {
            // Write the header row with keys as column names
            fputcsv($output, array_keys($array[0]), ';');
        }

        foreach ($array as $row) {
            // si algun valor es de tipo DateTime, se lo parsea a string
            foreach ($row as &$value) {
                if ($value instanceof \DateTime) {
                    $value = $value->format('d-m-Y');
                }
            }

            // escribo la linea
            fputcsv($output, $row, ';');
        }

        rewind($output);

        $csvContent = stream_get_contents($output);

        fclose($output);

        return $csvContent;
    }

    /**
     * Sanitiza el string pasado como argumento, para hacerlo seguro como nombre de archivo.
     *
     * @param string $nombreArchivo nombre del archivo a sanitizar
     *
     * @return string Retorna el nombre del archivo sanitizado
     */
    public function sanitizarNombreArchivo($nombreArchivo): string
    {
        $extensionArchivo = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
        $nombreArchivoString = pathinfo($nombreArchivo, PATHINFO_FILENAME);

        // Replaces all spaces with hyphens.
        $nombreArchivoString = str_replace(' ', '-', $nombreArchivoString);
        // Removes special chars.
        $nombreArchivoString = preg_replace(
            "/[^A-Za-z0-9\-\_]/",
            '',
            $nombreArchivoString
        );
        // Replaces multiple hyphens with single one.
        $nombreArchivoString = preg_replace('/-+/', '-', $nombreArchivoString);

        $nombreArchivoSanitizado =
            $nombreArchivoString.'.'.$extensionArchivo;

        return $nombreArchivoSanitizado;
    }

    /**
     * Convierte una fecha en numeros a texto.
     *
     * @param \DateTime $fecha fecha a convertir
     *
     * @return string Retorna la fecha en texto
     */
    public function convertirFechaATexto(\DateTime $fecha): string
    {
        if (!is_a($fecha, 'DateTime')) {
            throw new \InvalidArgumentException('Error de tipo del parametro.');
        }

        $formatterES = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);

        $diaNumero = date_format($fecha, 'j');
        $diaPalabra = $formatterES->format($diaNumero);

        $mesNumero = date_format($fecha, 'n');
        $mesPalabra = $this->getNombreMes($mesNumero);

        $anioNumero = date_format($fecha, 'Y');
        $anioPalabra = $formatterES->format($anioNumero);

        $fechaTexto = sprintf("$diaPalabra de $mesPalabra de $anioPalabra");

        return $fechaTexto;
    }

    /**
     * Retorna el nombre del mes en español a partir de su número.
     *
     * @param int $mes Número del mes (1-12)
     *
     * @return string Retorna el nombre del mes en español
     */
    public function getNombreMes(int $mes): string
    {
        switch ($mes) {
            case 1:
                $mesPalabra = 'Enero';
                break;
            case 2:
                $mesPalabra = 'Febrero';
                break;
            case 3:
                $mesPalabra = 'Marzo';
                break;
            case 4:
                $mesPalabra = 'Abril';
                break;
            case 5:
                $mesPalabra = 'Mayo';
                break;
            case 6:
                $mesPalabra = 'Junio';
                break;
            case 7:
                $mesPalabra = 'Julio';
                break;
            case 8:
                $mesPalabra = 'Agosto';
                break;
            case 9:
                $mesPalabra = 'Septiembre';
                break;
            case 10:
                $mesPalabra = 'Octubre';
                break;
            case 11:
                $mesPalabra = 'Noviembre';
                break;
            case 12:
                $mesPalabra = 'Diciembre';
        }

        return $mesPalabra;
    }

    /**
     * formatearFechaParaNota Formatea una fecha para ser usada como fecha de una nota.
     *
     * @param mixed $dateTime
     */
    public function formatearFechaParaNota(\DateTimeInterface $dateTime): string
    {
        $formatter = new \IntlDateFormatter(
            'es_ES',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE
        );

        $formatter->setPattern('d MMMM y');

        $fecha = $formatter->format($dateTime);

        $tokens = explode(' ', $fecha);

        $fecha = $tokens[0].' de '.$tokens[1].' de '.$tokens[2];

        return $fecha;
    }

    /**
     * errorNotification.
     *
     * Envía un correo a lista de destinatarios definida en variable de entorno RECIPIENTS_MESSAGES_NOTIFICACIONS
     * con información del código de error, su traza completa y se complementa con datos del usuario y su dirección IP
     *
     * @author Martín Maglianesi <mmaglianesi@justiciasantafe.gov.ar>
     */
    public function errorNotification(Request $request)
    {
        $clienteIp = $request->getClientIp();
        $user = $this->jwtImpersonationService->getRealUserFromPayload();
        $errorCode = $request->request->get('errorCode');
        $message = $request->request->get('message');
        $statusText = $request->request->get('statusText');
        $previus = $request->request->get('previus');
        $file = $request->request->get('file');
        $line = $request->request->get('line');
        $traceMessage = $request->request->get('traceMessage');
        $fromAdrress = $_ENV['MAIL_FROM'];
        $destinatarios = explode(
            ',',
            $_ENV['RECIPIENTS_MESSAGES_NOTIFICACIONS']
        );

        try {
            $email = (new TemplatedEmail())->from($fromAdrress);

            foreach ($destinatarios as $destinatario) {
                $email->addTo(trim($destinatario));
            }

            $nombreSistema = $_ENV['APP_NAME'];

            $email
                ->subject(
                    $nombreSistema.' - Error '.$errorCode
                )
                ->htmlTemplate('app/error_notification.html.twig')
                ->context([
                    'nombreSistema' => $nombreSistema,
                    'expiration_date' => new \DateTime('+7 days'),
                    'clienteIp' => $clienteIp,
                    'user' => $user,
                    'errorCode' => $errorCode,
                    'message' => $message,
                    'previus' => $previus,
                    'file' => $file,
                    'line' => $line,
                    'statusText' => $statusText,
                    'traceMessage' => $traceMessage,
                ]);
            $this->mailer->send($email);

            return true;
        } catch (\Symfony\Component\Mime\Exception\RfcComplianceException $e) {
            return false;
        }
    }

    /**
     * convertObjectToJson.
     *
     * @param object $object el objeto a ser convertido
     *
     * @return string devuelve un string en formato json del objeto
     */
    public function convertObjectToJson(object $object): string
    {
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];

        $serializer = new Serializer($normalizers, $encoders);

        $jsonContent = $serializer->serialize($object, 'json');

        return $jsonContent;
    }

    /**
     * crearYZippearArchivos Crea archivos y los zippea, a partir de los strings pasados como parametro, y devuelve un path al zip que se creo.
     * Usa las claves de cada elemento pasado como parametro para darle nombre al archivo.
     *
     * @return string path al archivo .zip
     */
    public function crearYZippearArchivos(array $contenidoArchivos): string
    {
        // Create a temporary directory to store files
        $tempDir = sys_get_temp_dir();

        // Create a ZIP file
        $zipPath = $tempDir.'/files.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach ($contenidoArchivos as $clave => $contenidoArchivo) {
            $file = $tempDir.'/'.$clave;

            // Write content to temporary files
            file_put_contents($file, $contenidoArchivo);
            // Agregar archivo al zip, y darle nombre y extension a partir de su clave
            $zip->addFile($file, $clave);
        }

        $zip->close();

        // Retornar path al .zip
        return $zipPath;
    }

    /**
     * Convierte un número (hasta 999.999.999,99) a texto en español mayuscula.
     * Ejemplo: 1200000.25 → "un millón doscientos mil con veinticinco centavos".
     */
    public function numeroATexto($numero): string
    {
        $entero = floor($numero);
        $centavos = round(($numero - $entero) * 100);

        $formatter = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
        $texto = $formatter->format($entero);

        if ($centavos > 0) {
            $texto .= ' con '.$formatter->format($centavos).' centavos';
        }

        return mb_strtoupper($texto);
    }

    /**
     * Compara dos importes considerando precisión decimal.
     */
    public function sonImportesIguales(
        float $importe1,
        float $importe2,
        int $precision = 2,
    ): bool {
        return abs(
            round($importe1, $precision) - round($importe2, $precision)
        ) < 0.01;
    }

    public function formatearMoneda(float $importe): string
    {
        $formatter = new \NumberFormatter('es_AR', \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return $formatter->formatCurrency($importe, 'ARS');
    }

    /**
     * Trunca una cadena a un máximo de caracteres incluyendo los tres puntos '...'.
     * Si la cadena es null, devuelve null.
     */
    public function truncarTexto(?string $texto, int $max = 100): ?string
    {
        if ($texto === null) {
            return null;
        }

        // Usar funciones mb para soporte multibyte
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max - 3).'...';
    }
}
