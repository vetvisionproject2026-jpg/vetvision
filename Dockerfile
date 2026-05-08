# 1. بنستخدم نسخة PHP مع Apache جاهزة
FROM php:8.2-apache

# 2. تثبيت المكتبات اللي مشروعك محتاجها (زي الصور وقاعدة البيانات)
RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# 3. بنجيب Composer جوه الـ Docker عشان ننزل المكتبات (Packages)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. بننسخ ملفات المشروع بتاعك كلها جوه السيرفر
COPY . /var/www/html

# 5. بنعرف السيرفر إن ملف التشغيل الأساسي في لارافل هو فولدر public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. حل المشكلة: بنعطل أي MPM تانية وبنشغل mpm_prefork اللي بيمشي مع PHP
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite

# 7. بننزل مكتبات Laravel (زي الـ Eloquent وغيرها)
RUN composer install --no-dev --optimize-autoloader

# 8. أهم خطوة للمبتدئين: بنعطي السيرفر صلاحية إنه يكتب ملفات في storage
# من غير دي هيطلع لك أيرور Permission Denied
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 9. بنفتح بورت 80 عشان نستقبل طلبات
EXPOSE 80

CMD ["apache2-foreground"]