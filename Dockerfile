FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 🔥 HARD FIX: remove all MPM modules completely
RUN rm -f /etc/apache2/mods-enabled/mpm_* \
    && rm -f /etc/apache2/mods-available/mpm_event* \
    && rm -f /etc/apache2/mods-available/mpm_worker* \
    && rm -f /etc/apache2/mods-available/mpm_prefork*

# 🔥 reinstall ONLY prefork cleanly
RUN a2enmod mpm_prefork \
    && a2enmod rewrite

RUN composer install --no-dev --optimize-autoloader

CMD ["apache2-foreground"]