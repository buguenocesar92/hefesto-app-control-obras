# ---------------------------------------------------------------------------
# Imagen de la API de Control de Obras
# ---------------------------------------------------------------------------
# Fases fijadas para que una reconstruccion dentro de seis meses produzca la
# misma imagen y no "casi la misma".
# ---------------------------------------------------------------------------

FROM composer:2.8.9 AS dependencias

WORKDIR /app

# Se copian primero los manifiestos: mientras el composer.json no cambie, la
# capa de dependencias se reutiliza y no se reinstala todo en cada despliegue.
COPY api/composer.json api/composer.lock* ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

# ---------------------------------------------------------------------------

FROM php:8.3-fpm-alpine AS ejecucion

# pdo_pgsql es obligatorio: sin el, la base no se puede abrir.
# pcntl lo necesita el worker de colas para manejar senales.
RUN apk add --no-cache \
        bash \
        libpq-dev \
        postgresql-client \
        nginx \
        supervisor \
        icu-libs \
        oniguruma \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pcntl \
        bcmath \
        intl \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=dependencias /app/vendor ./vendor
COPY api/ ./

COPY deploy/api/docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY deploy/api/docker/nginx.conf /etc/nginx/nginx.conf
COPY deploy/api/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/api/docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,views} \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]