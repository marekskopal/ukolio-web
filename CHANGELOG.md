# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- The backend runs on **`marekskopal/orm` 2.0** (and `orm-migrations` 2.0).
  Updates write only the columns that actually changed, so two actors editing
  different fields of the same task (say an MCP agent and a human) no longer
  overwrite each other's change, and saving an unchanged entity costs no query.
  Lazy relations load in batches, which removes N+1 queries when board and list
  views read each task's status, priority, and assignee. Prepared statements are
  reused per connection, and the database connection opens on the first query.
- The ORM schema is **dumped to `backend/var/orm-schema.php` during the image
  build** (`php bin/console orm:schema-dump`) and loaded from there under
  opcache, instead of scanning entity attributes and caching the result in
  memcached. Without the file (dev/test bind mount) the schema is built at
  process start, so after changing an entity you restart the backend instead of
  flushing memcached.
  **Upgrade notes:** rebuild the backend image. The old `Orm`/`Schema` memcached
  entry is no longer read and expires on its own.
- Deleting a task removes it and everything attached to it (field values, files,
  relations, checklist items, watchers, recurrence, tags) **in one transaction**.
  Before, a failure partway through could leave a half-deleted task. Stored file
  objects are removed only after their rows are gone.
- Writes that used to go row by row are batched into one transaction: reordering
  statuses, priorities, tasks, and checklist items (the moved row and its
  shifted siblings now save together), tag and custom-field-value sync,
  mark-all-read, unassigning a departing member's tasks, deleting a field or a
  comment thread, and account self-deletion. New projects write their default
  workflow and its three statuses together, and a recurring task's next
  occurrence copies the checklist with a single insert.
- `search:reindex` keeps memory bounded by the largest project instead of the
  whole installation.

## [1.2.0] - 2026-10-05

### Changed

- The realtime hub speaks **Mercure protocol 1.0**. FrankenPHP 1.13 bundles a
  Mercure release that only accepts the old `publisher_jwt`/`subscriber_jwt`
  setup in a relaxed compatibility mode, so the hub now trusts a single
  `ukolio` issuer (`issuer` block in `backend/Caddyfile`) and the backend mints
  RFC 9068 access tokens (`typ: at+jwt`, `iss`, `aud`, `exp`) whose grants ride
  in an RFC 9396 `authorization_details` claim instead of the legacy `mercure`
  claim (`MercureAccessToken`). The browser subscribes with `?match=` — the hub
  answers the old `?topic=` with `400`.
  **Upgrade notes:** the token audience defaults to `MERCURE_PUBLISH_URL`; set
  `MERCURE_RESOURCE_IDENTIFIER` only if the hub must be addressed by a different
  URL. Open tabs running the previous frontend lose live updates until they
  reload, and subscriber cookies issued before the upgrade are rejected until
  the next sign-in or token refresh replaces them — nothing else is affected.
- FrankenPHP 1.13 / PHP 8.5.11, Angular 22.2, and the remaining backend,
  frontend, and Docker image dependencies updated to their latest minor/patch
  releases. Adminer (dev profile) moves to 6.1.

### Fixed

- `docker compose up` failed again, this time with `unauthorized` on the MinIO
  pull: quay.io stopped serving the image too. The compose file now uses the
  community-maintained `pgsty/minio` build — same CLI and `RELEASE.*` tags, and
  it reads existing data volumes unchanged.

## [1.1.0] - 2026-09-17

### Added

- The admin user list shows a **Last login** column. It is stamped when a user
  proves their identity (password or Google sign-in), not on a token refresh —
  a refresh extends an existing session rather than starting one, so counting it
  would make every dormant account look active. Null until the account signs in
  for the first time after upgrading.
- The admin workspace list and detail show **project and task counts**, so how
  much work a workspace actually holds is visible without opening it.

### Fixed

- Code blocks in rendered markdown were unreadable. The preview painted `pre`
  with the theme's text/text-inverse pair, so blocks inverted against the rest
  of the UI in dark mode, and the generic `code` rule also matched the `<code>`
  nested inside `<pre>`, re-applying a muted background while the text kept the
  inverse colour — white on light grey in light mode, dark on dark grey in dark
  mode. Code now has dedicated theme-aware tokens and `pre` is styled once.
- `docker compose up` failed on every install with `pull access denied for
  minio/minio`: MinIO's images are no longer published to Docker Hub. The
  compose file now pulls the identical release from quay.io.

## [1.0.2] - 2026-09-10

### Fixed

