FROM php:8.2-apache

# 1. تثبيت الإضافات
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# 2. تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. نسخ الملفات
COPY . /var/www/html

# 4. الصلاحيات
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 5. إعدادات Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite

# 6. إنشاء سكربت التشغيل (بطريقة السطر الواحد الآمنة)
RUN printf "#!/bin/bash\nphp artisan migrate --force\nrm -f /etc/apache2/mods-enabled/mpm_*\nln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/\nln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/\nexec apache2-foreground" > /usr/local/bin/start-app.sh \
    && chmod +x /usr/local/bin/start-app.sh

# 7. تثبيت مكتبات Laravel
RUN composer install --no-dev --optimize-autoloader

# 8. التشغيل
CMD ["/usr/local/bin/start-app.sh"]
