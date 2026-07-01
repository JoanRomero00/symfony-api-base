# Symfony API Base — Entorno de Desarrollo Local

Este proyecto es una plantilla de API REST construida con **Symfony 7.3**, **API Platform 4**, **Doctrine ORM** y **PostgreSQL 16**. Cuenta con autenticación mediante JWT (`lexik/jwt-authentication-bundle`), sistema de auditoría por triggers y herramientas de formateo de código y testing listas para usar.

---

## Requisitos Previos

Antes de comenzar, asegúrate de tener instalado en tu máquina:
*   [Docker Desktop](https://www.docker.com/products/docker-desktop/) (ejecutándose)
*   [Git](https://git-scm.com/)
*   Un cliente de base de datos como [DBeaver](https://dbeaver.io/) (opcional, para explorar la base de datos)

---

## Levantando el Entorno (Totalmente Automatizado)

El entorno de desarrollo está configurado para inicializarse por completo con un único comando. Al levantar los contenedores, Docker ejecutará un script que instala dependencias, genera llaves de seguridad, corre migraciones y carga usuarios de prueba.

1.  **Clona el repositorio** (si aún no lo has hecho) e ingresa a la carpeta:
    ```bash
    git clone <url-del-repositorio>
    ```
2.  **Levanta los contenedores**:
    ```bash
    docker compose up -d --build
    ```
3.  **¡Listo!** El script de inicialización se encargará de:
    *   Instalar las dependencias de Composer si falta la carpeta `vendor/`.
    *   Generar las llaves JWT (`private.pem` y `public.pem`) si no existen.
    *   Esperar a que PostgreSQL esté listo para recibir conexiones.
    *   Ejecutar las migraciones pendientes de base de datos.
    *   Cargar los usuarios de prueba base (Fixtures) solo si la base de datos está vacía.

Una vez finalizado, puedes acceder a la documentación interactiva de la API (Swagger UI) en:
👉 **[http://localhost:8000/api/docs](http://localhost:8000/api/docs)**

---

## Conexión a la Base de Datos (PostgreSQL)

Para conectarte a la base de datos desde DBeaver u otro gestor, utiliza los siguientes datos de conexión:

*   **Host:** `localhost`
*   **Puerto:** `5432`
    *(Nota: Si tienes un PostgreSQL local instalado en Windows, detén el servicio de Windows o mapea un puerto alternativo en `docker-compose.yml` como `5435:5432`)*.
*   **Base de Datos (Database):** `app_db`
*   **Usuario (Username):** `app`
*   **Contraseña (Password):** `app`

---

## Usuarios de Prueba Disponibles (Fixtures)

El entorno se inicializa automáticamente con tres cuentas de prueba para desarrollo y testing:

| Usuario | Contraseña | Roles asignados |
|---|---|---|
| `superadmin` | `superpass` | `ROLE_SUPER_ADMIN` (Control total) |
| `testuser` | `testpass` | `ROLE_ADMIN`, `ROLE_AUDIT` (Administración y Auditoría) |
| `targetuser` | `targetpass` | `ROLE_USER` (Usuario común) |

### Cómo obtener tu JWT para probar en Frontend / Postman:
Realiza una petición POST a `http://localhost:8000/login` con el siguiente cuerpo JSON:
```json
{
  "username": "superadmin",
  "password": "superpass"
}
```
El servidor te devolverá un JSON con el token:
```json
{
  "token": "eyJhbGciOiJSUzI1..."
}
```
Para consultar endpoints protegidos, añade el header `Authorization` en tus peticiones:
`Authorization: Bearer <token>`

---

## Comandos Útiles de Desarrollo

Todos los comandos deben ejecutarse en la terminal desde la raíz del proyecto:

### Contenedores e Infraestructura
*   **Ver logs del contenedor de la API:**
    ```bash
    docker compose logs -f api
    ```
*   **Detener contenedores:**
    ```bash
    docker compose stop
    ```
*   **Resetear base de datos desde cero (Borra todos los datos locales):**
    ```bash
    docker compose down -v
    # Y luego vuelve a levantar:
    docker compose up -d
    ```

### Base de Datos y Código
*   **Crear una nueva migración (tras modificar una entidad en PHP):**
    ```bash
    docker compose exec api php bin/console make:migration
    ```
*   **Correr migraciones manualmente:**
    ```bash
    docker compose exec api php bin/console doctrine:migrations:migrate --no-interaction
    ```
*   **Forzar la recarga de fixtures (Borra datos y vuelve a insertar los de prueba):**
    ```bash
    docker compose exec api php bin/console doctrine:fixtures:load --no-interaction
    ```

### Calidad de Código y Tests
*   **Ejecutar los tests unitarios y funcionales:**
    ```bash
    docker compose exec api composer test
    ```
*   **Ejecutar y corregir automáticamente el estilo de código (PHP CS Fixer):**
    ```bash
    docker compose exec api composer php-cs-fixer-fix
    ```
*   **Exportar especificación OpenAPI actualizada para el Frontend:**
    ```bash
    docker compose exec api composer generate:openapi
    ```

---

## Documentación de Referencia
*   [Análisis Arquitectónico Detallado](docs/arquitectura-backend.md)
*   [Wiki del Proyecto Base Oficial](https://docu.justiciasantafe.gov.ar/books/guias/page/api-platform-proyecto-base)