<p align="center">
  <img src="frontend/src/assets/brand/logo-wordmark.svg" alt="Ukolio" width="320" />
</p>

<p align="center">
  <strong>The Kanban your <em>agents</em> can drive.</strong>
</p>

<p align="center">
  Multi-tenant task manager built around the <a href="https://modelcontextprotocol.io">Model Context Protocol</a>.
  Claude, Cursor, ChatGPT — any MCP client — plans, creates, moves, and closes tasks alongside your team.
  Self-hostable, MIT-licensed, EN + CS.
</p>

<p align="center">
  <a href="https://www.ukolio.com">www.ukolio.com</a> · MCP endpoint: <code>https://www.ukolio.com/api/mcp</code>
</p>

---

## Why Ukolio

- **MCP-native.** Streamable HTTP transport, session persistence, tools auto-discovered from the backend.
- **OAuth 2.1 + PKCE for agents.** No shared API keys, no copy-paste tokens — each agent has its own credential.
- **Human/Agent attribution.** Every event, comment, and task is tagged `Human` or `Agent`; append-only event log per workspace / project / task.
- **Multi-tenant.** Workspaces with Owner / Admin / Member roles, invitations, and a separate SystemAdmin tier for global operations.
- **Full Kanban kit.** Boards with drag-and-drop, workspace-wide task grid, custom fields, tags, comments, file attachments, task relations, realtime updates over Mercure.

## Stack

| Layer    | Tech |
|----------|------|
| Proxy    | nginx |
| Frontend | Angular 21 (standalone components + signals), SCSS, ngx-translate |
| Backend  | FrankenPHP, PHP 8.5, [`marekskopal/orm`](https://github.com/marekskopal/orm), [`marekskopal/router`](https://github.com/marekskopal/router), Symfony Mailer |
| Database | MariaDB 11.4 |
| Storage  | S3-compatible (MinIO in dev) for task file attachments |
| Realtime | Mercure hub for board / task push updates |
| Mail     | Mailpit (dev) / any SMTP (prod) |
| Auth     | JWT for web, OAuth 2.1 + PKCE for MCP |

## Quick start

```bash
cp .env.example .env                                          # adjust ports / secrets as needed
openssl rand -hex 32                                          # generate AUTHORIZATION_TOKEN_KEY (+ Mercure keys)
make up                                                       # build & start the full stack
make migrate                                                  # run database migrations
docker compose exec backend php bin/console admin:create      # bootstrap the first SystemAdmin
open http://localhost:4300/                                   # default proxy port
```

The backend refuses to boot when `AUTHORIZATION_TOKEN_KEY` is missing, shorter
than 32 characters, or still set to the `replace-with-32-char-random-hex-key-here`
placeholder. Generate one (and the two Mercure JWT keys) with
`openssl rand -hex 32`. With `APP_ENV=production` the same boot guard also
rejects the dev defaults for `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`,
`S3_ACCESS_KEY`, and `S3_SECRET_KEY` — rotate them before going live.

`admin:create` prompts for email + password (or accepts
`--email`/`--password`/`--name` flags for non-interactive provisioning). See
[DEPLOY.md](DEPLOY.md) for deployment details and the upgrade note for
installs that previously shipped a default admin.

Anyone can also sign up at `/sign-up`; the first registration auto-creates a
personal workspace. New accounts go through an email-verification flow
(`POST /api/authentication/verify-email`), and the standard password-reset
loop is wired (`request-password-reset` → `confirm-password-reset`).

## Domain

- **Workspace** — top-level tenant; users belong to one or more workspaces.
- **WorkspaceUser** — membership with a role (`Owner` / `Admin` / `Member`).
- **Invitation** — pending email invite, signed token, expires after 7 days.
- **User** — `email`, `password`, `name`, `currentWorkspaceId`, `systemRole`
  (`User` / `SystemAdmin`). `currentWorkspaceId` scopes every web request.
- **Project** — workspace-scoped; auto-seeds a `Workflow` of
  `To Do → In Progress → Done` on creation.
- **Workflow** → **Status** (`Start` / `Normal` / `Finish`, with name + color +
  position).
- **Task** — project-scoped, lives in a Status, has name / Markdown
  description / priority (`Low` / `Medium` / `High`) / due date / position.
  `createdByAgent = true` when the row was created via MCP.
- **Field / ProjectField / TaskFieldValue** — per-workspace catalog of custom
  fields (`Text` / `Textarea` / `Select` / `Version` semver). Projects opt-in
  to fields; their values are persisted per task.
- **Tag / TaskTag** — workspace-wide tag catalog with colors; tags attach to
  tasks many-to-many.
- **TaskComment** — Markdown comments attributed to the author and tagged
  `Human` or `Agent` (the MCP transport flips actor type via `ActorContext`).
- **TaskFile** — file attachments stored in the configured S3-compatible
  bucket; metadata persisted alongside the task.
- **TaskRelation** — typed link between two tasks (`Related` / `Duplicates` /
  `Parent` / `DependsOn`).
- **Event** — append-only audit log keyed to workspace / project / task; covers
  task / project / workflow / status / field / tag / comment / file / relation
  / membership / admin actions.

## Roles & permissions

Authorization is centralized in `Ukolio\Service\Auth\PermissionChecker`. Every
mutating controller routes through it.

- **SystemAdmin** — global; passes every `can*` check. Operates on workspaces
  they don't belong to via `/api/admin/*` endpoints (separate frontend at
  `/admin/users` and `/admin/workspaces`). Inside their own workspaces they act
  as a normal member.
