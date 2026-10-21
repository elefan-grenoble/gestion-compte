# Installation sur un serveur

## Prérequis

* PHP (version 7.2 et supérieure)
* [Composer](https://getcomposer.org/)
* Mysql (ou mariadb)
* php-mysql (ou php-pdo_mysql)
* php-xml
* php-gd

## Installation

Clone code

```shell
git clone https://github.com/elefan-grenoble/gestion-compte.git
cd gestion-compte
```

Lancer la configuration

```shell
composer install
```

Creer la base de donnée

```shell
php bin/console doctrine:database:create
```

Migrer : creation du schema

```shell
php bin/console doctrine:migration:migrate
```

Installer les medias

```shell
php bin/console assetic:dump
```

Lancer le serveur (si pas de serveur web)

```shell
php bin/console server:start
```

Attention, par défaut ce serveur n'est pas accessible depuis l'extérieur vu qu'il écoute en local seulement (127.0.0.1).
Pour le rendre accessible, il faut utiliser la commande suivante :

```shell
php bin/console server:start *:8080
```

Pour un usage en production, il est très fortement recommandé d'utiliser un vrai serveur Web tel que Apache ou Nginx.

Ajouter `127.0.0.1 membres.yourcoop.local` au fichier _/etc/hosts_.

Visiter [http://membres.yourcoop.local/user/install_admin](http://membres.yourcoop.local/user/install_admin) pour créer l'utilisateur super admin (valeurs par défaut : admin:password)

## En prod

### nginx

Avec nginx, ligne necessaire pour avoir les images dynamiques de qr et barecode (au lieu de 404)

```
location ~* ^/sw/(.*)/(qr|br)\.png$ {
	rewrite ^/sw/(.*)/(qr|br)\.png$ /app.php/sw/$1/$2.png last;
}
```

### Sécurité PHP (CVE-2026-109618 / CVE-2026-109619)

Ces deux failles permettent une exécution de code à distance sans authentification, avant même que l'application ne s'exécute. Elles touchent toutes les versions de PHP antérieures à 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12, ainsi que PHP 7.4.

* **Docker** : l'image (`.docker/Dockerfile`) recompile PHP 7.4.33 avec le correctif officiel de l'équipe sécurité PHP (`.docker/patches/`). Il suffit de reconstruire l'image et de recréer les conteneurs :

  ```shell
  docker compose build --pull php && docker compose up -d --force-recreate php
  ```

* **Serveur sans Docker** : installer un PHP corrigé (paquet de la distribution ou d'un fournisseur LTS incluant le correctif, ou recompilation depuis la branche [`PHP-7.4-CVE-2026-109618-109619`](https://github.com/php/php-src/tree/PHP-7.4-CVE-2026-109618-109619)). Seule cette mise à jour protège complètement.

Durcissement recommandé dans tous les cas, à mettre dans le pool php-fpm (et non dans un `.user.ini` ni via `ini_set()`, appliqués trop tard). Redémarrer ensuite php-fpm : un simple `reload` ne suffit pas.

```ini
php_admin_flag[session.upload_progress.enabled] = off
php_admin_value[post_max_size] = 32M
php_admin_value[upload_max_filesize] = 24M
php_admin_value[max_file_uploads] = 5
php_admin_value[max_input_nesting_level] = 16
```

Côté nginx, limiter la taille des requêtes en cohérence avec `post_max_size`, et ne transmettre à PHP que le contrôleur frontal :

```nginx
client_max_body_size 32m;

location ~ ^/index\.php(/|$) {
    # fastcgi_pass … ;
    internal;
}
location ~ \.php$ { return 404; }
```

Tant que PHP n'est pas corrigé, l'avis de sécurité recommande aussi `php_admin_value[memory_limit] = -1` (accompagné d'une limite mémoire cgroup sur le service) et `pm.max_requests = 1`. Ne jamais limiter php-fpm avec `RLIMIT_AS`/`RLIMIT_DATA` (`LimitAS=` systemd, `ulimit -v`).

Références : [GHSA-w8hw-w9fx-j79w](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w), [GHSA-6wmf-4rv5-p3rp](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp).

### crontab

```
# generate shifts in 27 days (same weekday as yesterday)
55 5 * * * php YOUR_INSTALL_DIR_ABSOLUTE_PATH/bin/console app:shift:generate $(date -d "+27 days" +\%Y-\%m-\%d)

# free pre-booked shifts
55 5 * * * php YOUR_INSTALL_DIR_ABSOLUT_PATH/bin/console app:shift:free $(date -d "+21 days" +\%Y-\%m-\%d)

# send reminder 2 days before shift
0 6 * * * php YOUR_INSTALL_DIR_ABSOLUT_PATH/bin/console app:shift:reminder $(date -d "+2 days" +\%Y-\%m-\%d)

# execute routine for cycle_end/cycle_start, everyday
5 6 * * * php YOUR_INSTALL_DIR_ABSOLUT_PATH/bin/console app:user:cycle_start

# send alert on shifts booking (low)
0 10 * * * php YOUR_INSTALL_DIR_ABSOLUT_PATH/bin/console app:shift:send_alerts $(date -d "+2 days" +\%Y-\%m-\%d) 1

# send a reminder mail to the user who generate the last code but did not validate the change.
45 21 * * * php YOUR_INSTALL_DIR_ABSOLUT_PATH/bin/console app:code:verify_change --last_run 24
```

## Mise en route

* Suivez le [guide de mise en route](start.md)
