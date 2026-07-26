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

## Installation

Voir le [guide d'installation](install.local.md).

## Modèle de données

Voir la page
wiki [Organisation de la base de donnée](https://github.com/elefan-grenoble/gestion-compte/wiki/Organisation-de-la-base-de-donn%C3%A9e).

## Stack technique

### Symfony

* [official doc](https://symfony.com/doc/current/index.html)

### Materialize

* [official doc](https://materializeweb.com/)

### mailcatcher

Permet de visualiser les mails envoyés en local.

[http://localhost:1080](http://localhost:1080/)

* La documentation : [mailcatcher.me](https://mailcatcher.me/)

## Appliquer les règles de codestyle

Des règles [php-cs-fixer](https://cs.symfony.com/) sont définies sur le projet. Pour les appliquer, il faut lancer la
commande

```shell
docker compose exec php ./vendor/bin/php-cs-fixer fix
```

Il est aussi possible de configurer PhpStorm pour utiliser php-cs-fixer comme outils de
formattage : https://www.jetbrains.com/help/phpstorm/using-php-cs-fixer.html
Une extension existe aussi pour vscode : https://marketplace.visualstudio.com/items?itemName=junstyle.php-cs-fixer

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
