# Passer PHP en Docker sur une installation serveur (correctif rapide)

> ⚠️ **Solution temporaire.** Cette procédure sert à corriger **vite et simplement** les failles
> [CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w) et
> [CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp) (21/10/2026)
> sur une installation sans Docker, **sans recompiler PHP sur le serveur**.
> Ce n'est pas l'architecture cible : une stack docker-compose complète (nginx, PHP, base de données,
> avec une procédure de migration des données) sera proposée séparément.
> PHP 7.4 reste en fin de vie : la montée vers un PHP maintenu reste indispensable.

## Principe

Seul **PHP-FPM** passe dans un conteneur, construit depuis l'image corrigée du projet (`.docker/Dockerfile`).
**Rien d'autre ne bouge** : nginx, MySQL/MariaDB, le code, le fichier `.env`, les uploads et la base restent
sur le serveur, à leur place. Aucune migration de données.

Le conteneur reprend **le même socket** que l'ancien PHP-FPM (`/run/php/php7.4-fpm.sock`). La
configuration nginx n'a donc pas à être modifiée, et aucun port n'est ouvert.

```
Internet ── nginx (hôte) ──socket /run/php/php7.4-fpm.sock──> php-fpm (conteneur) ──> MySQL (hôte)
                                                                 │
                                                code monté depuis l'hôte, au même chemin
```

Dans les commandes ci-dessous, on suppose :

* le code installé dans `/var/www/gestion-compte` (à adapter partout) ;
* l'ancien PHP-FPM de l'hôte est le service `php7.4-fpm` et écoute sur le socket
  `/run/php/php7.4-fpm.sock` : c'est le chemin du `fastcgi_pass unix:…` de votre vhost nginx
  (`/var/run/…` et `/run/…` désignent le même endroit). Si votre chemin est différent, adaptez-le partout.

Durée : environ 15 minutes, dont la construction de l'image. L'interruption de service ne dure que
quelques secondes (étape 4).

## 1. Prérequis

* Docker Engine et le plugin `docker compose` : [installation](https://docs.docker.com/engine/install/).
  Vérifier avec `docker compose version`.
* Sauvegarder la configuration actuelle du pool PHP-FPM :

  ```shell
  sudo cp -a /etc/php/7.4/fpm/pool.d /root/php-fpm-pool.d.bak
  ```

* Relire l'ancien pool (`/etc/php/7.4/fpm/pool.d/www.conf`) et noter tout ce qui a été modifié par rapport
  aux valeurs par défaut (`pm.*`, `php_admin_value[…]`, `env[…]`, `request_terminate_timeout`…) : à
  reporter à l'étape 3.
* Vérifier que l'utilisateur `www-data` a l'UID 33 sur l'hôte (c'est le cas sous Debian/Ubuntu),
  comme dans l'image :

  ```shell
  id -u www-data   # doit afficher 33
  ```

  Si les fichiers de l'application appartiennent à un autre utilisateur, voir la note de l'étape 3.
* Le code doit se trouver **réellement** dans `/var/www/gestion-compte` : si des liens symboliques pointent
  ailleurs (par exemple un dossier `shared/` ou `current -> releases/…`), montez aussi ces dossiers dans le
  conteneur, au même chemin.

## 2. Construire l'image PHP corrigée

L'image se construit depuis un clone séparé du dépôt. Ce clone **ne remplace pas** votre installation et
fonctionne quelle que soit la version de l'application que vous faites tourner.

```shell
# Tant que la PR #1301 n'est pas fusionnée, ajouter : -b fix/php-7.4-cve-2026-109618-109619
sudo git clone https://github.com/elefan-grenoble/gestion-compte.git /opt/gestion-compte-php/src
cd /opt/gestion-compte-php/src
sudo docker build -f .docker/Dockerfile \
    --build-arg PHP_BASE_IMAGE=php:7.4-fpm \
    -t gestion-compte-php:7.4.33-fpm-cve .
```

Comptez quelques minutes : PHP est recompilé avec le correctif. Le build échoue de lui-même si le PHP
obtenu ne contient pas le correctif.

## 3. Configurer le conteneur

Créer `/opt/gestion-compte-php/zzz-pool.conf`. Ce fichier surcharge la configuration du pool de l'image :

```ini
[www]
; Même socket que l'ancien PHP-FPM : nginx n'a pas à être modifié.
; IMPORTANT : sans cette ligne, l'image écoute sur le port 9000 de TOUTES les
; interfaces, ce qui permet d'exécuter du code à distance.
listen = /run/php/php7.4-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Base de données : si DATABASE_URL utilise "localhost", PHP passe par le socket
; Unix de MySQL, dont l'emplacement Debian/Ubuntu n'est pas celui par défaut de l'image.
php_admin_value[pdo_mysql.default_socket] = /run/mysqld/mysqld.sock

; Reporter ici les réglages de votre ancien pool
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

; Durcissement recommandé par l'avis de sécurité
php_admin_flag[session.upload_progress.enabled] = off
php_admin_value[post_max_size] = 32M
php_admin_value[upload_max_filesize] = 24M
php_admin_value[max_file_uploads] = 5
php_admin_value[max_input_nesting_level] = 16
```

> Si les fichiers de l'application n'appartiennent pas à `www-data` (UID 33), ajoutez
> `user = <uid>` et `group = <gid>` **en numérique** dans ce fichier : l'utilisateur de l'hôte n'existe
> pas dans le conteneur. Gardez `listen.owner`/`listen.group` sur l'utilisateur de nginx.

