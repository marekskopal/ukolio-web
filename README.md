# Task Manager

A minimalistic Kanban-style task manager. Architecture cloned from FinGather.

- **Proxy**: nginx
- **Frontend**: Angular 21 (standalone + signals) + Tailwind v4
- **Backend**: FrankenPHP + PHP 8.5 + `marekskopal/orm`
- **Database**: MariaDB 11.4
- **Auth**: JWT

## Quick start

```bash
cp .env.example .env
make up
make migrate
open http://localhost:4300/
```

## Layout

- `proxy/` — nginx reverse proxy
- `backend/` — FrankenPHP API
- `frontend/` — Angular SPA

## Commands

| Command | Description |
|---------|-------------|
| `make up` | Start the stack |
| `make down` | Stop the stack |
| `make logs` | Tail container logs |
| `make migrate` | Run database migrations |
| `make test` | Run all tests |
| `docker compose --profile dev up -d` | Start stack incl. Adminer |
