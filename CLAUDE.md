# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Symfony API Base — REST API template built with Symfony 7.3, API Platform 4, Doctrine ORM, PHP 8.4. PostgreSQL database with JWT authentication (lexik/jwt-authentication-bundle).

## Commands

```bash
# Dev server
composer ss              # Start with TLS (background)
composer ssc             # Start without TLS

# Database
composer migration       # Generate migration (make:migration)
composer migrate         # Run migrations

# Code style
composer php-cs-fixer-fix    # Fix code style
composer php-cs-fixer-check  # Check only

# Tests
composer test                # Run all tests (--testdox)
composer test:filter <name>  # Run tests matching name/class
composer test:coverage       # Generate HTML coverage to var/coverage/
composer test:endpoints      # Check endpoint coverage (app:test:endpoint-coverage)

# OpenAPI
composer generate:openapi    # Export to openapi/openapi.json

# Symfony console
symfony console <command>
```

## Testing

Functional test suite using `ApiPlatform\Symfony\Bundle\Test\ApiTestCase` + PHPUnit 12.

**Test bootstrap** (`tests/bootstrap.php`) runs before every suite: drops and recreates the test DB (`<main_db>_test`), installs the `unaccent` extension, applies the schema via `doctrine:schema:create`, sets up the audit schema from `resources/creacion_esquema_auditoria.sql`, and loads fixtures.

**Base class** (`tests/AbstractApiTestCase`): clears the rate-limiter cache store before each test so failed-login counters from one test don't bleed into the next.

**Auth helper** (`tests/Trait/AuthenticatedTestTrait`): `createAuthenticatedClient(username, password)` performs a real `/login` POST and returns a pre-authenticated HTTP client.

**Fixtures** (`src/DataFixtures/TestFixtures.php`) — three users created for tests:

| username | password | roles |
|---|---|---|
| `testuser` | `testpass` | `ROLE_ADMIN`, `ROLE_AUDIT` |
| `superadmin` | `superpass` | `ROLE_SUPER_ADMIN` |
| `targetuser` | `targetpass` | `ROLE_USER` |

Tests live in `tests/Functional/`: `UsuarioTest`, `AuditoriaTest`, `ConfiguracionSistemaTest`, `ImpersonationTest`, `LoginRateLimitTest`, `PublicApiTest`.

## Architecture

### Entities (`src/Entity/`)
- All use PHP 8+ attributes for ORM mapping and API Platform configuration
- Serialization groups: `entity:read` (e.g., `usuario:read`)
- Logical deletion (baja lógica): `fechaBaja` field on `Usuario`
- Audit tracking: `lastUserAppId` field + `storeId` (non-mapped) for audit events
- Base entities: `Usuario`, `ConfiguracionSistema`, `UsuarioLogAccion`

### API Platform 4 Patterns
- **Filters**: Defined via `QueryParameter` in `#[ApiResource]` parameters — NOT `#[ApiFilter]` (deprecated)
- **Native filters** (no-arg constructors): `ExactFilter`, `DateFilter`, `RangeFilter`, `BooleanFilter`, `ExistsFilter`
- **ManyToOne filtering**: Use `ExactFilter` with `property: 'relacion'` (without `.id`) — Doctrine resolves the FK
- **Custom filters** (`src/Filter/`): `GlobalSearchFilter` (param `q`), `CustomOrderFilter`, `SoloActivosFilter`
- **Processors** (`src/State/Processor/`): `BajaLogicaProcessor`, `ReactivarProcessor`, `ChangePasswordProcessor`, `ResetPasswordProcessor`, `UsuarioPasswordHasherProcessor`
- **Custom operations**: `Usuario` has `/dar-de-baja`, `/reactivar`, `/restablecer-clave` PATCH endpoints and `/cambiar-clave` POST
- **Pagination**: Default 10, max 9999, client-configurable. Custom `PaginationNormalizer` wraps responses.

