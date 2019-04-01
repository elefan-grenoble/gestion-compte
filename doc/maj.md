# Always up-to-date !

## Etapes d'exécution d'une mise à jour

### vous avez installé avec git

1. Déplacement à la racine du projet

```cd /var/www/html/gestion-compte```

2. Récupérer la dernière version

```git pull```

3. Servez vous à boire.

### vous n'avez pas installé avec git (ex via ftp)

1. Téléchargez la dernière version de l'application via https://github.com/elefan-grenoble
2. Déplacez l'intégralité du code téléchargé sur votre serveur afin d'écraser les anciens fichiers
3. Connectez-vous en ligne de commande sur votre serveur et déplacez vous dans le dossier

```cd /var/www/html/gestion-compte```

### suite : les étapes communes
4. Exécution de l'installation des dépendances

```composer install```

5. Mise à jour de la base de données

```php bin/console doctrine:migrations:migrate```

6. Installer les nouveaux media

```php bin/console assetic:dump```

7. Vider le cache de production

```php bin/console cache:clear --env=prod```

**Voilà ! Votre application est maintenant à jour**

