# Installation locale

## Installation

### Avec docker compose

#### Prérequis

* [docker](https://docs.docker.com/get-started/get-docker/)
* [docker compose](https://docs.docker.com/compose/install/)

#### Copier le fichier de configuration

Afin d'avoir un fichier de configuration qui fonctionne sur votre environnement, créer votre propre configuration docker compose

```shell
cp docker-compose.symfony_server.yml.dist compose.yaml
```

Il est par exemple possible d'ajuster la configuration des ports si certains ne sont pas libres pour vous

#### Construire les conteneurs

```shell
docker compose build
``` 

#### Lancer l'instance

Lancer le docker-compose pour deployer un conteneur de base de données (mariadb) et un conteneur symfony

```shell
docker compose up
```

Note: le premier lancement du docker-compose peut être long (~30s) du fait de plusieurs étapes : initialisation de la db, creation du fichier parameters.yml, ... La ligne `PHP 7.4.27 Development Server (http://0.0.0.0:8000) started` indique que le deploiement de l'espace membre est fonctionnel. La base de données est montée dans docker avec un volume, elle est donc persistente. Le fichier _parameters.yml_ doit être modifié suivant la configuration voulue.

N'oubliez pas de définir la variable d'environnement `DEV_MODE_ENABLED` dans le container qui exécute le code de l'application.

### Avec nix

Vous pouvez obtenir toutes les dépendances du projet en utilisant [Nix](https://nixos.org/download.html). Une fois installé lancez `nix develop --impure` et tous les outils nécessaires sont dans votre `PATH` à la bonne version, comme déclaré dans [flake.nix](../flake.nix).
Cela peut se faire automatiquement quand vous `cd` dans le répertoire si vous avez installé [direnv](https://direnv.net/).

Pour lancer l'instance mariadb de test utilisez `devenv up`.
Pour lancer l'application, utilisez `php bin/console server:run '*:8000'`

## Accès à l'application

Ajouter `127.0.0.1 membres.yourcoop.local` au fichier _/etc/hosts_.

Le site est en ligne à l'adresse [http://membres.yourcoop.local:8000](http://membres.yourcoop.local:8000).

Pour créer l'utilisateur super admin, visiter :
[http://membres.yourcoop.local:8000/user/install_admin](http://membres.yourcoop.local:8000/user/install_admin).

Vous pouvez vous connecter avec l'utilisateur super admin :
**admin** / **password**.

## Ajout de données

### Remplir la base de donnée avec des données fictives

```shell
docker compose exec php php bin/console doctrine:fixtures:load -n
```

Le groupe de fixtures "period" omet les données de la table **shift**, utile pour tester la génération des shifts à partir des périodes.

```shell
docker compose exec php php bin/console doctrine:fixtures:load -n --group=period
```

### Importer un dump de la base de données

```shell
# supprimer la base de données existante et la recréer
docker compose exec database mariadb -uroot -psecret -e 'DROP DATABASE IF EXISTS symfony; CREATE DATABASE IF NOT EXISTS symfony;'

# importer le dump
docker compose exec database mariadb -uroot -psecret symfony < espace_membres.sql
```

Vous pouvez aussi le faire directement sur phpmyadmin : [http://localhost:8080](http://localhost:8080)

## Troubleshooting

### Erreurs de permission sur les volumes avec un système immuable

Testé sur fedora silverblue, ajouter `:rw,z` sur les volumes.
Par exemple `./mysql:/var/lib/mysql:rw,z`
https://docs.docker.com/reference/compose-file/services/#short-syntax-5