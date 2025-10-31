FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo pdo_pgsql zip

# Install Redis PHP extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Set working directory
WORKDIR /var/www/html

# Copy composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first (layer caching)
COPY composer.json composer.lock* ./

# Install PHP dependencies (no autoload dump yet because app files not copied)
RUN composer install --no-interaction --no-scripts --no-autoloader

# Copy the rest of the app
COPY . .

# Finalize composer install with all sources
RUN composer install --no-interaction

# Try to generate app key (won't fail container build if .env not ready yet)
RUN php artisan key:generate || true

CMD ["php-fpm"]
