# THUGS(red) BBS - container image
# Build context is the repo root:  docker build -f docker/Dockerfile -t thugs-bbs .
FROM php:8.2-apache

LABEL org.opencontainers.image.title="THUGS(red) BBS" \
      org.opencontainers.image.description="Web-based ANSI/ASCII bulletin board system with a CRT terminal UI" \
      org.opencontainers.image.source="https://github.com/kawaiipantsu/bbs" \
      org.opencontainers.image.url="https://thugs.red" \
      org.opencontainers.image.licenses="MIT"

# --- PHP extensions -------------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libsodium-dev \
        default-mysql-client; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip sodium opcache; \
    pecl install redis; docker-php-ext-enable redis; \
    a2enmod rewrite headers deflate expires; \
    rm -rf /var/lib/apt/lists/*

# --- Apache -------------------------------------------------------------
COPY docker/000-bbs.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-bbs.ini

# --- App --------------------------------------------------------------
WORKDIR /app
COPY . /app
RUN set -eux; \
    mkdir -p /app/storage/files /app/storage/cache /app/storage/tmp /app/storage/logs; \
    [ -f /app/app/config.php ] || cp /app/app/config.sample.php /app/app/config.php; \
    chown -R www-data:www-data /app; \
    chmod +x /app/docker/entrypoint.sh

ENV APACHE_DOCUMENT_ROOT=/app/html
EXPOSE 80

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