### Security (`config/packages/security.yaml`)
- JWT stateless auth on all `/api/*` routes
- Roles: `ROLE_USER` < `ROLE_AUDIT` < `ROLE_ADMIN` < `ROLE_SUPER_ADMIN`
- `UserChecker` validates user is active before login
- Public endpoints: `/login`, `/api/docs*`
- Optional `ApiKeyAuthenticator` for public-facing firewall (X-API-Key header)

### Services (`src/Service/`)
- `AppService` — General utilities: CSV export, file handling, date formatting, error notification emails
- `BasePdfService` — PDF generation via wkhtmltopdf (knp-snappy)
- `AuditoriaService` / `UsuarioLogAccionService` — Audit trail
- `UsuarioService` — Password reset emails
- `ReportGeneratorService` — Report data processing
- `JWTImpersonationService` — User impersonation support

### Controllers (`src/Controller/`)
- `AuditoriaController` — Audit reporting
- `ImpersonationController` — User impersonation (ROLE_SUPER_ADMIN)

### Database
- PostgreSQL with optional multi-schema support
- Audit schema: configurable via `app.audit_shemma_audit_name` parameter
- Custom DQL functions registered in `config/packages/doctrine.yaml`: `UNACCENT`

## Conventions

- API-only project: JSON format exclusively
- Entities define their full API Platform config inline via attributes
- DTOs in `src/Dto/` for operations that don't map directly to entities (ChangePassword, Impersonation, report queries)
- Non-entity API resources in `src/ApiResource/` (Auditoria, Impersonation)
- Event listeners handle audit capture (`AuditoriaListener`) and JWT lifecycle (`JWTAuthenticationSubscriber`)

### OpenAPI Contract: required fields in responses

API Platform 4 **does not infer `required`** from PHP type hints — neither in entities (`?string = null`)
nor in readonly DTOs. For `@hey-api/openapi-ts` to generate the field as non-optional in TypeScript,
it must be explicitly marked.

Rules:
- **User-provided fields** (POST/PATCH): use `#[Assert\NotBlank]` or `#[Assert\NotNull]` —
  serves dual purpose: input validation + `required` in the OpenAPI schema.
- **Auto-populated fields** (PrePersist, computed) in entities: use `#[ApiProperty(required: true)]`
  — documents the contract only, without generating unnecessary input validation.
- **Output DTOs** (built programmatically, never user input): use
  `#[ApiProperty(required: true)]` on all non-nullable fields.
- **`id` fields**: covered automatically by `CustomOpenApiFactory` — no attribute needed.
- **String `min: 1` constraint**: use `#[Assert\Length(min: 1)]` alongside `#[Assert\NotBlank]`
  on required string fields — `NotBlank` alone does not produce `minLength: 1` in the OpenAPI spec.

## How to Add a New Entity

1. Create `src/Entity/MyEntity.php` with `#[ORM\Entity]` and `#[ApiResource]` attributes.
2. Define serialization groups (`myentity:read`, `myentity:write` if needed).
3. Add filters via `QueryParameter` in `#[ApiResource]` — use native filters first (`ExactFilter`, `DateFilter`, etc.).
4. If soft-delete is needed: add `fechaBaja` field + `/baja` and `/reactivar` endpoints using `BajaLogicaProcessor` / `ReactivarProcessor`.
5. Create `src/Repository/MyEntityRepository.php`.
6. Run `composer migration` to generate the migration, review it, then `composer migrate`.
7. If the entity should be audited, add it to `app.audit_entity_list` or remove it from `app.audit_all_entities_except` in `config/services.yaml`.

## REGLAS OBLIGATORIAS

1. **Menos código siempre**: antes de proponer cualquier solución, verificar si existe una nativa/built-in.
2. **Investigar antes de asumir**: no aplicar conocimiento previo sin verificarlo contra la versión actual del proyecto.
3. **API Platform 4**: siempre buscar en los filtros nativos disponibles antes de proponer `AbstractFilter` custom.
4. **NO usar `#[ApiFilter]`** (deprecado en AP4). Filtros se definen via `QueryParameter` en `#[ApiResource]`.