- **Owner** — workspace-scoped, one per workspace. Renames / deletes the
  workspace, manages all members, transfers ownership.
- **Admin** — workspace-scoped. Manages members (Member ↔ Admin), invites
  Members, full CRUD on projects, workflows, statuses, custom fields, tags,
  tasks.
- **Member** — workspace-scoped. Full CRUD on tasks (incl. comments, files,
  relations, tag assignment); read-only on the rest.

Ownership transfer (`POST /api/workspaces/{id}/transfer-ownership`) is atomic
— the old Owner becomes Admin. Workspace owner removal is blocked; transfer
first.

## Web UI

| Route | Purpose |
|-------|---------|
| `/login`, `/sign-up`, `/invitations/accept` | Public auth pages |
| `/projects` | Project list (workspace-scoped) |
| `/projects/:id/board` | Kanban board with drag-and-drop and task drawer |
| `/projects/:id/workflow` | Workflow editor |
| `/projects/:id/events` | Project activity log |
| `/tasks` | Workspace-wide task grid — search, multi-status filter, sortable columns, pagination |
| `/workspaces` | Membership, invitations, tags, custom fields, MCP clients, agent stats, events |
| `/admin/users`, `/admin/workspaces` | SystemAdmin tools |

i18n: EN + CS, switchable from the topbar. Choice is persisted to the user via
`PATCH /api/current-user` so transactional emails arrive in the right
language. Frontend uses `@ngx-translate/core`; backend renders emails via
`TranslatorService` loading `backend/translations/{en,cs}.json`.

## MCP server

Exposed at `POST/GET/DELETE /api/mcp` over Streamable HTTP (using `mcp/sdk`).
Sessions persist to `MCP_SESSION_DIR` (defaults to `<tmp>/ukolio-mcp-sessions`).

**Auth: OAuth 2.1 + PKCE.** Discovery endpoints:

- `GET /.well-known/oauth-authorization-server/api/mcp`
- `GET /.well-known/oauth-protected-resource/api/mcp`
- `POST /api/mcp/oauth/register` — dynamic client registration (open)
- `POST /api/mcp/oauth/authorize` — user approval (requires user JWT)
- `POST /api/mcp/oauth/token` — code/refresh-token exchange (open)
- `GET /api/mcp/oauth/client-info` — display name lookup (open)

401 responses include `WWW-Authenticate: Bearer resource_metadata="…"` per
RFC 9728. PKCE `S256` only; no client secret. Access token TTL 1 h, refresh
30 d. Tokens are stored as SHA-256 hashes in `oauth_clients` and
`oauth_authorizations`.

Auto-discovered tools (`backend/src/Mcp/Tool/`):

- `ProjectTools` — list / find / get / create / delete projects.
- `WorkflowTools` — list / find statuses for a project's workflow.
- `TaskTools` — list / find / get / create / update / move / delete tasks
  (move accepts `statusId` *or* `statusName`).
- `FieldTools` — manage the workspace's custom-field catalog and per-project
  attachments.
- `TagTools` — list / find / create / update / delete tags, plus
  `set_task_tags` to replace the tag set on a task.
- `TaskCommentTools` — list & add comments (agent-tagged automatically).
- `TaskFileTools` — list / attach (base64) / fetch / delete task files.
- `TaskRelationTools` — list / link / unlink typed task relations.

All MCP tools are scoped to the calling user's `currentWorkspace`. SystemAdmins
must use the web admin UI for cross-workspace work. Per-workspace MCP-client
inventory is exposed at `GET /api/workspaces/{id}/mcp-clients`, and
agent-vs-human activity ratios at `GET /api/workspaces/{id}/agent-stats`.

## Realtime

`Service\Realtime\RealtimePublisher` pushes board and task changes to a
Mercure hub. Subscriber JWTs are issued as cookies (`MercureCookieIssuer`) on
authentication / workspace switch; publisher tokens are minted per request.
Set `MERCURE_PUBLISHER_JWT_KEY` and `MERCURE_SUBSCRIBER_JWT_KEY` to enable —
when either is unset the boot guard wires `NullMercureHub` and the rest of
the app keeps working without push updates.

## Project layout

