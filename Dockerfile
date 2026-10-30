FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libicu-dev \
        libkrb5-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        unzip \
        git \
        curl \
        zip \
        autoconf \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure zip \
    && docker-php-ext-configure calendar \
    && docker-php-ext-install -j$(nproc) \
        calendar \
        curl \
        intl \
        mbstring \
        pdo \
        pdo_mysql \
        gd \
        zip \
        bcmath \
        exif \
        opcache

# Install redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy application
COPY . /var/www/html

# Install dependencies
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --optimize-autoloader \
    && rm -rf /root/.composer/cache \
    && rm -rf /tmp/*

# Create .env so isInstalled() check passes; runtime secrets override at runtime
RUN mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/framework/testing storage/logs && cp .env.example .env && php artisan key:generate --force

# Storage permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate optimized autoloader
RUN php artisan package:discover --ansi

EXPOSE 80
CMD php artisan migrate --force --no-interaction && php artisan db:seed --force --no-interaction && touch storage/installed && php artisan storage:link --force 2>/dev/null; php artisan serve --host=0.0.0.0 --port=$PORT
