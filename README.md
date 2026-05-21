# Symfony API Base

Template base para APIs REST con Symfony 7.3, API Platform 4, PHP 8.4 y PostgreSQL.

## Stack

- **Symfony** 7.3
- **API Platform** 4
- **PHP** 8.4
- **PostgreSQL** (multi-schema con schema de auditoría separado)
- **JWT** (lexik/jwt-authentication-bundle)
- **Messenger** (Doctrine transport)
- **PDF** (knp-snappy / wkhtmltopdf)

## Qué incluye

- Entidad `Usuario` con roles, JWT, baja lógica, cambio y reset de password
- `ConfiguracionSistema`: key-value de configuración dinámica
- Sistema de auditoría trigger-based en schema PostgreSQL separado
- Suplantación de usuarios (impersonation) con JWT
- Autenticación dual: JWT (endpoints privados) + API Key (endpoints públicos)
- Filtros genéricos: búsqueda global (`q`), orden (`order[campo]`), solo activos
- Serialización personalizada: campo `iri` en respuestas, paginación estructurada
- Rate limiting en `/login` (5 intentos/minuto)
- Suite de tests funcionales con PHPUnit + DAMA DoctrineTestBundle

## Setup de un proyecto nuevo

### 1. Clonar y renombrar

```bash
git clone <este-repo> mi-proyecto
cd mi-proyecto
```

Actualizar en `composer.json`: `name`, `description`.
Actualizar en `.env`: `APP_NAME`, `DATABASE_URL`, `AUDIT_SCHEMA_NAME`.

### 2. Instalar dependencias

```bash
composer install
```

### 3. Generar claves JWT

```bash
php bin/console lexik:jwt:generate-keypair
```

### 4. Crear base de datos y schema

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 5. Setup del schema de auditoría

Reemplazar `%%AUDIT_SCHEMA%%` con el valor de `AUDIT_SCHEMA_NAME` del `.env` y ejecutar los scripts en orden:

```bash
SCHEMA=$(grep AUDIT_SCHEMA_NAME .env | cut -d= -f2)
sed "s/%%AUDIT_SCHEMA%%/${SCHEMA}/g" resources/creacion_esquema_auditoria.sql | psql $DATABASE_URL
sed "s/%%AUDIT_SCHEMA%%/${SCHEMA}/g" resources/funcion_jsonb_delta.sql | psql $DATABASE_URL
sed "s/%%AUDIT_SCHEMA%%/${SCHEMA}/g" resources/funcion_activy_all_tables.sql | psql $DATABASE_URL
```

### 6. Datos iniciales

```bash
psql $DATABASE_URL < resources/poblacion_datos_iniciales.sql
```

## Comandos

```bash
composer ss              # Servidor con TLS (background)
composer ssc             # Servidor sin TLS
composer migration       # Generar migración
composer migrate         # Ejecutar migraciones
composer php-cs-fixer-fix    # Corregir estilo
composer php-cs-fixer-check  # Verificar estilo
composer generate:openapi    # Exportar OpenAPI a openapi/openapi.json
composer test            # Ejecutar tests
composer test:filter     # Ejecutar tests por filtro
```

## Agregar una nueva entidad

1. Crear la entidad en `src/Entity/` con los atributos API Platform
2. Incluir `lastUserAppId` (int|null) y `fechaBaja` (si aplica baja lógica)
3. Agregar el grupo de serialización (`miEntidad:read`)
4. Agregar al listado de auditoría en `config/services.yaml` (`app.audit_entity_list`)
5. Generar la migración: `composer migration && composer migrate`

## Roles

```
ROLE_USER < ROLE_AUDIT < ROLE_ADMIN < ROLE_SUPER_ADMIN
```

`ROLE_SUPER_ADMIN` incluye `ROLE_ALLOWED_TO_SWITCH` (impersonación).

## Tests

```bash
composer test
```

Los tests recrean la base de datos completa en cada ejecución. Requieren PostgreSQL disponible con las credenciales de `.env.test`.
