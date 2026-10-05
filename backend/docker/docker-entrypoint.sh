#!/bin/sh
# Prepare the log directories, then hand off to the container command (supervisord).
#
# /app/log is a bind mount shared with the other containers, so its ownership comes from the host
# and cannot be fixed in a Dockerfile layer. The script-worker runs as ukolio-script (uid 10001)
# and Tracy aborts the process when it cannot append to its log file, so give that process its own
# subdirectory here (BACKEND_LOG_DIR in docker/supervisord.conf). The log files shared with the
# root-run processes — frankenphp, the amqp-consumer, cron — stay out of its reach.
set -e

SCRIPT_LOG_DIR="${SCRIPT_WORKER_LOG_DIR:-/app/log/script}"

mkdir -p /app/log "$SCRIPT_LOG_DIR"

# Best-effort on Linux, where it makes the directory uid-10001's own. Bind mounts on some hosts
# (Docker Desktop) report success without changing anything, so the mode below is what actually
# guarantees the worker can write. It only widens this one subdirectory.
chown ukolio-script "$SCRIPT_LOG_DIR" 2>/dev/null || true
chmod 0777 "$SCRIPT_LOG_DIR"

# The Mercure hub (Caddyfile) pins its resource identifier — the "aud" every access token must
# carry — to the URL the backend publishes to. Keep in sync with MercureAccessToken::resourceIdentifier().
if [ -z "${MERCURE_RESOURCE_IDENTIFIER:-}" ]; then
	export MERCURE_RESOURCE_IDENTIFIER="${MERCURE_PUBLISH_URL:-http://localhost/.well-known/mercure}"
fi

exec "$@"
