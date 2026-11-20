FROM serversideup/php:8.4-fpm-nginx

ENV PHP_OPCACHE_ENABLE=1

# Extensions not included in the base image
USER root
RUN install-php-extensions bcmath exif gd intl
USER www-data

# Install dependencies first so this layer is cached until composer.lock changes
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev
