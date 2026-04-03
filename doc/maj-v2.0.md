

# Mettre à jour à la version 2.0

La version 2.0 embarque la mise à jour du framework Symfony dans sa version 4 (anciennement version 3).

⚠️ Avant de commencer, il est fortement recommandé de faire une sauvegarde complète de votre base de données et de votre fichier `parameters.yml`.


## Fichier de paramètres

Auparavant, vos paramètres se trouvaient dans un fichier `parameters.yml`.

À partir de la version 2.0 (Symfony 4), ce fichier s'appelle `.env` et se présente légèrement différemment.

Process :
1. copiez le fichier [`.env.dist`](https://github.com/elefan-grenoble/gestion-compte/blob/master/.env.dist) et renommez-le `.env`
2. pour chacune des variables ce nouveau fichier `.env` :
  - consultez votre fichier `parameters.yml` et trouvez cette variable (même nom, en minuscules)
  - reportez la valeur de l'ancienne variable, si la nouvelle n'a pas encore la bonne valeur


### Configuration des mails

La nouvelle variable de configuration `MAILER_DSN` n'existait pas dans la version 1.47.2.

Pour continuer à envoyer les mails en SMTP, il faut définir `MAILER_DSN` avec la valeur suivante :

```env
MAILER_DSN=smtp://${mailer_user}:${mailer_password}@${mailer_host}:${mailer_port}
```

(`${mailer_user}`, `${mailer_password}`, `${mailer_host}`, `${mailer_port}` sont les noms des anciennes configurations dans `parameters.yml`.)


### Cas particulier des variables de configuration avec une valeur `null`

Il est possible que votre fichier `parameters.yml` contienne des variables définies à `null`, par exemple :

```yml
time_log_saving_shift_free_min_time_in_advance_days: null
```

Dans le nouveau fichier `.env`, définir une variable à `null` se fait en omettant de définir la ligne.

Vous pouvez commenter ou supprimer la ligne qui porte cette variable dans votre fichier `.env` :

```env
# TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS=null
```

Les variables concernées sont les suivantes :
- `TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE`
- `MAX_TIME_IN_ADVANCE_TO_BOOK_EXTRA_SHIFTS`
- `HELLOASSO_*`
- `IGLOOHOME_*`


## Point d'entrée de l'application (nginx)

Le point d'entrée PHP a changé dans Symfony 4. La configuration nginx doit être mise à jour en conséquence :

| Version           | Point d'entrée     |
|-------------------|--------------------|
| 1.x (Symfony 3.4) | `web/app.php`      |
| 2.0 (Symfony 4.4) | `public/index.php` |

Penser à mettre à jour le `root` et le `fastcgi_param SCRIPT_FILENAME` dans la configuration nginx.

La modification du fichier de configuration nginx pourra donc ressembler à ceci :

```diff
-    root   /elefan/public/;
+    root   /elefan/web/;

    location / {
-        index  index.php;
+        index  app.php;
-        try_files $uri /index.php$is_args$args;
+        try_files $uri /app.php$is_args$args;
    }
```

## Stratégie de migration recommandée

Les versions 1.47 et 2.0 partagent le même schéma de base de données, ce qui permet de revenir en 1.47 sans manipulation
de BDD si nécessaire.

Il est donc recommandé de procéder ainsi :

1. Installer la version 2.0 dans un répertoire séparé (par exemple `/var/www/gestion-compte-sf4`).
2. Configurer le fichier `.env` de cette nouvelle installation. Suivre
   les [instructions d'installation](./install.serveur.md#installation) **sans utiliser les commandes qui modifient la base de
   données**
3. Créer une nouvelle configuration nginx pointant vers ce répertoire.
4. Changer le lien symbolique dans `/etc/nginx/sites-enabled` pour pointer sur la nouvelle configuration

```shell
sudo ln -sf /etc/nginx/sites-available/membres-sf4 /etc/nginx/sites-enabled/membres
sudo systemctl reload nginx
```

Cette approche permet de basculer (et revenir en arrière) sans interruption de service.
