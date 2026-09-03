FROM php:8.4-cli-alpine AS runtime

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && apk add --no-cache libcurl libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS curl-dev libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" curl opcache pcntl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

FROM runtime AS vendor

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

FROM runtime AS application

COPY --chown=www-data:www-data . /app
COPY --from=vendor --chown=www-data:www-data /app/vendor /app/vendor

RUN mkdir -p runtime public/uploads \
    && chown -R www-data:www-data runtime public/uploads

USER www-data

EXPOSE 8787

CMD ["php", "start.php", "start"]
