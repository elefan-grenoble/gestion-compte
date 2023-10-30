# Guide du développeur

## Contribuer

* Les *Issues* servent à documenter, discuter et suivre les bugs ou idées d'améliorations
* La branche principale est `master`
* Ouvrir une *Pull Request* (PR) pour tout changement de code :
  * en essayant de les garder petites (quite à faire 2 ou 3 PR pour une grosse fonctionnalité)
  * en préférant le Français (l'application est actuellement seulement disponible dans cette langue)
  * en donnant un titre clair (il apparaitra dans le contenu de la release)
  * quand la PR est acceptée le contributeur est libre de merger
  * un *squash* est effectué au moment du merge, pour garder un historique facilement lisible

## Modèle de données

Voir la page wiki [Organisation de la base de donnée](https://github.com/elefan-grenoble/gestion-compte/wiki/Organisation-de-la-base-de-donn%C3%A9e).

## Stack technique

### Symfony

* [official doc](https://symfony.com/doc/current/index.html)

### Materialize

* [official doc](https://materializeweb.com/)

### mailcatcher

Permet de visualiser les mails envoyés en local.

[http://localhost:1080](http://localhost:1080/)

* La documentation : [mailcatcher.me](https://mailcatcher.me/)

### Docker

Un *docker-compose.yml* existe pour permettre le développement sous Docker. Suivez le [guide d'installation](install.local.md).

N'oubliez pas de définir la variable d'environnement `DEV_MODE_ENABLED` dans le container qui exécute le code de l'application.

### Nix

Vous pouvez obtenir toutes les dépendances du projet en utilisant [Nix](https://nixos.org/download.html). Une fois installé lancez `nix develop --impure` et tous les outils nécessaires sont dans votre `PATH` à la bonne version, comme déclaré dans [flake.nix](../flake.nix).
Cela peut se faire automatiquement quand vous `cd` dans le répertoire si vous avez installé [direnv](https://direnv.net/).

Pour lancer l'instance mariadb de test utilisez `devenv up`.
Pour lancer l'application, utilisez `php bin/console server:run '*:8000'`

## Tests

```shell
// créer la base de donnée de test + initialiser avec le schema
docker exec -i php php bin/console --env=test doctrine:database:create
docker exec -i php php bin/console --env=test doctrine:schema:create
// lancer les tests
docker exec -i php php ./vendor/bin/phpunit
```

## Logs

En local

```shell
// voir les 100 dernières lignes
tail -100 var/logs/dev.log
```
