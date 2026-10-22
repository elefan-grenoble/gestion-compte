# php:7.4 (CLI, used by the dev compose with `symfony serve`) by default;
# pass --build-arg PHP_BASE_IMAGE=php:7.4-fpm to get a patched php-fpm.
ARG PHP_BASE_IMAGE=php:7.4
FROM ${PHP_BASE_IMAGE}

# Debian 11 (bullseye) left LTS on 2026-08-31: deb.debian.org still serves the
# indexes but the security pool is gone, so installs fail with 404s.
RUN sed -i 's#http://deb.debian.org#http://archive.debian.org#g' /etc/apt/sources.list \
    && ! grep -q deb.debian.org /etc/apt/sources.list \
    && grep -q archive.debian.org /etc/apt/sources.list

# PHP 7.4 is EOL: rebuild the image's 7.4.33 sources with the upstream security
# backport for CVE-2026-109618 / CVE-2026-109619 (php-src commit 712e60f9ed,
# branch PHP-7.4-CVE-2026-109618-109619). Must stay the first build step so
# that every extension below is compiled against the patched headers.
COPY .docker/patches/php-7.4-cve-2026-109618-109619.patch /tmp/php-cve.patch
RUN set -eux; \
    echo "406d8abc23dc218eb315e47d69e5c3cbf6ce0b5f0830b93ff5af1e9a86fd4d76  /tmp/php-cve.patch" | sha256sum -c -; \
    savedAptMark="$(apt-mark showmanual)"; \
    apt-get update; \
    apt-get install -y --no-install-recommends $PHPIZE_DEPS patch xz-utils \
        libargon2-dev libcurl4-openssl-dev libonig-dev libreadline-dev \
        libsodium-dev libsqlite3-dev libssl-dev libxml2-dev zlib1g-dev; \
    docker-php-source extract; \
    cd /usr/src/php; \
    patch -p1 < /tmp/php-cve.patch; \
    # Repack the patched sources so docker-php-ext-* builds from them too
    rm -f /usr/src/php.tar.xz.asc; \
    tar -cJf /usr/src/php.tar.xz -C /usr/src php; \
    export CFLAGS="$PHP_CFLAGS" CPPFLAGS="$PHP_CPPFLAGS" LDFLAGS="$PHP_LDFLAGS"; \
    eval "./configure $(php-config --configure-options)"; \
    make -j"$(nproc)"; \
    find -type f -name '*.a' -delete; \
    make install; \
    find /usr/local -type f -perm '/0111' -exec sh -euxc 'strip --strip-all "$@" || :' -- '{}' +; \
    cd /; \
    docker-php-source delete; \
    rm /tmp/php-cve.patch; \
    apt-mark auto '.*' > /dev/null; \
    apt-mark manual $savedAptMark > /dev/null; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*; \
    # Fail the build if the patched engine is not the one installed
    grep -q zend_new_array_singleton /usr/local/bin/php; \
    if [ -e /usr/local/sbin/php-fpm ]; then \
        grep -q zend_new_array_singleton /usr/local/sbin/php-fpm; \
        php-fpm -t; \
    fi; \
    php -r 'exit(PHP_VERSION === "7.4.33" ? 0 : 1);'; \
    php -v

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
RUN docker-php-ext-install -j$(nproc) pdo_mysql pcntl gd opcache

WORKDIR /var/www/app/