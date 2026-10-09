# Installation sur un serveur

## Prérequis

* PHP 7.4
* Node.js (construction des fichiers JavaScript et CSS)
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
php bin/console doctrine:migrations:migrate
```

Construire les fichiers JavaScript et CSS (Webpack Encore)

```shell
npm ci
npm run build
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

Créer l'utilisateur super admin en ligne de commande : `php bin/console app:user:install_super_admin` (identifiants : `SUPER_ADMIN_USERNAME` / `SUPER_ADMIN_INITIAL_PASSWORD`, à changer à la première connexion). La page `/user/install_admin` ne crée plus le super admin d'une instance neuve.

## En prod

### nginx

Avec nginx, ligne necessaire pour avoir les images dynamiques de qr et barecode (au lieu de 404)

```
location ~* ^/sw/(.*)/(qr|br)\.png$ {
	rewrite ^/sw/(.*)/(qr|br)\.png$ /index.php/sw/$1/$2.png last;
}
```

Les fichiers envoyés (logos de services, images d'événements) sont stockés dans `web/uploads/` et ne doivent jamais être exécutés. `web/uploads/.htaccess` le garantit avec Apache ; avec nginx, ajouter :

```
location ^~ /uploads/ {
	location ~* \.(php\d?|phtml|phar|pht)$ { return 403; }
}
```

### Sécurité PHP (CVE-2026-109618 / CVE-2026-109619)

Ces deux failles permettent une exécution de code à distance sans authentification, avant même que l'application ne s'exécute. Versions corrigées : 8.2.35, 8.3.36, 8.4.27 et 8.5.12. Les branches en fin de vie (8.1 et antérieures, dont 7.4) ne recevront pas de version corrigée : l'équipe sécurité PHP n'a publié pour 7.4, 8.0 et 8.1 qu'un correctif source à recompiler.

* **Docker** : l'image (`.docker/Dockerfile`) recompile PHP 7.4.33 avec ce correctif (`.docker/patches/`, identique au commit php-src `712e60f9ed`). Il suffit de reconstruire l'image et de recréer les conteneurs :

  ```shell
  docker compose build --pull php && docker compose up -d --force-recreate php
  ```

  Le correctif désactive définitivement la progression d'upload des sessions (non utilisée par l'application). En conséquence, les tests php-src `ext/session/tests/rfc1867*.phpt` échouent, et `tests/lang/bug45392.phpt` aussi (un saut de ligne en plus avant le message d'erreur mémoire) : c'est attendu.

* **Serveur sans Docker, PHP natif** : installer un PHP corrigé, soit le paquet de la distribution ou d'un fournisseur LTS incluant le correctif, soit une recompilation des sources 7.4.33 avec `.docker/patches/php-7.4-cve-2026-109618-109619.patch` (`patch -p1`). Préférer ce patch à la branche [`PHP-7.4-CVE-2026-109618-109619`](https://github.com/php/php-src/tree/PHP-7.4-CVE-2026-109618-109619) compilée telle quelle : elle se déclare en version `7.4.31-dev`. Seule cette mise à jour protège complètement.

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