```
proxy/      nginx reverse proxy (/api/* → backend, /* → frontend)
backend/    FrankenPHP + PHP 8.5
  src/
    Controller/       HTTP endpoints (attribute-routed via marekskopal/router)
    Dto/              Wire-level DTOs for requests / responses
    Model/Entity/     ORM entities + enums
    Model/Repository/ Repository classes (+ Enum/ for query enums)
    Service/          Providers, auth, request, translator, realtime, storage, etc.
    Mcp/              MCP tools, DTOs, user context
    OAuth/            OAuth 2.1 + PKCE flow for MCP clients
    Middleware/       Authorization, CORS, error handler
    PhpStan/          Custom PHPStan extension for ORM property semantics
  migrations/         marekskopal/orm-migrations
  translations/       en.json, cs.json — backend (email) strings
  tests/              PHPUnit
frontend/   Angular 21 SPA
  src/app/
    authentication/   Login, sign-up, password reset, email verification
    projects/         Project list + CRUD
    board/            Kanban board + task drawer (tags, comments, files, relations)
    workflow-editor/  Workflow + status editing
    tasks/            Workspace-wide tasks grid
    events/           Activity log
    workspaces/       Workspace management, invitations, tags, MCP clients
    agents/           Agent activity stats
    admin/            SystemAdmin pages
    invitations/      Invitation accept flow
    oauth/            MCP OAuth consent screen
    settings/         User account settings
    services/         API clients
    models/           TypeScript interfaces
    shared/components/ Layout, alert, pagination
    core/             Guards, interceptors
  src/assets/brand/   Logo marks + wordmarks (SVG)
  src/i18n/           en.json, cs.json — frontend strings
  src/styles/         SCSS design tokens + mixins
log/        Backend log mount
```

## Common commands

| Command | What it does |
|---------|--------------|
| `make up` | Build & start the full stack |
| `make down` | Stop the stack |
| `make logs` | Tail container logs |
| `make migrate` | Run database migrations |
| `make test` | All tests (backend + frontend + e2e) |
| `make test-backend` | PHPUnit only |
| `make test-frontend` | Vitest only |
| `make test-e2e` | Playwright (boots the docker stack via webServer) |
| `make test-e2e-ui` | Playwright UI mode |
| `make lint` | PHPStan (max) + PHPCS |
| `make lint-fix` | phpcbf auto-fix |
| `make install` | `composer install` + `pnpm install` on host |
| `docker compose --profile dev up -d` | Stack + Adminer at the proxy |

### Direct frontend commands

From `frontend/`:

```bash
pnpm start         # ng serve (proxies API via dev server config)
pnpm build         # production build
pnpm test          # vitest run
pnpm run lint      # ng lint --max-warnings=0
```

### Direct backend commands

From `backend/`:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/phpcs
vendor/bin/phpcbf
php bin/console migration:run
```

## Linting

- **Backend**: PHPStan at `max` level with `bleedingEdge.neon` + strict /
  deprecation / phpunit / shipmonk / cognitive-complexity / unused-public
  rules. PHPCS uses the slevomat ruleset (tabs, single-line method signatures
  ≤ 140 chars). A custom PHPStan extension
  (`Ukolio\PhpStan\OrmReadWritePropertiesExtension`) marks `#[Column]` /
  `#[ManyToOne]` / `#[ColumnEnum]` properties as ORM-managed (always read,
  always written, always initialized).
- **Frontend**: angular-eslint + `@typescript-eslint`, `simple-import-sort`,
  `unused-imports`. `pnpm run lint` enforces zero warnings.

## Environment variables

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | `development` (default) or `production`. `production` rejects default MYSQL/S3 credentials and short secrets at boot |
| `PROXY_PORT` | Host port the nginx proxy binds to |
| `MYSQL_*` | Database credentials (rotate from defaults before `APP_ENV=production`) |
| `AUTHORIZATION_TOKEN_KEY` | 32-char secret used to sign JWTs. Generate with `openssl rand -hex 32`; boot fails on the placeholder |
| `MERCURE_PUBLISHER_JWT_KEY` / `MERCURE_SUBSCRIBER_JWT_KEY` | Mercure realtime hub JWT keys. Generate with `openssl rand -hex 32` |
| `S3_ACCESS_KEY` / `S3_SECRET_KEY` / `S3_BUCKET` / `S3_ENDPOINT` / `S3_REGION` | Object-storage credentials for task file attachments (rotate from `minioadmin` before `APP_ENV=production`) |
| `MCP_SESSION_DIR` | Override directory for persisted MCP sessions (default `<tmp>/ukolio-mcp-sessions`) |
| `BACKEND_FRANKENPHP_WORKERS` | FrankenPHP worker count |
| `BACKEND_CORS_ALLOWED_ORIGIN` | Allowed Origin(s) for `/api/*` and Mercure. `*` for dev; with `APP_ENV=production` an explicit space- or comma-separated list is required |
| `BACKEND_LOG_LEVEL` | `development` / `production` |
| `SMTP_HOST` / `SMTP_PORT` / `SMTP_USER` / `SMTP_PASSWORD` | Outbound mail |
| `EMAIL_FROM` | Sender used by invitation, verification, and password-reset emails |
| `APP_URL` | Base URL embedded in email links |

`mailpit` is wired into `docker-compose.yml` so local invitations are captured
at the SMTP layer instead of being sent.

## License

[MIT](LICENSE)
