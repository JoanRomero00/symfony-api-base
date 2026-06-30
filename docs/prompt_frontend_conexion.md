# Prompt de Instrucción para Desarrollar el Frontend (Conexión API)

Puedes copiar y pegar el texto de abajo directamente en la IA o asistente que utilices para desarrollar tu frontend en **Shadcn UI (React / Next.js / Vite)**. Le dará todo el contexto técnico necesario para estructurar la comunicación.

---

```text
Actúa como un desarrollador experto en React, TypeScript y Shadcn UI.
Tengo un backend API REST construido con Symfony 7.3 y API Platform 4 que ya está corriendo en http://localhost:8000. 
El backend utiliza autenticación JWT stateless a través de lexik/jwt-authentication-bundle.

Necesito que me ayudes a estructurar el flujo de conexión en el frontend de mi proyecto Shadcn UI.

Detalles técnicos del Backend:
1. Endpoint de Login:
   - URL: POST http://localhost:8000/login
   - Body esperado (JSON): { "username": "...", "password": "..." }
   - Respuesta de éxito (Status 200): { "token": "..." }
   - Respuestas de error comunes: HTTP 401 (Credenciales inválidas) y HTTP 429 (Límite de peticiones de login superado).

2. Rutas protegidas:
   - Todas las rutas bajo la URL http://localhost:8000/api/* requieren que se envíe el token JWT.
   - Formato de autenticación: Cabecera 'Authorization: Bearer <token_jwt>'.
   - Si el token expira o es inválido, el backend devuelve un HTTP 401.

3. Archivo OpenAPI disponible:
   - Tengo un archivo OpenAPI completo en formato JSON generado por el backend en la ruta relativa "../symfony-api-base/openapi/openapi.json".
   - Quiero usar "@hey-api/openapi-ts" para generar de forma automatizada los tipos de TypeScript y los métodos clientes para React.

Tareas requeridas:
1. Explícame cómo instalar y configurar "@hey-api/openapi-ts" en mi proyecto frontend y qué script añadir en package.json.
2. Escribe una configuración del cliente (usando la API autogenerada de @hey-api/openapi-ts) en un archivo 'src/lib/api.ts' que añada de manera global la URL base 'http://localhost:8000' e inyecte dinámicamente el token JWT desde 'localStorage' usando interceptores de request.
3. Escribe un componente funcional de React con un formulario de Login usando los componentes de Shadcn UI (Form, FormControl, FormField, FormLabel, FormMessage, Input, Button) integrado con Zod para validación de datos. El componente debe capturar errores de credenciales (401) o de límite de intentos (429) y mostrarlos en el formulario.
4. Escribe un ejemplo de petición protegida utilizando uno de los servicios generados (por ejemplo, 'getUsuarioCollection') para listar datos de la API en una tabla o lista simple utilizando componentes de Shadcn UI.
```
