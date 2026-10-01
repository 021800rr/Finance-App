FROM php:8.5-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        bcmath \
        intl \
        zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get purge -y libicu-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm", "-F"]