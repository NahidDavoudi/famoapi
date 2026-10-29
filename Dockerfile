# syntax=docker/dockerfile:1
# Build context: ریشه‌ی همین ریپو

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM php:8.4-apache AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql mysqli zip intl opcache bcmath \
    && a2enmod rewrite headers remoteip \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/custom.ini "$PHP_INI_DIR/conf.d/zz-custom.ini"
COPY docker/remoteip.conf /etc/apache2/conf-available/remoteip.conf
COPY docker/apache-tune.conf /etc/apache2/conf-available/apache-tune.conf
RUN a2enconf remoteip apache-tune

RUN sed -ri "s!/var/www/html!/var/www/html/public!g" \
    /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -ri "s/AllowOverride None/AllowOverride All/g" /etc/apache2/apache2.conf

COPY . /var/www/html/
COPY --from=vendor /app/vendor/ /var/www/html/vendor/
RUN mkdir -p /var/www/html/uploads /var/www/html/storage/cache /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80