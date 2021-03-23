FROM php:7.4-apache

ENV APP_ENV prod
ENV SYMFONY_ENV prod
ENV APP_DEBUG 0

# Paramétrage de l'heure du conteneur
ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN dpkg-reconfigure --frontend noninteractive tzdata

# Installation des dépendances nécessaires
RUN apt-get update && apt-get install -y \
    unzip \
    locales \
    libpng-dev \
    libfreetype6-dev \
    libjpeg-dev

# Paramétrage de locale pour le Français
RUN sed -i 's/# fr_FR.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen && locale-gen

# Installation de Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configuration de gd avec FreeType
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Installation des extensions nécessaires
RUN docker-php-ext-install -j$(nproc) pdo_mysql pcntl gd

# Activation du Rewrite Mode pour Apache
RUN a2enmod rewrite
RUN sed -i 's#/var/www/html#/var/www/html/web#g' /etc/apache2/sites-available/000-default.conf

# Copie du fichier de configuration de PHP
RUN cp /usr/local/etc/php/php.ini-production $PHP_INI_DIR/conf.d/php-dev.ini
RUN sed -i 's#;date.timezone =#date.timezone= Europe/Paris#g' $PHP_INI_DIR/conf.d/*.ini

COPY --chown=www-data:www-data . /var/www/html/
COPY --chown=www-data:www-data ./app/config/parameters.yml /var/www/html/app/config/


RUN mkdir /var/www/.composer && chown www-data:www-data /var/www/.composer

USER www-data

RUN COMPOSER_MEMORY_LIMIT=2G composer update && php bin/console assetic:dump --env=prod --no-debug
USER root
