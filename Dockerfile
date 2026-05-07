FROM php:8.2-apache

# Install dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Set Laravel public folder
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Fix Apache document root
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 🔥 IMPORTANT FIX: Apache MPM conflict
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Start Apache (Railway uses this)
CMD ["apache2-foreground"]