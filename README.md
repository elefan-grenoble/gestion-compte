Espace adhérent l'éléfàn
========================
## Modèle de données

![modele](https://yuml.me/15627302.svg)

* yuml.me code:
https://yuml.me/edit/5c392db4

Nouveau schema (pour les utilisateurs et bénéficiaires) :

![modele V2](http://yuml.me/463ff905.svg)
http://yuml.me/edit/463ff905

## Guide du développeur

### Prérequis

* PHP (version 7+) installé
* [Composer](https://getcomposer.org/) installé
* Mysql installé et configuré (ou mariadb sur Fedora)
* php-mysql (php-pdo_mysql on Fedora)
* php-xml
* Créer une nouvelle base pour le projet

### Installation

* ``git clone https://github.com/elefan-grenoble/gestion-compte.git``
* ``cd gestion-compte``
* ``composer install`` (utiliser le nom de la base précédemment créée)
* ``bin/console doctrine:schema:create``
* add ``127.0.0.1 membres.lelefan.local`` to your _/etc/hosts_ file
* ``php bin/console server:start``
* visit http://membres.lelefan.local/user/install_admin to create the super admin user (babar:password)

### Installation du mailcatcher
https://mailcatcher.me/
sudo apt-get install unzip ruby-full build-essential
unzip mailcatcher-master.zip
sudo gem install mailcatcher
mailcatcher
#### Configuration du serveur symfony pour envoi des mails
Modifications des fichiers config.yml et parameters.yml, ajout du champ mailer_port = 1025

### Créer un utilisateur

* Se connecter avec l'utilisateur super admin babar/password
* Créer un utilisateur

#### Activer l'utilisateur

L'activation passe par un envoi de mail. Il faut installer un mail catcher pour pouvoir faire fonctionner l'envoi de mail en local.

Sinon, il est possible d'activer un utilisateur via cette procédure:

* Activer l'utilisateur avec la commande suivante ``php bin/console fos:user:activate $username``
* Changer le mot de passe avec la commande suivante ``php bin/console fos:user:change-password $username newp@ssword``

Documentation Symfony pour manipuler les utilisateurs: http://symfony.com/doc/2.0/bundles/FOSUserBundle/command_line_tools.html

### Mise en route des créneaux

Dans l'admin panel :

- Créer les *rôles* (qualifications) que les bénévoles peuvent avoir (ressource, ambassadeur, fermeture, ...)
- Créer les *postes de bénévolat* à assurer lors d'un créneau (épicerie, bureau des membres) et choisir la couleur principale d'affichage dans l'emploi du temps
- Aller dans la *semaine type* pour définir les horaires et types de créneaux
- **Créer** un créneau-type en renseignant le jour de la semaine, les heures de début et de fin et le *poste* associé au créneau
- Indiquer le rôle et le nombre de personnes avec ce rôle qui peuvent s'inscrire sur le créneau, puis cliquer sur **Ajouter**
- Pour permettre à des bénévoles sans qualification de s'inscrire, laisser le champ rôle vide
- *Sauvegarder* pour créer le créneau-type et les positions
- Quand tous les créneaux-types et postes d'une journée sont créés, il est possible de les *dupliquer* sur une autre journée avec la fonction idoine
- Une fois la semaine type créée, il faut *générer les créneaux* sur une période de temps donnée

La génération de créneaux peut être automatisée via une tâche cron.

### Cheatsheet

#### Mise à jour du modèle

* Créer une nouvelle entité: ``php bin/console doctrine:generate:entity AppBundle:EntityName``
* Générer les getters et setters d'une entité: ``php bin/console doctrine:generate:entities``
* Appliquer les mises à jours sur la base:
   * Dryrun: ``php bin/console doctrine:schema:update``
   * Voir les requêtes: ``php bin/console doctrine:schema:update --dump-sql``
   * Appliquer les changements: ``php bin/console doctrine:schema:update --force``
