FROM php:7.4-apache
RUN docker-php-ext-install pdo_mysql
COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini
