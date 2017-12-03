Espace adhérent l'éléfàn
========================
## Modèle de données

![modele](https://yuml.me/6590c986.svg)

* yuml.me code:
<code>[FOS User|username;password]1-1++[User|member_number]
      [User]++1-1..*<>[Beneficiary|is_main;lastname;firstname;phone;email]
      [User]++-1<>[Address|street;zip;city]
      [User]<*-*>[Commission|name],[User]<2-++[Commission],[user]1-*++[Registration|date;amount;mode]</code>

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
