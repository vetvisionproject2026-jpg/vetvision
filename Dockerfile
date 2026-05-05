FROM php:8.2-apache

# 1. تثبيت إضافات النظام
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# 2. تثبيت إضافات PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 3. تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. نسخ ملفات المشروع (دي أهم خطوة لازم تكون قبل الـ composer)
COPY . /var/www/html

# 5. تثبيت مكتبات Laravel
RUN composer install --no-dev --optimize-autoloader

# 6. إعطاء الصلاحيات
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 7. ضبط الـ Apache ليقرأ من مجلد public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 8. تفعيل الـ Rewrite Module في Apache (مهم جداً لـ Laravel Routes)
RUN a2enmod rewrite