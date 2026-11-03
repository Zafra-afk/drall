
FROM php:8.4-cli

# ==========================================
# 1. SYSTEM DEPENDENCIES
# ==========================================
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
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

# ==========================================
# 2. CONFIGURE PHP GD
# ==========================================
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

# ==========================================
# 3. INSTALL PHP EXTENSIONS
# ==========================================
RUN docker-php-ext-install -j"$(nproc)" \
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

# ==========================================
# 4. VERIFY GD SUPPORT
# ==========================================
RUN php -r '$gd = gd_info(); \
    $features = ["JPEG Support", "PNG Support", "WebP Support", "FreeType Support"]; \
    foreach ($features as $feature) { \
        echo $feature . ": " . (!empty($gd[$feature]) ? "ENABLED" : "DISABLED") . PHP_EOL; \
    } \
    if (!function_exists("imagewebp") || empty($gd["WebP Support"])) { \
        fwrite(STDERR, "ERROR: GD WebP support is missing!\n"); \
        exit(1); \
    } \
    echo "SUCCESS: GD WebP support is enabled.\n";'

# ==========================================
# 5. INSTALL REDIS
# ==========================================
RUN pecl install redis \
    && docker-php-ext-enable redis

# ==========================================
# 6. INSTALL COMPOSER
# ==========================================
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# ==========================================
# 7. COPY APPLICATION
# ==========================================
COPY . .

# ==========================================
# 8. INSTALL COMPOSER DEPENDENCIES
# ==========================================
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

# ==========================================
# 9. LARAVEL DIRECTORIES & PERMISSIONS
# ==========================================
RUN mkdir -p \
    storage/framework/views \
    storage/framework/cache \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ==========================================
# 10. LARAVEL PACKAGE DISCOVERY
# ==========================================
RUN php artisan package:discover --ansi

# ==========================================
# 11. START CONTAINER
# ==========================================
EXPOSE 8080

CMD ["sh", "docker/start.sh"]

