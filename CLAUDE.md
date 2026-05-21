# Ukolio

Minimalistic, multi-tenant Kanban task manager. Designed for AI agents (MCP)
as the primary actor; the web UI is for human overview.

## Services

- `proxy/` — nginx reverse proxy (`/api/*` → backend, `/` → frontend)
- `backend/` — FrankenPHP + PHP 8.5 + `marekskopal/orm` +
  `marekskopal/router` + MariaDB
- `frontend/` — Angular 21 (standalone components + signals), SCSS design
  tokens (`frontend/src/styles/_variables.scss` + `_mixins.scss`). No Tailwind.

## Domain

- `Workspace` (owner, name) — top-level tenant; users belong to one or more workspaces.
- `WorkspaceUser` (workspace, user, role ∈ Owner/Admin/Member) — membership.
- `Invitation` (workspace, inviter, email, tokenHash, role, expiresAt, acceptedAt?) — pending invites.
- `User` (email, password, name, currentWorkspaceId?, systemRole ∈ User/SystemAdmin, locale) — `currentWorkspaceId` scopes data; `systemRole = SystemAdmin` grants global admin.
- `Project` (workspace, name, description) → has one `Workflow`, many `Tasks`, many `ProjectField` attachments.
- `Workflow` (project, name) → has many `Status`.
- `Status` (workflow, name, color, position, type ∈ Start/Normal/Finish).
- `Task` (project, status, name, description [markdown], priority, dueDate, position, createdByAgent) → has many `TaskFieldValue`. `createdByAgent = true` when the row was created via the MCP transport.
- `Field` (workspace, name, type ∈ Text/Textarea/Select/Version, required, defaultValue, options) — per-workspace custom-field catalog.
- `ProjectField` (project, field, position, required) — attaches a workspace field to a project and orders it in the task drawer.
- `TaskFieldValue` (task, field, value) — concrete value per task.
- `Event` (author, type, metadata JSON, project?, workspaceId?, taskId?, actorType ∈ Human/Agent, mcpClientId?, mcpClientName?) — append-only audit log; `project`/`workspaceId` nullable so workspace- and admin-level events fit alongside project events. `actorType` + `mcpClient*` are set by `ActorContext`, which `McpController` flips to `Agent` after OAuth-token validation.

On sign-up a personal `Workspace` is auto-created and the user becomes its
owner. New `Project` auto-seeds workflow `To Do → In Progress → Done`.
Inviting a member sends an email via Symfony Mailer (SMTP env:
`SMTP_HOST/PORT/USER/PASSWORD`, `EMAIL_FROM`); `mailpit` is wired in
`docker-compose.yml` for local capture.

## Roles & permissions

Authorization is centralized in `Ukolio\Service\Auth\PermissionChecker`
(interface + impl). Every mutating controller and the SystemAdmin endpoints
route their decisions through it.

- **SystemAdmin** (`User.systemRole`): global; passes every `can*` check. Operates on workspaces they don't belong to via dedicated `/api/admin/*` endpoints (see `Ukolio\Controller\Admin\`) with a separate frontend at `/admin/users` and `/admin/workspaces`. Inside their own workspaces they act as a normal member of whatever role they hold.
- **Owner** (workspace-scoped): one per workspace. Rename/delete workspace, manage all members, transfer ownership (sole way to assign a new Owner).
- **Admin** (workspace-scoped): manage members (Member ↔ Admin), invite Members (cannot invite Admins or Owners), full CRUD on projects, workflows, statuses, custom fields, and tasks. Cannot remove or demote the Owner.
- **Member** (workspace-scoped): full CRUD on tasks; read-only on projects, workflows, statuses, and fields.

Ownership transfer (`POST /api/workspaces/{id}/transfer-ownership`)
atomically updates `Workspace.owner` and both `WorkspaceUser` rows (old Owner
becomes Admin). Workspace owner removal is blocked — transfer first.

The first SystemAdmin is provisioned out-of-band via
`docker compose exec backend php bin/console admin:create` (see DEPLOY.md).
Earlier builds seeded a default `admin@ukolio.com` / `admin`; migration
`20260520_120000_InvalidateDefaultAdminPassword` neutralises that account on
existing installs by replacing its password with an unverifiable string when
the default is still in place.

MCP tools remain scoped to `currentWorkspace` — sysadmins must use the web
admin UI for cross-workspace management.

## HTTP API surface

All routes live in `Ukolio\Route\Routes` (single enum). Highlights:

