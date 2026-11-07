# Hive NSP application image (directive §4 "Scale"). NOT YET BUILT OR VERIFIED:
# Docker was not available on the development machine. Build and run it on staging first.
# Stateless: sessions, cache and queue live in the database (or Redis, if approved); files on a mounted
# volume or S3-compatible storage (decision OD7). One image serves web, scheduler and worker roles.
FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libpng-dev libxml2-dev \
 && docker-php-ext-install intl pdo_mysql zip gd bcmath opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf
COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/nsp.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev \
 && chown -R www-data:www-data storage bootstrap/cache

COPY deploy/docker/entrypoint.sh /usr/local/bin/nsp-entrypoint
RUN chmod +x /usr/local/bin/nsp-entrypoint
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost/health || exit 1
ENTRYPOINT ["nsp-entrypoint"]
CMD ["web"]
