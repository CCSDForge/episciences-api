FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create user with same UID as host user to avoid permission issues
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

# Change ownership of our applications
RUN chown -R www:www /var/www/html

# Switch to www user
USER www

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]