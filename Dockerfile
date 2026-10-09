# syntax=docker/dockerfile:1

FROM php:7.4-cli AS dependencies

# PHP 7.4 is retained for application compatibility; install only build dependencies here.
RUN sed -i \
    -e 's|deb.debian.org/debian-security|archive.debian.org/debian-security|g' \
    -e 's|deb.debian.org/debian|archive.debian.org/debian|g' \
    /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99no-check-valid-until \
    && apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libxml2-dev libcurl4-openssl-dev libzip-dev unzip \
    && docker-php-ext-install pdo_mysql mbstring curl xml zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# Copy dependency manifests first to maximize build-cache reuse.
COPY composer.json ./
COPY composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

FROM php:7.4-apache AS production

# PHP 7.4 is retained for application compatibility; archive repositories are required.
RUN sed -i \
    -e 's|deb.debian.org/debian-security|archive.debian.org/debian-security|g' \
    -e 's|deb.debian.org/debian|archive.debian.org/debian|g' \
    /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99no-check-valid-until \
    && apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql mbstring curl xml \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/web!g' /etc/apache2/sites-available/000-default.conf \
    && rm -rf /var/lib/apt/lists/*

ENV APP_ENV=prod \
    APP_DEBUG=false

WORKDIR /var/www/html
COPY --from=dependencies /app/vendor ./vendor
COPY . .

RUN mkdir -p runtime web/assets \
    && chown -R www-data:www-data runtime web/assets \
    && chmod -R u+rwX,g+rwX runtime web/assets \
    && chown -R www-data:www-data /var/www/html

USER www-data
EXPOSE 80
CMD ["apache2-foreground"]
