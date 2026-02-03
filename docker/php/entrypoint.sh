#!/bin/sh
set -e

ROLE="${APP_ROLE:-web}"
APP_DIR="/var/www/html"

# -----------------------------
# Helpers
# -----------------------------
log() {
  echo "[entrypoint][$ROLE] $*"
}

ensure_dir() {
  # mkdir always safe
  mkdir -p "$1"
}

ensure_writable_dir() {
  dir="$1"
  uid="${UID:-1000}"
  gid="${GID:-1000}"

  ensure_dir "$dir"

  # Quick writable check: if not writable, try to fix
  if [ ! -w "$dir" ]; then
    log "Directory not writable: $dir (attempting to fix permissions)"

    # Try ownership first (may fail on some bind mounts - keep going)
    chown "$uid:$gid" "$dir" 2>/dev/null || true

    # Ensure dir itself is writable by owner/group
    chmod 775 "$dir" 2>/dev/null || true
  fi

  # Extra safety: try creating a tiny temp file to confirm writability
  tmp="$dir/.perm_test_$$"
  (umask 002 && : > "$tmp") 2>/dev/null || true
  rm -f "$tmp" 2>/dev/null || true
}

# -----------------------------
# 1) Always: minimal filesystem prep (safe for workers too)
# -----------------------------
ensure_writable_dir "$APP_DIR/storage"
ensure_writable_dir "$APP_DIR/bootstrap/cache"

ensure_writable_dir "$APP_DIR/storage/framework/cache"
ensure_writable_dir "$APP_DIR/storage/framework/sessions"
ensure_writable_dir "$APP_DIR/storage/framework/views"
ensure_writable_dir "$APP_DIR/storage/logs"

# -----------------------------
# 2) Web-only: heavy init steps (DB, composer, migrate)
# -----------------------------
if [ "$ROLE" = "web" ]; then
  log "Running web init steps (db/composer/migrate)"

  # DB create (retry loop)
  set +e
  for i in 1 2 3 4 5 6 7 8 9 10; do
    mysql --ssl=0 -h "${DB_HOST}" -P "${DB_PORT}" -u root -p"${MYSQL_ROOT_PASSWORD}" \
      -e "CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\`;" && break
    log "Waiting for MySQL... ($i/10)"
    sleep 3
  done

  # User/grants (best-effort)
  mysql --ssl=0 -h "${DB_HOST}" -P "${DB_PORT}" -u root -p"${MYSQL_ROOT_PASSWORD}" \
    -e "CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY 'laravel';
        GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
        FLUSH PRIVILEGES;" || true

  # Composer install only if vendor missing
  if [ -f "$APP_DIR/composer.json" ] && [ ! -d "$APP_DIR/vendor" ]; then
    log "Installing composer dependencies (vendor missing)"
    composer install --no-interaction --prefer-dist || true
  fi

  # Migrate (retry loop)
  for i in 1 2 3 4 5; do
    php "$APP_DIR/artisan" migrate --force && break
    log "Migrate failed, retrying... ($i/5)"
    sleep 5
  done
  set -e
else
  log "Skipping web init steps (ROLE=$ROLE)"
fi

# -----------------------------
# 3) Run the actual container command
#    - If no args provided -> default php-fpm
#    - If args provided -> run them (e.g., queue worker)
# -----------------------------
if [ "$#" -eq 0 ]; then
  exec php-fpm
fi

exec "$@"