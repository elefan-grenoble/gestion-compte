# Cheatsheet Symfony

## Mise à jour du modèle

* Créer une nouvelle entité: ``php bin/console doctrine:generate:entity AppBundle:EntityName``
* Générer les getters et setters d'une entité: ``php bin/console doctrine:generate:entities``
* Appliquer les mises à jours sur la base:
   * Dryrun: ``php bin/console doctrine:schema:update``
   * Voir les requêtes: ``php bin/console doctrine:schema:update --dump-sql``
   * Appliquer les changements: ``php bin/console doctrine:schema:update --force``