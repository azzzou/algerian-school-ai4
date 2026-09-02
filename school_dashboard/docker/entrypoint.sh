#!/usr/bin/env sh
set -eu

# =============================================================================
#  Render/cloud entrypoint for the Algerian School Support Laravel app.
#  Runs as root, then exec's Apache (which spawns www-data workers).
#
#  1. Re-point Apache to Render's $PORT (Apache defaults to 80).
#  2. Fix Laravel runtime permissions (storage + bootstrap/cache).
#  3. Clear stale caches, then build config/route/view caches from runtime env.
#  4. Apply DB migrations.
#  5. Launch Apache in the foreground.
# =============================================================================

PORT="${PORT:-10000}"
APP_DIR="${APP_DIR:-/var/www/html}"
DB_DATABASE="${DB_DATABASE:-${APP_DIR}/database/database.sqlite}"

echo "[entrypoint] Port=${PORT} AppDir=${APP_DIR}"

# --- 0. Preconditions ---------------------------------------------------------
# Render injects these at runtime. APP_KEY is required for cookie/session
# encryption; warn clearly instead of failing cryptically later.
if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] WARN: APP_KEY is not set. Set it in Render Dashboard / env group." >&2
fi

# --- 1. Apache ports ---------------------------------------------------------
# Bind Apache deterministically to Render's $PORT (default 10000). We WRITE the
# files outright instead of relying on sed matching the base image, so the
# container is guaranteed to open a listening port (avoids Render's
# "no open ports detected" failure).
printf 'Listen %s\n' "${PORT}" > /etc/apache2/ports.conf
# Re-point the site's <VirtualHost *:80> to <VirtualHost *:$PORT>.
sed -ri "s/^<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/*.conf /etc/apache2/sites-enabled/*.conf 2>/dev/null || true

cd "${APP_DIR}"

# --- 2. Writable runtime dirs ------------------------------------------------
# Ensure every Laravel runtime directory exists and is owned/writable by the
# Apache worker user (www-data). bootstrap/cache must be writable so
# route:cache / config:cache can write their serialized files.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    "$(dirname "${DB_DATABASE}")"

chown -R www-data:www-data storage bootstrap/cache "$(dirname "${DB_DATABASE}")" 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# --- SQLite bootstrap --------------------------------------------------------
# First boot: seed the DB from the app's shipped copy if empty/missing.
if [ ! -f "${DB_DATABASE}" ] || [ ! -s "${DB_DATABASE}" ]; then
    if [ -f "${APP_DIR}/database/database.sqlite" ]; then
        cp "${APP_DIR}/database/database.sqlite" "${DB_DATABASE}"
    else
        touch "${DB_DATABASE}"
    fi
fi
chown www-data:www-data "${DB_DATABASE}" 2>/dev/null || true

# --- 3. Clear stale caches, then build fresh ones ----------------------------
# APP_KEY etc. are injected by Render only at runtime, so caches must NEVER be
# baked at image build time. We generate them here. If caching fails (e.g.
# missing secret on first boot), clear and boot uncached so the app still runs.
# Run artisan as the Apache worker user so generated caches stay writable by
# www-data. If `su` is unavailable, run as the current (root) user and re-chown.
run_laravel() {
    _cmd="php artisan $*"
    if [ "$(id -u)" = "0" ]; then
        # Prefer running as www-data so our caches are theirs from the start.
        if su -s /bin/sh www-data -c "$_cmd" 2>/dev/null; then
            return 0
        fi
        # Fallback: run as root, then make the output writable by www-data.
        sh -c "$_cmd"
        _rc=$?
        chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
        return ${_rc}
    fi
    php artisan "$@"
}

run_laravel config:clear  >/dev/null 2>&1 || true
run_laravel cache:clear   >/dev/null 2>&1 || true
run_laravel view:clear    >/dev/null 2>&1 || true

if run_laravel config:cache && run_laravel route:cache && run_laravel view:cache; then
    echo "[entrypoint] config:cache, route:cache, view:cache built OK."
else
    echo "[entrypoint] WARN: cache build failed; booting uncached." >&2
    run_laravel config:clear >/dev/null 2>&1 || true
    run_laravel route:clear  >/dev/null 2>&1 || true
    run_laravel view:clear   >/dev/null 2>&1 || true
fi

# --- 4. Migrations + seeding (non-fatal so the health probe can pass early) --
run_laravel migrate --force >/dev/null 2>&1 || echo "[entrypoint] WARN: migrate skipped/failed." >&2

# Guaranteed super-admin so the login page is always reachable. Idempotent: the
# DefaultAdminSeeder checks by email and never wipes existing users, so it safe
# to run on every boot even without Shell access (Render free tier).
run_laravel db:seed --class=DefaultAdminSeeder --force >/dev/null 2>&1 \
    || echo "[entrypoint] WARN: admin seeder skipped/failed." >&2

# Full dataset (settings, subjects, user types, ...) is only seeded once, on the
# first boot when the DB is empty. On later restarts the table has rows and the
# general db:seed is skipped, preserving any admin/user changes made in-app.
_SEED_MARKER="${DB_DATABASE}.seeded"
if [ ! -f "${_SEED_MARKER}" ]; then
    if run_laravel db:seed --force >/dev/null 2>&1; then
        : > "${_SEED_MARKER}"
        echo "[entrypoint] Full db:seed completed (one-time)."
    else
        echo "[entrypoint] WARN: full db:seed skipped/failed." >&2
    fi
else
    echo "[entrypoint] Full db:seed already run; skipping."
fi

echo "[entrypoint] Starting Apache on 0.0.0.0:${PORT}"
exec /usr/sbin/apache2ctl -D FOREGROUND