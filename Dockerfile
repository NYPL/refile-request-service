FROM php:7.1-fpm as base

RUN apt-get update
RUN apt-get install zip unzip -y
RUN apt-get update && apt-get install -y libpq-dev

RUN docker-php-ext-install pdo pdo_pgsql sockets

RUN echo 'memory_limit = 1024M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini;

COPY . /app
WORKDIR /app

COPY --from=composer:2.2.21 /usr/bin/composer /usr/local/bin/composer
RUN COMPOSER_ALLOW_SUPERUSER=1 composer update

FROM base as tests
RUN ["./vendor/bin/phpunit", "tests"]

