# Deploying Ukolio

## First boot

After bringing the stack up and applying migrations, the database has no
SystemAdmin — you must create one before anyone can manage workspaces or
access `/admin/*`.

```bash
make up                                              # build & start the stack
make migrate                                         # apply migrations

# Interactive (prompts for email + password):
docker compose exec backend php bin/console admin:create

# Non-interactive (e.g. provisioning scripts):
docker compose exec -T backend php bin/console admin:create \
    --email admin@example.com \
    --password "$(openssl rand -base64 24)" \
    --name "Ops"
```

Passwords must be at least 12 characters. Re-run the command later to add
additional SystemAdmins; existing users are detected by email and rejected.

## Upgrading from a build that seeded `admin@ukolio.com`

Earlier builds shipped a default SystemAdmin (`admin@ukolio.com` / `admin`)
that was created by the init migration. From `20260520_120000` onward, the
init migration no longer seeds that user, and a follow-up migration
invalidates the password on existing installs **if it is still the default**.

After `make migrate`:

1. The legacy `admin@ukolio.com` account is still in the database but its
   password is replaced with an unverifiable random string, so nothing can
   log in as it.
2. Run `admin:create` to provision your real SystemAdmin (above).
3. Log in as the new SystemAdmin and either rotate the legacy user's
   password via the admin UI or delete the row.

If your team had already rotated the admin password, the follow-up migration
detects this (it only acts when `password_verify('admin', …)` succeeds) and
leaves the account alone.

## Environment

Required variables — see `.env.example` for the full list:

| Variable | Notes |
|----------|-------|
| `AUTHORIZATION_TOKEN_KEY` | ≥ 32 chars; sign with `openssl rand -hex 32` |
| `MYSQL_*` | Database host + credentials |
| `SMTP_*`, `EMAIL_FROM` | Outbound mail (invitations, password resets) |
| `APP_URL` | Embedded in email links |
| `S3_*` | Object storage for task file attachments |
| `REDIS_*` | Used by the MCP session store |

## SSL termination

The default `docker-compose.yml` exposes plain HTTP on `${PROXY_PORT}`. To
terminate TLS at the proxy, layer on `docker-compose.ssl.yml`:

```bash
docker compose -f docker-compose.yml -f docker-compose.ssl.yml up -d
```

Requires `PROXY_HOST`, `PROXY_PORT_SSL`, `PROXY_SSL_CERT`, `PROXY_SSL_KEY`.
