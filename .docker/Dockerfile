FROM php:7.4-fpm

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
    libjpeg-dev \
    netcat

RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash
RUN apt-get install -y symfony-cli

ARG UNAME=www-data
ARG UGROUP=www-data
ARG UID=1000
ARG GID=1000
RUN usermod  --uid $UID $UNAME
RUN groupmod --gid $GID $UGROUP

RUN apt-get install -y nginx

# Paramétrage de locale pour le Français
RUN sed -i 's/# fr_FR.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen && locale-gen

# Installation de Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Configuration de gd avec FreeType
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Installation des extensions nécessaires
RUN docker-php-ext-install -j$(nproc) pdo_mysql pcntl gd

# install xdebug
RUN pecl install xdebug-3.1.5 \
      && docker-php-ext-enable xdebug  \
      && rm -f /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /

RUN touch /usr/local/etc/php/conf.d/zz-app-local.ini

RUN echo "; Note:" >> /usr/local/etc/php/conf.d/zz-app-local.ini \
&& echo "; Remove the comment ; to enable debugging" >> /usr/local/etc/php/conf.d/zz-app-local.ini \
&& echo "zend_extension=xdebug" >> /usr/local/etc/php/conf.d/zz-app-local.ini \
&& echo "xdebug.idekey=\"PHPSTORM\"" >> /usr/local/etc/php/conf.d/zz-app-local.ini \
&& echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/zz-app-local.ini \
&& echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/zz-app-local.ini \
&& echo "xdebug.discover_client_host = true" >> /usr/local/etc/php/conf.d/zz-app-local.ini \
&& echo "xdebug.client_port = 9003" >> /usr/local/etc/php/conf.d/zz-app-local.ini \
&& echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/zz-app-local.ini

COPY nginx-docker.conf /etc/nginx/sites-enabled/website.conf
COPY php.ini usr/local/etc/php/php.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh

WORKDIR /var/www/site/

ENTRYPOINT ["entrypoint.sh"]