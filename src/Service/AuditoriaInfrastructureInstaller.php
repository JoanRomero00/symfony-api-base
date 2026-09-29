<?php

/**
 * Instala la infraestructura compartida de auditoría en PostgreSQL.
 */

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class AuditoriaInfrastructureInstaller
{
    private const RESOURCE_FILES = [
        'creacion_esquema_auditoria.sql',
        'funcion_jsonb_delta.sql',
        'funcion_activy_all_tables.sql',
    ];

    public function __construct(
        private Connection $connection,
        private ContainerBagInterface $parameters,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function install(): void
    {
        $schemaName = $this->getSchemaName();

        $this->connection->transactional(function (Connection $connection) use ($schemaName): void {
            foreach (self::RESOURCE_FILES as $resourceFile) {
                $sql = $this->loadResource($resourceFile, $schemaName);
                $connection->executeStatement($sql);
            }
        });

        $missingObjects = $this->getMissingObjects();
        if ([] !== $missingObjects) {
            throw new \RuntimeException(sprintf('La instalación de auditoría finalizó incompleta. Objetos faltantes: %s.', implode(', ', $missingObjects)));
        }
    }

    public function getSchemaName(): string
    {
        $schemaName = (string) $this->parameters->get('app.audit_shemma_audit_name');

        if (1 !== preg_match('/^[a-z_][a-z0-9_]*$/i', $schemaName)) {
            throw new \InvalidArgumentException(sprintf('El nombre del esquema de auditoría "%s" no es un identificador PostgreSQL válido.', $schemaName));
        }

        return $schemaName;
    }

    /**
     * @return list<string>
     */
    public function getMissingObjects(): array
    {
        $schemaName = $this->getSchemaName();
        $missingObjects = [];

        $schemaExists = (bool) $this->connection->fetchOne(
            'SELECT EXISTS(SELECT 1 FROM pg_namespace WHERE nspname = :schema)',
            ['schema' => $schemaName]
        );
        if (!$schemaExists) {
            $missingObjects[] = $schemaName;

            return $missingObjects;
        }

        $functions = [
            'acp_audit_create_function(name)',
            'acp_audit_table(name)',
            'acp_audit_userid_delete(character varying,integer,integer)',
            'jsonb_delta(jsonb,jsonb)',
            'activy_all_tables(date,date,text,text,integer)',
        ];

        foreach ($functions as $function) {
            $signature = $schemaName.'.'.$function;
            $exists = $this->connection->fetchOne(
                'SELECT to_regprocedure(:signature) IS NOT NULL',
                ['signature' => $signature]
            );

            if (!(bool) $exists) {
                $missingObjects[] = $signature;
            }
        }

        return $missingObjects;
    }

    private function loadResource(string $resourceFile, string $schemaName): string
    {
        $path = $this->projectDir.'/resources/'.$resourceFile;
        $sql = file_get_contents($path);

        if (false === $sql) {
            throw new \RuntimeException(sprintf('No se pudo leer el recurso SQL de auditoría "%s".', $path));
        }

        return str_replace('%%AUDIT_SCHEMA%%', $schemaName, $sql);
    }
}
