FROM php:8.4-fpm

# Встановлення системних залежностей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev

# Очищення кешу apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Встановлення PHP розширень
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

RUN pecl install redis \
    && docker-php-ext-enable redis

# Налаштування робочої директорії
WORKDIR /var/www

# Встановлення Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копіювання додатку
COPY . /var/www

# Налаштування прав доступу
RUN chown -R www-data:www-data /var/www

# Запуск PHP-FPM
CMD ["php-fpm"]

# Expose порт 9000 для PHP-FPM
EXPOSE 9000
