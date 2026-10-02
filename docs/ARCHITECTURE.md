# Mini CRM — Architecture

This document explains the shape of the system and the reasoning behind the choices that aren't obvious from reading the code. It's written for a reviewer who wants to understand what was decided and why, not what every line does.

## Overview

Two separate repositories, deployed independently:

- **[crm-back](https://github.com/minicrm-uz/crm-back)** — Laravel 13 API-only. PHP 8.3, PostgreSQL 16. Hands out JWTs (HS256 via `firebase/php-jwt`), persists leads + audit trail.
- **[crm-front](https://github.com/minicrm-uz/crm-front)** — Vite + React 19 + TypeScript SPA. Consumes the API with Axios and React Query, routes with React Router v7, styles with TailwindCSS v4.

The two repos communicate over JSON only. There is no shared session, no Laravel Blade view used by the SPA, and no Sanctum middleware. The frontend can be redeployed independently.

## Data model

```
┌────────────────────────┐
│ users                  │
├────────────────────────┤
│ id          bigserial  │◀───────────────┐
│ name        string     │                │
│ email       string UQ  │                │
│ password    string     │                │
│ created_at  timestamp  │                │
│ updated_at  timestamp  │                │
└────────────────────────┘                │
                                           │  owner_id (cascade)
                                           │
┌────────────────────────┐                │
│ leads  (soft delete)   │────────────────┘
├────────────────────────┤
│ id          bigserial  │◀────────┐
│ owner_id    FK users   │         │
│ name        string     │         │  lead_id (cascade)
│ phone       string?    │         │
│ email       string?    │         │
│ source      lead_source│         │
│ status      lead_status│         │
│ note        text?      │         │
│ created_at  timestamp  │         │
│ updated_at  timestamp  │         │
│ deleted_at  timestamp? │         │
└────────────────────────┘         │
                                    │
                                    │
┌────────────────────────┐         │        ┌────────────────────────┐
│ lead_activities        │─────────┘        │ refresh_tokens         │
├────────────────────────┤                  ├────────────────────────┤
│ id          bigserial  │                  │ id          bigserial  │
│ lead_id     FK leads   │                  │ user_id     FK users   │─┐
│ actor_id    FK users?  │─ (null on del) ─▶│ token_hash  string(64) │ │
│ action      lead_action│                  │ expires_at  timestamp  │ │
│ changes     jsonb?     │                  │ revoked_at  timestamp? │ │
│ created_at  timestamp  │                  │ last_used_at timestamp?│ │
└────────────────────────┘                  │ created_at  timestamp  │ │
                                             └────────────────────────┘ │
                                                                         │
                                                        user_id (cascade)┘
```

### Postgres native enums

Three native `ENUM` types rather than `varchar + CHECK`:

- `lead_status`  — `New | Contacted | Qualified | Won | Lost`
- `lead_source`  — `Website | Referral | Social | Cold Call | Event | Other`
- `lead_action`  — `created | updated | status_changed | deleted`

They're mirrored one-to-one by PHP 8.1 backed enums (`App\Enums\LeadStatus` et al.) and the Eloquent cast handles conversion. The DB rejects an invalid status even if the application layer is bypassed.

### Indexes

- `leads(owner_id, status)` — composite, matches the dashboard filter patterns.
- `leads(created_at DESC)` — list default sort.
- `leads(name) USING GIN (gin_trgm_ops)` — trigram, used by `ILIKE '%q%'` substring search.
- `lead_activities(lead_id, created_at DESC)` — timeline reads.
- `refresh_tokens(token_hash)` is unique and `(user_id)` + `(expires_at)` are indexed for revocation and cleanup.

### Soft deletes

Only `leads` is soft-deleted. The `delete` activity is written to `lead_activities` and remains visible even after the row is restored, so a reviewer can see "deleted at T by X, restored at T+2 by Y" as a timeline.

`refresh_tokens` is intentionally **not** soft-deleted — revocation is recorded with `revoked_at` so a revoked token is still readable for audit without an extra deleted flag.

## Auth flow

The service writes JWT handling by hand on top of `firebase/php-jwt` rather than installing a Laravel JWT package. This keeps the surface small (one service class, one middleware) and makes the token lifecycle explicit for a reviewer.

### Register / login

```
Client                           API                             DB
  │                               │                               │
  │ POST /api/auth/register       │                               │
  │ { name, email, password }     │                               │
  │─────────────────────────────▶ │                               │
  │                               │ validate (RegisterRequest)    │
  │                               │ bcrypt(password), INSERT user │
  │                               │─────────────────────────────▶ │
  │                               │ issue access JWT (900 s)      │
  │                               │ generate 48-byte refresh      │
  │                               │ INSERT refresh_tokens hashed  │
  │                               │─────────────────────────────▶ │
  │ 201 { user, access, refresh } │                               │
  │ ◀───────────────────────────── │                              │
```

Access tokens carry `iss`, `sub` (user_id), `iat`, `exp`, `jti` and are signed HS256. They are **never** persisted; the service verifies them stateless-ly on every request.

Refresh tokens are 48 random bytes, base64url-encoded. The **raw token is sent to the client, but only the SHA-256 hash is stored**. A DB read alone therefore cannot forge a refresh — the attacker needs the raw value.

### Refresh (rotation)

```
Client                           API                             DB
  │ POST /api/auth/refresh        │                               │
  │ { refresh }                   │                               │
  │─────────────────────────────▶ │                               │
  │                               │ hash(refresh), SELECT by hash │
  │                               │─────────────────────────────▶ │
  │                               │ ◀──── record or null ──────── │
  │                               │ reject if null / expired /    │
  │                               │ revoked                       │
  │                               │ UPDATE revoked_at = now()     │
  │                               │─────────────────────────────▶ │
  │                               │ issue new access + refresh    │
  │                               │ INSERT new refresh_tokens row │
  │                               │─────────────────────────────▶ │
  │ 200 { user, access, refresh } │                               │
  │ ◀───────────────────────────── │                              │
```

Rotation means the presented refresh is single-use. If a stolen token is reused after the real client has rotated, the request lands on a revoked record and 401s — a signal of possible token theft. Explicit replay detection and global revocation on replay could be added later; the schema is already set up for it.

### Protected request

```
Client                           API
  │ GET /api/leads                 │
  │ Authorization: Bearer <jwt>    │
  │─────────────────────────────▶  │
  │                                │ JwtAuth middleware:
  │                                │   JWT::$timestamp = Carbon::now()
  │                                │   JWT::decode(secret, HS256)
  │                                │   User::find(sub)
  │                                │   Auth::setUser($user)
  │                                │
  │                                │ LeadController@index
  │                                │ Lead::ownedBy(user)->paginate()
  │                                │
  │ 200 { data: Lead[], meta, ... }│
  │ ◀───────────────────────────── │
```

`JwtAuth` sets `JWT::$timestamp = Carbon::now()->getTimestamp()` before decoding so Laravel's `travel()` helper can produce verifiably-expired tokens in tests. In production the two clocks coincide and this is a no-op.

### Logout

Logout requires a valid bearer and revokes **every** active refresh token for the user. This is "log out of all devices" semantics. Per-device logout would need the client to send its refresh token so the server can revoke that one specifically — easy to add later but not needed for a single-SPA assessment.

### Frontend storage tradeoff

The SPA keeps the **access token in memory only** (`src/auth/tokenStore.ts`). It stores the **refresh token in `localStorage`**. On reload the access token is lost; the AuthProvider boot effect calls `/api/auth/me`, which 401s, which the axios interceptor turns into a refresh + retry, which transparently restores the session.

The tradeoff is called out in both `tokenStore.ts` and the frontend README. The production hardening path is an `httpOnly` + `SameSite=Strict` cookie for the refresh token plus a CSRF token on `/auth/refresh`. For an assessment scope the localStorage approach is explicit, inspectable, and avoids a cookie/CSRF layer.

## Request lifecycle

Every mutating endpoint is a four-step chain:

```
HTTP → FormRequest → Controller → Policy (if touching a Lead) → Model/Observer → Resource
       └─ validates   └─ orchestrates    └─ owner-only        └─ writes DB,     └─ shapes
          + authorises                                           fires events      JSON out
```

- **FormRequest** carries validation rules + any authorisation that doesn't need the model. Example: `StoreLeadRequest` requires `name` and a `source` from the enum.
- **Controller** stays thin. It calls `authorize('view', $lead)` once the model is bound, and it never contains validation logic.
- **Policy** (`LeadPolicy`) enforces owner-only. Laravel's auto-discovery binds it; `App\Policies\LeadPolicy@view` is called for `view`, `update`, `delete`.
- **Observer** (`LeadObserver`) handles the audit trail (see next section).
- **Resource** (`LeadResource`, `LeadActivityResource`, `UserResource`) shapes the JSON. Enums become their string value, timestamps are ISO 8601, and relationships are included via `whenLoaded`.

An important subtlety: `LeadController@index` filters by `ownedBy($userId)` **inside the query** instead of relying on the policy alone. A route-model-bound `show` call runs the policy; an index with a stray `?owner_id=` filter would otherwise be a path to see another user's leads. Belt + braces.

## Activity log (observer pattern)

Writes to `lead_activities` are driven by the `LeadObserver`, attached via `#[ObservedBy(LeadObserver::class)]` on the model so there is no `boot()` wiring to miss.

| Model event | Activity row                                                                                                                                        |
|-------------|------------------------------------------------------------------------------------------------------------------------------------------------------|
| `created`   | `action=created`, `changes.after` = snapshot of all audited fields including the final status                                                       |
| `updated`   | `action=updated`, `changes = { before, after }` of all dirty audited fields **except** `status`                                                      |
| `updated`   | Additionally emits `action=status_changed` with `{ before: { status }, after: { status } }` if `status` is dirty                                     |
| `deleted`   | `action=deleted`, `changes = null`                                                                                                                   |

A single `update()` that modifies both status and a field produces **two** activity rows (one `updated`, one `status_changed`). The frontend timeline can render each distinctly without peeking inside the `changes` blob.

`actor_id` is `Auth::id()` and is nullable so system-triggered mutations (seeders, console commands) still log without crashing.

Why observer and not an explicit `LeadService::update()` that writes both the lead and the activity? Because the controller is the only mutation entry point today, and the observer catches **any** future mutation path (future console command, admin backfill, etc.) automatically.

## Dashboard

`GET /api/dashboard/stats` is owner-scoped and returns:

```jsonc
{
  "total":       100,
  "by_status":   { "New": 20, "Contacted": 15, "Qualified": 10, "Won": 30, "Lost": 25 },
  "by_source":   { "Website": 40, "Referral": 25, "Social": 10, "Cold Call": 5, "Event": 10, "Other": 10 },
  "won_rate":    30.00,
  "this_week":   8,
  "this_month":  34
}
```

`by_status` and `by_source` are **zero-filled** across every enum case so the frontend can bind to a stable key set. Without zero-fill, a user who has never won a lead would see no "Won" bar on the chart at all.

No caching is layered on this. The queries are five indexed `COUNT`s against a user's own leads; adding a cache would require invalidating it on every lead mutation for a measurable but tiny performance win.

## Testing

58 feature tests, 185 assertions, ~4 s against a dedicated `crm_test` Postgres database. Tests use `RefreshDatabase`; the first `create_pg_types` migration includes `DROP TYPE IF EXISTS` so a second run after `migrate:fresh` (which drops tables but leaves custom types) doesn't error with `type lead_status already exists`.

The suite covers:

- **Auth** (17 tests) — register/login/refresh/logout/me, happy paths and all 401/422 branches, bearer required, refresh rotation revokes the old token, replay 401s.
- **Lead CRUD** (26 tests) — index pagination/search/filter/sort/own-scope, show 403 for non-owner, store with owner_id injection and mass-assignment protection, update that cannot change status, soft delete, status endpoint authz.
- **Activity** (9 tests) — observer emits the correct rows per event, 403 for non-owner on `/activities`, newest-first ordering.
- **Dashboard** (6 tests) — auth required, zero-fill, owner-scope, counts per enum, `won_rate` math, time windows (test freezes time to a mid-month Wednesday to avoid the `startOfWeek < startOfMonth` edge case).

## Decision log

Short rationale for the choices that could reasonably have gone the other way.

| Decision                                 | Alternative                           | Why we picked this                                                                                                                             |
|------------------------------------------|---------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------|
| JWT, not session cookie                   | Laravel session + Sanctum             | Stateless, mobile-ready, horizontal scaling. Session cookies would have required another CSRF layer for the SPA.                                |
| `firebase/php-jwt`, not `tymon/jwt-auth`  | `tymon/jwt-auth` or `php-open-source-saver/jwt-auth` | Direct library, no Laravel-specific layering. Writing the service by hand is ~100 lines and makes rotation + revocation explicit for review.    |
| API-only Laravel + separate SPA repo      | Monorepo with Blade or Inertia        | Clean deploy boundary. Later a mobile client reuses the same API with no changes.                                                              |
| PostgreSQL                                | MySQL / SQLite                        | Native `ENUM`, `JSONB` for activity changes, `pg_trgm` trigram index for fuzzy search.                                                         |
| Backed PHP enums + PG enums               | string columns + `in:` validation     | Type-safe at both layers; DB rejects bad values even if the application is bypassed.                                                           |
| `FormRequest + Resource + Policy`         | Everything in the controller          | Idiomatic Laravel, each file has one job, controller stays readable.                                                                           |
| Observer for activity log                 | Controller writes activity rows       | Catches **any** mutation path, not just HTTP. Controllers stay thin.                                                                           |
| Soft delete on `leads`                    | Hard delete + separate archive table  | Reversible, keeps a 'deleted' event alive in the timeline.                                                                                     |
| Status via its own endpoint               | Allow status in `PATCH /leads/{id}`   | Audit log cleanly says "status changed" vs "field edited"; room for a future state machine (forbid `Won → New`) in one place.                   |
| Refresh = opaque random, access = JWT     | Both JWT                              | Opaque refresh means server owns revocation; access can stay stateless and short-lived.                                                        |
| Rotation + hashed refresh storage         | Long-lived refresh JWT                | Lets us revoke individual refresh tokens, detect replay, and never persist the raw token.                                                      |
| PHP 8 attributes for OpenAPI              | Docblock `@OA\...` annotations        | swagger-php v6 ships without doctrine/annotations; attributes are first-class and don't require a third-party reader.                           |
| Zero-fill dashboard enum buckets          | Only return non-zero buckets          | Frontend binds to a stable key set; no defensive `?? 0` on every chart.                                                                        |
| Lead list `ownedBy` scope in the query    | Rely on policy only                   | A stray `?owner_id=` filter can't leak another user's row through index; policy still guards show/update/delete.                                |

### Frontend-only decisions

| Decision                                   | Alternative                 | Why                                                                                                              |
|--------------------------------------------|-----------------------------|-------------------------------------------------------------------------------------------------------------------|
| Access in memory, refresh in localStorage   | Both in cookie / httpOnly    | Explicit, inspectable, no CSRF layer. Prod hardening is documented in `tokenStore.ts`.                             |
| `useSearchParams` as list source of truth   | Local state                  | Back button, bookmarks, refresh preserve the view.                                                                |
| `useDebouncedValue(350ms)` for search input | Debounce the query key        | Keeps typing responsive; URL and query only update once per burst.                                                |
| `EditLeadForm` keyed on `lead.id`           | `useEffect` to seed form      | Lazy `useState` init runs once per mount; refetches never clobber user edits. Also keeps oxlint happy.             |
| recharts, not plotly / Chart.js             | Chart.js                     | Component-first API matches the React mental model; bundle cost is comparable.                                    |
| sonner for toasts                           | react-hot-toast / custom     | Smallest API surface, modern defaults, no provider boilerplate beyond a single `<Toaster />`.                      |
