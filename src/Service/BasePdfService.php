<?php

/**
 * Clase base para generación de PDFs con wkhtmltopdf. Extender por proyecto para cada tipo de PDF.
 */

namespace App\Service;

use Knp\Snappy\Pdf;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Clase Base para la generación de reportes en PDF.
 * Encargada de setear configuraciones comunes a todos los reportes.
 *
 * @author Juani Alarcón <jialarcon@justiciasantafe.gov.ar>
 */
class BasePdfService
{
    /**
     * Constructor.
     */
    public function __construct(private Pdf $pdf, Security $security)
    {
        // Seteo el usuario al reporte (Por pedido del usuario se quiza este dato en los reportes)
        $this->pdf->setOption('footer-center', mb_strtoupper($security->getUser()));
    }

    /**
     * Retorna instancia de objeto Pdf.
     */
    public function getPdf(): Pdf
    {
        return $this->pdf;
    }

    /**
     * Setea el Footer Izquierdo con la Fecha de Emisión.
     *
     * @param \DateTime $fechaEmision Fecha de emisión del reporte
     */
    public function setFooterLeft(\DateTime $fechaEmision): void
    {
        $this->pdf->setOption('footer-left', sprintf('EMITIDO EL %s', $fechaEmision->format('d M Y')));
    }

    /**
     * Setea la pagina en formato apaisado.
     */
    public function setApaisado(): void
    {
        $this->pdf->setOption('orientation', 'landscape');
    }

    /**
     * Setea la pagina para encuadernar.
     */
    public function setEncuadernado(): void
    {
        $this->pdf->setOption('margin-left', '1.5cm');
    }

    /**
     * Setea la pagina en formato vertical.
     */
    public function setVertical(): void
    {
        $this->pdf->setOption('orientation', 'portrait');
    }

    /**
     * Quita el pie de página por defecto.
     */
    public function resetFooter(): void
    {
        $this->pdf->setOption('footer-left', null);
        $this->pdf->setOption('footer-center', null);
        $this->pdf->setOption('footer-right', null);
        $this->pdf->setOption('footer-line', null);
    }
}
