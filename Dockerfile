FROM php:8.2-apache

# 1. تثبيت البرامج الأساسية و Node.js (عشان الـ CSS والـ JS)
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip curl \
    && curl -sL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# 2. تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. نسخ ملفات المشروع
COPY . /var/www/html

# 4. تثبيت مكتبات PHP و Node.js وبناء الملفات
RUN composer install --no-dev --optimize-autoloader \
    && npm install \
    && npm run build

# 5. إعطاء الصلاحيات
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 6. ضبط Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite

# 7. سكربت التشغيل "الخارق"
RUN echo '#!/bin/bash\n\
# حل مشكلة الـ MPM\n\
rm -f /etc/apache2/mods-enabled/mpm_*\n\
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/\n\
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/\n\
# بناء الجداول وربط المجلدات\n\
php artisan migrate --force\n\
php artisan storage:link --force\n\
# تشغيل السيرفر\n\
exec apache2-foreground' > /usr/local/bin/start-app.sh \
    && chmod +x /usr/local/bin/start-app.sh

CMD ["/usr/local/bin/start-app.sh"]
