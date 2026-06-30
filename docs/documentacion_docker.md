# Explicación Detallada del Entorno Docker

Este documento explica cómo funciona la arquitectura de contenedores configurada para levantar la **API de Symfony** sin depender de una instalación local de PHP o PostgreSQL en el sistema operativo del host.

---

## 1. El Dockerfile (Configuración de la Aplicación)

El archivo `Dockerfile` define paso a paso cómo se construye la imagen para el contenedor `api` (donde corre PHP y Symfony).

### Código y Funcionamiento:
```dockerfile
FROM php:8.4-cli-alpine
```
*   **`php:8.4-cli-alpine`**: Es la imagen base oficial de PHP 8.4. Usamos la versión `cli` porque Symfony CLI levantará su propio servidor de desarrollo integrado. La variante `alpine` es una distribución Linux extremadamente ligera (apenas 5MB base), lo que acelera las descargas y el consumo de recursos.

```dockerfile
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
```
*   **`php-extension-installer`**: Copia una herramienta de código abierto muy popular que automatiza y simplifica la compilación de extensiones de PHP en entornos Docker, manejando las dependencias del sistema operativo por debajo.

```dockerfile
RUN apk add --no-cache git bash openssh-client wget \
    && install-php-extensions pdo_pgsql intl zip opcache apcu gd iconv
```
*   **`apk add`**: Instala herramientas del sistema requeridas para descargar paquetes y usar Git dentro del contenedor.
*   **`install-php-extensions`**: Instala y compila las extensiones necesarias para que corra el backend:
    *   `pdo_pgsql`: Driver para conectarse a PostgreSQL.
    *   `intl`: Para la validación de internacionalización y formatos locales.
    *   `zip`: Requerido por Composer para descomprimir paquetes de dependencias.
    *   `opcache`: Cache de compilación para mejorar el rendimiento del motor de PHP.
    *   `apcu`: Sistema de almacenamiento en caché en memoria utilizado por API Platform y Doctrine.
    *   `gd` e `iconv`: Manipulación de strings e imágenes.

```dockerfile
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
```
*   **Composer**: Copia el ejecutable oficial de Composer directamente en los binarios del contenedor para poder descargar dependencias de PHP.

```dockerfile
RUN wget https://get.symfony.com/cli/installer -O - | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony
```
*   **Symfony CLI**: Descarga e instala la interfaz de comandos de Symfony para que podamos levantar el servidor web nativo de desarrollo y depurar más fácilmente.

```dockerfile
WORKDIR /var/www
EXPOSE 8000
CMD ["symfony", "server:start", "--no-tls", "--port=8000", "--allow-all-ip"]
```
*   **`WORKDIR`**: Establece `/var/www` como la carpeta de trabajo por defecto dentro del contenedor.
*   **`EXPOSE 8000`**: Documenta que el contenedor escuchará en el puerto 8000.
*   **`CMD`**: El comando que corre al iniciar el contenedor. Levanta el servidor web de desarrollo de Symfony (`server:start`) en HTTP (`--no-tls`), en el puerto `8000`, y configurado para aceptar peticiones de fuera del contenedor (`--allow-all-ip`).

---

## 2. El docker-compose.yml (Orquestación de Servicios)

El archivo `docker-compose.yml` une el contenedor de la base de datos y el de la aplicación para que trabajen en la misma red de Docker.

### Servicios definidos:

### A. Servicio `database` (PostgreSQL)
```yaml
  database:
    image: postgres:16-alpine
    container_name: symfony_api_db
    environment:
      POSTGRES_DB: app_db
      POSTGRES_USER: app
      POSTGRES_PASSWORD: app
    ports:
      - "5432:5432"
    volumes:
      - db_data:/var/lib/postgresql/data
```
*   **`image`**: Descarga la base de datos oficial PostgreSQL versión 16 sobre Alpine.
*   **`environment`**: Configura las credenciales de administrador de la base de datos que usará la aplicación.
*   **`ports`**: Mapea el puerto `5432` del contenedor al puerto `5432` de tu Windows host. Esto te permite conectar clientes externos (como DBeaver, pgAdmin o Postman).
*   **`volumes`**: Utiliza un volumen de Docker (`db_data`) para persistir los datos de las tablas. Si apagas o destruyes los contenedores, tus datos **no se perderán**.

### B. Servicio `api` (Symfony)
```yaml
  api:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: symfony_api_app
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www
    depends_on:
      - database
    environment:
      DATABASE_URL: pgsql://app:app@database:5432/app_db?serverVersion=16
      APP_ENV: dev
      APP_SECRET: 5b4c5c7d812480e64c39bbfe383ff9bb
      JWT_PASSPHRASE: changeme
      CORS_ALLOW_ORIGIN: '^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$$'
      MAILER_DSN: 'null://null'
      MAIL_FROM: 'info@miapp.com'
      PATH_SISTEMA: '/var/www'
```
*   **`build`**: Le dice a Docker Compose que debe construir la imagen usando el `Dockerfile` local.
*   **`volumes`**: Mapea el directorio actual de tu Windows (`.`) en la carpeta `/var/www` del contenedor. Gracias a esto, cualquier archivo que crees o edites en Windows se sincronizará inmediatamente en el contenedor (soporte para Hot-Reload).
*   **`depends_on`**: Asegura que el servicio `database` inicie antes que la API.
*   **`environment`**: Sobrescribe las variables de entorno principales. Nota la variable `DATABASE_URL` que usa el host `database` (el nombre del servicio en Docker) en lugar de `127.0.0.1`.

---

## 3. Generación de la Especificación de la API (openapi.json)

El archivo `openapi/openapi.json` contiene la estructura completa de la API. 

### ¿Cuándo debes generar este archivo?
*   **NO es necesario generarlo solo para que la API funcione**. La API corre perfectamente en cualquier entorno Docker sin este archivo.
*   **SÓLO debes generarlo cuando realices modificaciones en el backend** (por ejemplo, al crear nuevas tablas, añadir campos a un usuario o modificar las rutas de los endpoints) y desees que tu frontend se entere de estos cambios para actualizar su cliente y tipos de TypeScript.

### ¿Cómo se genera?
Debes correr el comando de generación **dentro del contenedor de la API** ejecutando:
```bash
docker compose exec api composer generate:openapi
```
Este comando exportará el estado actual de tu API Platform y guardará el resultado en `openapi/openapi.json` en tu carpeta de Windows (gracias al volumen compartido).

### Recomendación sobre Git:
Es altamente recomendable **subir el archivo `openapi/openapi.json` a Git**. De esta forma:
1. Tu frontend podrá compilar, autogenerar el cliente de TypeScript y trabajar con los tipos actualizados incluso si el backend en Docker no está encendido en la máquina del desarrollador frontend.
2. Queda un registro histórico del contrato de la API en el repositorio.

