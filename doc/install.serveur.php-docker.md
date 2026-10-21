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

```
Internet ── nginx (hôte) ──fastcgi 127.0.0.1:9000──> php-fpm (conteneur) ──> MySQL (hôte)
                                                        │
                                       code monté depuis l'hôte, au même chemin
```

Dans les commandes ci-dessous, on suppose :

* le code installé dans `/var/www/gestion-compte` (à adapter partout) ;
* l'ancien PHP-FPM de l'hôte est le service `php7.4-fpm` (voir `PHP_SERVICE_NAME` dans `.env`).

Durée : environ 15 minutes, dont la construction de l'image. L'interruption de service ne dure que
quelques secondes (rechargement de nginx).

## 1. Prérequis

* Docker Engine et le plugin `docker compose` : [installation](https://docs.docker.com/engine/install/).
  Vérifier avec `docker compose version`.
* Sauvegarder la configuration actuelle :

  ```shell
  sudo cp -a /etc/nginx/sites-available /root/nginx-sites-available.bak
  sudo cp -a /etc/php/7.4/fpm/pool.d /root/php-fpm-pool.d.bak
  ```

* Noter les réglages actuels du pool PHP-FPM (`pm`, `pm.max_children`…) dans
  `/etc/php/7.4/fpm/pool.d/www.conf`, pour les reporter à l'étape 3.
* Vérifier que l'utilisateur `www-data` a l'UID 33 sur l'hôte (c'est le cas sous Debian/Ubuntu),
  comme dans l'image :

  ```shell
  id -u www-data   # doit afficher 33
  ```

  Si les fichiers de l'application appartiennent à un autre utilisateur, voir la note de l'étape 3.

## 2. Construire l'image PHP corrigée

L'image se construit depuis un clone séparé du dépôt. Ce clone **ne remplace pas** votre installation et
fonctionne quelle que soit la version de l'application que vous faites tourner.

```shell
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
; IMPORTANT : écouter uniquement en local. Par défaut, l'image écoute sur toutes
; les interfaces. Avec network_mode: host, PHP-FPM serait alors joignable depuis
; Internet, ce qui permet d'exécuter du code à distance.
listen = 127.0.0.1:9000

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
> pas dans le conteneur.

Créer `/opt/gestion-compte-php/compose.yaml` :

```yaml
services:
  php:
    image: gestion-compte-php:7.4.33-fpm-cve
    # Le conteneur partage le réseau de l'hôte : 127.0.0.1 désigne le serveur,
    # donc nginx et le DATABASE_URL existant fonctionnent sans modification.
    network_mode: host
    restart: unless-stopped
    environment:
      TZ: Europe/Paris
    working_dir: /var/www/gestion-compte
    volumes:
      # Le code est monté au MÊME chemin que sur l'hôte : la configuration nginx
      # (root, SCRIPT_FILENAME) reste valable telle quelle.
      - /var/www/gestion-compte:/var/www/gestion-compte
      - ./zzz-pool.conf:/usr/local/etc/php-fpm.d/zzz-pool.conf:ro
      # Seulement si DATABASE_URL utilise "localhost" (socket Unix MySQL) :
      # - /run/mysqld:/run/mysqld
    deploy:
      resources:
        limits:
          memory: 2G
