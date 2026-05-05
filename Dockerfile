FROM php:8.2-apache

# 1. تثبيت إضافات النظام
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# 2. تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. نسخ ملفات المشروع 
COPY . /var/www/html

# 4. إعطاء الصلاحيات
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 5. ضبط الـ Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. تفعيل مود الـ Rewrite
RUN a2enmod rewrite

# 7. إنشاء سكربت التشغيل لحل مشكلة MPM عند كل بداية
RUN echo '#!/bin/bash\n\
# حذف أي MPM مفعل قسراً\n\
rm -f /etc/apache2/mods-enabled/mpm_*\n\
# تفعيل prefork فقط\n\
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/\n\
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/\n\
# تشغيل Apache\n\
exec apache2-foreground' > /usr/local/bin/start-app.sh \
    && chmod +x /usr/local/bin/start-app.sh

# 8. تثبيت مكتبات Laravel
RUN composer install --no-dev --optimize-autoloader

# 9. استخدام السكربت الجديد للتشغيل
CMD ["/usr/local/bin/start-app.sh"]
