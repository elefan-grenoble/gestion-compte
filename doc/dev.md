# Guide du développeur

## Modèle de données

![modele V2](https://yuml.me/66888c7d.png)
http://yuml.me/edit/66888c7d

## mailcatcher

Pour récupérer les mails envoyés (mode DEV)

* [mailcatcher.me](https://mailcatcher.me/)
<pre>sudo apt-get install ruby-full build-essential ruby-sqlite3</pre>
<pre>sudo gem install mailcatcher</pre>
<pre>mailcatcher</pre>

## Guides lines
* [GitFlow](https://www.grafikart.fr/formations/git/git-flow)

## Symfony
* [official doc](https://symfony.com/doc/current/index.html)

## Materialize
* [official doc](https://materializecss.com/)

## Docker

Si vous souhaitez développer en utilisant Docker, n'oubliez pas de définir
la variable d'environnement `DEV_MODE_ENABLED` dans le container qui exécute
le code de l'application.