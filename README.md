Espace adhérent super marché coopératifs
========================

Bonjour,
Ceci est le code source d'une application symfony pour la gestion d'une épicerie ou d'un super marché
coopératif.

Ce code est à l'initiative de [l'éléfan](https://lelefan.org/), projet grenoblois de super marché coopératif.

![home](doc/images/homepage_25102018.png)

Il est open source sous licence LGPL?

# Liste des fonctionnalités 
travail en cours

## Fonctions "Admin"
(some features in video (18 sept 2018))
[![Some features demo](http://img.youtube.com/vi/7rKr5UjAI-w/0.jpg)](https://www.youtube.com/watch?v=7rKr5UjAI-w "admin demo")
* Gestion des membres
    * Inscriptions "rapides" (email + paiement) pour les événements et réunion d'info [WIP]
    * Inscriptions complètes
    * Recherche utilisateur rapide via le header du site (ROLE_ADMIN)
    * Recherche utilisateur compléte (filtre) via une page dédiée (ROLE_USER_MANAGER)
    * Login As (ROLE_ADMIN)
    * Export csv des emails des résultats filtrés
    * Envoi de mail via résultats filtrés
    * Envoi de mail de masse via command line (pour newsletter)
    * Bénéficiaires
        * Ajout d'un bénéficiaire à un compte membre (ROLE_USER_MANAGER)
        * Suppression d'un bénéficiaire
        * Editer un bénéficiaire (infos, commisions, formations)
        * écrire éditer supprimer une note sur un bénéficiaire
    * Gestion des paiements (ROLE_FINANCE_MANAGER)
        * liste des adhésions / ré-adhésions
        * liste des paiement helloasso via api
        * Corrections manuel du lien helloasso <=> App
    * Badge
        * générer les badges
        * imprimer les badges
    * Liste des adhésions en retard pour appel téléphonique
    * Espace "post it" pour bureau des membres
* Gestion des créneaux
    * Formation
        * créer une formation        
        * éditer une formation
        * supprimer une formation
    * Job
        * créer un job (poste de travail)        
        * éditer une job
        * supprimer une job
    * Semaine type
        * créer des créneau (lié à un job)
        * définir les créneau (nb personne, formations necessaires)
    * Dupliquer un jour type
    * Générer manuellement une période donnée
    * Inscrire / libérer un membre pour un créneau
    * voir le calendrier (En cours, passé et futur)
    * Log individuel de temps [TODO manually add custom log]
* Gestion associative
    * Evénements
        * Créer des événement (date, desc, photo) pour AG, ou autre rencontre
        * Modifier des événements
        * Editer la liste d'émargement avec procurations
    * Taches
        * créer une tache
        * éditer une tache
        * supprimer une tache
    * Commission
        * créer une Commission
        * éditer une Commission
        * supprimer une Commission
        * nommer, changer un référent
* Divers
    * Services Oauth2
        * Créer des services externes Oauth2 pour les membres (Wiki, Mattermost, NextCloud)
        * Supprimer des services externes
        * rendre publique / privé un service

## Fonctions "Membre"
* Usages
    * Se connecter pour la toute première fois avec son numéro d'adhérent ou son prénom
    * Voir et modifier ces informations
    * Se connecter à l'aide du QR sur le badge
    * Se connecter à un service tiers avec le Oauth2 (Wiki, Mattermoser, NextCloud, ...)
    [![OAuth2 demo](http://img.youtube.com/vi/sghxx1VqIp4/0.jpg)](https://www.youtube.com/watch?v=sghxx1VqIp4 "OAuth 2 demo")
* Gestion associative
    * Activer son badge
    * Réadhérer via HelloAsso (API)
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
    * contacter les autres membres de son créneau
    
## Fonctions logged out
* Visualisation anonyme du "planning" des prochains jours
* Scan de carte membre avec scannette sur l'écran d'accueil (info sur a jour, en retard, ...)

# Projet

* Suivre [sur github](https://github.com/elefan-grenoble/gestion-compte/projects/2) 

# Installation

* Suivez le [guide d'installation](doc/install.md)

# Initialisation

* Suivez le [guide de mise en route](doc/start.md)

# Developpements

* [Developer Guide](doc/dev.md)
