# php:7.4 (CLI, used by the dev compose with `symfony serve`) by default;
# pass --build-arg PHP_BASE_IMAGE=php:7.4-fpm to get a patched php-fpm.
#
# Targets:
#   dev (default) / test   development images, code bind-mounted
#   prod                   php-fpm + application code, vendor and assets
#                          (requires PHP_BASE_IMAGE=php:7.4-fpm)
#   web                    Caddy serving public/ in front of prod
ARG PHP_BASE_IMAGE=php:7.4
ARG NODE_IMAGE=node:22-bookworm-slim@sha256:e9f565d32859f0cbfb52125eafe8be39390f24dac302dc3cdf566e8ba3ab21ee
ARG CADDY_IMAGE=caddy:2.11.9-alpine@sha256:0bbb055bda6793c999bde78b1b5ce3e89f829fb031af2cf50056ea1246559fee

FROM ${NODE_IMAGE} AS node
FROM ${CADDY_IMAGE} AS caddy

FROM ${PHP_BASE_IMAGE} AS base

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

# ---------------------------------------------------------------------------
# Production
# ---------------------------------------------------------------------------

# PHP dependencies, without dev packages nor Symfony scripts (they need the
# application code, run later in the prod stage)
FROM base AS vendor
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
        --no-interaction --no-progress --prefer-dist

# Front-end assets (Webpack Encore)
FROM node AS assets
WORKDIR /app
COPY package.json package-lock.json ./
# No Cypress binary: only Encore runs here
RUN CYPRESS_INSTALL_BINARY=0 npm ci --no-audit --no-fund
COPY webpack.config.js ./
COPY assets ./assets
RUN npx encore production

# Needs --build-arg PHP_BASE_IMAGE=php:7.4-fpm (checked by the entrypoint, not
# here: the legacy builder builds every stage, also for the default dev target)
FROM base AS prod

# Cron runner for the scheduled console commands (cron service)
ARG SUPERCRONIC_VERSION=v0.2.51
ARG SUPERCRONIC_SHA256_AMD64=cc232108374e8e2b537045f2d5eaa1229f619db314c3ba6a14d38b35dc0aa123
ARG SUPERCRONIC_SHA256_ARM64=4ae22576a78223d8ccdf5f3b3660a5cbb5288416bf0ca96afc4a36d195932b94
RUN set -eux; \
    arch="$(dpkg --print-architecture)"; \
    case "$arch" in \
        amd64) sha="$SUPERCRONIC_SHA256_AMD64" ;; \
        arm64) sha="$SUPERCRONIC_SHA256_ARM64" ;; \
        *) echo "unsupported architecture: $arch" >&2; exit 1 ;; \
    esac; \
    curl -fsSLo /usr/local/bin/supercronic \
        "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-${arch}"; \
    echo "$sha  /usr/local/bin/supercronic" | sha256sum -c -; \
    chmod 0755 /usr/local/bin/supercronic

COPY .docker/prod/php.ini "$PHP_INI_DIR/conf.d/zz-prod.ini"
COPY .docker/prod/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY .docker/prod/crontab /etc/supercronic/crontab
COPY .docker/prod/entrypoint.sh /usr/local/bin/app-entrypoint

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    FPM_MAX_CHILDREN=10

COPY --from=vendor /var/www/app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

# The real configuration is the .env mounted at runtime; .env.dist is only
# read here so that the kernel can boot for assets:install.
RUN set -eux; \
    composer dump-autoload --no-dev --classmap-authoritative --no-interaction; \
    php bin/console assets:install public --no-interaction; \
    rm -rf var; \
    mkdir -p var/cache var/log var/sessions \
        public/uploads/service/logo public/uploads/event public/media/cache; \
    chown -R www-data:www-data var public/uploads public/media

USER www-data
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM caddy AS web
COPY .docker/prod/Caddyfile /etc/caddy/Caddyfile
COPY --from=prod /var/www/app/public /var/www/app/public
# Run unprivileged: the compose file lets non-root bind ports 80/443 through
# the net.ipv4.ip_unprivileged_port_start sysctl
RUN chown -R nobody:nobody /data /config
USER nobody

# ---------------------------------------------------------------------------
# Development
# ---------------------------------------------------------------------------

FROM base AS devbase
COPY --link \
    --from=ghcr.io/symfony-cli/symfony-cli:latest \
    /usr/local/bin/symfony /usr/local/bin/symfony

# Image de tests (`make up`) : base + pilote de couverture pour
# `make test-coverage`, désactivé par défaut (composer test-coverage le
# réactive avec -d pcov.enabled=1)
FROM devbase AS test
RUN pecl install pcov \
    && docker-php-ext-enable pcov \
    && echo 'pcov.enabled=0' > /usr/local/etc/php/conf.d/zz-pcov.ini

# Image de développement : dernière étape, donc cible par défaut. Sans pcov.
FROM devbase AS dev
