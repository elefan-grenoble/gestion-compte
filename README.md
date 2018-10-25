Espace adhérent super marché coopératifs
========================

Bonjour,
Ceci est le code source d'une application symfony pour la gestion d'une épicerie ou d'un super marché
coopératif.

Ce code est à l'initiative de [l'éléfan](https://lelefan.org/), projet Grenoblois de Super Marché coopératif.

Il est open source.

# Liste des fonctionnalitées

## Fonction "Admin"
* Gestion des membres
    * Inscriptions "rapides" (email + paiement) pour les événements et réunion d'info [WIP]
    * Inscriptions complètes
    * Recherche rapide via le header du site pour la ROLE_ADMIN
    * Recherche compléte via une page dédiée (ROLE_ADMIN,ROLE_USER_MANAGER)
    * Bénéficiaires
        * Ajout d'un bénéficiaire à un compte membre (ROLE_ADMIN,ROLE_USER_MANAGER)
        * Suppression d'un bénéficiaire
* Gestion des créneaux
* Gestion des instances
* Gestion associative
    * Evénements
        * Créer des événement (date, desc, photo) pour AG, ou autre rencontre
        * Modifier des événement
        * Editer la liste d'émargement avec procurations
    * Taches
* Divers
    * Services Oauth2
        * Créer des services externes Oauth2 pour les membres (Wiki, Mattermost, NextCloud)
        * Supprimer des services externes
        * rendre publique / privé un service

## Fonctions "Membre"
* Usages
    * Se connecter pour la toute première fois avec son numéro d'adhérent ou son prénom
    * Voir et modifier ces informations
    * Se connecter à un service tiers avec le Oauth2 (Wiki, Mattermoser, NextCloud, ...)
* Gestion associative
    * Evénements
        * Voir les prochains événement
        * Faire une procuration pour un événement à venir (je suis absent)
            * procuration anonyme (à qui veux bien la prendre)
            * procuration nominative
        * Accepter une procuration anonyme pour un événement à venir
    * Créer une taches si je fait parti d'une comission
    * Ajouter et retirer des membres si je suis référent d'une commission.
* Créneaux
    * Choisir un créneau et le reserver
    * voir les créneaux réservés
    * se désangager d'un créneau réservé
    * reprendre un créneau pour lequel on s'est désangagé
    
## Fonctions "logout out"
* Visualisation anonyme du "planning" des prochains jours
* Scan de carte membre avec scannette sur l'écran d'accueil (a jour, en retard, ...)

# Installation

* Suivez le [guide d'installation](doc/install.md)

# Initialisation

* Suivez le [guide de mise en route](doc/start.md)

# Developpements

* [Developer Guide](doc/dev.md)
