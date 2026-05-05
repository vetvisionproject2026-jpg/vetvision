FROM richarvey/php-apache-heroku:latest
COPY . /var/www/html
ENV WEBROOT /var/www/html/public
ENV APP_KEY base64:base64:i53RPHtxZWp/yhp74tcTcwOB9fWlmEy1kYp6agtUuIY=
RUN composer install --no-dev