# Symfony API Base

Este proyecto es una plantilla base para el desarrollo de APIs REST construida sobre **Symfony 7.3**, **API Platform 4** y **PHP 8.4**. Incluye configuraciones preestablecidas para autenticación JWT, un sistema de auditoría de acciones de usuarios, bajas lógicas y una suite de pruebas funcionales estructurada.

---

## 🛠️ Tecnologías y Dependencias

*   **Lenguaje:** PHP >= 8.4
*   **Framework:** Symfony 7.3.*
*   **API Engine:** API Platform 4 (Doctrine ORM)
*   **Base de Datos:** PostgreSQL (con soporte para DQL `UNACCENT` y auditoría de base de datos)
*   **Seguridad:** Autenticación JWT (`lexik/jwt-authentication-bundle`) con control de ratio de intentos de login (`symfony/rate-limiter`)
*   **Herramientas de Calidad:** PHPUnit 12, PHP-CS-Fixer (para estilo de código) y PHPStan/MakerBundle para desarrollo rápido.

---

## ✨ Características Principales

1.  **Seguridad y Autenticación:**
    *   Autenticación apátrida (stateless) mediante JSON Web Tokens (JWT) para todas las rutas bajo `/api/*`.
    *   Endpoint público `/login` para autenticación inicial y obtención del token.
    *   Control de roles predefinido: `ROLE_USER` < `ROLE_AUDIT` < `ROLE_ADMIN` < `ROLE_SUPER_ADMIN`.
    *   `UserChecker` integrado para evitar el inicio de sesión de usuarios inactivos o dados de baja.

2.  **Gestión de Usuarios (`src/Entity/Usuario.php`):**
    *   Flujo completo de usuarios con operaciones personalizadas en API Platform.
    *   **Baja lógica:** Marcar usuarios como inactivos mediante el campo `fechaBaja` (sin eliminar físicamente el registro) usando el endpoint PATCH `/dar-de-baja`.
    *   Operaciones de Reactivación, Restablecimiento de clave y Cambio de clave.

3.  **Sistema de Auditoría (`src/Entity/UsuarioLogAccion.php`):**
    *   Registro automático de acciones críticas en el sistema.
    *   Base de datos configurada para soportar un esquema específico de auditoría.

4.  **Generación de OpenAPI:**
    *   Documentación interactiva autogenerada disponible en `/api/docs`.
    *   Exportación rápida de especificaciones a un archivo estático.

---

## 🚀 Comandos de Desarrollo Comunes

El proyecto incluye accesos directos configurados en `composer.json` para facilitar las tareas comunes:

### Servidor de Desarrollo
*   **Iniciar servidor con TLS (seguro):**
    ```bash
    composer ss
    ```
*   **Iniciar servidor sin TLS:**
    ```bash
    composer ssc
    ```

### Base de Datos y Migraciones
*   **Generar una nueva migración:**
    ```bash
    composer migration
    ```
*   **Ejecutar las migraciones pendientes:**
    ```bash
    composer migrate
    ```

### Calidad de Código y Formateo
*   **Corregir automáticamente el estilo de código (PHP-CS-Fixer):**
    ```bash
    composer php-cs-fixer-fix
    ```
*   **Comprobar el estilo de código sin modificar archivos:**
    ```bash
    composer php-cs-fixer-check
    ```

### Pruebas (Testing)
*   **Ejecutar la suite completa de pruebas:**
    ```bash
    composer test
    ```
*   **Filtrar pruebas por nombre o clase:**
    ```bash
    composer test:filter <NombreDeLaPrueba>
    ```
*   **Generar reporte de cobertura en HTML (`var/coverage/`):**
    ```bash
    composer test:coverage
    ```

### Documentación
*   **Generar y exportar la especificación OpenAPI a `openapi/openapi.json`:**
    ```bash
    composer generate:openapi
    ```