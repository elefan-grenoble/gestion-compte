# Changelog

## 2023-10-27 (v1.45.6)

* Événements : Procurations : refactoring de l'envoi de l'e-mail de confirmation by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1040
* Créneaux : Rappel : refactoring de l'envoi via l'EmailingEventListener by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1044
* Répare les tests sur ShiftService by @samueleyre in https://github.com/elefan-grenoble/gestion-compte/pull/1052
* Membre (coté Admin) : petits refactoring (template pour status_icon, cacher beneficiary_count si pas pertinent) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1053
* Page "À propos" by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1048
* Badgeuse : optimisations et améliorations by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1054
* Created the first fixtures for the main tables of the application by @samueleyre in https://github.com/elefan-grenoble/gestion-compte/pull/1046
* Script pour réparer les créneaux sans poste type by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1055

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.5...v1.45.6

## 2023-10-23 (v1.45.5)

* Profile : pouvoir afficher son historique de postes fixes annulés by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1037
* Membre (coté admin) : pouvoir afficher l'historique de ses postes fixes annulés by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1038
* Admin : nouvelle page qui liste les bénéficiaires fixes sans créneau fixe by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1039
* Langue : forcer le Français en langue par défaut by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1032
* Semaine type : répare la fonction de duplication d'un jour entier by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1043

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.4...v1.45.5

## 2023-10-17 (v1.45.4)

* Compte épargne
  * répare à nouveau l'erreur de début de cycle by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1010
  * lors de la validation d'un créneau, ne pas passer l'info du créneau dans le log du compteur épargne by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1017
  * nouveau paramètre permettant au membre d'annuler son créneau même si il n'a pas suffisamment d'épargne by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1030
* Créneaux
  * réparer & améliorer le processus de pré-réservation de créneaux by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1024
  * petites améliorations de sécurité by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1029
  * réorganisé un peu le fichier by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1031
  * nouveau paramètre pour configurer le délai de pré-reservation by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1034
* Événements
  * pouvoir mettre en avant sur la page d'accueil by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1025
  * améliorations sur les procurations by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1026
  * améliorations pour les utilisateurs anonymes by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1027
  * améliorations supplémentaires sur les procurations by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1028
* Horaires d'ouverture : pouvoir configurer les messages du bandeau Ouvert / Fermé by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1011
* Admin :
  * Membre : pouvoir afficher l'historique de ses créneaux annulés by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1012
  * Filtre sur les membres : améliorer la liste des membres en retard de créneaux by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1014
  * Filtre sur les membres : améliorer la liste des membres en retard d'adhésion ou de ré-adhésion by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1019
  * Adhésion : afficher un message pour les membres sans adhésions by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1002
* Pouvoir définir le champ createdAt de façon arbitraire by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1015
* Refactoring
  * Compteur : bouger le tableau dans un template dédié by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1013
  * renommer MembershipShiftExemptionController en AdminMembershipShiftExemptionController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1022
  * renommer ShiftExemptionController en AdminShiftExemptionController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1023
* Mise à jour de la documentation d'installation by @samueleyre in https://github.com/elefan-grenoble/gestion-compte/pull/1033

## New Contributors

* @samueleyre made their first contribution in https://github.com/elefan-grenoble/gestion-compte/pull/1033

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.3...v1.45.4

## 2023-09-22 (v1.45.3)

* Compte épargne :
  * corrige l'affichage sur la badgeuse by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/991
  * ne pas incrémenter tout de suite si la coop ne valide pas les créneaux by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1001
  * répare une erreur en début de cycle by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/990
* Adhésion :
  * améliorations sur la page "Adhésion rapide" (en particulier si la coop a 1 seul bénéficiaire par membre) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/996
* Documentation :
  * mise à jour du README
  * nouvelles pages dans le Wiki

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.2...v1.45.3

## 2023-09-13 (v1.45.2)

* Membre & Profil
  * améliorations de la gestion des badges by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/978
* Badgeuse
  * mise à jour des messages lorsque le membre scan son badge by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/980
* Evénements
  * améliorer l'affichage pour les événements en cours by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/985
* Horaires d'ouverture
  * pouvoir indiquer un jour fermé by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/981
  * ajouter un badge à coté des horaires d'ouvertures actives by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/987
  * afficher un bandeau ouvert/fermé en fonction des données by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/886
* Fermetures exceptionnelles
  * améliorer l'affichage pour les fermetures en cours by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/986

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.1...v1.45.2

## 2023-09-02 (v1.45.1)

