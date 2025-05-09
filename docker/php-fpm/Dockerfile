FROM php:8.2-fpm

# Set timezone
ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install necessary packages and PHP extensions
RUN apt-get update  \
    && apt-get install -y --no-install-recommends --no-install-suggests \
    libxslt1-dev \
    libxml2  \
    libcurl4-gnutls-dev \
    libmagickwand-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    && apt-get clean

RUN apt-get install -y --no-install-recommends --no-install-suggests \
     zip \
     unzip \
     git \
     vim \
     poppler-utils \
    && apt-get clean

RUN pecl install xdebug && \
    docker-php-ext-enable xdebug

RUN docker-php-ext-install curl dom fileinfo iconv intl pdo pdo_mysql posix simplexml xml xsl zip
RUN docker-php-ext-enable curl dom fileinfo iconv intl pdo pdo_mysql posix simplexml xml xsl zip

# Install Node.js and Yarn
# Install Node.js (version 18.x) and Yarn
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && npm install -g yarn

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add Xdebug configuration
COPY ./docker/php-fpm/xdebug.ini /usr/local/etc/php/conf.d/

# disable opcache configuration
#COPY ./docker/php-fpm/opcache.ini /usr/local/etc/php/conf.d/

# Copy entrypoint script and set permissions
COPY ./docker/php-fpm/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Run the default php-fpm command
CMD ["php-fpm"]
