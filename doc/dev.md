# Guide du développeur

## Modèle de données

![modele V2](https://yuml.me/66888c7d.png)
http://yuml.me/edit/66888c7d

## mailcatcher

Pour récupérer les mails envoyés (mode DEV)

* [mailcatcher.me](https://mailcatcher.me/)

```shell
sudo apt-get install ruby-dev libsqlite3-dev
gem install mailcatcher
mailcatcher
```

Si la dernière commande ne marche pas, vérifiez que vous avez le dossier des gem Ruby dans votre `PATH`. Plus de détails [ici](https://guides.rubygems.org/faqs/#user-install).

## Guides lines

* [GitFlow](https://www.grafikart.fr/formations/git/git-flow)

## Symfony

* [official doc](https://symfony.com/doc/current/index.html)

## Materialize

* [official doc](https://materializecss.com/)

## Docker

Un _docker-compose.yml_ existe pour permettre le développement sous Docker. Suivez le [guide d'installation](install.md).

N'oubliez pas de définir la variable d'environnement `DEV_MODE_ENABLED` dans le container qui exécute le code de l'application.
