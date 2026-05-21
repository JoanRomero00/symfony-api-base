<?php

/**
 * Gestiona el sistema de auditoría de base de datos: activa, pausa, reanuda y elimina auditoría por tabla.
 */

namespace App\Service;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuditoriaService
 * Servicio utilizado para realizar acciones generales de Auditoría de Datos.
 *
 * @author Martín Maglianesi <mmaglianesi@justiciasantafe.gov.ar>
 *
 * Refactorización
 * @author Juani Alarcón <jialarcon@justiciasantafe.gov.ar>
 */
class AuditoriaService
{
    private $entitiesAuditables;
    private $entitiesList;
    private $schemaAuditName;   // Nombre del Esquema de Auditoría
    private $schemaDataName;    // Nombre del Esquema sobre que el se realiza la Auditoría
    private $auditNameSuffix;   // Sufijo sobre los nombres de las tablas de Auditoría
    private $auditEntitiesList;
    private $auditAllEntitiesExcept;

    public function __construct(private EntityManagerInterface $entityManager, private AppService $appService, ContainerBagInterface $contenedorParametros)
    {
        $this->entitiesList = $this->obtainEntitiesList();

        // Obtenemos los datos globales configurados en services.yaml
        $this->schemaAuditName = $contenedorParametros->get('app.audit_shemma_audit_name');
        $this->schemaDataName = $contenedorParametros->get('app.audit_shemma_data_name');
        $this->auditNameSuffix = $contenedorParametros->get('app.audit_name_suffix');
        $this->auditEntitiesList = $contenedorParametros->get('app.audit_entity_list');
        $this->auditAllEntitiesExcept = $contenedorParametros->get('app.audit_all_entities_except');

        if (!empty($this->auditEntitiesList)) {
            $this->entitiesAuditables = $this->obtainEntitiesAuditables();
        }
        if (!empty($this->auditAllEntitiesExcept)) {
            $this->entitiesAuditables = $this->obtainAllEntitiesListForAudit();
        }
    }

    /**
     * Ante un evento de DELETE, graba en el evento de auditoría asociado el ID del Usuario (a nivel de Aplicación) que generó la acción.
     *
     * @param string nombreEntidad
     * @param int idEntidad
     * @param int idUsuario
     */
    public function stampUserIdDelete(string $nombreEntidad, int $idEntidad, int $idUsuario): void
    {
        $query = sprintf("SELECT %s.acp_audit_userid_delete('%s', %d, %d)", $this->schemaAuditName, $this->getTableName($nombreEntidad), $idEntidad, $idUsuario);
        $resultado = $this->entityManager
            ->getConnection()
            ->prepare($query)
            ->executeQuery()
            ->fetchOne();
        // Manejo los posibles errores
        switch ($resultado) {
            case -1:
            case 0:
                $request = new Request();
                $request->attributes->set('errorCode', Response::HTTP_INTERNAL_SERVER_ERROR);
                $request->attributes->set('message', 'Fallo en generación de Auditoría');
                $request->attributes->set('statusText', sprintf('Fallo al intentar auditar eliminación de entidad %s, con idEntidad %d para el usuario %d', $nombreEntidad, $idEntidad, $idUsuario));
                $request->attributes->set('file', (new \ReflectionClass($this))->getShortName());
                $request->attributes->set('line', 54);
                $request->attributes->set('traceMessage', $resultado == -1 ? sprintf('Función postgres %s.acp_audit_userid_delete esta retornando 42P01 cuando se ejecuta', $this->schemaAuditName) : sprintf('Función postgres %s.acp_audit_userid_delete no está updetando ningun registro (cosa que es imposible ˙◠˙)', $this->schemaAuditName));
                // Envío la notificación
                $this->appService->errorNotification($request);
                break;
        }
    }

