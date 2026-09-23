FROM php:8.2-apache

# mysqli for the database, gd for the avatar/cab image resizing in Users.php
# and Master.php.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" mysqli gd \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# uploads/ is written at runtime.
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads

WORKDIR /var/www/html
