# Installation avec Docker

## Prérequis

* Machine sous Linux avec Docker, Docker Compose et Git installés.
* Avoir extrait les sources du projet localement.

## Paramétrage

Si vous avez déjà installé le module et que vous disposez d'un fichier de paramètres vous pouvez le copier directement dans le répertoire du module avec la commande :  

    cp parameters.yml ./gestion-compte/app/config/

Si vous ne disposez pas de fichier de paramètres, vous pourrez les saisir manuellement pendant l'installation.

Il est nécessaire de configurer une variable d'environnement `DBROOTPWD` avec un mot de passe root permettant l'accès à la base de données.
Il suffit pour cela de configurer la variable dans le fichier .env situé à la racine du projet.

## Démarrage des conteneurs

Le Docker Compose démarre trois services :

* un conteneur de bases de données (MariaDB) ;
* un conteneur Adminer pour administrer la base de données ;
* un conteneur comprenant un serveur Apache et PHP.

Pour démarrer les conteneurs, la commande est

    # docker-compose -f ./docker-compose.yml up -d

## Paramétrage MariaDB

### Création d'un utilisateur

#### Par la ligne de commande

    # docker-compose exec db bash
    # mysql -u root -p
    mysql> CREATE USER '<username>'@'%' IDENTIFIED BY '<password>';
    mysql> GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, FILE, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES, CREATE VIEW, EVENT, TRIGGER, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EXECUTE ON  *.* TO '<username>'@'%' ;

#### Par l'interface Adminer

Adminer est disponible sur le port 8080.

### Création d'une base de données

    mysql> CREATE DATABASE `gestion_membres`;

## Configuration du module

    # docker-compose exec --user www-data gestion-membres composer install
À cette étape vous devrez peut-être saisir différents paramètres de configuration, notamment si c'est votre première installation.
    # docker-compose exec gestion-membres php bin/console doctrine:schema:create

Aller sur `http://hostname/user/install_admin` pour créer le superadministrateur.