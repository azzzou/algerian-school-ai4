#!/usr/bin/env sh
set -e

# ---------------------------------------------------------------------------
# Render/cloud entrypoint for the Algerian School Support Laravel app.
# Render injects $PORT on web services; Apache must bind to that port instead
# of the Dockerfile default 80 so Render's health checks succeed.
# SQLite uses Laravel's default path (database/database.sqlite) inside the
# container. On Render's free plan there is no persistent disk, so data is
# ephemeral across redeploys; the shipped DB is re-seeded + migrated on boot.
# ---------------------------------------------------------------------------

PORT="${PORT:-80}"
DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"

# Point Apache's Listen + VirtualHost ports at $PORT.
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/^<VirtualHost .*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf

# Ensure the database directory exists and is writable.
mkdir -p "$(dirname "${DB_DATABASE}")"
chown www-data:www-data "$(dirname "${DB_DATABASE}")" 2>/dev/null || true

# First boot: seed the database from the app's shipped copy if empty/missing.
if [ ! -f "${DB_DATABASE}" ] || [ ! -s "${DB_DATABASE}" ]; then
    if [ -f database/database.sqlite ]; then
        cp database/database.sqlite "${DB_DATABASE}"
    else
        touch "${DB_DATABASE}"
    fi
fi
chown www-data:www-data "${DB_DATABASE}" 2>/dev/null || true

# Apply any pending schema changes (non-fatal so health probe passes early).
php artisan migrate --force 2>/dev/null || true

echo "Laravel App listening on 0.0.0.0:${PORT}"
exec /usr/sbin/apache2ctl -D FOREGROUND