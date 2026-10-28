FROM php:8.4-apache

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
        unzip \
        git \
        curl \
        zip \
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

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# Set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
    && ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log

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
RUN cp .env.example .env && php artisan key:generate --force

# Storage permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate optimized autoloader
RUN php artisan package:discover --ansi

EXPOSE 80

CMD ["apache2-foreground"]
