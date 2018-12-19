FROM php:7.2-apache

RUN apt-get update && apt-get install -y \
    unzip \
    libpng-dev

# Installation de Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installation des extensions nécessaires
RUN docker-php-ext-install pdo_mysql pcntl gd

# Activation du Rewrite Mode pour Apache
RUN a2enmod rewrite

COPY --chown=www-data:www-data . /var/www/html/
RUN sed -i 's#/var/www/html#/var/www/html/web#g' /etc/apache2/sites-available/000-default.conf