* Gestion des utilisateurs
  * Profile : afficher la section Adhésion by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/972
  * Admin : nouvelle section "informations diverses" by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/971
  * Admin : améliorer la section "Notes" by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/973
  * Admin : améliorations de l'affichage des créneaux by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/975
* Evénements
  * pouvoir ajouter des liens dans le widget by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/960
* Horaires d'ouverture
  * pouvoir définir le type d'horaire by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/964
* Fermetures exceptionnelles
  * pouvoir générer un widget by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/967
  * séparer index & list by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/976
  * autoriser la suppression des fermetures futures by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/977
* Période
  * améliorer encore l'affichage lorsque cycle_type n'est pas défini by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/961
* Exemption
  * afficher un badge dans la card utilisateur by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/969
* Tech
  * Refactoring : séparer OpeningHourController et AdminOpeningHourController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/959
  * Refactoring : renommer EventKindController en AdminEventKindController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/962
  * Refactoring : renommer ClosingExceptionController en AdminClosingExceptionController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/966
  * Materialize CSS : traduction du datepicker by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/965
  * Répare l'affichage des formfields "markdown" dans les modals by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/974

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.0...v1.45.1

## 2023-08-22 (v1.45.0)

* Gestion des utilisateurs 
  * améliorer la liste des utilisateurs admin by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/905
  * nouvelle page avec la liste des utilisateurs non-membres by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/906
  * Bénéficiaire : ne pas cacher les informations qui débordent by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/930
  *  Profile : pouvoir afficher son historique de créneaux annulés by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/862
  * Profile : améliorer la modale d'association de son badge by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/911
* Evénements
  * améliorer l'affichage du titre lorsqu'il y a une image by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/904
* Configuration
  * Admin : cacher les annulations de postes fixes si l'épicerie n'utilise pas la notion de fixe by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/939
  * Période : afficher ABCD seulement si cycle_type est défini by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/933
  * Adhésion : nouveau paramètre pour afficher/cacher le bouton 'Nouvelle adhésion' by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/929
* Horaires d'ouverture
  * pouvoir générer un widget by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/931
* Header : ouvrir les liens dans un nouvel onglet by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/932
* MaterializeCSS : améliorer l'affichage de l'autocomplete by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/910
* fix display for "Relances Créneaux" using dynamic registration date (#901) by @janssens in https://github.com/elefan-grenoble/gestion-compte/pull/913

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.7...v1.45.0

## 2023-06-28 (v1.44.7)

* Fermetures exceptionnelles
  * Nouvelle entité pour les stocker by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/889
  * Prendre en compte les fermetures exceptionnelles au moment de la génération des créneaux by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/890
  * Pouvoir donner une raison à la fermeture exceptionnelle by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/891
* Horaires d'ouverture : améliorer l'affichage des heures rondes by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/885
* Formations & Postes : ajout du champ created_by by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/892
* Créneaux : autoriser la suppression d'un bucket si use_time_log_saving by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/893
* Admin : message d'information sur la page qui liste les rôles by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/894
* Réparations de bugs : https://github.com/elefan-grenoble/gestion-compte/commit/729897e88fb74258ee6677793b168f31e1909630 , https://github.com/elefan-grenoble/gestion-compte/commit/45764b8b59f774540bf11f4de6fda6d782272c3d , https://github.com/elefan-grenoble/gestion-compte/commit/bcdccc03528cd5185560e427d9ae4d445ca5a829

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.6...v1.44.7

## 2023-06-09 (v1.44.6)

* Evénements
  * Page détail by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/876
  * Permettre aux ROLE_PROCESS_MANAGER de les créer & modifier by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/880
  * Nouveau champ pour indiquer le lieu by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/882
  * Rendre le champ description facultatif by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/883
  * Améliorations sur la création de widgets by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/874
* Refactoring
  * Séparer EventController & AdminEventController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/875
  * Séparer PeriodController & AdminPeriodController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/878
* Admin
  * Nouveau filtre des membres par "a un créneau fixe" by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/872
  * Nouveau filtre des membres par "s'est inscrit à un créneau" by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/873
* Widget : enlever les marges by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/877
* Fix days format in shift renewal email by @phfroidmont in https://github.com/elefan-grenoble/gestion-compte/pull/863

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.5...v1.44.6

## 2023-05-20 (v1.44.5)

Home
* Nouveau contenu dynamique au bas de la page d'accueil by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/870
* Homogénéiser le wording (utiliser Ton au lieu de Mon) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/868

E-mails
* Nouveau contenu dynamique pour l'email de pré-adhésion by @phfroidmont in https://github.com/elefan-grenoble/gestion-compte/pull/854

Evénements
* Rendre la liste publique au membres by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/855

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.4...v1.44.5
