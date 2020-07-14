# Elefan sous Docker

## Build

<pre>
cd build
docker-compose build
docker-compose push
</pre>

Pour builder une branch spécifique :
<pre>
export GIT_BRANCH=v1.19
docker-compose build --build-arg GIT_BRANCH=$GIT_BRANCH http php
docker-compose push http php
</pre>

## Dev

Cloner le code du projet :
<pre>
git clone --branch v1.26.1 git@github.com:Scopeli44/gestion-compte.git elefan
cd elefan/
</pre>

Cloner dépôt docker du projet :
<pre>
git clone git@gitlab.com:scopeli44/elefan-docker.git docker
</pre>

Lier les fichiers du répertoire docker/dev à la racine du projet (afin d'avoir composer et php dans les bonnes version sous Docker) :
<pre>
ln -s docker/dev/* ./
</pre>

Copier les fichiers :
<pre>
cp docker/build/php/config_prod.yml app/config/
cp docker/build/php/parameters.yml.dist app/config/
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

Aller sur http://elefan/user/install_admin pour créer l'utilisateur super admin (valeurs par défaut : admin:password)
