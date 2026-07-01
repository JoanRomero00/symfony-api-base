#!/bin/sh
set -e

echo "=== Iniciando automatización de entorno dev ==="

# 1. Instalar dependencias de Composer si no existe la carpeta vendor
if [ ! -d "vendor" ]; then
    echo "Instalando dependencias de Composer (esto puede tardar la primera vez)..."
    composer install --no-interaction --prefer-dist
else
    echo "Dependencias de Composer ya instaladas."
fi

# 2. Generar claves JWT si no existen
if [ ! -f "config/jwt/private.pem" ]; then
    echo "Generando llaves de seguridad JWT..."
    php bin/console lexik:jwt:generate-keypair --no-interaction
else
    echo "Llaves JWT ya existentes."
fi

# 3. Esperar a que la base de datos esté lista para recibir conexiones
echo "Esperando que PostgreSQL esté listo..."
php -r '
$dsn = "pgsql:host=database;port=5432;dbname=app_db";
for ($i = 0; $i < 30; $i++) {
    try {
        $pdo = new PDO($dsn, "app", "app");
        exit(0);
    } catch (PDOException $e) {
        sleep(1);
    }
}
echo "Error: No se pudo conectar a la base de datos.\n";
exit(1);
'

# 4. Ejecutar migraciones pendientes
echo "Ejecutando migraciones de base de datos..."
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Cargar fixtures solo si la tabla de usuarios está vacía
echo "Verificando si la base de datos tiene datos..."
USER_COUNT=$(php -r '
try {
    $pdo = new PDO("pgsql:host=database;port=5432;dbname=app_db", "app", "app");
    $stmt = $pdo->query("SELECT COUNT(*) FROM app_schema.usuario");
    echo $stmt->fetchColumn();
} catch (Exception $e) {
    echo "0";
}
')

if [ "$USER_COUNT" = "0" ]; then
    echo "La base de datos está vacía. Cargando fixtures de prueba..."
    php bin/console doctrine:fixtures:load --no-interaction
else
    echo "La base de datos ya tiene usuarios ($USER_COUNT). Omitiendo carga de fixtures."
fi

echo "=== Inicialización finalizada con éxito ==="

# Ejecutar el comando principal del contenedor (Symfony server)
exec "$@"
