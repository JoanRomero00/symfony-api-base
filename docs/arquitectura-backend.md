# Análisis Arquitectónico — Symfony API Base

## Stack Tecnológico

| Componente | Versión |
|---|---|
| PHP | 8.4+ |
| Symfony | 7.3 |
| API Platform | 4.2 |
| Doctrine ORM | 3.6 |
| PostgreSQL | 16+ |
| lexik/jwt-authentication-bundle | 3.2 |
| knplabs/knp-snappy-bundle | wkhtmltopdf |

---

## 1. Arquitectura General

### Patrón: API-First con API Platform como Motor

El backend es una **REST API pura** (sin vistas HTML, sin Twig para responses HTTP). Toda la superficie pública es JSON. La arquitectura sigue el modelo de API Platform 4 donde las **entidades Doctrine son los recursos API de primera clase**, configuradas mediante atributos PHP 8.

```
src/
├── Entity/          → Entidades Doctrine + configuración API Platform completa (atributos)
├── State/
│   └── Processor/   → Lógica de persistencia custom (State Pattern de API Platform)
├── Filter/          → Filtros de consulta reutilizables (DQL)
├── Dto/             → Data Transfer Objects (operaciones sin entidad directa)
├── ApiResource/     → Recursos API sin entidad Doctrine (Auditoria, Impersonation)
├── Controller/      → Controllers Symfony para operaciones no-REST (PDF, CSV)
├── Service/         → Lógica de negocio transversal
├── Serializer/      → Normalizadores custom (paginación, IRIs)
├── EventListener/   → Listeners Doctrine (auditoría de datos)
├── EventSubscriber/ → Subscribers Symfony (auditoría de autenticación)
└── Security/        → UserChecker, ApiKeyAuthenticator
```

### Separación de Responsabilidades

| Capa | Responsabilidad |
|---|---|
| `Entity/` | Mapeo ORM + configuración completa de operaciones API + validación |
| `State/Processor/` | Lógica de persistencia que no cabe en el ciclo estándar de API Platform |
| `Filter/` | Extensiones DQL reutilizables para filtrado de colecciones |
| `Dto/` | Contratos de entrada/salida para operaciones sin entidad directa |
| `ApiResource/` | Recursos API que no tienen tabla propia (Auditoría, Impersonación) |
| `Controller/` | Operaciones no-REST: generar PDFs, exportar CSVs |
| `Service/` | Lógica de negocio pura: emails, auditoría, PDF, JWT custom |
| `Serializer/` | Normalización de respuestas: estructura de paginación, IRIs |
| `EventListener/` | Hooks Doctrine: inyectar `lastUserAppId` en pre/post persist/update/remove |
| `EventSubscriber/` | Hooks del ciclo JWT: auditar logins |

---

## 2. Entidades Base

### Entidades incluidas en el template

| Entidad | Tabla | Descripción |
|---|---|---|
| `Usuario` | `usuario` | Usuarios del sistema con JWT auth, baja lógica, 2FA opcional |
| `ConfiguracionSistema` | `configuracion_sistema` | Pares clave-valor de configuración de la aplicación |
| `UsuarioLogAccion` | `usuario_log_accion` | Auditoría de accesos y acciones de usuarios |

### Convenciones Aplicadas a Todas las Entidades

**1. Mapeo ORM via atributos PHP 8:**
```php
#[ORM\Entity(repositoryClass: MyEntityRepository::class)]
```

**2. Configuración API Platform inline (todo en la entidad):**
```php
#[ApiResource(
    operations: [
        new GetCollection(...),
        new Post(...),
        new Get(...),
        new Patch(...),
    ],
)]
```

**3. Grupos de serialización:**
```php
#[Groups(['myentity:read'])]
private ?string $campo = null;
```

**4. Auditoría de último usuario (`lastUserAppId`):**
Todas las entidades persistibles incluyen el campo `lastUserAppId` (no mapeado como relación, solo como entero). El `AuditoriaListener` lo inyecta automáticamente en cada persist/update.

**5. Variable `storeId` (no mapeada):**
Usada por el `AuditoriaListener` para capturar el ID antes del borrado físico (DELETE).

---

## 3. Seguridad

### Firewalls

| Firewall | Ruta | Autenticación |
|---|---|---|
| `main` | `/api/*` | JWT Bearer token |
| `public_api` (opcional) | `/api/public/*` | X-API-Key header |
| `login` | `/login` | JSON credentials |

### Jerarquía de Roles

```
ROLE_USER < ROLE_AUDIT < ROLE_ADMIN < ROLE_SUPER_ADMIN
```

### JWT Lifecycle

El `JWTAuthenticationSubscriber` escucha eventos de lexik JWT para:
- Registrar el último acceso del usuario (`ultimoAcceso`)
- Incrementar el contador de accesos (`cantidadAccesos`)
- Registrar la acción en `UsuarioLogAccion`
- Inyectar datos adicionales en el payload JWT (username, roles)

### UserChecker

`Security/UserChecker.php` valida antes del login que el usuario no tenga `fechaBaja` seteada. Si está dado de baja, el login es rechazado.

