# =============================================================================
#  Algerian School Support - school_dashboard (Laravel 8)
#  Deployed on Render via render.yaml (runtime: docker).
#  Build context (/dockerContext) is ./school_dashboard, so this Dockerfile's
#  files land at /var/www/html/ directly (NO /var/www/html/school_dashboard
#  subfolder exists inside the container).
#
#  Runtime architecture:
#    - Apache 2.4 + mod_php (php:8.2)
#    - DocumentRoot -> /var/www/html/public
#    - Listens on $PORT (Render-injected) — re-pointed by docker/entrypoint.sh
#    - config:cache / route:cache / view:cache generated on BOOT (not baked),
#      because APP_KEY and other env vars are injected at runtime.
# =============================================================================

# ------------------------- Stage 1: Composer deps ---------------------------
FROM composer:2 AS composer

WORKDIR /app

# Copy manifests first for layer + Composer cache reuse.
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --no-interaction \
      --no-progress \
      --prefer-dist \
      --ignore-platform-reqs

# Bring in the rest of the source, then build a production autoloader.
COPY . .
RUN composer dump-autoload --no-dev --optimize

# ---------------------- Stage 2: Runtime Apache image -----------------------
FROM php:8.2-apache

# System libs needed to compile extensions + dompdf.
# mbstring/xml/curl/fileinfo/pdo/sqlite3 are pre-built into the php image, so
# they are NOT passed to docker-php-ext-install (doing so breaks the build).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libxml2-dev libcurl4-openssl-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_sqlite zip bcmath gd \
    && a2enmod rewrite headers expires \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---- Apache: explicit Laravel vhost (DocumentRoot + AllowOverride + index) --
# Bound to *:80 at build time; docker/entrypoint.sh rewrites it to Render's
# $PORT (default 10000) at runtime so the container actually opens a port.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN mkdir -p /etc/apache2/sites-available /etc/apache2/sites-enabled \
    && printf '%s\n' \
        'ServerName localhost' \
        '<VirtualHost *:80>' \
        "    DocumentRoot ${APACHE_DOCUMENT_ROOT}" \
        "    <Directory ${APACHE_DOCUMENT_ROOT}>" \
        '        AllowOverride All' \
        '        Require all granted' \
        '        DirectoryIndex index.php' \
        '        Options -Indexes +FollowSymLinks' \
        '    </Directory>' \
        '<Directory /var/www/html/>' \
        '    Options -Indexes' \
        '</Directory>' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/laravel.conf \
    && rm -f /etc/apache2/sites-enabled/000-default.conf \
    && ln -sf /etc/apache2/sites-available/laravel.conf /etc/apache2/sites-enabled/000-laravel.conf \
    && printf 'Listen 80\n' > /etc/apache2/ports.conf \
    && which apache2ctl >/dev/null

WORKDIR /var/www/html

# Copy the app + vendor from Composer stage, owned by the web user.
COPY --chown=www-data:www-data --from=composer /app /var/www/html/

# Laravel runtime entrypoint (Apache port = $PORT, caches, permissions).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Pre-create Laravel runtime storage subdirectories (writable by www-data).
RUN mkdir -p storage/app/public storage/framework/cache/data \
             storage/framework/sessions storage/framework/views \
             storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

# Optional orchestrator health probe. Uses PHP (always present) rather than
# curl. Render performs its OWN probe via render.yaml healthCheckPath=/api/health.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:".(getenv("PORT")?:80)."/api/health")===false?1:0);'

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]