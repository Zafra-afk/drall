# Single-stage build for production (based on working original)
FROM php:8.4-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    curl \
    unzip \
    git \
    nodejs \
    npm \
    gettext-base \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    gd \
    zip \
    bcmath \
    intl \
    opcache \
    mbstring \
    xml \
    pcntl \
    calendar

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev, optimized autoloader)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

# Copy application code
COPY . .

# Build frontend assets
RUN cd /var/www/html/packages/Webkul/Admin && npm install --no-audit --no-fund && npm run build \
    && cd /var/www/html/packages/Webkul/Shop && npm install --no-audit --no-fund && npm run build \
    && cd /var/www/html/packages/Webkul/Installer && npm install --no-audit --no-fund && npm run build

# Set runtime directory permissions without recursively changing the application files.
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Configure PHP-FPM (TCP port 9000)
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Configure Nginx
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/site.conf /etc/nginx/sites-available/default
RUN mkdir -p /etc/nginx/sites-enabled && rm -f /etc/nginx/sites-enabled/default && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Configure Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port 8000 (Railway default)
EXPOSE 8000

# Entrypoint: bootstrap .env, generate APP_KEY, set permissions
COPY docker/railway/entrypoint-nginx.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:${PORT:-8000}/health || exit 1

# Entrypoint handles .env bootstrap and starts Supervisor
ENTRYPOINT ["entrypoint.sh"]

# Start supervisor (fallback CMD)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