- `POST /api/authentication/{login,sign-up,refresh-token}`
- `GET/PATCH /api/current-user`
- `GET/POST /api/workspaces`, `PUT/DELETE /api/workspaces/{id}`, plus `/switch`, `/members`, `/transfer-ownership`, `/invitations`, `/fields`, `/mcp-clients`, `/events`, `/agent-stats`.
- `GET/POST/PUT/DELETE /api/invitations/...`
- `GET/POST/PUT/DELETE /api/projects[/{id}]`, plus `/board`, `/events`, `/workflow`, `/tasks`, `/fields`.
- `GET /api/workflows` — workspace-wide list of workflows with nested statuses + `projectName` (used by the Tasks grid's status filter).
- `GET/POST/PUT/DELETE /api/workflows/{id}/statuses`, `/api/statuses/{id}`, `/api/statuses/{id}/move`.
- `GET /api/tasks` — workspace-wide paginated list. Query params: `limit` (default 50, max 200), `offset`, `orderBy` (`created_at|name|status_id`), `orderDirection` (`ASC|DESC`), `search`, `statusIds` (pipe-delimited), `onlyActive` (status type ≠ Finish). Response shape: `{ tasks: TaskListItemDto[], count: int }`.
- `GET/PUT/DELETE /api/tasks/{id}`, `PUT /api/tasks/{id}/move`, `POST /api/projects/{id}/tasks`.
- Admin: `GET/PUT/DELETE /api/admin/users[/{id}]`, `GET/PUT/DELETE /api/admin/workspaces[/{id}]`, plus `/members`, `/transfer-ownership`.
- MCP: `POST/GET/DELETE /api/mcp`, OAuth discovery + flow endpoints (see below).

Query enums live under `backend/src/Model/Repository/Enum/`
(`OrderDirectionEnum`, `TaskOrderByEnum`).

## Frontend routes

Public:

- `/login`, `/sign-up`, `/invitations/accept`

Inside `LayoutComponent` (AuthGuard-protected):

- `/projects`, `/projects/new`, `/projects/:id/edit`, `/projects/:id/board`, `/projects/:id/workflow`, `/projects/:id/events`
- `/tasks` — workspace-wide grid (see below)
- `/workspaces` — membership management
- `/admin/users`, `/admin/workspaces` — SystemAdmin only

Shared components live under `frontend/src/app/shared/components/`
(`layout`, `alert`, `pagination`).

### Tasks grid (`/tasks`)

Workspace-scoped paginated table. State is held as signals in
`TasksGridComponent` — no URL or localStorage
persistence (yet). Filter / sort / page-size changes reset to page 1. Row
click opens the existing `TaskDetailDrawerComponent` in place — the drawer is
already cleanly parameterized (`task`, `statuses`, `projectId`,
`projectFields` inputs; `saved`/`deleted`/`cancelled` outputs) and is reused
without refactor. Reusable `PaginationComponent` lives in
`frontend/src/app/shared/components/pagination/` with options
`[25, 50, 100, 200]` (default 50).

## i18n

- Backend: `Ukolio\Service\Translator\TranslatorService` loads `backend/translations/{en,cs}.json`. `EmailFactory` renders subject + section per `User.locale`; invitee's locale falls back to the inviter when they don't yet have an account.
- Frontend: `@ngx-translate/core` + `@ngx-translate/http-loader`. JSONs live in `frontend/src/i18n/{en,cs}.json`, served from `/i18n/` via `angular.json` assets. `LanguageService` initialises from `?lang=`, then localStorage, then `navigator.language`. `PATCH /api/current-user` syncs the user's choice to the backend so emails arrive in the right language. The topbar has a language switcher.

## Docker

```bash
docker compose up -d --build              # Full stack
docker compose --profile dev up -d        # +Adminer
make migrate                              # Apply migrations
```

## MCP server

Exposed at `POST/GET/DELETE /api/mcp` (Streamable HTTP transport, `mcp/sdk`).
Sessions persisted to `MCP_SESSION_DIR` (defaults to
`<tmp>/ukolio-mcp-sessions`).

Auth is **OAuth 2.1 with PKCE**. Discovery endpoints:

- `GET /.well-known/oauth-authorization-server/api/mcp` — issuer/authz/token/registration URLs
- `GET /.well-known/oauth-protected-resource/api/mcp` — resource metadata
- `POST /api/mcp/oauth/register` — dynamic client registration (open)
- `POST /api/mcp/oauth/authorize` — user approval (requires user JWT)
- `POST /api/mcp/oauth/token` — code/refresh-token exchange (open)
- `GET /api/mcp/oauth/client-info` — display name lookup (open)

401 responses include `WWW-Authenticate: Bearer resource_metadata="…"` per
RFC 9728 so MCP clients can auto-discover. PKCE `S256` only; no client
secret. Access token lifetime 1 h, refresh 30 d. Storage: `oauth_clients` and
`oauth_authorizations` tables (tokens stored as SHA-256 hashes).

Tools live in `backend/src/Mcp/Tool/` (auto-discovered by basePath/scanDirs):

- `ProjectTools` — list/find/get/create/delete projects
- `WorkflowTools` — list/find statuses for a project's workflow
- `TaskTools` — list/find/get/create/update/move/delete tasks (move accepts `statusId` or `statusName`)
- `FieldTools` — manage the workspace's custom-field catalog and per-project attachments

Designed for AI-agent-driven flows; the frontend stays for human overview.

## Testing

```bash
make test                    # All tests (backend + frontend + e2e)
make test-backend            # PHPUnit (runs inside the backend container)
make test-backend-coverage   # +pcov HTML report at backend/.phpunit.cache/coverage-html
make test-frontend           # Vitest (jsdom + @analogjs/vite-plugin-angular)
make test-e2e                # Playwright (boots the docker stack via webServer)
make test-e2e-ui             # Playwright UI mode
```

Backend tests boot the full `ApplicationFactory` container against a separate
MariaDB database (`ukolio_test`, auto-created by `tests/bootstrap.php`) and
truncate tables between tests via `IntegrationTestCase`. Test helpers live in
`backend/tests/Support/` — `AppHarness` (per-suite singleton),
`IntegrationTestCase` (HTTP dispatch + DB reset), and `Fixture` (deterministic
user/workspace/project/JWT builders). `phpunit.xml` scopes coverage to
`src/{Controller,Mcp,Service,OAuth,Validator}`.

Frontend tests use Vitest 4 with jsdom and the AnalogJS Vite plugin (config
in `frontend/vitest.config.ts`, TestBed bootstrap in `frontend/src/test-setup.ts`).
The app is zoneless, so specs **must not** import `zone.js/testing` — use
`provideZonelessChangeDetection()` in TestBed providers and the standard
`fixture.detectChanges()` / `await fixture.whenStable()` lifecycle.

- File naming: `*.spec.ts` co-located next to the unit under test.
- Shared TestBed boilerplate lives in `frontend/src/app/testing/test-providers.ts` —
  prefer `commonTestProviders()` (zoneless + router + HTTP testing) and
  `provideTranslateStub()` (covers any component whose template uses `TranslatePipe`).
- Run: `pnpm run test` (single run) or `pnpm run test:watch`. `make test-frontend`
  is the equivalent from the repo root.

End-to-end tests use Playwright (config in `frontend/playwright.config.ts`).
Specs live in `frontend/e2e/` with page objects under `frontend/e2e/pages/`.

- The `setup` Playwright project (`e2e/setup/auth.setup.ts`) signs up a fresh
  fixture user per run and writes `e2e/.auth/user.json` (storage state) plus
  `e2e/.auth/credentials.json` (for specs that need to log in again).
- The default `chromium` project reuses that storage state. Auth specs
  (`sign-up.spec.ts`, `login.spec.ts`) opt out via `test.use({storageState: {cookies: [], origins: []}})`.
- `webServer` invokes `docker compose up -d --build --wait` from the repo root.
  `reuseExistingServer: true` makes the run a no-op when `make up` is already
  running. Override the URL with `E2E_BASE_URL=...` and disable the auto-up
  with `E2E_SKIP_WEBSERVER=1` (CI with an external stack).
- Self-signed certs in dev are ignored (`ignoreHTTPSErrors: true`).
- Credentials default to `Test1234!`; override with `E2E_USER_EMAIL` / `E2E_PASSWORD`
  in `.env.test` at the repo root.
- Coverage: sign-up + login + workspace switch/create + project CRUD +
  workflow status CRUD + task CRUD (create → edit → move across statuses →
  delete).

## Linting

Backend uses PHPStan at `max` level (with `bleedingEdge.neon` +
strict/deprecation/phpunit/shipmonk rules + cognitive-complexity +
unused-public) and PHPCS with the slevomat ruleset (tabs, single-line method
signatures ≤140 chars). Custom PHPStan extension
`Ukolio\PhpStan\OrmReadWritePropertiesExtension` marks
`Column`/`ManyToOne`/`ColumnEnum`-attributed properties as ORM-managed
(always read, always written, always initialized).

Frontend uses angular-eslint + `@typescript-eslint`, with
`simple-import-sort` and `unused-imports`. `pnpm run lint` runs with
`--max-warnings=0`.

```bash
make lint           # PHPStan + PHPCS
make lint-fix       # phpcbf auto-fix
```
