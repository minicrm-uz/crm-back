# AI usage log

This project was built with Claude Code (Anthropic) acting as a pair-programming assistant inside the terminal. I kept this log honest about where the AI contributed so a reviewer knows what came out of the model, what I steered, and what I rejected.

The short version: **I set the plan and the architecture, the AI typed most of the code, and I reviewed every PR before merging to `main`.** Where the AI's first attempt was wrong, I pushed back and we iterated.

## Where AI wrote code

Code **drafted by Claude**, **reviewed and merged by me**:

| Area                                               | What the AI produced                                                                                            | My role                                                                                             |
|----------------------------------------------------|-----------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
| Day 2 — JWT auth                                   | `JwtTokenService`, `JwtAuth` middleware, `AuthController`, FormRequests, feature tests                           | Set the design (opaque refresh + hashed storage + rotation). Reviewed every method.                 |
| Day 2 — Migrations + models                        | PG enum types + indexes + GIN trigram, Lead/LeadActivity/RefreshToken models, factories, seeder                 | Specified the schema (soft deletes on leads, JSONB `changes`, zero-fill rules). Reviewed indexes.    |
| Day 3 — Lead CRUD                                  | `LeadController`, `LeadPolicy`, query scopes (`search/filterStatus/filterSource/sortBy`), FormRequests, tests   | Signed off on splitting status into its own endpoint; reviewed the "ownedBy in query AND policy" belt+braces decision. |
| Day 4 — Observer + dashboard                       | `LeadObserver`, two-row split on simultaneous field+status change, dashboard aggregation, tests                 | Specified the two-row behaviour. Reviewed the `updated` event timing (pre vs post `syncOriginal`).  |
| Day 5 — OpenAPI annotations                        | PHP 8 attributes on every endpoint, resource schemas, `TokenPair/Error/ValidationError` shared schemas          | Reviewed the per-endpoint response-code coverage.                                                   |
| Day 5 — Frontend scaffold + auth                   | Vite+React+TS+Tailwind setup, axios interceptor with refresh coalescing, AuthProvider, Login/Register pages     | Chose the "access in memory, refresh in localStorage" tradeoff and had the AI document it inline.   |
| Day 6 — Dashboard + leads list + detail            | Recharts dashboard, URL-synced leads list with debounced search, create modal, detail with activity timeline   | Reviewed the lazy-init form pattern and the mutation→invalidation graph.                             |

The AI also wrote all commit messages. I reviewed each one before pushing; a few were tightened by hand when they described *what* changed instead of *why*.

## Where AI first attempt was wrong

The useful artefacts here are the places the model got something **wrong** and I had to push back. Three worth logging:

1. **OpenAPI docblock annotations failed, pivoted to PHP 8 attributes.** The AI first tried `@OA\Info()` docblock annotations. `php artisan l5-swagger:generate` erred with `Required @OA\Info() not found`. The AI then tried adding `use OpenApi\Annotations as OA;` — still failed, because swagger-php v6 doesn't bundle doctrine/annotations. We pivoted to PHP 8 attribute syntax (`OpenApi\Attributes`), which worked first try. The decision log in ARCHITECTURE.md records this.

2. **Observer "created" event saw a null status.** The AI's first observer wrote `changes.after.status` using `$lead->getAttribute('status')` inside the `created` event. The test failed because `Lead::create([...])->refresh()` returns the refreshed lead to the controller, but the observer fires **during** `create()`, before the controller's `refresh()`. We fixed it by setting `$attributes = ['status' => 'New', 'source' => 'Other']` on the model so the in-memory status matches the DB default when the observer runs.

3. **Dashboard `this_week/this_month` test flaked on month boundaries.** The AI's first version of the test used `now()->startOfWeek()->subDays(2)` as a "this month but before this week" anchor. On a Saturday near the start of a month, `startOfWeek < startOfMonth` and the window is empty. We switched to `Carbon::setTestNow(Carbon::create(2026, 10, 14, 12))` (a mid-month Wednesday) so the assertion is deterministic.

## Where I did not take AI's suggestion

- When oxlint flagged a `setState-in-effect` warning on the lead detail form, the AI's first move was to add an `oxlint-disable-next-line` comment. I pushed for the "React way" instead: split `EditLeadForm` into its own component, use lazy `useState(() => lead.field)` initializers, and key the component on `lead.id` from the parent. No disable comment, no lint warning, and the form no longer clobbers in-flight edits on refetch.
- The AI proposed caching the dashboard stats endpoint. I rejected this: the queries are five indexed `COUNT`s against a user's own data, and caching would add an invalidation step to every mutation for no measurable win. The decision log in ARCHITECTURE.md records this.
- The AI proposed PHP 8 attribute syntax for OpenAPI from the start of a different approach; I had wanted docblock first because it was more familiar. After the failure described above I agreed and we moved to attributes.

## Where AI handled plumbing I didn't want to type

Things the AI did that are mechanical and not interesting to review line-by-line, but which I verified by running the tool afterwards:

- `winget install OpenJS.NodeJS.LTS` to install Node 24, plus the PATH refresh dance for new PowerShell shells.
- Author rewrites: when GitHub committed the initial `Botir <...+botirTurgonboyev@users.noreply.github.com>` from the "Create repository" button and my own machine was globally configured as a different git identity, the AI drove `git filter-branch --env-filter` and `git push --force` for both repos until every commit on `main` showed `botirTurgonboyev <botirsamhl5@gmail.com>`.
- Boilerplate: factories, seeders, resource field lists mirroring the migrations, TypeScript types mirroring the resources.

## Review workflow

Every day of work was its own feature branch (`feat/auth`, `feat/lead-crud`, `feat/activity`, `feat/swagger`, `feat/scaffold`, `feat/frontend-leads`, `feat/docs`). Each branch opened a PR on GitHub and I merged it from the UI only after reading the diff. Nothing was pushed directly to `main`.

Nothing in this repo was committed sight-unseen.
