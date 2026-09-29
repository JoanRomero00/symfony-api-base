<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// El contenedor de desarrollo exporta APP_ENV=dev. Forzamos test también a
// nivel de proceso para que los kernels creados por PHPUnit usen su configuración.
putenv('APP_ENV=test');
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';

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
    $testPdo = new \PDO($testDsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $testPdo->exec('CREATE EXTENSION IF NOT EXISTS unaccent');
} catch (\PDOException) {
}

passthru('php bin/console doctrine:schema:create --env=test 2>&1');

// Instalar la misma infraestructura de auditoría que utiliza el entorno de desarrollo
passthru(
    'php bin/console app:auditoria:instalar --env=test --no-interaction 2>&1',
    $auditInstallExitCode
);
if (0 !== $auditInstallExitCode) {
    throw new \RuntimeException('No se pudo instalar la infraestructura de auditoría para los tests.');
}

passthru('php bin/console doctrine:fixtures:load --env=test --no-interaction 2>&1');
