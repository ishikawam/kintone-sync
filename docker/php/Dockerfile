FROM php:8.2-cli

RUN docker-php-ext-install pdo_mysql

RUN apt update && apt install -y git zip unzip

COPY php.ini /usr/local/etc/php/

COPY --from=composer /usr/bin/composer /usr/bin/composer
