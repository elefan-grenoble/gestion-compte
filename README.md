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

* git clone *this_repo*
* composer install
* bin/console doctrine:schema:create
* php bin/console server:start
* visite SITE_URL/user/install_admin
