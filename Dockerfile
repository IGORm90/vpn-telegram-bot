FROM php:8.4-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Установка Redis расширения для PHP
RUN pecl install redis \
    && docker-php-ext-enable redis

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка рабочей директории
WORKDIR /var/www

# Копирование файлов приложения
COPY . /var/www

# Установка зависимостей
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Открытие порта
EXPOSE 9000

CMD ["php-fpm"]

