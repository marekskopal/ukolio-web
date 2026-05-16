# Task Manager

Minimalistic Kanban task manager. Multi-user JWT auth. Architecture clones FinGather (`/Users/marek/web/www/fingather/`).

## Services

- `proxy/` — nginx reverse proxy (`/api/*` → backend, `/` → frontend)
- `backend/` — FrankenPHP + PHP 8.5 + `marekskopal/orm` + MariaDB
- `frontend/` — Angular 21 (standalone, signals) + Tailwind v4

## Domain

- `Project` (user, name, description) → has one `Workflow`, many `Tasks`
- `Workflow` (project, name) → has many `Status`
- `Status` (workflow, name, color, position, type ∈ start/normal/finish)
- `Task` (project, status, name, description [markdown], priority, dueDate, position)
- `Event` (author, project, taskId?, type, metadata JSON) — append-only audit log

New `Project` auto-seeds workflow `To Do → In Progress → Done`.

## Docker

```bash
docker compose up -d --build              # Full stack
docker compose --profile dev up -d        # +Adminer
make migrate                               # Apply migrations
```

## MCP server

Exposed at `POST/GET/DELETE /api/mcp` (Streamable HTTP transport, `mcp/sdk`). Mirrors `fingather/backend/src/Mcp/`. Sessions persisted to `MCP_SESSION_DIR` (defaults to `<tmp>/task-manager-mcp-sessions`).

Auth is **OAuth 2.1 with PKCE** (mirrors fingather/backend/src/OAuth/). Discovery endpoints:
- `GET /.well-known/oauth-authorization-server/api/mcp` — issuer/authz/token/registration URLs
- `GET /.well-known/oauth-protected-resource/api/mcp` — resource metadata
- `POST /api/mcp/oauth/register` — dynamic client registration (open)
- `POST /api/mcp/oauth/authorize` — user approval (requires user JWT)
- `POST /api/mcp/oauth/token` — code/refresh-token exchange (open)
- `GET /api/mcp/oauth/client-info` — display name lookup (open)

401 responses include `WWW-Authenticate: Bearer resource_metadata="…"` per RFC 9728 so MCP clients can auto-discover. PKCE `S256` only; no client secret. Access token lifetime 1h, refresh 30d. Storage: `oauth_clients` and `oauth_authorizations` tables (tokens stored as SHA-256 hashes).

Tools live in `backend/src/Mcp/Tool/` (auto-discovered by basePath/scanDirs):
- `ProjectTools` — list/find/get/create/delete projects
- `WorkflowTools` — list/find statuses for a project's workflow
- `TaskTools` — list/find/get/create/update/move/delete tasks (move accepts `statusId` or `statusName`)

Designed for AI-agent-driven flows; the frontend stays for human overview.

## Testing

```bash
make test           # All tests (backend + frontend)
make test-backend   # PHPUnit
make test-frontend  # Vitest
```

## Linting

Backend uses PHPStan at `max` level (with `bleedingEdge.neon` + strict/deprecation/phpunit/shipmonk rules + cognitive-complexity + unused-public) and PHPCS with the slevomat ruleset (ported from fingather; tabs, single-line method signatures ≤140 chars). Custom PHPStan extension `TaskManager\PhpStan\OrmReadWritePropertiesExtension` marks `Column`/`ManyToOne`/`ColumnEnum`-attributed properties as ORM-managed (always read, always written, always initialized).

```bash
make lint           # PHPStan + PHPCS
make lint-fix       # phpcbf auto-fix
```
