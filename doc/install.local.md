# Installation locale

## Utilisation via docker-compose

### Prérequis

* docker
* docker-compose


### Créer les conteneurs

```shell
docker-compose build
``` 

### Lancer l'instance

Lancer le docker-compose pour deployer un conteneur de base de données (mariadb) et un conteneur symfony

```shell
docker-compose up
```

Ajouter `127.0.0.1 membres.yourcoop.local` au fichier _/etc/hosts_.

Note: le premier lancement du docker-compose peut être long (~30s) du fait de plusieurs étapes : initialisation de la db, creation du fichier parameters.yml, ... La ligne `PHP 7.4.27 Development Server (http://0.0.0.0:8000) started` indique que le deploiement de l'espace membre est fonctionnel. La base de données est montée dans docker avec un volume, elle est donc persistente. Le fichier _parameters.yml_ doit être modifié suivant la configuration voulue.

Le site est en ligne à l'adresse [http://membres.yourcoop.local:8000](http://membres.yourcoop.local:8000).

Pour créer l'utilisateur super admin, visiter :
[http://membres.yourcoop.local:8000/user/install_admin](http://membres.yourcoop.local:8000/user/install_admin).

Vous pouvez vous connecter avec l'utilisateur super admin :
**admin** / **password**.

### Remplir la base de donnée avec des données fictives

```shell
docker compose exec php php bin/console doctrine:fixtures:load -n
```

Le groupe de fixtures "period" omet les données de la table **shift**, utile pour tester la génération des shifts à partir des périodes.

```shell
docker compose exec php php bin/console doctrine:fixtures:load -n --group=period
```

### Importer un dump de la base de données

#### S

```shell
# supprimer la base de données existante et la recréer
docker compose exec database mariadb -uroot -psecret -e 'DROP DATABASE IF EXISTS symfony; CREATE DATABASE IF NOT EXISTS symfony;'

# importer le dump
docker compose exec database mariadb -uroot -psecret symfony < espace_membres.sql
```

Vous pouvez aussi le faire directement sur phpmyadmin : [http://localhost:8080](http://localhost:8080)
