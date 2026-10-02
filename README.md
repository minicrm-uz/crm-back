# Mini CRM — Backend

A lead management system built as a technical assessment.
**Laravel 13** (API-only) · DB: **PostgreSQL 16** · Auth: **JWT (self-written, firebase/php-jwt)**.

> Status: Day 1 — scaffold complete. Auth, data model, CRUD and frontend arriving in subsequent commits.
> Frontend lives in a sibling repo: [minicrm-uz/crm-front](https://github.com/minicrm-uz/crm-front).

## Repository layout

```
.
├── app/                 Laravel application code
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── docker/
│   ├── nginx/           Nginx vhost
│   └── php/             PHP-FPM 8.3 image
├── docker-compose.yml   app + nginx + postgres stack
├── .env.example         Backend + compose-level env
├── composer.json
└── artisan
```

## Prerequisites

- Docker 24+ and Docker Compose v2
- Or local: PHP 8.3 with `pdo_pgsql`, Composer 2, PostgreSQL 16

## Quick start (Docker)

```bash
cp .env.example .env
# Edit .env — set DB_HOST=db (service name) and generate JWT_SECRET
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

API: http://localhost:8000

## Quick start (local, no Docker)

```bash
cp .env.example .env
composer install
php artisan key:generate
# Edit .env — set DB_HOST=127.0.0.1 and your local Postgres creds
php artisan migrate
php artisan serve
```

## Environment variables (.env)

| Var | Purpose | Default (dev) |
|---|---|---|
| `DB_CONNECTION` | DB driver | `pgsql` |
| `DB_HOST` | DB host (use `db` inside Docker, `127.0.0.1` locally) | `127.0.0.1` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Also consumed by `docker-compose.yml` for the Postgres container | `crm` / `crm` / `crm_secret` |
| `JWT_SECRET` | HMAC secret for access/refresh tokens — generate with `openssl rand -base64 64` | *(empty — must set)* |
| `JWT_ALGO` | JWT signing algorithm | `HS256` |
| `JWT_ACCESS_TTL` | Access token lifetime (seconds) | `900` (15 min) |
| `JWT_REFRESH_TTL` | Refresh token lifetime (seconds) | `2592000` (30 days) |

## Roadmap

- [x] Day 1 — Scaffold: Laravel 13, Docker Compose, JWT + Swagger packages installed, PostgreSQL wired up
- [ ] Day 2 — Data model (users, leads, lead_activities) + JWT auth endpoints
- [ ] Day 3 — Lead CRUD + policies + filtering/pagination
- [ ] Day 4 — Activity log (observer pattern) + dashboard stats endpoint
- [ ] Day 5 — Swagger annotations + React frontend scaffold (in crm-front)
- [ ] Day 6 — Frontend features: list, detail, dashboard
- [ ] Day 7 — Polish, docs, architecture explanation

Architecture notes and AI-usage log will be added under `docs/` in a subsequent commit.
