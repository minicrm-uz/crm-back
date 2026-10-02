# Mini CRM — Backend

A lead-management system built as a technical assessment. The frontend lives in a sibling repo: [minicrm-uz/crm-front](https://github.com/minicrm-uz/crm-front).

**Stack:** Laravel 13 · PHP 8.3 · PostgreSQL 16 · JWT (self-written, `firebase/php-jwt`) · L5-Swagger (OpenAPI 3) · PHPUnit

> ✅ 58 feature tests passing · 185 assertions · ~4 s
>
> See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the data model, auth flow, and the full decision log.
> See [`docs/AI_USAGE.md`](docs/AI_USAGE.md) for an honest log of where AI (Claude Code) was used.
> OpenAPI spec snapshot: [`docs/openapi.json`](docs/openapi.json).

---

## Repository layout

```
.
├── app/                   Laravel application code
│   ├── Enums/             LeadStatus / LeadSource / LeadAction (PHP 8.1 backed enums)
│   ├── Http/
│   │   ├── Controllers/   Auth, Lead, Dashboard (+ OpenAPI attribute annotations)
│   │   ├── Middleware/    JwtAuth
│   │   ├── Requests/      FormRequests (per-endpoint validation)
│   │   └── Resources/     JsonResources + OpenAPI schemas
│   ├── Models/            User, Lead, LeadActivity, RefreshToken
│   ├── Observers/         LeadObserver (audit log)
│   ├── Policies/          LeadPolicy (owner-only)
│   └── Services/          JwtTokenService
├── config/jwt.php         JWT secret, algo, access/refresh TTLs
├── database/
│   ├── factories/         User + Lead factories
│   ├── migrations/        PG enum types + pg_trgm, leads, lead_activities, refresh_tokens
│   └── seeders/           DatabaseSeeder: 10 users (incl. demo@example.com) + 100 leads
├── docker/{nginx,php}     Nginx vhost + PHP-FPM 8.3 image
├── docker-compose.yml     app + nginx + db stack
├── docs/
│   ├── ARCHITECTURE.md    Data model, auth flow, decision log
│   ├── AI_USAGE.md        Where AI was used, where I pushed back
│   └── openapi.json       Generated OpenAPI 3 spec
├── routes/api.php         All /api/* endpoints
├── tests/Feature/         58 feature tests across Auth / Leads / Activity / Dashboard
└── .env.example
```

## Prerequisites

- Docker 24+ and Docker Compose v2, **or**
- Local PHP 8.3 (with `pdo_pgsql`), Composer 2, and PostgreSQL 16

## Quick start (Docker)

```bash
cp .env.example .env
# Edit .env — set DB_HOST=db (service name) and generate JWT_SECRET
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The API is now on **http://localhost:8000**. Swagger UI: **http://localhost:8000/api/documentation**.

Seeded demo credentials: `demo@example.com` / `password`.

## Quick start (local, no Docker)

```bash
cp .env.example .env
composer install
php artisan key:generate
# Edit .env — set DB_HOST=127.0.0.1 and your Postgres creds, and set JWT_SECRET
createdb crm
php artisan migrate --seed
php artisan serve
```

API on **http://localhost:8000**.

## Running the tests

Tests use a dedicated `crm_test` database on the same Postgres instance.

```bash
# One-time setup
createdb crm_test              # or inside Docker: docker compose exec db psql -U crm -c "CREATE DATABASE crm_test"

# Run
php artisan test               # 58 tests, 185 assertions
```

Why pgsql for tests? The migrations use `CREATE TYPE` and `pg_trgm` — SQLite can't run them, so the alternative would be dialect-forking the migrations, which the schema isn't worth doing for.

## Environment variables (`.env`)

| Var                             | Purpose                                                                                            | Default (dev)        |
|---------------------------------|-----------------------------------------------------------------------------------------------------|----------------------|
| `DB_CONNECTION`                 | DB driver                                                                                           | `pgsql`              |
| `DB_HOST`                       | DB host (`db` inside Docker, `127.0.0.1` locally)                                                   | `127.0.0.1`          |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Also consumed by `docker-compose.yml` for the Postgres container                       | `crm` / `crm` / `crm_secret` |
| `JWT_SECRET`                    | HMAC secret for access/refresh tokens — generate with `openssl rand -base64 64`                     | *(empty — must set)* |
| `JWT_ALGO`                      | JWT signing algorithm                                                                               | `HS256`              |
| `JWT_ACCESS_TTL`                | Access token lifetime (seconds)                                                                     | `900` (15 min)       |
| `JWT_REFRESH_TTL`               | Refresh token lifetime (seconds)                                                                    | `2592000` (30 days)  |
| `L5_SWAGGER_GENERATE_ALWAYS`    | Set `true` in dev so `/api/documentation` reflects the latest annotations without a manual rebuild  | `false`              |

## API reference

OpenAPI 3 spec is maintained via PHP 8 attribute annotations on each controller method. Three ways to consume it:

- **Browse interactively** at `/api/documentation` (Swagger UI, served by L5-Swagger).
- **Import into Postman / Insomnia / Bruno**: open the API tool → *Import* → point it at [`docs/openapi.json`](docs/openapi.json). All 10 paths and 8 schemas come across with request bodies and response codes populated.
- **Regenerate** after editing annotations:
  ```bash
  php artisan l5-swagger:generate
  cp storage/api-docs/api-docs.json docs/openapi.json  # if you want to update the committed snapshot
  ```

### Endpoint summary

| Method & Path                        | Auth    | Purpose                                                          |
|--------------------------------------|---------|------------------------------------------------------------------|
| `POST /api/auth/register`            | public  | Create user + issue token pair                                   |
| `POST /api/auth/login`               | public  | Credentials → token pair                                         |
| `POST /api/auth/refresh`             | public  | Rotate refresh token → new token pair                            |
| `POST /api/auth/logout`              | Bearer  | Revoke all refresh tokens for the user                           |
| `GET  /api/auth/me`                  | Bearer  | Current user                                                     |
| `GET  /api/leads`                    | Bearer  | List (q, status, source, sort, page, per_page) — owner-scoped    |
| `POST /api/leads`                    | Bearer  | Create (owner_id injected from auth)                             |
| `GET  /api/leads/{id}`               | Bearer  | Show                                                             |
| `PATCH /api/leads/{id}`              | Bearer  | Update fields (status is **not** touched here)                   |
| `PATCH /api/leads/{id}/status`       | Bearer  | Status transition (audited separately as `status_changed`)       |
| `DELETE /api/leads/{id}`             | Bearer  | Soft delete                                                      |
| `GET  /api/leads/{id}/activities`    | Bearer  | Audit timeline for a lead                                        |
| `GET  /api/dashboard/stats`          | Bearer  | Owner-scoped aggregates for the dashboard                        |

## Roadmap

- [x] Day 1 — Scaffold: Laravel 13, Docker Compose, PostgreSQL, JWT + Swagger packages
- [x] Day 2 — Data model (users, leads, lead_activities, refresh_tokens) + JWT auth
- [x] Day 3 — Lead CRUD + policies + filtering/pagination
- [x] Day 4 — Activity log (observer) + dashboard stats endpoint
- [x] Day 5 — OpenAPI annotations + React frontend scaffold ([crm-front](https://github.com/minicrm-uz/crm-front))
- [x] Day 6 — Frontend features: dashboard, leads list, detail, create modal, activity timeline
- [x] Day 7 — Polish, docs, architecture explanation, AI-usage log
