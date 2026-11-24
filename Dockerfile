FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libicu-dev libonig-dev curl poppler-utils libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql pcntl intl zip bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/ereader-entrypoint
RUN chmod +x /usr/local/bin/ereader-entrypoint

ENTRYPOINT ["ereader-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