- Scheduled scripts were dispatched but never executed. The script-worker runs
  as the unprivileged `ukolio-script` user (uid 10001, added in 1.0.1's
  hardening) and could not append to the shared `/app/log` files owned by the
  root-run processes. Tracy throws when it cannot write, that throw escaped the
  consume callback before the message was acked or nacked, and after three fast
  restarts supervisord left the `script-run` queue with no consumer at all — so
  every scheduled run since piled up unprocessed. The worker now logs to its
  own `/app/log/script` directory (`BACKEND_LOG_DIR`, prepared by the new
  container entrypoint), and a logging fault can no longer abort the caller: it
  falls back to stderr via `SafeLogger`.
- `supervisorctl` works inside the backend container again (`[unix_http_server]`
  / `[supervisorctl]` / `[rpcinterface]` sections were missing, so
  `supervisorctl status` failed with "ini file does not include supervisorctl
  section" and a dead program could not be inspected or restarted).
- The queue consumers tolerate a dependency that is briefly unreachable at boot
  (`startretries=30`): the default of three start retries was exhausted within
  seconds of a RabbitMQ restart, disabling the queues until the next deploy.

## [1.0.1] - 2026-07-03

### Fixed

- Scheduled automation scripts, due-date reminders, and recurring-task
  spawning now actually run: the backend container ships a built-in
  supercronic cron (`scripts:tick` every minute, `notifications:due-tick` and
  `recurring-tasks:tick` hourly) instead of relying on a host cron that
  operators had to install by hand.
- MCP `list_scripts` / `get_script` (and script create/update responses) now
  report the real `runCount` and `lastStatus` instead of always `0` / `null`.
- DEPLOY.md worker-restart instructions now use `pkill` (supervisord respawns
  the process); the documented `supervisorctl restart` command never worked.

## [1.0.0] - 2026-07-03

First public release. Ukolio is a minimalistic, multi-tenant project & task
manager where AI agents (over MCP) and humans (over the web UI) are equal
first-class actors.

### Added

- **Projects, tasks & views** — projects with customizable per-project
  workflows (statuses), tasks rendered as board, table, calendar, and timeline;
  priorities, tags, custom fields, due/start dates, and task archiving.
- **Task collaboration** — markdown descriptions, threaded comments with
  `@mentions`, checklists, subtasks and typed task relations, file attachments,
  duplication, and reusable task templates.
- **Recurring tasks** — daily/weekly/monthly/cron cadences with a single-carrier
  invariant and hybrid (event- and cron-driven) spawning.
- **Multi-tenancy & roles** — workspaces with Owner/Admin/Member roles,
  invitations, ownership transfer, and a SystemAdmin surface for cross-workspace
  administration.
- **Notifications** — in-app notification center with a realtime bell (Mercure),
  task watchers, and email notifications for mentions, assignments, and
  due-date reminders.
- **MCP server** — OAuth 2.1 + PKCE-secured Streamable HTTP transport exposing
  the full task/project surface plus search, events, and a sandboxed automation
  scripting layer, so agents can plan, create, move, and close work.
- **Automation scripts** — admin-authored JavaScript automations running in a
  V8 sandbox, triggered on events or a cron schedule.
- **Search** — typo-tolerant full-text search across tasks (Meilisearch).
- **Realtime** — live board/task updates and notification pings via a Mercure
  hub.
- **Internationalization** — English and Czech, on both the web UI and
  transactional emails.
- **Authentication** — email/password and Google sign-in for the web UI, with
  email verification and password reset.

### Security

- OAuth dynamic client registration now rejects non-`https` `redirect_uri`
  values (loopback `http` still allowed), closing a stored-XSS vector in the app
  origin; the frontend authorize flow additionally guards the redirect sink.
- The V8 automation-script worker now runs as an unprivileged user instead of
  root, so a sandbox escape no longer lands with full container privileges.
- Logout now expires the HttpOnly Mercure subscriber cookie, preventing a
  subsequent user on a shared browser from resuming the previous session's
  realtime stream.

### Fixed

- Malformed or invalid request bodies (bad JSON, missing/mistyped fields) now
  return `400 Bad Request` instead of `500`, and are logged at warning rather
  than error level.

[Unreleased]: https://github.com/marekskopal/ukolio/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/marekskopal/ukolio/releases/tag/v1.2.0
[1.1.0]: https://github.com/marekskopal/ukolio/releases/tag/v1.1.0
[1.0.2]: https://github.com/marekskopal/ukolio/releases/tag/v1.0.2
[1.0.1]: https://github.com/marekskopal/ukolio/releases/tag/v1.0.1
[1.0.0]: https://github.com/marekskopal/ukolio/releases/tag/v1.0.0
