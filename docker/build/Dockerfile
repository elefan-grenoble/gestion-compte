FROM ubuntu:19.10 as builder
MAINTAINER Jérémy Lajotte <jeremy@wenjy.fr>

ARG GIT_BRANCH

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
	php7.3-cli php7.3-gd php7.3-xml php7.3-mbstring composer libarchive-tools unzip \
  && apt-get clean \
	&& rm -rf /var/cache/apt/archives/* /var/lib/apt/lists/*

WORKDIR /elefan/

ADD https://github.com/Scopeli44/gestion-compte/archive/${GIT_BRANCH:-scopeli-dev}.zip /elefan/archive.zip

RUN bsdtar -xf /elefan/archive.zip -s'|[^/]*/||' && rm archive.zip

COPY php/parameters.yml.dist /elefan/app/config/

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --prefer-dist

RUN php bin/console assetic:dump

########
# HTTP #
########

FROM nginx:1 as http

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
	nginx locales \
	&& apt-get clean \
	&& rm -rf /var/cache/apt/archives/* /var/lib/apt/lists/*

# forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log && \
	ln -sf /dev/stderr /var/log/nginx/error.log

# configure nginx
COPY http/default.conf /etc/nginx/conf.d/

# supprimer les fichiers php ?
COPY --from=builder --chown=www-data:www-data /elefan/web /elefan/web
COPY --from=builder --chown=www-data:www-data /elefan/src/AppBundle/Resources/public /elefan/web/bundles/app
COPY --from=builder --chown=www-data:www-data /elefan/vendor/evheniy/jquery-bundle/Evheniy/JqueryBundle/Resources/public /elefan/web/bundles/jquery
COPY --from=builder --chown=www-data:www-data /elefan/vendor/evheniy/materialize-bundle/Evheniy/MaterializeBundle/Resources/public /elefan/web/bundles/materialize

EXPOSE 80
      
CMD ["nginx", "-g", "daemon off;"]


########
# PHP #
########
FROM ubuntu:19.10 as php

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
    php7.3-fpm php7.3-cli php7.3-gd php7.3-xml php7.3-mbstring php7.3-intl php7.3-pdo-mysql \
    ca-certificates \
    locales \
  && apt-get clean \
	&& rm -rf /var/cache/apt/archives/* /var/lib/apt/lists/*

COPY --from=builder --chown=www-data:www-data /elefan/app /elefan/app
COPY --from=builder --chown=www-data:www-data /elefan/bin /elefan/bin
COPY --from=builder --chown=www-data:www-data /elefan/var /elefan/var
COPY --from=builder --chown=www-data:www-data /elefan/vendor /elefan/vendor
COPY --from=builder --chown=www-data:www-data /elefan/web /elefan/web
COPY --from=builder --chown=www-data:www-data /elefan/src /elefan/src

COPY --chown=www-data:www-data php/config_prod.yml /elefan/app/config/

# Configuration des locales
#RUN sed -i -E 's/^(# )(.+UTF-8)$/\2/' /etc/locale.gen && locale-gen

# configure php
COPY php/etc/php/7.3/mods-available/php.ini /etc/php/7.3/mods-available/
RUN ln -s ../../mods-available/php.ini /etc/php/7.3/fpm/conf.d/99-php.ini && \
    ln -s ../../mods-available/php.ini /etc/php/7.3/cli/conf.d/99-php.ini && \
    ln -sf /dev/stderr /elefan/var/logs/prod.log && \
    ln -sf /dev/stderr /var/log/fpm-php.www.log && \
    ln -sf /dev/stderr /var/log/php7.3-fpm.log && \
	ln -sf /dev/stderr /var/log/php.err.log && \
	mkdir /run/php && \
	echo "listen = 9000" >> /etc/php/7.3/fpm/pool.d/www.conf && \
    echo "clear_env = no" >> /etc/php/7.3/fpm/pool.d/www.conf && \
    echo "catch_workers_output = yes" >> /etc/php/7.3/fpm/pool.d/www.conf && \
    echo "decorate_workers_output = no" >> /etc/php/7.3/fpm/pool.d/www.conf

EXPOSE 9000

ENTRYPOINT ["php-fpm7.3", "--nodaemonize", "--fpm-config", "/etc/php/7.3/fpm/php-fpm.conf"]


#######
# DEV #
#######

FROM php as dev
RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
    composer git php7.3-cli unzip \
  && apt-get clean \
	&& rm -rf /var/cache/apt/archives/* /var/lib/apt/lists/*

