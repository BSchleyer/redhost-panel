# syntax=docker/dockerfile:1
#
# Nebrix Panel – production image (PHP 8.4 + Apache).
# Build from the repository root:  docker build -f .docker/Dockerfile -t nebrix-panel .

# ---------------------------------------------------------------- dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# The composer image has no pdo_mysql; the runtime stage below installs it.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress --ignore-platform-req=ext-pdo_mysql
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# --------------------------------------------------------------------- runtime
FROM php:8.4-apache AS runtime

RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY .docker/php/nebrix.ini "$PHP_INI_DIR/conf.d/zz-nebrix.ini"
COPY .docker/apache/nebrix.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/apache/security.conf /etc/apache2/conf-available/security.conf

WORKDIR /var/www/nebrix
COPY --from=vendor --chown=root:root /app /var/www/nebrix

# Only storage/ and public/uploads/ are writable by the web server user.
RUN rm -rf .git tests .docker/Dockerfile \
    && mkdir -p storage/logs storage/cache storage/invoices public/uploads/branding \
    && chown -R www-data:www-data storage public/uploads \
    && chmod -R u=rwX,g=rX,o= storage public/uploads

USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/health") === false ? 1 : 0);'
