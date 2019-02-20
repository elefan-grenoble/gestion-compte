# Always up-to-date !

## Etapes d'exécution d'une mise à jour

1. Téléchargez la dernière version de l'application via https://github.com/elefan-grenoble

3. Déplacez l'intégralité du code téléchargé sur votre serveur afin d'écraser les anciens fichiers
     
3. Connectez-vous en ligne de commande sur votre serveur et exécuter les lignes de commandes suivantes :
<pre>
# Déplacement à la racine du projet
cd /var/www/html/gestion-compte 

# Exécution de l'installation des dépendances
composer install

# Création du cache de production
php bin/console cache:clear --env=prod

# Mise à jour de la base de données
php bin/console doctrine:migrations:migrate
</pre>

### Votre application est maintenant à jour 

