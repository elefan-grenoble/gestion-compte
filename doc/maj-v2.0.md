

# Mettre à jour à la version 2.0

La version 2.0 embarque la mise à jour du framework Symfony dans sa version 4 (anciennement version 3).


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