```

À propos de la connexion à la base, dans le `.env` de l'application :

* `DATABASE_URL` pointe vers `127.0.0.1` : rien à faire ;
* il pointe vers `localhost` : PHP passe par le socket Unix de MySQL, décommentez la ligne
  `/run/mysqld` ci-dessus (ou remplacez `localhost` par `127.0.0.1`).

Si `MAILER_DSN` utilise `sendmail://`, il ne fonctionnera pas depuis le conteneur : passez en
`smtp://` (vers votre relais, ou `smtp://127.0.0.1:25` si un MTA tourne sur l'hôte).

## 4. Démarrer PHP en conteneur

Si l'ancien PHP-FPM écoute lui aussi sur `127.0.0.1:9000` (et non sur un socket), arrêtez-le d'abord avec
`sudo systemctl stop php7.4-fpm` : les deux ne peuvent pas écouter sur le même port.

```shell
cd /opt/gestion-compte-php
sudo docker compose up -d
sudo docker compose logs php          # doit afficher "ready to handle connections"
sudo ss -ltnp | grep ':9000'          # doit afficher 127.0.0.1:9000 UNIQUEMENT
```

Si `ss` affiche `0.0.0.0:9000` ou `*:9000`, **arrêtez tout** (`sudo docker compose down`) et vérifiez
le montage de `zzz-pool.conf`.

Vider le cache Symfony, généré par l'ancien PHP :

```shell
sudo docker compose exec -u www-data php php bin/console cache:clear --env=prod
```

## 5. Basculer nginx

Dans le vhost de l'application (`/etc/nginx/sites-available/…`), remplacer **chaque** `fastcgi_pass`
vers l'ancien PHP-FPM :

```diff
-        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
+        fastcgi_pass 127.0.0.1:9000;
```

Puis :

```shell
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl disable --now php7.4-fpm
```

## 6. Vérifier

```shell
sudo docker compose exec php php -v                      # PHP 7.4.33
sudo docker compose exec php sh -c 'grep -c zend_new_array_singleton /usr/local/sbin/php-fpm'   # > 0 = correctif présent
```

Dans le navigateur : se connecter, afficher le planning des créneaux, une fiche membre, et un QR code
(`/sw/…/qr.png`). En cas d'erreur : `sudo docker compose logs php` et les logs nginx.

## 7. Tâches planifiées (cron)

Dans la crontab qui lance les commandes de l'application (`sudo crontab -e`, ou celle de `www-data`),
remplacer `php /var/www/gestion-compte/bin/console` par une exécution dans le conteneur. Par exemple :

```diff
-55 5 * * * php /var/www/gestion-compte/bin/console app:shift:generate $(date -d "+27 days" +\%Y-\%m-\%d)
+55 5 * * * docker compose -f /opt/gestion-compte-php/compose.yaml exec -T -u www-data php php bin/console app:shift:generate $(date -d "+27 days" +\%Y-\%m-\%d)
```

`-u www-data` est important : sans lui, la commande tourne en root et crée dans `var/` des fichiers que
PHP-FPM ne pourra plus modifier. La crontab doit appartenir à un utilisateur autorisé à utiliser Docker
(root, ou membre du groupe `docker`).

## 8. Mises à jour de l'application

Les commandes PHP (`composer`, `bin/console`) se lancent désormais dans le conteneur. Composer y est
installé :

```shell
cd /opt/gestion-compte-php
sudo docker compose exec -u www-data php composer install --no-dev --optimize-autoloader
sudo docker compose exec -u www-data php php bin/console doctrine:migrations:migrate
sudo docker compose restart php      # remplace "systemctl restart php7.4-fpm"
```

Le script `dploy.sh` appelle encore le PHP de l'hôte : ne l'utilisez plus tel quel.

## Retour arrière

```shell
# remettre l'ancien fastcgi_pass dans le vhost, puis :
sudo systemctl enable --now php7.4-fpm
sudo nginx -t && sudo systemctl reload nginx
cd /opt/gestion-compte-php && sudo docker compose down
```

Attention : le retour arrière remet en service le PHP **vulnérable** de l'hôte.

## Et après

* À chaque nouveau correctif de sécurité publié dans le dépôt : `git pull` dans
  `/opt/gestion-compte-php/src`, reconstruire l'image (étape 2, en ajoutant `--pull --no-cache`), puis
  `sudo docker compose up -d --force-recreate`.
* Une fois la bascule validée, le PHP de l'hôte peut être désinstallé.
* Cette solution reste **temporaire** : elle sera remplacée par une stack docker-compose complète, et
  PHP 7.4 ne recevra plus d'autres correctifs.
