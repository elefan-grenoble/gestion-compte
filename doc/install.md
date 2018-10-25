### Prérequis

* PHP (version 7+) installé
* [Composer](https://getcomposer.org/) installé
* Mysql installé et configuré (ou mariadb)
* php-mysql (ou php-pdo_mysql)
* php-xml
* php-gd
* Créer une base de donnée pour le projet

### Installation

* Create a mysql database ``mysql -e "CREATE DATABASE my_db_name;"``
* ``git clone https://github.com/elefan-grenoble/gestion-compte.git``
* ``cd gestion-compte``
* ``composer install`` (utiliser le nom de la base précédemment créée)
* ``bin/console doctrine:schema:create``
* add ``127.0.0.1 membres.lelefan.local`` to your _/etc/hosts_ file (/!\important, le login ne fonctionnera pas sinon)
* ``php bin/console server:start``
* visit http://membres.lelefan.local/user/install_admin to create the super admin user (babar:password)

#### En Prod
Pour nginx, ligne necessaire pour ne pas avoir les images dynamiques de qr et barecode en 404 
<pre>location ~* ^/sw/(.*)/(qr|br)\.png$ {
		rewrite ^/sw/(.*)/(qr|br)\.png$ /app.php/sw/$1/$2.png last;
	}
</pre>

### Installation de mailcatcher, pour récupérer les mails envoyés (mode DEV)

* https://mailcatcher.me/
* sudo apt-get install unzip ruby-full build-essential
* unzip mailcatcher-master.zip
* sudo gem install mailcatcher
* mailcatcher

## crontab

<pre>
#generate shifts in 27 days (same weekday as yesterday)
55 5 * * * php YOUR_INSTALL_DIR_ABSOLUTE_PATH/bin/console app:shift:generate $(date -d "+27 days" +\%Y-\%m-\%d)
#free pre-booked shifts
55 5 * * * php YOUR_INSTALL_DIR_ABSOLUT_PATH/bin/console app:shift:free $(date -d "+21 days" +\%Y-\%m-\%d)
#send reminder 2 days before shift
0 6 * * * php YOUR_INSTALL_DIR_ABSOLUT_PATH/bin/console app:shift:reminder $(date -d "+2 days" +\%Y-\%m-\%d)
#execute routine for cycle_end/cycle_start, everyday
5 6 * * * php YOUR_INSTALL_DIR_ABSOLUT_PATH/bin/console app:user:cycle_start
</pre>

## mise en route

* Suivez le [guide de mise en route](start.md)