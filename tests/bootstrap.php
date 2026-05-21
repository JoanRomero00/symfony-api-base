<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Recrear BD de test: schema desde entidades + fixtures
// Terminar conexiones activas a la BD de test antes de dropear
$databaseUrl = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '';
$params = parse_url($databaseUrl);
$dbName = ltrim($params['path'] ?? '', '/') . '_test';
$host = $params['host'] ?? '127.0.0.1';
$port = $params['port'] ?? 5432;
$user = $params['user'] ?? '';
$pass = $params['pass'] ?? '';

$mainDsn = "pgsql:host={$host};port={$port};dbname=postgres";
try {
    $pdo = new \PDO($mainDsn, $user, $pass);
    $pdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$dbName}' AND pid <> pg_backend_pid()");
} catch (\PDOException) {
    // Si falla (ej: BD postgres no accesible), continuamos igualmente
}

passthru('php bin/console doctrine:database:drop --force --env=test --if-exists 2>&1');
passthru('php bin/console doctrine:database:create --env=test --if-not-exists 2>&1');

// Crear extensiones PostgreSQL necesarias en la BD de test
$testDsn = "pgsql:host={$host};port={$port};dbname={$dbName}";
try {
    $testPdo = new \PDO($testDsn, $user, $pass);
    $testPdo->exec('CREATE EXTENSION IF NOT EXISTS unaccent');
} catch (\PDOException) {
}

passthru('php bin/console doctrine:schema:create --env=test 2>&1');

// Crear esquema de auditoría (funciones base)
$auditSchemaName = $_SERVER['AUDIT_SCHEMA_NAME'] ?? $_ENV['AUDIT_SCHEMA_NAME'] ?? 'app_audit_test';
$auditSql = file_get_contents(dirname(__DIR__) . '/resources/creacion_esquema_auditoria.sql');
$auditSql = str_replace('%%AUDIT_SCHEMA%%', $auditSchemaName, $auditSql);
$auditSql = str_replace('AUTHORIZATION postgres', "AUTHORIZATION {$user}", $auditSql);
$auditSql = str_replace('OWNER TO postgres', "OWNER TO {$user}", $auditSql);
$activateAllSql = file_get_contents(dirname(__DIR__) . '/resources/funcion_activy_all_tables.sql');
$activateAllSql = str_replace('%%AUDIT_SCHEMA%%', $auditSchemaName, $activateAllSql);
try {
    $testPdo = $testPdo ?? new \PDO($testDsn, $user, $pass);
    $testPdo->exec($auditSql);
    $testPdo->exec($activateAllSql);
} catch (\PDOException $e) {
    echo "Warning: audit schema setup failed: " . $e->getMessage() . "\n";
}

passthru('php bin/console doctrine:fixtures:load --env=test --no-interaction 2>&1');
