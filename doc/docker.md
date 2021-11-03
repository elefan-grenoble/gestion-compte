# Elefan sous Docker

## Build

<pre>
cd build
docker-compose build
docker-compose push
</pre>

Pour builder une branch spécifique :
<pre>
export GIT_BRANCH=v1.26.1
docker-compose build --build-arg GIT_BRANCH=$GIT_BRANCH http php
docker-compose push http php
</pre>

## Dev

Cloner le code du projet :
<pre>
git clone --branch  scopeli-from-1.26.1-dev git@github.com:Scopeli44/gestion-compte.git elefan
cd elefan/
</pre>


Verifier que les fichiers du répertoire docker/dev à la racine du projet sont bien present sous forme de liens symboliques
(afin d'avoir composer et php dans les bonnes version sous Docker)
Sinon :
<pre>
ln -s docker/dev/* ./
</pre>

Lancer la configuration (ignorer les paramètres à configurer, ceux ci seront définis par les variables d'environnement docker dans le fichier ./docker-compose.yml) :
<pre>
./composer install
</pre>

Modifier définir certains répertoire et modifier les droits

Lancer la création de la base de données :
<pre>
docker-compose up -d mysql
</pre>
Attendre que mysql se soit lancé (pour suivre l'avancement "docker-compose logs -f mysql" et attendre la ligne : "[Note] mysqld: ready for connections."), puis :
<pre>
./php bin/console doctrine:migration:migrate
</pre>

Il y a des répertoires qui doivent être accessibles en écriture par le container docker (utilisateur www-data) :
<pre>
sudo chown -R :33 var/cache
sudo chown -R :33 var/logs
sudo chown -R :33 var/sessions
sudo chmod -R g+w var/
</pre>

exporter les assets
<pre>
./php bin/console assetic:dump
</pre>

Lancer le serveur web :
<pre>
docker-compose up http
</pre>

Créer une redirection local (127.0.0.1 -> elefan)
Aller sur http://elefan/user/install_admin pour créer l'utilisateur super admin (valeurs par défaut : admin:password)
