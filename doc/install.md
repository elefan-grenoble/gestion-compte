# Installation

## Utilisation via docker-compose

### Prérequis

* docker
* docker-compose

### Lancer l'instance

Lancer le docker-compose pour deployer un conteneur de base de données (mariadb) et un conteneur symfony

```shell
docker-compose up
```

Ajouter `127.0.0.1 membres.yourcoop.local` au fichier _/etc/hosts_.

Créer l'utilisateur super admin (valeurs par défaut : admin:password)

Visiter [http://membres.yourcoop.local:8000/user/install_admin](http://membres.yourcoop.local:8000/user/install_admin) pour créer l'utilisateur super admin (valeurs par défaut : admin:password)

Note: le premier lancement du docker-compose peut être long (~30s) du fait de plusieurs étapes : initialisation de la db, creation du fichier parameters.yml, ... La ligne `PHP 7.4.27 Development Server (http://0.0.0.0:8000) started` indique que le deploiement de l'espace membre est fonctionnel. La base de données est montée dans docker avec un volume, elle est donc persistente. Le fichier _parameters.yml_ doit être modifié suivant la configuration voulue.

### Charger la base de donénes à partir d'un dump

Supprimer une base de données existante (si elle existe)

```shell
docker exec -it database mysql -uroot -psecret -e 'DROP DATABASE IF EXISTS symfony;'
```

Recréer la base de données

```shell
docker exec -it database mysql -uroot -psecret -e 'CREATE DATABASE IF NOT EXISTS symfony;'
```

Charger la base données depuis une sauvegarde

```shell
docker exec -i database mysql -uroot -psecret symfony < espace_membres.sql
```

## Installation sur une serveur

### Prérequis

* PHP (version 7.2 et supérieure)
* [Composer](https://getcomposer.org/)
* Mysql (ou mariadb)
* php-mysql (ou php-pdo_mysql)
* php-xml
* php-gd

### Installation

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

Ajouter ``127.0.0.1 membres.yourcoop.local`` au fichier _/etc/hosts_.

Visiter [http://membres.yourcoop.local/user/install_admin](http://membres.yourcoop.local/user/install_admin) pour créer l'utilisateur super admin (valeurs par défaut : admin:password)

## Autres

### En Prod

Avec nginx, ligne necessaire pour avoir les images dynamiques de qr et barecode (au lieu de 404)

```
location ~* ^/sw/(.*)/(qr|br)\.png$ {
	rewrite ^/sw/(.*)/(qr|br)\.png$ /app.php/sw/$1/$2.png last;
}
```

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

### mise en route

* Suivez le [guide de mise en route](start.md)
