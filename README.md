Espace adhérent l'éléfàn
========================
## Modèle de données

![modele](http://yuml.me/ee3093b1.svg)

* yuml.me code:
http://yuml.me/edit/ee3093b1

## Install

### Prerequisites

* PHP
* Composer
* Mysql installed and configured (or mariadb on Fedora)
* php-mysql (php-pdo_mysql on Fedora)

### Setup

* ``git clone https://github.com/elefan-grenoble/gestion-compte.git``
* ``cd gestion-compte``
* ``composer install``
* ``bin/console doctrine:schema:create``
* add ``127.0.0.1 membres.lelefan.local`` to your _/etc/hosts_ file
* ``php bin/console server:start``
* visit http://membres.lelefan.local/user/install_admin to create the super admin user (babar:password)
