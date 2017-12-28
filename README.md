Espace adhérent l'éléfàn
========================
## Modèle de données

![modele](http://yuml.me/5c392db4.svg)

* yuml.me code:
http://yuml.me/edit/5c392db4

## Guide du développeur

### Prérequis

* PHP (version 7+) installé
* [Composer](https://getcomposer.org/) installé
* Mysql installé et configuré (ou mariadb sur Fedora)
* php-mysql (php-pdo_mysql on Fedora)
* Créer une nouvelle base pour le projet

### Installation

* ``git clone https://github.com/elefan-grenoble/gestion-compte.git``
* ``cd gestion-compte``
* ``composer install`` (utiliser le nom de la base précédemment créée)
* ``bin/console doctrine:schema:create``
* add ``127.0.0.1 membres.lelefan.local`` to your _/etc/hosts_ file
* ``php bin/console server:start``
* visit http://membres.lelefan.local/user/install_admin to create the super admin user (babar:password)

### Créer un utilisateur

* Se connecter avec l'utilisateur super admin babar/password
* Créer un utilisateur

#### Activer l'utilisateur

L'activation passe par un envoi de mail. Il faut installer un mail catcher pour pouvoir faire fonctionner l'envoi de mail en local.

Sinon, il est possible d'activer un utilisateur via cette procédure:

* Activer l'utilisateur avec la commande suivante ``php bin/console fos:user:activate $username``
* Changer le mot de passe avec la commande suivante ``php bin/console fos:user:change-password $username newp@ssword``

Documentation Symfony pour manipuler les utilisateurs: http://symfony.com/doc/2.0/bundles/FOSUserBundle/command_line_tools.html

### Cheatsheet

#### Mise à jour du modèle

* Créer une nouvelle entité: ``php bin/console doctrine:generate:entity AppBundle:EntityName``
* Générer les getters et setters d'une entité: ``php bin/console doctrine:generate:entities``
* Appliquer les mises à jours sur la base: 
   * Dryrun: ``php bin/console doctrine:schema:update``
   * Voir les requêtes: ``php bin/console doctrine:schema:update --dump-sql``
   * Appliquer les changements: ``php bin/console doctrine:schema:update --force``