Créer `/opt/gestion-compte-php/compose.yaml` :

```yaml
services:
  php:
    image: gestion-compte-php:7.4.33-fpm-cve
    # Le conteneur partage le réseau de l'hôte : 127.0.0.1 désigne le serveur,
    # donc DATABASE_URL et MAILER_DSN fonctionnent sans modification.
    network_mode: host
    restart: unless-stopped
    environment:
      TZ: Europe/Paris
    working_dir: /var/www/gestion-compte
    volumes:
      # Le code est monté au MÊME chemin que sur l'hôte : la configuration nginx
      # (root, SCRIPT_FILENAME) reste valable telle quelle.
      - /var/www/gestion-compte:/var/www/gestion-compte
      # Socket partagé avec nginx
      - /run/php:/run/php
      # Socket MySQL de l'hôte
      - /run/mysqld:/run/mysqld
      - ./zzz-pool.conf:/usr/local/etc/php-fpm.d/zzz-pool.conf:ro
    deploy:
      resources:
        limits:
          memory: 2G
```

Si `MAILER_DSN` utilise `sendmail://`, il ne fonctionnera pas depuis le conteneur : passez en
`smtp://` (vers votre relais, ou `smtp://127.0.0.1:25` si un MTA tourne sur l'hôte).

Avant de continuer, vérifier que le fichier du pool est bien là. S'il manque, Docker crée un dossier à sa
place et PHP-FPM démarre **exposé sur le port 9000** :

```shell
test -f /opt/gestion-compte-php/zzz-pool.conf && echo OK
```

## 4. Basculer

Les deux PHP-FPM ne peuvent pas utiliser le même socket : on arrête l'ancien juste avant de démarrer le
conteneur (coupure de quelques secondes).

```shell
cd /opt/gestion-compte-php
sudo systemctl stop php7.4-fpm && sudo docker compose up -d
sudo docker compose logs php          # doit afficher "ready to handle connections"
```

Contrôle de sécurité, **obligatoire** : cette commande ne doit **rien** afficher.

```shell
sudo ss -ltn | grep ':9000'
```

Si elle affiche quoi que ce soit (`0.0.0.0:9000`, `[::]:9000`, `*:9000`…), **arrêtez tout**
(`sudo docker compose down && sudo systemctl start php7.4-fpm`) et vérifiez `zzz-pool.conf`.

Si tout est correct, désactiver l'ancien PHP-FPM pour qu'il ne redémarre pas au prochain reboot, puis
vider le cache Symfony, généré par l'ancien PHP :

```shell
sudo systemctl disable php7.4-fpm
sudo docker compose exec -u www-data php php bin/console cache:clear --env=prod
```

## 5. Vérifier

```shell
sudo docker compose exec php php -v                      # PHP 7.4.33
sudo docker compose exec php grep -c zend_new_array_singleton /usr/local/sbin/php-fpm   # > 0 = correctif présent
```

Dans le navigateur : se connecter, afficher le planning des créneaux, une fiche membre, et un QR code
(`/sw/…/qr.png`). En cas d'erreur : `sudo docker compose logs php` et les logs nginx. Une erreur 502 de
nginx signale en général un problème de droits sur le socket (`ls -l /run/php/`).

## 6. Tâches planifiées (cron)

Dans la crontab de **root** (`sudo crontab -e`), remplacer `php /var/www/gestion-compte/bin/console` par une
exécution dans le conteneur. Par exemple :

```diff
-55 5 * * * php /var/www/gestion-compte/bin/console app:shift:generate $(date -d "+27 days" +\%Y-\%m-\%d)
+55 5 * * * docker compose -f /opt/gestion-compte-php/compose.yaml exec -T -u www-data php php bin/console app:shift:generate $(date -d "+27 days" +\%Y-\%m-\%d)
```

`-u www-data` est important : sans lui, la commande tourne en root et crée dans `var/` des fichiers que
PHP-FPM ne pourra plus modifier. Si ces tâches étaient dans la crontab de `www-data`, déplacez-les dans
celle de root : ne donnez pas à `www-data` l'accès à Docker (cela équivaut à un accès root).

## 7. Mises à jour de l'application

Les commandes PHP (`composer`, `bin/console`) se lancent désormais dans le conteneur. Composer y est
installé :

```shell
cd /opt/gestion-compte-php
sudo docker compose exec -u www-data -e COMPOSER_HOME=/tmp/composer php composer install --no-dev --optimize-autoloader
sudo docker compose exec -u www-data php php bin/console doctrine:migrations:migrate
sudo docker compose restart php      # remplace "systemctl restart php7.4-fpm"
```

Le script `dploy.sh` appelle encore le PHP de l'hôte : ne l'utilisez plus tel quel.

## Retour arrière

```shell
cd /opt/gestion-compte-php && sudo docker compose down
sudo systemctl enable --now php7.4-fpm
```

Il n'y a rien à changer dans nginx. Attention : le retour arrière remet en service le PHP **vulnérable**
de l'hôte.

## Et après

* À chaque nouveau correctif de sécurité publié sur la branche `main` du dépôt : `sudo git pull` dans
  `/opt/gestion-compte-php/src`, reconstruire l'image (étape 2, en ajoutant `--pull --no-cache`), puis
  `sudo docker compose up -d --force-recreate`.
* Une fois la bascule validée, le PHP de l'hôte peut être désinstallé.
* Cette solution reste **temporaire** : elle sera remplacée par une stack docker-compose complète, et
  PHP 7.4 ne recevra plus d'autres correctifs.
