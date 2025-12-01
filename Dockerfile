FROM php:8.2-apache

# Установка системных зависимостей для работы с PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Включаем модуль rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

# Копируем исходники
COPY src/ /var/www/html/

# Готовим директории для загрузок и выставляем права
RUN mkdir -p /var/www/html/uploads/avatars \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/uploads/avatars