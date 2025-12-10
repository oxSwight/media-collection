FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY src/ /var/www/html/

RUN mkdir -p /var/www/html/uploads/avatars \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/uploads/avatars