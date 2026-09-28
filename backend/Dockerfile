FROM php:8.4-cli-alpine

# Install system dependencies and PHP extensions required for Laravel, Redis, MySQL, and League/CSV
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    pcre-dev \
    ${PHPIZE_DEPS} \
    linux-headers \
    && docker-php-ext-install pdo pdo_mysql bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del ${PHPIZE_DEPS}

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Expose port
EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
