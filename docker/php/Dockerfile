FROM php:7.1-cli

#ENV MEMCACHED_DEPS zlib-dev libmemcached-dev cyrus-sasl-dev
#RUN apk add --no-cache --update libmemcached-libs zlib zlib-dev libpng-dev
#RUN set -xe \
#    && apk add --no-cache --update --virtual .phpize-deps $PHPIZE_DEPS \
#    && apk add --no-cache --update --virtual .memcached-deps $MEMCACHED_DEPS \
#    && pecl install memcached \
#    && echo "extension=memcached.so" > /usr/local/etc/php/conf.d/20_memcached.ini \
#    && rm -rf /usr/share/php7 \
#    && rm -rf /tmp/* \
#    && apk del .memcached-deps .phpize-deps
#
#RUN docker-php-ext-install pdo_mysql zip gd opcache

RUN docker-php-ext-install pdo_mysql
COPY php.ini /usr/local/etc/php/
