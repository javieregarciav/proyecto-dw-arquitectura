FROM php:8.3-apache

ARG APP_ENV=local

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY docker/php.ini "$PHP_INI_DIR/conf.d/app.ini"
COPY docker/apache/seguridad.conf /etc/apache2/conf-available/seguridad.conf
RUN a2enconf seguridad

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN if [ "$APP_ENV" = "produccion" ]; then FLAGS="--no-dev"; else FLAGS=""; fi \
    && composer install --no-interaction --prefer-dist --no-autoloader $FLAGS

COPY . .
RUN if [ "$APP_ENV" = "produccion" ]; then FLAGS="--no-dev"; else FLAGS=""; fi \
    && composer dump-autoload --optimize $FLAGS
