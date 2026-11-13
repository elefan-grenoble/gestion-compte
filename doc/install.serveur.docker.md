# Installation sur un serveur avec Docker (production)

Cette installation fait tourner l'espace membre dans quatre conteneurs, à partir d'images construites et publiées par le projet. Elle ne demande ni PHP, ni Composer, ni Node.js, ni MySQL sur le serveur, ni même une copie du dépôt.

```
Internet ──443/80──> web (Caddy, TLS) ──FastCGI──> php (php-fpm) ──> database (MariaDB 10.11)
                                                    cron (supercronic) ──┘
```

| Service | Image | Rôle |
|---|---|---|
| `web` | `ghcr.io/elefan-grenoble/gestion-compte-web` | Caddy : HTTPS (certificat Let's Encrypt obtenu et renouvelé automatiquement), fichiers statiques, transmission du reste à PHP |
| `php` | `ghcr.io/elefan-grenoble/gestion-compte` | PHP 7.4.33 corrigé (CVE-2026-109618 / CVE-2026-109619) et le code de l'application |
| `cron` | même image que `php` | Tâches planifiées (`.docker/prod/crontab`) |
| `database` | `mariadb:10.11` | Base de données, jamais exposée hors de la stack |

Les données persistantes sont dans des volumes Docker : base (`db_data`), fichiers envoyés (`uploads`), sessions (`sessions`, pour que les membres ne soient pas déconnectés à chaque mise à jour), journaux (`logs`), miniatures (`media_cache`) et certificats (`caddy_data`).

Vous migrez une installation existante sans Docker ? Suivez plutôt la [procédure de migration](migration.baremetal-docker.md), qui reprend cette page.

## Prérequis

* Un serveur Linux amd64 ou arm64 avec Docker Engine et le plugin `docker compose` ([installation](https://docs.docker.com/engine/install/)) ; vérifier avec `docker compose version`. 2 Go de mémoire suffisent pour une coopérative de quelques milliers de membres.
* Docker **rootful** (installation standard). Avec Docker rootless, l'application ne voit pas l'adresse IP réelle des visiteurs, ce qui fausse `ENABLE_PLACE_LOCAL_IP_ADDRESS_CHECK`.
* Un nom de domaine (par exemple `membres.macoop.fr`) qui pointe vers le serveur, ports 80 et 443 ouverts. Si le serveur a déjà un nginx ou un autre proxy sur ces ports, voir [Derrière un proxy existant](#derrière-un-proxy-existant).
* Un serveur SMTP pour l'envoi des e-mails (`sendmail://` ne fonctionne pas dans un conteneur).

## 1. Récupérer les fichiers

Choisir la version à installer parmi les [releases](https://github.com/elefan-grenoble/gestion-compte/releases) (par exemple `1.48.0`), puis :

```shell
VERSION=1.48.0
sudo mkdir -p /opt/gestion-compte && cd /opt/gestion-compte
base=https://raw.githubusercontent.com/elefan-grenoble/gestion-compte/v${VERSION}
sudo curl -fsSLo compose.yaml ${base}/.docker/prod/compose.yaml
sudo curl -fsSLo .env.dist    ${base}/.docker/prod/.env.dist
sudo curl -fsSLo app.env.dist ${base}/.env.dist
```

Deux fichiers de configuration, à ne pas confondre :

* **`.env`** : la configuration de la stack (version, domaine, mots de passe de la base), lue par `docker compose` ;
* **`app.env`** : la configuration de l'application (le `.env` Symfony habituel), montée en lecture seule dans les conteneurs.

## 2. Configurer la stack

```shell
sudo cp .env.dist .env && sudo chmod 600 .env
sudo nano .env
```

Renseigner `APP_VERSION` (sans le `v`), `SITE_ADDRESS` (le domaine), `MARIADB_PASSWORD` et `MARIADB_ROOT_PASSWORD`. Pour générer un mot de passe : `openssl rand -base64 24`.

## 3. Configurer l'application

```shell
sudo cp app.env.dist app.env
sudo chown root:33 app.env && sudo chmod 640 app.env
sudo nano app.env
```

`33` est le groupe `www-data` des conteneurs, qui doit pouvoir lire ce fichier. Les réglages propres à cette installation :

```ini
APP_ENV=prod
APP_SECRET=<openssl rand -hex 32>
# Hôte "database", identifiants de .env, version exacte du serveur
DATABASE_URL="mysql://gestion_compte:<MARIADB_PASSWORD>@database:3306/gestion_compte?serverVersion=mariadb-10.11.19&charset=utf8mb4"
MAILER_DSN=smtp://utilisateur:motdepasse@smtp.example.org:587
ROUTER_REQUEST_CONTEXT_HOST=membres.macoop.fr
ROUTER_REQUEST_CONTEXT_SCHEME=https
```

`serverVersion` doit correspondre au serveur réel : l'application ne se connecte pas à la base au démarrage pour la deviner. Le reste du fichier (nom de la coopérative, e-mails, fonctionnalités…) se règle comme pour toute installation : voir les commentaires de `app.env.dist`.

## 4. Démarrer

```shell
sudo docker compose pull
sudo docker compose up -d --wait
sudo docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Visiter `https://<domaine>/user/install_admin` pour créer le super administrateur, puis suivre le [guide de mise en route](start.md).

En cas de problème : `sudo docker compose ps` et `sudo docker compose logs <service>`. Le premier démarrage de `web` peut prendre quelques dizaines de secondes, le temps d'obtenir le certificat.

## Mettre à jour

Avant chaque mise à jour, lire les notes de version, puis :

```shell
cd /opt/gestion-compte
# 1. Sauvegarde de la base
sudo docker compose exec -T database sh -c 'mariadb-dump --single-transaction --routines --triggers -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
    | gzip > "/root/gestion-compte-$(date +%F-%H%M).sql.gz"
# 2. Nouvelle version : APP_VERSION dans .env, et compose.yaml de cette version
sudo nano .env
sudo curl -fsSLo compose.yaml https://raw.githubusercontent.com/elefan-grenoble/gestion-compte/v<VERSION>/.docker/prod/compose.yaml
# 3. Bascule et migrations
sudo docker compose pull
sudo docker compose up -d --wait
sudo docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Retour arrière : remettre l'ancienne `APP_VERSION`, `sudo docker compose up -d`, et si des migrations ont été jouées, restaurer la sauvegarde (voir [Restaurer une sauvegarde](#restaurer-une-sauvegarde)).

Vérifier l'origine d'une image (facultatif, nécessite la CLI `gh`) :

```shell
gh attestation verify oci://ghcr.io/elefan-grenoble/gestion-compte:<VERSION> --owner elefan-grenoble
```

## Exploitation

| Besoin | Commande (depuis `/opt/gestion-compte`) |
|---|---|
| État des services | `sudo docker compose ps` |
| Journaux | `sudo docker compose logs -f web php cron` |
| Journal de l'application | `sudo docker compose exec php tail -f var/log/prod.log` |
| Commande Symfony | `sudo docker compose exec php php bin/console <commande>` |
| Console SQL | `sudo docker compose exec database sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'` |
| Tâches planifiées | `sudo docker compose logs cron` (horaires dans `.docker/prod/crontab`) |

Les commandes tournent sous l'utilisateur `www-data` des conteneurs : pas besoin de `-u`.

### Sauvegardes

Aucune sauvegarde automatique n'est incluse. Planifier au minimum la sauvegarde de la base (commande de l'étape 1 de la mise à jour, dans la crontab de root) et des fichiers envoyés :

```shell
sudo docker run --rm -v gestion-compte_uploads:/uploads:ro -v /root:/backup alpine \
    tar -czf /backup/gestion-compte-uploads-$(date +%F).tar.gz -C /uploads .
```

### Restaurer une sauvegarde

```shell
gunzip -c /root/gestion-compte-<date>.sql.gz \
    | sudo docker compose exec -T database sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
```

## Derrière un proxy existant

Si un nginx (ou un autre proxy) occupe déjà les ports 80 et 443 et gère le TLS, Caddy écoute seulement en local et en HTTP. Dans `.env` :

```ini
SITE_ADDRESS=:80
HTTP_PUBLISH=127.0.0.1:8080
HTTPS_PUBLISH=127.0.0.1:8443
CADDY_TRUSTED_PROXIES=private_ranges
```

Dans `app.env`, faire confiance au proxy pour l'adresse IP des visiteurs :

```ini
TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12,192.168.0.0/16,10.0.0.0/8
```

Et côté nginx :

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    client_max_body_size 32m;
}
```

## Construire les images soi-même

Pour un fork ou sans accès à `ghcr.io`, les images se construisent depuis une copie du dépôt (compter 10 à 15 minutes, PHP est recompilé) :

```shell
git clone -b v<VERSION> https://github.com/elefan-grenoble/gestion-compte.git
cd gestion-compte/.docker/prod
# .env et app.env dans ce dossier, puis :
sudo APP_IMAGE=gestion-compte APP_VERSION=<VERSION> WEB_IMAGE=gestion-compte-web docker compose build
```

Mettre alors `APP_IMAGE=gestion-compte` et `WEB_IMAGE=gestion-compte-web` dans `.env`.

## Sécurité

* PHP-FPM et la base ne sont accessibles que depuis les conteneurs de la stack ; seul Caddy publie des ports. La base est sur un réseau sans accès à Internet.
* Tous les conteneurs de l'application tournent sans privilèges (`www-data`, `nobody` pour Caddy, aucune capability).
* Seul `public/index.php` est exécuté : un fichier PHP déposé dans `public/uploads/` est refusé, jamais exécuté ni affiché.
* PHP 7.4 est en fin de vie : l'image corrige CVE-2026-109618 / CVE-2026-109619, mais la montée vers un PHP maintenu reste indispensable.