    /**
     * Obtiene Entidades Auditables a partir de las definidas en el parámetro app.audit_entity_list en el service.yaml
     * Este método se ejecuta por única vez al momento de instanciar la clase.
     */
    private function obtainEntitiesAuditables(): array
    {
        try {
            $entitiesAuditables = explode(',', $this->auditEntitiesList);

            foreach ($entitiesAuditables as &$entitieAuditable) {
                $entitieAuditable = $this->entiyNameToFullyQualifiedName(trim($entitieAuditable));
            }

            return $entitiesAuditables;
        } catch (\ErrorException $e) {
            return []; // Retorna un array vacío si no se encuentra definida la variable de entorno
        }
    }

    /**
     * Obtiene lista de Entidades gestionadas por Doctrine
     * Este método se ejecuta por única vez al momento de instanciar la clase.
     */
    private function obtainEntitiesList(): array
    {
        $entities = [];

        $metas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        foreach ($metas as $meta) {
            $entities[] = $meta->getName();
        }

        return $entities;
    }

    /**
     * Establece todas las entidades del Sistema como Auditables excepto las definidas en el parámetro app.audit_all_entities_except del archivo service.yaml
     * Este método se ejecuta por única vez al momento de instanciar la clase.
     */
    private function obtainAllEntitiesListForAudit(): array
    {
        $entitiesForAudit = [];
        $nonAuditableEntityList = array_map('strtolower', array_map('trim', explode(',', $this->auditAllEntitiesExcept))); // Quito espacios y transformo todo a minúsculas para comparar después
        $prefixEntityFullyQualifiedName = 'App\\Entity\\';  // Para eliminar prefijo de los nombres de las Entidades del Sistema
        foreach ($this->entitiesList as $entity) {
            $entityName = strtolower(substr(trim($entity), strlen($prefixEntityFullyQualifiedName))); // Para cada entidad del Sistema obtengo el nombre en minúsculas sin el prefijo Fully Qualified
            if (!in_array($entityName, $nonAuditableEntityList)) { // Si la entidad  no se encuentra en la lista de excepción la incluye en la lista de entidades para auditar
                $entitiesForAudit[] = $entity;
            }
        }

        return $entitiesForAudit;
    }

    /**
     * Obtiene nombre la Tabla en Base de Datos de una Entidad.
     *
     * @return string Nombre de Tabla en Base de Datos asociada a la Entidad
     */
    public function getTableName(string $entityName): string
    {
        $entityName = $this->entiyNameToFullyQualifiedName($entityName);

        try {
            $metaData = $this->entityManager->getMetadataFactory()->getMetadataFor($entityName);

            return $metaData->table['name'];
        } catch (MappingException $e) {
            return '';
        }
    }

