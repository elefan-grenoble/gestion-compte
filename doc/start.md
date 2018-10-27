# Initialisation

## Créer un utilisateur

* Se connecter avec l'utilisateur super admin babar/password
* Créer un utilisateur

### Activer l'utilisateur

L'activation passe par un envoi de mail. En dev, il est conseillé d'installer [un mail catcher](./dev.md#mailcatcher) pour pouvoir faire fonctionner l'envoi de mail en local.

Sinon, il est possible d'activer un utilisateur via cette procédure:

* Activer l'utilisateur avec la commande suivante ``php bin/console fos:user:activate $username``
* Changer le mot de passe avec la commande suivante ``php bin/console fos:user:change-password $username newp@ssword``

Documentation Symfony pour manipuler les utilisateurs: http://symfony.com/doc/2.0/bundles/FOSUserBundle/command_line_tools.html

## Mise en route des créneaux

Dans l'admin panel :

- Créer les *formations* (qualifications) que les bénévoles peuvent avoir (ressource, ambassadeur, fermeture, ...)
- Créer les *postes de bénévolat* à assurer lors d'un créneau (épicerie, bureau des membres) et choisir la couleur principale d'affichage dans l'emploi du temps
- Aller dans la *semaine type* pour définir les horaires et types de créneaux
- **Créer** un créneau-type en renseignant le jour de la semaine, les heures de début et de fin et le *poste* associé au créneau
- Indiquer la formation et le nombre de personnes avec cette formation qui peuvent s'inscrire sur le créneau, puis cliquer sur **Ajouter**
- Pour permettre à des bénévoles sans qualification de s'inscrire, laisser le champ formation vide
- *Sauvegarder* pour créer le créneau-type et les positions
- Quand tous les créneaux-types et postes d'une journée sont créés, il est possible de les *dupliquer* sur une autre journée avec la fonction idoine
- Une fois la semaine type créée, il faut *générer les créneaux* sur une période de temps donnée

La génération de créneaux peut être automatisée via une [tâche cron](install.md#crontab).
