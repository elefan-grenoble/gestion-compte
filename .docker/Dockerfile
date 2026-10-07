FROM php:7.4

COPY --link \
    --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

# Paramétrage de l'heure du conteneur
ENV TZ=Europe/Paris
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
RUN dpkg-reconfigure --frontend noninteractive tzdata

# Installation des dépendances nécessaires
RUN apt-get update && apt-get install -y \
    unzip \
    locales \
    locales-all \
    libpng-dev \
    libfreetype6-dev \
    libjpeg-dev \
    netcat

# Paramétrage de locale pour le Français
RUN sed -i 's/# fr_FR.UTF-8 UTF-8/fr_FR.UTF-8 UTF-8/' /etc/locale.gen && locale-gen

# Installation de Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

COPY php.ini usr/local/etc/php/php.ini

# Configuration de gd avec FreeType
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Installation des extensions nécessaires
RUN docker-php-ext-install -j$(nproc) pdo_mysql pcntl gd

# Pilote de couverture pour `make test-coverage`, désactivé par défaut
# (composer test-coverage le réactive avec -d pcov.enabled=1)
RUN pecl install pcov \
    && docker-php-ext-enable pcov \
    && echo 'pcov.enabled=0' > /usr/local/etc/php/conf.d/zz-pcov.ini

WORKDIR /var/www/app/