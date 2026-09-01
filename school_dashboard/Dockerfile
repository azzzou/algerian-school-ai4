# =============================================================================
#  Algerian School Support - school_dashboard (Laravel 8) Dockerfile
#  Deployed on Render via render.yaml (dockerContext = ./school_dashboard).
#  The container runs Apache + mod_php and listens on $PORT (Render-injected).
#  All runtime secrets come from Render environment vars, never baked in.
# =============================================================================

# ---------------- Stage 1: Composer dependencies ----------------------------
FROM composer:2 AS composer

WORKDIR /app

# Copy manifests first to maximise layer + Composer cache reuse.
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --no-interaction \
      --no-progress \
      --prefer-dist \
      --ignore-platform-reqs

# Bring in the rest of the source, then build a production-optimised autoloader.
COPY . .
RUN composer dump-autoload --no-dev --optimize

# ---------------- Stage 2: Runtime image (Apache + mod_php) -----------------
FROM php:8.0-apache

# Base system packages needed by extensions / dompdf / tidy output.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd \
        --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo pdo_sqlite sqlite3 mbstring xml curl zip bcmath gd fileinfo \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Expose Render's default arbitrary port (overridden at runtime by entrypoint).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Point Apache at Laravel's public/ directory.
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copy the installed vendor + source from the Composer stage.
COPY --from=composer /app /var/www/html/

# Laravel runtime entrypoint (Apache port = $PORT + bootstrapping).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# NOTE: config/route/view caches are intentionally NOT baked here. Render
# injects env at runtime, so baking caches with empty APP_KEY would lock in
# invalid config. Laravel boots fresh from runtime env instead.

# Writeable SQLite location (Laravel default) for the free plan — no disk.
RUN mkdir -p /var/www/html/database /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/database /var/www/html/storage

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]