---

## 4. Filtros

### Filtros Nativos (API Platform 4)

Se declaran via `QueryParameter` en `#[ApiResource]`. No requieren registro de servicio.

| Filtro | Clase | Uso típico |
|---|---|---|
| Exacto | `new ExactFilter()` | Igualdad, incluso en relaciones ManyToOne |
| Fecha | `new DateFilter()` | Rango de fechas |
| Rango | `new RangeFilter()` | Rango numérico |
| Booleano | `new BooleanFilter()` | true/false |
| Existencia | `new ExistsFilter()` | IS NULL / IS NOT NULL |

**Filtro ManyToOne correcto:**
```php
'relacion' => new QueryParameter(
    filter: new ExactFilter(),
    property: 'relacion'   // sin .id — Doctrine resuelve la FK
),
```

### Filtros Custom

| Clase | Parámetro | Descripción |
|---|---|---|
| `GlobalSearchFilter` | `q` | Búsqueda en múltiples campos con UNACCENT |
| `CustomOrderFilter` | `order` | Ordenamiento configurable |
| `SoloActivosFilter` | `soloActivos` | Filtra registros sin `fechaBaja` |

---

## 5. Processors (State Pattern)

| Clase | Operación | Descripción |
|---|---|---|
| `BajaLogicaProcessor` | PATCH `/baja` | Setea `fechaBaja = now()` |
| `ReactivarProcessor` | PATCH `/reactivar` | Limpia `fechaBaja` |
| `UsuarioPasswordHasherProcessor` | POST/PATCH Usuario | Hashea la contraseña antes de persistir |
| `ChangePasswordProcessor` | POST `/change-password` | Cambia contraseña del usuario logueado |
| `ResetPasswordProcessor` | PATCH `/reset-password` | Genera nueva contraseña aleatoria y envía email |

---

## 6. Paginación Personalizada

El `PaginationNormalizer` transforma la respuesta estándar de API Platform en:

```json
{
  "items": [...],
  "meta": {
    "totalItems": 100,
    "itemsPerPage": 10,
    "currentPage": 1,
    "totalPages": 10
  },
  "links": {
    "self": "/api/recursos?page=1",
    "first": "/api/recursos?page=1",
    "last": "/api/recursos?page=10",
    "next": "/api/recursos?page=2",
    "prev": null
  }
}
```

El `IriNormalizer` inyecta el campo `iri` en cada objeto de la colección.

El `CustomOpenApiFactory` ajusta la documentación OpenAPI para reflejar esta estructura.

---

## 7. Auditoría de Datos

### AuditoriaListener

Escucha eventos Doctrine (`prePersist`, `preUpdate`, `preRemove`, `postRemove`) en todas las entidades configuradas y registra los cambios en las tablas de auditoría del schema definido en `app.audit_shemma_audit_name`.

### Configuración en services.yaml

```yaml
parameters:
    app.audit_entity_list: []            # Lista explícita de entidades a auditar
    app.audit_all_entities_except: []    # Auditar todo excepto estas entidades
    app.audit_shemma_audit_name: "audit" # Schema de auditoría
    app.audit_shemma_data_name: "public" # Schema de datos
    app.audit.tablas_principales: []     # Para informe de actividad
```

### AuditoriaService

Expone endpoints REST (via `AuditoriaController`) para:
- Consultar configuración de auditoría activa
- Activar/pausar/reanudar auditoría por entidad
- Generar reportes PDF de auditoría y actividad

---

## 8. Impersonación

`ImpersonationController` permite a usuarios `ROLE_SUPER_ADMIN` obtener un JWT que representa a otro usuario, sin conocer su contraseña. Útil para soporte y debugging.

Endpoints:
- `POST /api/impersonate/in/{id}` — Genera JWT del usuario objetivo
- `POST /api/impersonate/out` — Vuelve al JWT original

---

## 9. OpenAPI

`CustomOpenApiFactory` decora el factory de API Platform para:
- Ajustar la estructura de paginación en las respuestas de colecciones
- Inyectar la versión desde git tags (`git describe --tags`)
- Marcar campos `id` como `required` automáticamente

Solo se activa en entornos `dev` y `test` (en `prod`, la documentación está deshabilitada).

---

## 10. Cómo Extender el Template

### Agregar una entidad nueva

1. Crear `src/Entity/MyEntity.php` con atributos ORM y API Platform
2. Crear `src/Repository/MyEntityRepository.php`
3. `composer migration` → revisar → `composer migrate`
4. Si necesita baja lógica: agregar campo `fechaBaja` y endpoints `/baja`/`/reactivar`
5. Si debe auditarse: configurar `app.audit_entity_list` en `services.yaml`

### Agregar un filtro de dominio

Verificar primero si algún filtro nativo cubre el caso. Solo crear `AbstractFilter` custom si no hay alternativa nativa.

### Agregar un endpoint no-REST

Crear un controller Symfony en `src/Controller/` y registrar la ruta en `config/routes.yaml`.
