FROM dunglas/frankenphp:1.4-php8.4

# Install necessary system extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libpq-dev \
    unzip \
    zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

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

# Clear any existing bootstrap cache
RUN rm -rf bootstrap/cache/*.php

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache

# Caddy Configuration
ENV SERVER_NAME=:80
ENV APP_ENV=production
ENV APP_DEBUG=false

# Expose port
EXPOSE 80

# Start with migrations
CMD ["sh", "-c", "php artisan migrate --force && frankenphp php-server"]
