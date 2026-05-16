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

## Testing

```bash
make test           # All tests (backend + frontend)
make test-backend   # PHPUnit
make test-frontend  # Vitest
```
