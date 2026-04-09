# =============================================================================
# Stage 1: Build frontend assets
# =============================================================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy package files and install dependencies
COPY package.json package-lock.json ./
RUN npm ci

# Copy source files needed for build
COPY vite.config.js postcss.config.js tailwind.config.js tsconfig.json ./
COPY resources/ resources/
COPY public/ public/

# Tailwind content config references vendor/laravel pagination views
# Create stub directory so the content glob doesn't error during build
RUN mkdir -p vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
    && mkdir -p storage/framework/views

# Build production assets
RUN npm run build

# =============================================================================
# Stage 2: Install PHP dependencies
# =============================================================================
# Use PHP 8.2 base (not composer:2 which ships PHP 8.5, breaking locked deps)
FROM php:8.2-cli-alpine AS composer

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install extensions needed by composer dependencies
RUN apk add --no-cache git unzip libzip-dev \
    && docker-php-ext-install zip

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# Copy full source for post-install scripts
COPY . .
RUN composer dump-autoload --optimize

# =============================================================================
# Stage 3: Production image
# =============================================================================
FROM php:8.2-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    postgresql16-client \
    sqlite-dev \
    && rm -rf /var/cache/apk/*

# Install PHP extensions (pdo_sqlite, sqlite3, mbstring already included in base image)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        xml \
        bcmath \
        opcache \
        pcntl \
        exif

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-custom.ini"

# Configure nginx
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Configure supervisord
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Copy application code
COPY --chown=www-data:www-data . .

# Copy built frontend assets from stage 1
COPY --from=frontend --chown=www-data:www-data /app/public/build public/build

# Copy vendor directory from composer stage
COPY --from=composer --chown=www-data:www-data /app/vendor vendor

# Create required Laravel directories with proper permissions
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy .env.example as fallback — real env vars should be injected at runtime
# Key generation, migrations, and caching are handled by entrypoint.sh
RUN cp .env.example .env

EXPOSE 80

# Health check (longer start_period to allow for migrations on first boot)
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

# Entrypoint handles: wait for DB, migrate, cache config, fix permissions
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
