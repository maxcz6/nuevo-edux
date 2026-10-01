# ============================================================
# backend/Dockerfile
# Stages:
#   builder     → instala dependencias y genera autoloader
#   production  → imagen final optimizada (sin devDeps, OPcache)
# ============================================================

# ── Stage 1: builder ─────────────────────────────────────────
FROM php:8.3-fpm-alpine AS builder

# System dependencies (git/unzip son necesarios solo para Composer)
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    zlib-dev \
    bash \
    git \
    unzip

# PHP extensions
RUN docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
    && apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps

# Composer (version pinned for reproducibility)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instalar dependencias PHP (sin scripts para evitar llamadas a artisan)
COPY composer.json ./
COPY composer.lock* ./
RUN composer install \
    --no-scripts \
    --no-autoloader \
    --no-dev \
    --prefer-dist \
    --no-interaction

# Copiar código fuente completo
COPY . .

# Crear directorios necesarios y fijar permisos
RUN mkdir -p \
        storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/app/materiales \
        storage/app/entregas \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Autoloader optimizado para producción (--classmap-authoritative)
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload \
    --optimize \
    --classmap-authoritative \
    --no-dev

# ── Stage 2: production ───────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=256 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    PHP_OPCACHE_REVALIDATE_FREQ=0 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# Solo las librerías runtime necesarias (sin git, unzip, etc.)
RUN apk add --no-cache \
    postgresql-libs \
    libpng \
    libzip \
    oniguruma \
    icu-libs \
    zlib

# Copiar extensiones PHP compiladas desde builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/     /usr/local/etc/php/conf.d/

# Instalar extensiones nativas (necesario para que PHP las registre)
RUN docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
    && apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps

# Configuraciones PHP optimizadas para producción
COPY php/opcache.ini  /usr/local/etc/php/conf.d/opcache.ini
COPY php/php-prod.ini /usr/local/etc/php/conf.d/php-prod.ini

WORKDIR /var/www/html

# Copiar código + vendor ya construido desde builder
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Verificar que OPcache está habilitado
RUN php -r "if (!extension_loaded('opcache')) { echo 'OPcache NOT loaded'; exit(1); }"

# Asegurar permisos finales
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