    /**
     * Verifica si una entidad esta preparada para Auditoría
     * (se verifica la existencia de las propiedades lastUserAppId y storeId y de los métodos setLastUserAppId(), getStoreId() y setStoreId()).
     */
    public function isReadyToAudit(string $entityName): bool
    {
        try {
            $metaData = $this->entityManager->getMetadataFactory()->getMetadataFor($entityName)->getReflectionClass();

            return
                $metaData->getProperty('lastUserAppId')
                && $metaData->getProperty('storeId')
                && $metaData->getMethod('setLastUserAppId')
                && $metaData->getMethod('getStoreId')
                && $metaData->getMethod('setStoreId')
            ;
        } catch (MappingException $e) {
            return false;
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Obtiene información de tamaño y cantidad de registros de una tabla de la Base de Datos.
     *
     * @param string Nombre de la tabla
     *
     * @return mixed array tableInfo | bool
     */
    public function getTableInfo(string $tableName): mixed
    {
        $query = sprintf("SELECT count(*) as registros, (SELECT pg_size_pretty( pg_total_relation_size('%s') )) as size FROM %s", $tableName, $tableName);

        try {
            $result = $this->entityManager
                ->getConnection()
                ->prepare($query)
                ->executeQuery()
                ->fetchAssociative();

            return $result;
        } catch (TableNotFoundException $e) {
            return false;
        }
    }

    /**
     * Obtiene primer y último registro de un campo basado en la cronología de auditoría.
     *
     * @return array|int [first, last] | 0 si la tabla no existe
     */
    public function getValueFirstLastFieldTable(string $tableName, string $fieldName): mixed
    {
        // Usamos audit_timestamp para garantizar el orden temporal
        // %1$s = fieldName, %2$s = tableName
        $query = sprintf('
        SELECT 
            (SELECT %1$s FROM %2$s ORDER BY audit_timestamp ASC LIMIT 1) as "first",
            (SELECT %1$s FROM %2$s ORDER BY audit_timestamp DESC LIMIT 1) as "last"',
            $fieldName,
            $tableName
        );

        try {
            $result = $this->entityManager
                ->getConnection()
                ->prepare($query)
                ->executeQuery()
                ->fetchAssociative();

            // Si la tabla está vacía, fetchAssociative devuelve false o null
            return $result ?: ['first' => null, 'last' => null];
        } catch (TableNotFoundException $e) {
            return 0;
        }
    }

    /**
     * Determina si la entidad es Auditada.
     */
    public function isAudited(string $entityName): bool
    {
        // Convierto todo a minúsculas para hacer una búsqueda insensible a Mayúsculas y Minúsculas
        $entityName = strtolower($entityName);
        $entityFullyQualifiedName = strtolower($this->entiyNameToFullyQualifiedName(trim($entityName)));

        return
            array_search($entityName, array_map('strtolower', $this->getEntitiesAuditables())) !== false
            || array_search($entityFullyQualifiedName, array_map('strtolower', $this->getEntitiesAuditables())) !== false
        ;
    }

    /**
     * Obtiene nombre la Tabla de Auditoria asociada a la Entidad.
     *
     * @return string Nombre de Tabla de Auditoria en Base de Datos asociada a la Entidad
     */
    public function getAuditTableName(string $entityName): string
    {
        return $this->schemaAuditName.'.'.$entityName.$this->auditNameSuffix;
    }

    /**
     * Verifica si existe tabla de datos de Auditoria asociada a la Entidad.
     */
    private function isExistTableAudit(string $entityName): bool
    {
        $query = sprintf("SELECT count(*) FROM information_schema.columns WHERE table_schema ilike '%s' AND table_name ilike '%s%s'", $this->schemaAuditName, $entityName, $this->auditNameSuffix);

        try {
            $result = $this->entityManager
                ->getConnection()
                ->prepare($query)
                ->executeQuery()
                ->fetchOne();

            if ($result) {
                return true;
            }

            return false;
        } catch (InvalidFieldNameException $e) {
            return false;
        } catch (TableNotFoundException $e) {
            return false;
        }
    }

    /**
     * Verifica si existen los trigger de auditoría a nivel de Base de Datos para la Entidad.
     */
    private function isExistTriggerAudit(string $entityName): bool
    {
        $query = sprintf("SELECT count(*) FROM information_schema.triggers WHERE trigger_schema ilike '%s' AND trigger_name ilike '%s%s_upd'", $this->schemaDataName, $entityName, $this->auditNameSuffix);

        try {
            $result = $this->entityManager
                ->getConnection()
                ->prepare($query)
                ->executeQuery()
                ->fetchOne();

            // Verifico que existan los trigger de INSERT, DELETE y UPDATE
            return $result == 3;
        } catch (InvalidFieldNameException $e) {
            return false;
        } catch (TableNotFoundException $e) {
            return false;
        }
    }

    /**
     * Obtiene Configuración de Auditoría por cada Entidad a nivel de Aplicación.
     */
    public function getConfig(): array
    {
        $auditConfig = [];
        $i = 0;

        foreach ($this->getEntitiesList() as $entity) {
            $tableName = $this->getTableName($entity);
            $tableInfo = $this->getTableInfo($this->schemaDataName.'.'.$tableName);
            $existTableAudit = $this->isExistTableAudit($tableName);
            $existTriggerAudit = $this->isExistTriggerAudit($tableName);
            $isAuditable = $this->isReadyToAudit($entity);
            $isAudited = $this->isAudited($entity);

            if ($tableInfo) {
                $auditConfig[$i]['id'] = $i;
                $auditConfig[$i]['entidad'] = substr($entity, 11); // Me quedo con el nombre de la entidad (sin el App/Entity)
                $auditConfig[$i]['tableName'] = $tableName;
                $auditConfig[$i]['cntRegister'] = $tableInfo['registros'] ?? '';
                $auditConfig[$i]['size'] = $tableInfo['size'] ?? '';
                $auditConfig[$i]['isAuditable'] = $isAuditable;
                $auditConfig[$i]['estado'] = ''; // Auxiliar donde se implementará lógica de estado (Auditoría inactiva, activa, pausada)

                if ($isAudited) {
                    $auditConfig[$i]['isAudited'] = true;
                } else {
                    $auditConfig[$i]['isAudited'] = false;
                }

                if ($isAudited && !$isAuditable) {
                    $auditConfig[$i]['isAudited'] = null;   // Retorna null para mostrar advertencia visual que está configurada
                    // en el .env pero no implementa los métodos de auditoría
                }

                $auditConfig[$i]['existTableAudit'] = $existTableAudit;
                $auditConfig[$i]['existTriggerAudit'] = $existTriggerAudit;

                if ($existTableAudit) {
                    // Usamos una variable distinta para no pisar la $tableInfo de la tabla principal
                    $tableInfoAudit = $this->getTableInfo($this->getAuditTableName($tableName));
                    $firstLast = $this->getValueFirstLastFieldTable($this->getAuditTableName($tableName), 'audit_timestamp');

                    $auditConfig[$i]['cntRegisterAudit'] = $tableInfoAudit['registros'] ?? 0;
                    $auditConfig[$i]['sizeAudit'] = $tableInfoAudit['size'] ?? '';
                    $auditConfig[$i]['first'] = $firstLast['first'] ?? null;
                    $auditConfig[$i]['last'] = $firstLast['last'] ?? null;
                } else {
                    $auditConfig[$i]['cntRegisterAudit'] = 0;
                    $auditConfig[$i]['sizeAudit'] = '';
                    $auditConfig[$i]['first'] = null;
                    $auditConfig[$i]['last'] = null;
                }

                // Calcular el estado de la auditoría para la entidad
                $auditConfig[$i]['estado'] = match (true) {
                    // Cuando a la entidad le faltan atributos para ser auditada, tal como storeId --> si se quiere, agregar atributos
                    (!$isAuditable) => 'FALTAN ATRIBUTOS',
                    // Cuando la entidad tiene lo anterior, pero no se encuentra entre las entidades a auditar configuradas en el .env --> si quiere, agregar a la configuración del .env
                    (!$isAudited) => 'NO AUDITABLE',
                    // Cuando la entidad tiene lo anterior, pero no tiene tabla de auditoría creada en la base de datos (lo que implica que no se ha ejecutado la función de activación para esa entidad)
                    (!$existTableAudit) => 'NO ACTIVADA',
                    // Cuando la entidad tiene el trigger de auditoría pausado/eliminado.
                    (!$existTriggerAudit) => 'PAUSADA',
                    default => 'ACTIVA', // Cuando la entidad está efectivamente siendo auditada
                };
            }
            ++$i;
        }

        return $auditConfig;
    }

    /**
     * Activa la auditoría a nivel de Base de Datos para la Entidad.
     */
    public function activate(string $tableName): string
    {
        // IMPORTANTE: EL PROCESO DE CREACIÓN SE ENCUENTRA RESTRINGIDO A NOMBRES DE TABLAS DE HASTA 31 CARACTERES. SI ES MAYOR FALLARÁ POR RESTRICCIÓN DE LONGITUD DE NOMBRE DE ARCHIVOS DE INDICE
        // EN ESE CASO, SE DEBERA ACHICAR EL NOMBRE DE LA ENTIDAD O EL NOMBRE DE LOS INDICES QUE SE CREAN ASOCIADOS A LA ENTIDAD DE AUDITORIA EN "audit_personal.acp_audit_table"
        $query = sprintf("SELECT %s.acp_audit_table('%s.%s')", $this->schemaAuditName, $this->schemaDataName, $tableName);

        try {
            $this->entityManager
                ->getConnection()
                ->prepare($query)
                ->executeQuery()
                ->fetchOne();

            return '';
        } catch (TableNotFoundException $e) {
            return 'La tabla a Auditar no existe!';
        } catch (TableExistsException $e) {
            return 'La tabla de Auditoría ya existe!';
        } catch (DriverException  $e) {
            return 'Error en función activate. Ya existe un objeto similar al que se intentó crear!<br>Verifique que no exista la tabla o trigger de Auditoría para la tabla.<hr><small>'.$e.'</small>';
        }
    }

    /**
     * Detiene la auditoría a nivel de Base de Datos para la Entidad.
     */
    public function pause(string $tableName): string
    {
        $query = sprintf('DROP TRIGGER IF EXISTS %s%s_upd ON %s.%s', $tableName, $this->auditNameSuffix, $this->schemaDataName, $tableName);

        try {
            $this->entityManager
                ->getConnection()
                ->prepare($query)
                ->executeQuery()
                ->fetchOne();

            return '';
        } catch (DriverException  $e) {
            return 'Error en función pause. Ya existe un objeto similar al que se intentó crear!<br>Verifique que no exista la tabla o trigger de Auditoría para la tabla.<hr><small>'.$e.'</small>';
        }
    }

    /**
     * Reanuda la auditoría a nivel de Base de Datos para la Entidad.
     */
    public function resume(string $tableName): string
    {
        // CREATE TRIGGER administrador_audit_upd BEFORE INSERT OR DELETE OR UPDATE  ON personal.administrador FOR EACH ROW EXECUTE FUNCTION audit_personal.administrador_audit_upd()
        $query = sprintf('CREATE TRIGGER %s%s_upd BEFORE INSERT OR DELETE OR UPDATE ON %s.%s FOR EACH ROW EXECUTE FUNCTION %s.%s%s_upd()', $tableName, $this->auditNameSuffix, $this->schemaDataName, $tableName, $this->schemaAuditName, $tableName, $this->auditNameSuffix);
        // $query = sprintf("CREATE TRIGGER %s%s_upd BEFORE INSERT OR DELETE OR UPDATE ON %s.%s FOR EACH ROW EXECUTE FUNCTION %s.%s%s_upd()", $tableName, $this->auditNameSuffix, $this->schemaAuditName, $tableName, $this->schemaDataName, $tableName, $this->auditNameSuffix);

        try {
            $this->entityManager
                ->getConnection()
                ->prepare($query)
                ->executeQuery()
                ->fetchOne();

            return '';
        } catch (DriverException  $e) {
            return 'Error en función resume. Ya existe un objeto similar al que se intentó crear!<br>Verifique que no exista la tabla o trigger de Auditoría para la tabla.<hr><small>'.$e.'</small>';
        }
    }

    /**
     * Borra tabla de Auditoría a nivel de Base de Datos para la Entidad.
     *
     * @return string
     */
    public function delete(string $tableName)
    {
        $query1 = sprintf('DROP TABLE IF EXISTS %s.%s%s', $this->schemaAuditName, $tableName, $this->auditNameSuffix);
        $query2 = sprintf('DROP SEQUENCE IF EXISTS %s.%s%s_id_seq', $this->schemaAuditName, $tableName, $this->auditNameSuffix);

        try {
            $this->entityManager
                ->getConnection()
                ->prepare($query1)
                ->executeQuery()
                ->fetchOne();
            $this->entityManager
                ->getConnection()
                ->prepare($query2)
                ->executeQuery()
                ->fetchOne();

            return '';
        } catch (DriverException  $e) {
            return 'Se ha producido un error.<hr><small>'.$e.'</small>';
        }
    }

    /**
     * Obtiene eventos de auditoría sobre una tabla.
     */
    public function getAuditoria(
        string $entityName,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?int $registroEntidadId = null,
        ?int $idUsuario = null,
        ?string $tipoOperacion = null,
        ?int $limiteResultados = null,
        bool $incluirModificaciones,
    ): array {
        $tableName = $this->getTableName($entityName);

        if (!$tableName) {
            throw new \LogicException('No se pudo determinar el nombre de la tabla de auditoría');
        }

        $query = sprintf("SELECT a.id, a.audit_timestamp, a.audit_iduserapp, a.audit_identity, 
                    CASE audit_action
                        WHEN 'I' THEN 'Alta'
                        WHEN 'U' THEN 'Modificación' --En this->auditDiff se refencia el tipo por 'Modificación'
                        WHEN 'D' THEN 'Baja'
                    ELSE 
                        ''
                    END as audit_action,
                    a.old_data,
                    a.new_data,
                    %s.jsonb_delta(old_data, new_data) as diff,
                    u.username,
                    count(*) OVER() AS total_registros,
                    '%s' as Entidad
                FROM %s.%s%s a
                INNER JOIN %s.usuario u ON u.id = a.audit_iduserapp", $this->schemaAuditName, $tableName, $this->schemaAuditName, $tableName, $this->auditNameSuffix, $this->schemaDataName);
        if ($fechaDesde && $fechaHasta) {
            $query = sprintf("%s WHERE a.audit_timestamp BETWEEN '%s' AND '%s'", $query, $fechaDesde, $fechaHasta);
        }

        if (!is_null($registroEntidadId)) {
            $query = sprintf('%s AND a.audit_identity = %s', $query, $registroEntidadId);
        }
        if (!is_null($idUsuario)) {
            $query = sprintf('%s AND u.id = %s', $query, $idUsuario);
        }
        if (!is_null($tipoOperacion)) {
            $query = sprintf("%s AND upper(audit_action) like upper('%s')", $query, $tipoOperacion);
        }

        $query = sprintf('%s ORDER BY a.id', $query);

        if (!is_null($limiteResultados)) {
            $query = sprintf('%s LIMIT %s', $query, $limiteResultados);
        }

        $result = $this->entityManager
            ->getConnection()
            ->prepare($query)
            ->executeQuery()
            ->fetchAllAssociative();

        // Obtienen diferencias en caso que se encuentre tildado la Inclusión del detalle de las Modificaiones
        if ($incluirModificaciones) {
            $result = $this->auditDiff($result); // Obtengo Diff para todas las operaciones del Registro
        }

        return $result;
    }

    /**
     * Obtiene eventos de auditoría sobre entidades asociadas a un Legajo.
     *
     * @param int $idPersona
     */
    public function getAuditoriaLegajobyEntity(?string $nameForeignKkeyField = null, $idPersona = 0, ?string $entityName = null): array
    {
        $tableName = $this->getTableName($entityName);

        if (!$nameForeignKkeyField) {
            throw new \LogicException('Se debe indicar el nombre del campo de clave foránea utilizado para recuperar los registros correspondientes.');
        }

        if (!$tableName) {
            throw new \LogicException('No se pudo determinar el nombre de la tabla de auditoría');
        }

        $query = sprintf("SELECT a.id, a.audit_timestamp, a.audit_iduserapp, a.audit_identity, 
                    CASE audit_action
                        WHEN 'I' THEN 'Alta'
                        WHEN 'U' THEN 'Modificación' --En this->auditDiff se refencia el tipo por 'Modificación'
                        WHEN 'D' THEN 'Baja'
                    ELSE 
                        ''
                    END as audit_action,
                    a.old_data,
                    a.new_data,
                    %s.jsonb_delta(old_data, new_data) as diff,
                    u.username,
                    count(*) OVER() AS total_registros, 
                    '%s' as Entidad
                FROM %s.%s%s a
                INNER JOIN %s.usuario u ON u.id = a.audit_iduserapp
                WHERE old_data->>'%s' = '$idPersona' or new_data->>'%s' = '$idPersona'
                ORDER BY a.id", $this->schemaAuditName, $tableName, $this->schemaAuditName, $tableName, $this->auditNameSuffix, $this->schemaDataName, $nameForeignKkeyField, $nameForeignKkeyField);

        $result = $this->entityManager
            ->getConnection()
            ->prepare($query)
            ->executeQuery()
            ->fetchAllAssociative();

        // Obtiene diferencias de Cambio
        $result = $this->auditDiff($result);

        return $result;
    }

    /**
     * Obtiene Lista de Entidades Auditables a partir de las definidas en el parámetro ENTIDADES_A_AUDITAR del archivo .env.
     */
    public function getEntitiesAuditables(): ?array
    {
        return $this->entitiesAuditables;
    }

    /**
     * Obtiene lista de Entidades gestionadas por Doctrine.
     */
    public function getEntitiesList(): array
    {
        return $this->entitiesList;
    }

    /**
     * Retorna un nombre de entidad Fully Qualified a partir del nombre simple de la misma.
     * Ej. domicilio es retornado como "App\Entity\Domicilio".
     *
     * Si $entityName ya se encuentra Fully Qualified, retorna el mismo valor
     *
     * @return string $entityName Fully Qualified
     */
    public function entiyNameToFullyQualifiedName(string $entityName): string
    {
        // Full Qualified Name por si la llamada viene sólo con el nombre de la entidad (informes de Auditoría)
        if (substr($entityName, 0, 11) != 'App\\Entity\\') {
            $entityName = 'App\\Entity\\'.$entityName;
        }

        return $entityName;
    }

    /**
     * Retorna las diferencias entre el Old y el New de un Registro de Auditoría.
     *
     * @param array $recordSet Registros de Auditoría
     *
     * @return array $recordSet     Devuelve el mismo $recordSet recibido por parámetros al que se le ha añadido una columna "diferencias" con 3 elementos:
     *               - Nombre del Campo Modificado
     *               - Valor anterior a la Modificación
     *               - Valor posterior a la Modificación
     */
    public function auditDiff(array $recordSet): array
    {
        // Proceso $recordSet para añadir un nuevo elemento que es el diff entre los 2 Jsonb de Auditoría
        // Se implementa de esta forma porque Twig no tiene forma de hacer un "decode" del diff en formato
        //  Jsonb que contiene el recorset. Por lo tanto se implementa como un array PHP vía json_decode
        foreach ($recordSet as &$r) {
            $r['diferencias'] = json_decode($r['diff'], true);
        }

        return $recordSet;
    }

    /** Retorna un array asociativo con los descriptores para los tipos de operación de la auditoría.
     * @param void
     */
    public function getTiposOperacion(): array
    {
        return ['I' => 'Alta', 'U' => 'Modificación', 'D' => 'Baja'];
    }

    /**
     * Este método retorna el nombre de la tabla de auditoría en BD  a partir de una Entidad.
     *
     * Este método elimina los prefijos del nombre (Full Qualified) retornando una expresión que debería ser
     * la referencia al nombre la tabla de auditoría en la Base de Datos y agrega el prefijo
     * y sufijo correspondiente para expresar la referencia a la tabla en BD que contiene
     * la auditoría.
     */
    public function getAuditTableNameDB($entity)
    {
        return str_replace('App\\Entity\\', '', $this->schemaAuditName.'.'.$this->getTableName($entity).$this->auditNameSuffix);
    }

    /**
     * Obtiene la cantidad de Altas, Bajas y Modificaciones de cada tabla de auditoría
     * para un rango de fechas y sede en particular.
     */
    public function obtainActivy(string $desde, string $hasta): array // int $oficinaId = 0): array
    {
        $query = "select * from $this->schemaAuditName.activy_all_tables('$desde', '$hasta', '$this->schemaAuditName', '$this->schemaDataName')"; // $oficinaId)";
        try {
            $result = $this->entityManager
                ->getConnection()
                ->prepare($query)
                ->executeQuery()
                ->fetchAllAssociative();

            return $result;
        } catch (DriverException $e) {
            throw new \LogicException('Se produjo un error. Es posible que la función de recoleción de Actividad no esté implementada en esta Base de Datos.');
        }
    }
}
