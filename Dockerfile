FROM dunglas/frankenphp:1.4-php8.4-alpine

# Install necessary system extensions
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    zip \
    intl \
    mbstring \
    exif \
    pcntl \
    bcmath \
    pdo_pgsql

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# Caddy Configuration (FrankenPHP uses Caddy)
ENV SERVER_NAME=:80
ENV PHP_INI_SCAN_DIR=:/usr/local/etc/php/conf.d

# Set PHP Production settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Environment variables for production
ENV APP_ENV=production
ENV APP_DEBUG=false

# Expose port
EXPOSE 80

# Start FrankenPHP with migrations
CMD ["sh", "-c", "php artisan migrate --force && frankenphp php-server"]
