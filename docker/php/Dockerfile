FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install intl mysqli pdo_mysql gd zip exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Alinea el usuario de Apache con el del host para poder escribir en writable/
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g ${GID} www-data

RUN a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/backend/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

COPY docker/php/php.ini /usr/local/etc/php/conf.d/tuentidad.ini

WORKDIR /var/www/html/backend
