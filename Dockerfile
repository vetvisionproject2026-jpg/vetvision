أسهل طريقة هي أن تجعل الموقع يقوم ببناء الجداول تلقائياً في كل مرة ترفعه فيها.

```dockerfile
FROM php:8.2-apache

# 1. تثبيت البرامج الأساسية
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# 2. تثبيت Composer (مدير مكتبات Laravel)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. نسخ ملفات مشروعك داخل السيرفر
COPY . /var/www/html

# 4. إعطاء صلاحيات للمجلدات (مهم جداً لـ Laravel)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 5. ضبط إعدادات الـ Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. تفعيل الروابط (Rewrite)
RUN a2enmod rewrite

# 7. سكربت التشغيل الذكي (هذا هو الحل السحري)
# سيقوم بحل مشكلة الـ MPM وبناء الجداول تلقائياً
RUN echo '#!/bin/bash\n\
# بناء جداول قاعدة البيانات تلقائياً\n\
php artisan migrate --force\n\
# حل مشكلة السائقين (MPM)\n\
rm -f /etc/apache2/mods-enabled/mpm_*\n\
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/\n\
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/\n\
# تشغيل الموقع\n\
exec apache2-foreground' > /usr/local/bin/start-app.sh \
    && chmod +x /usr/local/bin/start-app.sh

# 8. تثبيت مكتبات المشروع
RUN composer install --no-dev --optimize-autoloader

# 9. تشغيل الموقع باستخدام السكربت الذكي
CMD ["/usr/local/bin/start-app.sh"]
