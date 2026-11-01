
FROM php:8.4-cli

# System dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
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

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
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

# Verify GD capabilities during build
RUN php -r '\
    $gd = gd_info(); \
    foreach (["JPEG Support", "PNG Support", "WebP Support", "FreeType Support"] as $feature) { \
        echo $feature . ": " . (($gd[$feature] ?? false) ? "ENABLED" : "DISABLED") . PHP_EOL; \
    } \
    if (!function_exists("imagewebp")) { \
        fwrite(STDERR, "ERROR: WebP support is missing in GD!" . PHP_EOL); \
        exit(1); \
    } \
'

# Redis
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Application
COPY . .

# Composer dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

# Laravel directories
RUN mkdir -p \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Composer package discovery
RUN php artisan package:discover --ansi

EXPOSE 8080

CMD ["sh", "docker/start.sh"]

