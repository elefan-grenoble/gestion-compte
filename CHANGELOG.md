# Changelog

## [v1.45.7](https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.6...v1.45.7) (2023-11-17)

* Créneaux : Alertes : refactoring de l'envoi via l'EmailingEventListener & MattermostEventListener by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1057
* Membre : pouvoir rajouter la notion de volant by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1058
* Membre (coté admin) : améliore la fermeture / ré-ouverture de compte by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1062
* Membre (coté admin) : pouvoir modifier le statut fixe / volant by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1061
* Admin : petites améliorations sur la page filtre des ambassadeurs by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1063
* Membre fixe/volant : afficher des messages pour les cas particuliers by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/999
* Test fonctionnel : import des users par CSV en ligne de commande by @samueleyre in https://github.com/elefan-grenoble/gestion-compte/pull/1059
* Améliorer la commande d'anonymisation de la base de données by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1066
* Mise à jour de la documentation : git, maj by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1047
* Générer le fichier CHANGELOG.md by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1069
* Ajout de set_locale pour les commandes by @petitalb in https://github.com/elefan-grenoble/gestion-compte/pull/1068
* Correction de la dépendance des fixtures en prod by @samueleyre in https://github.com/elefan-grenoble/gestion-compte/pull/1071

## [v1.45.6](https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.5...v1.45.6) (2023-10-27)

* Événements : Procurations : refactoring de l'envoi de l'e-mail de confirmation by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1040
* Créneaux : Rappel : refactoring de l'envoi via l'EmailingEventListener by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1044
* Répare les tests sur ShiftService by @samueleyre in https://github.com/elefan-grenoble/gestion-compte/pull/1052
* Membre (coté Admin) : petits refactoring (template pour status_icon, cacher beneficiary_count si pas pertinent) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1053
* Page "À propos" by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1048
* Badgeuse : optimisations et améliorations by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1054
* Created the first fixtures for the main tables of the application by @samueleyre in https://github.com/elefan-grenoble/gestion-compte/pull/1046
* Script pour réparer les créneaux sans poste type by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1055

## [v1.45.5](https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.4...v1.45.5) (2023-10-23)

* Profile : pouvoir afficher son historique de postes fixes annulés by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1037
* Membre (coté admin) : pouvoir afficher l'historique de ses postes fixes annulés by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1038
* Admin : nouvelle page qui liste les bénéficiaires fixes sans créneau fixe by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1039
* Langue : forcer le Français en langue par défaut by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1032
* Semaine type : répare la fonction de duplication d'un jour entier by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1043

## [v1.45.4](https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.3...v1.45.4) (2023-10-17)

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

### New Contributors

* @samueleyre made their first contribution in https://github.com/elefan-grenoble/gestion-compte/pull/1033

## [v1.45.3](https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.2...v1.45.3) (2023-09-22)

* Compte épargne :
  * corrige l'affichage sur la badgeuse by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/991
  * ne pas incrémenter tout de suite si la coop ne valide pas les créneaux by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/1001
  * répare une erreur en début de cycle by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/990
* Adhésion :
  * améliorations sur la page "Adhésion rapide" (en particulier si la coop a 1 seul bénéficiaire par membre) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/996
* Documentation :
  * mise à jour du README
  * nouvelles pages dans le Wiki

## [v1.45.2](https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.1...v1.45.2) (2023-09-13)

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

## [v1.45.1](https://github.com/elefan-grenoble/gestion-compte/compare/v1.45.0...v1.45.1) (2023-09-02)

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

## [v1.45.0](https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.7...v1.45.0) (2023-08-22)

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

## 2023-05-18 (v1.44.4)

* Admin
  * Compteur temps : utiliser la méthode DELETE lors de la suppression by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/856
  * Membre : modale de confirmation lors de la suppression d'un compte by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/857
  * PeriodPosition : popup de confirmation lors de la libération d'un poste type by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/861
* Affichage des dates & heures
  * Créneaux : homogénéiser l'affichage de l'intitulé by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/858
  * Cleanup : finaliser l'homogénéisation de l'affichage des dates & heures by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/859
* Logs
  * Semaine type : nouvelle entité PeriodPositionFreeLog pour stocker l'historique des créneaux fixes by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/840
  * Créneaux : nouveau champ ShiftFreeLog.shiftString by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/860

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.3...v1.44.4

## 2023-05-14 (v1.44.3)

* Evénements
  * pouvoir créer des widgets by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/837
* Cleanup
  * Refactoring : déplacer Widget dans ShiftController (& JobController) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/836
  * Semaine type : améliorations d'affichage by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/842
* Optimisations des requêtes SQL (1/4) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/845
  * Semaine type : optimisations des requêtes SQL by @petitalb in https://github.com/elefan-grenoble/gestion-compte/pull/851
  * Créneaux : optimisation des requêtes SQL by @petitalb in https://github.com/elefan-grenoble/gestion-compte/pull/849
  * Page admin d'un membre : optimisation des requêtes SQL by @petitalb in https://github.com/elefan-grenoble/gestion-compte/pull/850

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.2...v1.44.3

## 2023-05-05 (v1.44.2)

* Pouvoir gérer une liste d'horaires d'ouvertures by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/830
* Home : déplacer 'Mon compte' sous 'Mon bénévolat' by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/831
* Événements
  * savoir qui a créé et mis à jour by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/835
* Admin
  * Semaine type : réorganisation de la page de modification d'un créneau type by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/839
  * Créneaux : pouvoir supprimer un bucket seulement si tous les créneaux sont libres by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/838
* Logs
  * Semaine type : savoir qui a créé et mis à jour les créneaux / postes type by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/841

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.1...v1.44.2

## 2023-04-23 (v1.44.1)

* Cleanup : homogénéiser la pagination by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/825
* Créneau fixe : afficher le nombre de créneaux effectués by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/808
* Compteur épargne : fin de cycle by @petitalb in https://github.com/elefan-grenoble/gestion-compte/pull/783
* E-mail de confirmation lors de la réservation d'un créneau by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/827
* E-mail de confirmation lors de la libération d'un créneau by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/829

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.44.0...v1.44.1

## 2023-04-19 (v1.44.0)

* Événements
  * Refondu la liste by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/820
  * Nouveau champ 'end' by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/821
  * Nouvelle entité pour définir des types d'événements by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/822
  * Pouvoir définir le type d'événement by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/823
  * Séparer index & liste by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/824
  * Ajout de la pagination sur la page liste by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/826

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.43.5...v1.44.0

## 2023-04-15 (v1.43.5)

* Refactoring : création d'un WidgetController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/810
* Compte épargne : répare la logique de validation   by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/816
* Cleanup : améliorer l'affichage des adhésions by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/815
* Cleanup : améliorer les urls de ServiceController & FormationController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/814
* Cleanup : améliorer les urls de JobController & ClientController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/813
* Cleanup : améliorer les urls de BeneficiaryController & MembershipController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/812
* Refactoring : déplacer le formulaire de contact créneau dans ShiftController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/811
* Admin : nouveau paramètre qui permet d'empêcher les membres de réserver leur propre créneau by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/805
* Afficher les réseaux sociaux dans le footer by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/809

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.43.4...v1.43.5

## 2023-04-05 (v1.43.4)

* Créneau : nouveau champ Shift.createdBy by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/799
* Admin : petit template pour afficher un lien vers le bénéficiaire si il existe by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/800
* Pouvoir gérer une liste de réseaux sociaux by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/803

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.43.3...v1.43.4

## 2023-04-05 (v1.43.3)

* Admin
  * Admin : nouveau paramètre qui permet d'empêcher les membres d'annuler leur propre créneau by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/796
  * Admin : nouveau paramètre qui permet d'empêcher les membres de (in)valider leur propre créneau by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/801
* Logs
  * Semaine type : ajout des champs createdAt by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/794
* Bugfix
  * Semaine type : répare l'url de submit du filtre sur la version anonyme by @symartin in https://github.com/elefan-grenoble/gestion-compte/pull/804

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.43.2...v1.43.3

## 2023-03-27 (v1.43.2)

* Ajout d'une version annonyme de la semaine type (V2) by @symartin in https://github.com/elefan-grenoble/gestion-compte/pull/791
* Compte épargne : nouveau paramètre pour définir un délais minimal pour annuler by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/787
* Créneau fixe : nouveau paramètre pour autoriser l'annulation de créneaux fixes by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/788
* Compte épargne : règles d'utilisation lorsqu'un créneau est libéré by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/793
* Compte épargne : homogénéiser les droits d'annulation d'un créneau (entre membre & admin) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/792

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.43.1...v1.43.2

## 2023-03-15 (v1.43.1)

* Parameter to disable place IP check by @phfroidmont in https://github.com/elefan-grenoble/gestion-compte/pull/778
* Less restrictive constraints on zip code by @phfroidmont in https://github.com/elefan-grenoble/gestion-compte/pull/779
* Homogénéiser l'affichage des dates by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/777
* Membre : nouveau filtre pour trouver les membres sans adhésion by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/789
* Admin : nouvelle page qui liste les membres sans adhésion by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/790

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.43.0...v1.43.1

## 2023-03-02 (v1.43.0)

* Compte épargne : initialisation by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/767
* Compte épargne : incrémenter après un créneau "extra" validé by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/768
* Compte épargne : mettre à jour le filtre par compteur by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/770
* Compte épargne : décrémenter après un créneau libéré by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/771
* Compte épargne : affichage basique by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/769

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.42.5...v1.43.0

## 2023-02-26 (v1.42.5)

* Reproducible dev environment by @phfroidmont in https://github.com/elefan-grenoble/gestion-compte/pull/762
* Roles : restreindre la suppression de créneaux (et de bucket) aux ADMIN by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/765
* Roles : restreindre la suppression de poste type aux ADMIN by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/766

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.42.4...v1.42.5

## 2023-02-22 (v1.42.4)

* Améliore la mise en avant des nouveaux membres by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/742
* Fix : filtre par dernière adhésion sur la liste des membres by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/744
* Admin : ajout de liens vers les membership by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/743
* Home : remonter l'info d'exemption, créer un template dédié by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/745
* Fix member registration by @phfroidmont in https://github.com/elefan-grenoble/gestion-compte/pull/756
* Semaine type : fix sur le filtre par type de créneau by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/763
* Filtre sur les membres : disable certains champs coté Ambassadeur by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/754
* Home : séparer les template anonyme & connecté by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/746
* Home : cleanup du template by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/747
* Home : section dédié à la (ré)adhésion by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/750
* Planning : afficher tous les créneaux à venir (et non seulement +7 jours) by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/760
* Evenement : nouvelle fonction findFutures dans le Repository by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/764

### New Contributors
* @phfroidmont made their first contribution in https://github.com/elefan-grenoble/gestion-compte/pull/756

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.42.3...v1.42.4

## 2023-02-06 (v1.42.3)

* Shift : fix pouvoir annuler son créneau by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/733
* TimeLog : ne pas toujours supprimer lors de onShiftInvalidated by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/723
* TimeLog : ajouter le beneficiary au ShiftDeletedEvent by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/731
* Accueil : ordonner les créneaux by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/734
* Nouvelle fonction filterBucketsByDayAndJobByFilling() by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/735
* Nouveau controller CardReaderController by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/736
* Twig : cleanup de certaines variables dans les include by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/740
* Config : ajouter les différents états d'un membre dans les paramètres by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/732
* Répare l'affichage des membres en retard d'adhésion by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/741
* Exemption : nouveau filtre par membre by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/739
* Semaine type : pouvoir filtrer par bénéficiaire by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/738

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.42.2...v1.42.3

## 2023-02-01 (v1.42.2)

* TimeLog : modifier la date lors de onShiftValidated by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/722
* Exemptions : filtre basique sur la liste des membres exemptés by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/726
* Annulations : filtre basique sur la liste des créneaux annulés by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/727
* TimeLog : nouveau champ requestRoute by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/730
* Mettre un peu en avant les membres sans adhésions by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/729

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.42.1...v1.42.2

## 2023-01-30 (v1.42.1)

* Renommer shift_dismiss en shift_free by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/721
* Renommer les urls admin de ShiftFreeLog & ShiftExemption by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/725
* Différencier les bucket vérrouillés de complets by @raphodn in https://github.com/elefan-grenoble/gestion-compte/pull/720

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.42...v1.42.1

## 2023-01-28 (v1.42.0)

### Nouveauté

* Ajout d'une entité permettant de sauvegarder l'historique d'annulation des créneaux : `ShiftFreeLog` (PR https://github.com/elefan-grenoble/gestion-compte/pull/702)
** Refactoring, utiliser un EventListener (comme `TimeLogEventListener`) (PR https://github.com/elefan-grenoble/gestion-compte/pull/714)
** Savoir si le créneau libéré était fixe ou pas (PR https://github.com/elefan-grenoble/gestion-compte/pull/724)
** Permettre à un admin de donner une raison lors de l'annulation (PR https://github.com/elefan-grenoble/gestion-compte/pull/713)

### Amélioration

* Améliorations sur les exemptions de créneaux
** Ajouter des icônes dans la liste (PR https://github.com/elefan-grenoble/gestion-compte/pull/707)
** Ajouter un filtre dans la liste des membres (PR https://github.com/elefan-grenoble/gestion-compte/pull/709)
** Remplacer isValid par isCurrent pour clarifier (PR https://github.com/elefan-grenoble/gestion-compte/pull/706)

* Refactoring
** Code entity : cleanup (PR https://github.com/elefan-grenoble/gestion-compte/pull/703)
** Shift : afficher les X derniers cycles (PR https://github.com/elefan-grenoble/gestion-compte/pull/710)
** Shift : cleanup du nom des formulaires (PR https://github.com/elefan-grenoble/gestion-compte/pull/711)
** Shift : enlever le champ 'reason' inutilisé (PR https://github.com/elefan-grenoble/gestion-compte/pull/719)
** Booking : cacher l'option "fixe" aux membres (PR https://github.com/elefan-grenoble/gestion-compte/pull/716)
** TimeLog : TimeLogService, cleanup, refactoring (PR https://github.com/elefan-grenoble/gestion-compte/pull/705)

### Bugfix

* Créneaux : fix pouvoir supprimer le dernier créneau d'un bucket (PR https://github.com/elefan-grenoble/gestion-compte/pull/708)
* Booking : fix ne pas permettre de réserver des shifts déjà pris (PR https://github.com/elefan-grenoble/gestion-compte/pull/717)
* Ré-adhésion : fix date de début (PR https://github.com/elefan-grenoble/gestion-compte/pull/701)
* Shift : fix pouvoir annuler un créneau dans certains cas bizarres (PR https://github.com/elefan-grenoble/gestion-compte/pull/712)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.41.7...v1.42

## 2023-01-05 (v1.41.7)

* Amélioration de l'adhésion by @petitalb in https://github.com/elefan-grenoble/gestion-compte/pull/700

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.41.6...v1.41.7

## 2023-01-04 (v1.41.6)

Nouveauté :
* Nouveau paramètre `max_event_proxy_per_member` pour pouvoir définir le nombre maximal de procurations par compte-membre + gestion des multi procuration si le chiffre est supérieur à 1 (PR #567)

Amélioration :
* Mieux afficher et exporter les procurations (PR #585)
* Tests : ajout de PHPStan (dépendance & CI) (PR #662)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.41.5...v1.41.6

## 2023-01-02 (v1.41.5)

Nouveauté :
* Nouvelle commande `AmbassadorShiftTimeLogCommand.php` permettant d'envoyer par e-mail la liste des bénéficiaires en retard de créneau (PR #647)

Amélioration :
* Ajouter les groups dans l'API utilisée par Nextcloud (PR #697)
* Réériture du code qui filtre l& liste des utilisateurs (présent dans plusieurs vues) (PR #694)
* Mise à jour des annotations `@Route` en vu de Symfony 4 (PR #695)

Bugfix :
* Répare un erreur lors de l'annulation de créneau (PR #696)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.41.4...v1.41.5

## 2022-12-27 (v1.41.4)

Amélioration :
* Améliore l'affichage des logs de temps (PR https://github.com/elefan-grenoble/gestion-compte/pull/692)
* Admin : mini modifs de wording pour homogénéiser (PR https://github.com/elefan-grenoble/gestion-compte/pull/691)
* Mise à jour de Materialize CSS à la v1.2.1 (PR https://github.com/elefan-grenoble/gestion-compte/pull/690)

Bugfix :
* Enlève la possibilité de se logguer avec le cookie pour accéder à un service externe (PR https://github.com/elefan-grenoble/gestion-compte/pull/668)
* Fix : seul les utilisateurs connectés peuvent accéder à self_registry (PR https://github.com/elefan-grenoble/gestion-compte/pull/693)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.41.3...v1.41.4

## 2022-12-26 (v1.41.3)

Amélioration :
* Admin > liste des membres : afficher les comptes ouvert par défaut (PR https://github.com/elefan-grenoble/gestion-compte/pull/680)
* Exemption : amélioration sur les couleurs (PR https://github.com/elefan-grenoble/gestion-compte/pull/677)
* Config : cleanup de l'utilisation du paramètre `main_color` (PR https://github.com/elefan-grenoble/gestion-compte/pull/679)

Bugfix :
* Fix : pouvoir (dé)geler son compte sur son profil (PR https://github.com/elefan-grenoble/gestion-compte/pull/676)
* Admin > membre : répare l'affichage des membres sans bénéficiaire (PR https://github.com/elefan-grenoble/gestion-compte/pull/683)
* Admin > liste des membres : répare l'ordre par e-mail (PR https://github.com/elefan-grenoble/gestion-compte/pull/682)
* Admin > gérer les créneaux : Fix du filtre par semaine (PR https://github.com/elefan-grenoble/gestion-compte/pull/687)
* Admin > gérer les créneaux : répare l'affichage du select dans la modale de créneau (PR https://github.com/elefan-grenoble/gestion-compte/pull/689)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.41.2...v1.41.3

## 2022-12-14 (v1.41.2)

Bugfix :
* Admin > gérer les créneaux : corrige une erreur sur le filtre par numéro de semaine (https://github.com/elefan-grenoble/gestion-compte/commit/a0c2accb385d57558f1387f2a35a6aa8060d5b88)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.41.1...v1.41.2

## 2022-12-14 (v1.41.1)

Amélioration :
* Admin > gérer les créneaux : afficher tous les créneaux générés (PR https://github.com/elefan-grenoble/gestion-compte/pull/671)

Bugfix :
* Fix sur la création de créneau (PR https://github.com/elefan-grenoble/gestion-compte/pull/675)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.41.0...v1.41.1

## 2022-12-12 (v1.41.0)

Amélioration :
* Admin > gérer les créneaux : utiliser des appels Ajax pour éviter de recharger la page (PR https://github.com/elefan-grenoble/gestion-compte/pull/674)
* Exemption de créneau : cleanup ; affichage admin ; affichage dashboard (PR https://github.com/elefan-grenoble/gestion-compte/pull/669)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.40.3...v1.41.0

## 2022-12-05 (v1.40.3)

Amélioration :
* Fix commands to use cycle abcd (PR https://github.com/elefan-grenoble/gestion-compte/pull/667)

Bugfix :
* Bug dans ShiftService (PR https://github.com/elefan-grenoble/gestion-compte/pull/665)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.40.2...v1.40.3

## 2022-11-29 (v1.40.2)

Bugfix :
* Corrige à nouveau un bug d'affichage du code du boîtier (https://github.com/elefan-grenoble/gestion-compte/commit/1d49e2b14b2c1e7365136544822d9735da794461)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.40.1...v1.40.2

## 2022-11-28 (v1.40.1)

Bugfix :
* Corrige un bug empêchant l'affichage du code du boîtier pendant un créneau (https://github.com/elefan-grenoble/gestion-compte/commit/3bf6249c170c5cd5b7791e8ed6df685b5e482ead)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.40.0...v1.40.1

## 2022-11-28 (v1.40.0)

Nouveauté :
* Implémentation de l'exemption de créneau (PR https://github.com/elefan-grenoble/gestion-compte/pull/659)
* Tests : ajout de la CI Github Actions (PR https://github.com/elefan-grenoble/gestion-compte/pull/661)

Amélioration :
* Ordonnancer l'affichage des créneaux des membres (PR https://github.com/elefan-grenoble/gestion-compte/pull/625)
* Avoir le cycle des utilisateurs qui suit les semaines ABCD (PR https://github.com/elefan-grenoble/gestion-compte/pull/664)

Bugfix :
* Fix : répare l'édition de bucket (PR https://github.com/elefan-grenoble/gestion-compte/pull/658)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.39.1...v1.40.0

## 2022-11-27 (v1.39.1)

Amélioration :
* Créneaux type : améliorations cosmétiques & d'URL (PR https://github.com/elefan-grenoble/gestion-compte/pull/627)
* Créneaux : amélioration UX sur le bouton supprimer (PR https://github.com/elefan-grenoble/gestion-compte/pull/650)
* Admin : homogénéiser l'ordre d'affichage des créneaux des bucket (PR https://github.com/elefan-grenoble/gestion-compte/pull/655)
* Profile : bouger la section "Action" plus haut (PR https://github.com/elefan-grenoble/gestion-compte/pull/584)
* BookingController : refactoring (PR https://github.com/elefan-grenoble/gestion-compte/pull/646)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.39.0...v1.39.1

## 2022-11-26 (v1.39.0)

Nouveauté :
* Début de la gestion de l'exemption de créneau (ajout des entités) (PR https://github.com/elefan-grenoble/gestion-compte/pull/580)

Amélioration :
* Améliore la gestion de l'autocompletion (PR https://github.com/elefan-grenoble/gestion-compte/pull/657)

Bugfix :
* Fix : coté admin, pouvoir réserver un créneau 'volant' (PR https://github.com/elefan-grenoble/gestion-compte/pull/652)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.38.2...v1.39.0

## 2022-11-25 (v1.38.2)

Bugfix :
* Répare les conflits dans le routing des urls `/membre` (MembershipController) (PR https://github.com/elefan-grenoble/gestion-compte/pull/649)
  * la route `member_show` a été renommé de `/member/{member_number}` à `/member/{member_number}/show`
  * sinon des routes comme `/member/office_tools` ou `/member/join` étaient considérés comme des member_show, et ca provoquait une erreur pour accéder à la page (dû à la PR #641)
* Répare l'envoi d'e-mails (PR https://github.com/elefan-grenoble/gestion-compte/pull/648)
  * dû à la PR #620, qui engendré le bug : on confondait le member_number avec le beneficiary_id
  * l'utilisateur pensait envoyer un e-mail à l'utilisateur avec le numéro de membre #123 mais il était en fait envoyé à l'utilisateur avec l'identifiant en base de donnée #123

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.38.1...v1.38.2

## 2022-11-23 (v1.38.1)

Nouveauté :
* Gel / Fermeture : indiquer dans la modale de confirmation les créneaux à venir du membre (PR https://github.com/elefan-grenoble/gestion-compte/pull/640)
* Ecran badgeuse : séparer les créneaux en cours des créneaux à venir (PR https://github.com/elefan-grenoble/gestion-compte/pull/638)

Amélioration :
* Gel / Fermeture : utiliser la méthode POST pour les formulaires (PR https://github.com/elefan-grenoble/gestion-compte/pull/641)
* Membres avec plusieurs bénéficiaires : afficher le bénéficiaire principal en premier (PR https://github.com/elefan-grenoble/gestion-compte/pull/639)
* Mini-ajustements sur l'affichage admin des membres si max_beneficiary = 1 (PR https://github.com/elefan-grenoble/gestion-compte/pull/636)
* Renommé l'url admin d'un membre : `/member/show/<id>` en `/member/<id>` (PR https://github.com/elefan-grenoble/gestion-compte/pull/641)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.38.0...v1.38.1

## 2022-11-20 (v1.38.0)

Nouveauté :
* Pouvoir séparer (détacher) un bénéficiaire d'un compte membre (PR https://github.com/elefan-grenoble/gestion-compte/pull/591)

Amélioration :
* Joindre deux comptes : rajout de vérifications (pour éviter de dépasser le nombre maximum de bénéficiaires par compte) (PR https://github.com/elefan-grenoble/gestion-compte/pull/622)
* Joindre deux comptes : améliore la sélection des membres (gestion de l'autocomplete) (PR https://github.com/elefan-grenoble/gestion-compte/pull/630)

Bugfix :
* Répare le bug sur la recherche de bénéficiaire via l'autocomplete (provoqué par la release précédente) (PR https://github.com/elefan-grenoble/gestion-compte/pull/633)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.9...v1.38.0

## 2022-11-19 (v1.37.9)

Nouveauté :
* Contenu dynamique : timestamps de création et d'édition + l'auteur (PR https://github.com/elefan-grenoble/gestion-compte/pull/624)

Amélioration :
* Homogéniser et généraliser l'affichage de "libre" : Gérer les créneaux, Semaine type, cardReader (PR https://github.com/elefan-grenoble/gestion-compte/pull/621)
* Homogéniser l'affichage des membres : Numéro de membre + Prénom + Nom de famille (complet ou 1è lettre) (PR https://github.com/elefan-grenoble/gestion-compte/pull/620)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.8...v1.37.9

## 2022-11-17 (v1.37.8)

Nouveauté :
* Afficher le nom des membres sur le planning (via le paramètre existant `display_name_shifters`) (PR #613)

Amélioration :
* permettre aux ROLE_USER_VIEWER de relancer les pré-adhésions (ils avaient déjà accès à la page, mais pas au bouton) (PR #617)
* Home : finir de généraliser le vouvoiement si plusieurs bénéficiaires (PR #626)

Bugfix :
* Erreur de config pour le paramètre `profile_display_task_list`
* Erreur de `createdAt` sur la page des pré-adhésions

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.7...v1.37.8

## 2022-11-15 (v1.37.7)

Améliorations :
* Home : élargir le container (PR https://github.com/elefan-grenoble/gestion-compte/pull/611)

Bugfix :
* Répare les droits d'accès au contenu dynamique (pour les ROLE_PROCESS_MANAGER) (PR #614)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.6...v1.37.7

## 2022-11-13 (v1.37.6)

Nouveauté :
* Pouvoir ajouter une description sur une formation (PR https://github.com/elefan-grenoble/gestion-compte/pull/601)
* Ajout d'un champ Beneficiary.created_at (PR https://github.com/elefan-grenoble/gestion-compte/pull/604)
* Ajout d'un champ Membership.created_at (PR https://github.com/elefan-grenoble/gestion-compte/pull/605)

Amélioration :
* Rendre le champ Membership.member_number unique (PR https://github.com/elefan-grenoble/gestion-compte/pull/606)
* Modèle de donnée : suite et fin du renommage de createdAt (PR https://github.com/elefan-grenoble/gestion-compte/pull/595)
* Petites améliorations sur les pages avec des listes : afficher le count, homogénéiser le style des tableaux (PR https://github.com/elefan-grenoble/gestion-compte/pull/596)
* Mise à jour du README (PR https://github.com/elefan-grenoble/gestion-compte/pull/598)

Bugfix :
* Liste des membres : répare le filtre par formation ou commission (PR https://github.com/elefan-grenoble/gestion-compte/pull/602)
* Liste des membres : si le compte est fermé, affiché un fond rouge par défaut (PR https://github.com/elefan-grenoble/gestion-compte/pull/603)
* Rôles : un SUPER_ADMIN doit pouvoir ajouter/retirer le rôle ADMIN (PR https://github.com/elefan-grenoble/gestion-compte/pull/610)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.5...v1.37.6

## 2022-11-07 (v1.37.5)

Nouveauté :
* Pouvoir ajouter une url sur un poste de bénévolat (PR https://github.com/elefan-grenoble/gestion-compte/pull/597)
* Stocker la date de fermeture d'un compte (et l'auteur de l'action) (PR https://github.com/elefan-grenoble/gestion-compte/pull/586)

Amélioration :
* Ajustements sur l'affichage des états d'un membre (∅ / ❄️ / ⚐) (PR https://github.com/elefan-grenoble/gestion-compte/pull/589)
* Home : généraliser le vouvoiement si plusieurs bénéficiaires (PR https://github.com/elefan-grenoble/gestion-compte/pull/599)
* Liste des membres : améliorer le fonctionnement pour les comptes avec plusieurs bénéficiaires (PR https://github.com/elefan-grenoble/gestion-compte/pull/590)
  * nouveau filtre par nombre de bénéficiaires
  * toujours afficher l'ensemble des bénéificiaires, même après filtre

Bugfix :
* Fix recipients array when null (PR https://github.com/elefan-grenoble/gestion-compte/pull/594)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.4...v1.37.5

## 2022-11-02 (v1.37.4)

Bugfix :
* Correction d'un bug dans l'affichage des créneaux (vue admin)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.3...v1.37.4

## 2022-11-02 (v1.37.3)

Nouveautés :
* Modèle de données : ajout du champ createdAt aux modèles Formation, Job, Shift, Commission et Event (https://github.com/elefan-grenoble/gestion-compte/pull/566)

Améliorations :
* Profile : enlever le bouton "Mon badge" (pour faire de la place ; il est toujours disponible dans "Gérer mon compte") (https://github.com/elefan-grenoble/gestion-compte/pull/576)
* Profile : pouvoir cacher le bouton "Tâches en cours" (utile seulement si il y a des commissions) (https://github.com/elefan-grenoble/gestion-compte/pull/575)
* Admin : remonter les boutons liés aux Membres et aux Créneaux & déplacer le bouton "Relances créneaux" vers la section Membres (https://github.com/elefan-grenoble/gestion-compte/pull/572)
* Amélioration des log de temps : stocker l'auteur, action de suppression seulement réservée aux SUPER_ADMIN (https://github.com/elefan-grenoble/gestion-compte/pull/570)
* Réécriture de bout de code de l'entité shift pour plus de simplicité (https://github.com/elefan-grenoble/gestion-compte/pull/577)
* Admin : améliorations de la performance pour la réservation de créneaux (https://github.com/elefan-grenoble/gestion-compte/pull/578)

Bugfix :
* Différentes corrections de bug liées aux créneaux : permettre aux utilisateurs non-bénéficiaires de faire certaines actions (https://github.com/elefan-grenoble/gestion-compte/pull/579)

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.2...v1.37.3

## 2022-10-31 (v1.37.2)

Bugfix :
* corrige des erreurs sur SwipeCardLog (apparues après la release v1.32.0)
 
<hr />

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37.1...v1.37.2

## 2022-10-31 (v1.37.1)

Bugfix :
* corrige un bug sur l'autocomplete des bénéficiaires

<hr />

**Full Changelog**: https://github.com/elefan-grenoble/gestion-compte/compare/v1.37...v1.37.1

## 2022-10-30 (v1.37.0)

Nouveautés :
* Pouvoir échanger le bénéficiaire principal avec le bénéficiaire secondaire (PR https://github.com/elefan-grenoble/gestion-compte/pull/563)
* Profile : pouvoir afficher le compteur temps (nouveau paramètre `profile_display_time_log`) (PR https://github.com/elefan-grenoble/gestion-compte/pull/569)

Améliorations :
* Rôles : permettre à ROLE_USER_VIEWER d'accéder à la page Admin (PR https://github.com/elefan-grenoble/gestion-compte/pull/507)
* Admin : déplacer les boutons "Adhésion / Ré-adhésion" dans la page Admin (PR https://github.com/elefan-grenoble/gestion-compte/pull/507)
* SwipeCardLog : pouvoir les rattacher à SwipeCard (nouveau paramètre `swipe_card_logging_anonymous`) (PR https://github.com/elefan-grenoble/gestion-compte/pull/559)

Bugfix :
* Corrige l'envoi de mail depuis le formulaire de l'espace membre (PR https://github.com/elefan-grenoble/gestion-compte/pull/564)

<hr />

Changelog complet : https://github.com/elefan-grenoble/gestion-compte/compare/v1.36.3...v1.37.0

## 2022-10-25 (v1.36.3)

Nouveauté :
- pour chaque formation, afficher la liste des rôles associés (Issue https://github.com/elefan-grenoble/gestion-compte/issues/557 / PR https://github.com/elefan-grenoble/gestion-compte/pull/558)

<hr />

Changelog complet: https://github.com/elefan-grenoble/gestion-compte/compare/v1.36.2...v1.36.3

## 2022-10-21 (v1.36.2)

Nouveauté :
- Admin : page avec la liste des rôles (PR #504 / Issue #503)

Bugfix :
- Admin : permettre à un n'importe quel ROLE_ADMIN_PANEL d'accéder à l'admin (PR #550)

<hr />

Changelog complet : https://github.com/elefan-grenoble/gestion-compte/compare/v1.36.1...v1.36.2

## 2022-10-21 (v1.36.1)

Nouveauté :
- un ROLE_ADMIN peut maintenant ajouter (et retirer) des rôles enfants (PR #547 / Issue #511)
- dans la liste des postes de bénévolat, afficher pour chaque poste le nombre de créneaux correspondants (PR #545)

Améliorations :
- cleanup du controller et des templates pour les formations (PR #544)

## 2022-10-18 (v1.36.0)

Améliorations :
- permettre à un ROLE_ADMIN de créer un événement (PR #541)
  - note : c'était déjà possible mais seulement en connaissant l'url de création
- permettre à un ROLE_ADMIN de supprimer un événement (PR #542)
  - note : le bouton apparaissait pour les ROLE_ADMIN, mais ils n'avaient pas les droits

## 2022-10-18 (v1.35.5)

Améliorations :
- suppression des dépendances `components/jquery` & `evheniy/materialize-bundle` (on les appelle déjà dans le layout.html) (PR #543)
- mise à jour des dépendances & forcer PHP 7.3 dans le `composer.json` (PR #546)

## 2022-10-13 (v1.35.3)

Nouveauté
- nouveau paramètre `display_freeze_account_false_message` qui permet de définir un message à afficher lorsque `display_freeze_account=False` (Issue #516)

## 2022-10-12 (v1.35.2)

Améliorations :
- homogénéise les templates des "card" créneaux

Bugfix :
- corrige la langue d'affichage du jour de son créneau fixe (apparaissait en anglais dans son tableau de bord)
- corrige l'affichage du menu (`sidenav`) sur les petits écrans (le texte des boutons n'apparaissait pas)

## 2022-10-06 (v1.35.1)

Nouveauté :
- améliore l'affiche du header sur les écrans de taille moyenne (PR https://github.com/elefan-grenoble/gestion-compte/pull/517)

Bugfix :
- renommer les fichiers JS & CSS de materialize pour contourner le cache des navigateurs des utilisateurs (PR https://github.com/elefan-grenoble/gestion-compte/pull/529)
- répare le fonctionnement des collapsible "expendable" (PR https://github.com/elefan-grenoble/gestion-compte/pull/530)

## 2022-10-06 (v1.35.0)

#### Mise a jour de la liste d’émargement :
- La liste d’émargement est calculée qd le bouton est cliqué. Elle peut être générer plusieurs fois, et sera tjs faite sur l’information la plus récente ds la base de données. 
- Sur cette liste les comptes (membership ds le code) désactivés ne sont pas inscrits.
- Les membre avec <=-9h sont indiqués comme n’ayant pas le droit de voter.
- Les comptes qui ont fait une procuration sont indiqués comme tels (avec le nom de la personne ayant la procuration).
- Si un compte à donner une procuration à qq1, mais est <=-9, la procuration est dans la liste (à côté du nom de la personne porteuse de procuration), mais avec une indication disant que le vote est interdit.
- Si une personne porteuse d’une procuration est liées à un compte qui atteint les -9h entre la procuration et le jour J, la procuration sera dans la liste d’émargement et pourra pas voter pour elle-même, mais pourra voter pour la procuration.
- La mise en page à était revue pour être (un peu) plus claire. Les noms des bénéficiaires sont regroupé par première lettre de leur nom. Mais il reste des petits bug, i.e. s’il y a un changement de page et de lettre au même endroit, l’entête du tableau est indiqué deux fois :-/

#### Mise à jour du système de procuration :
- Il est impossible de faire une procuration entre bénéficiaires (beneficiary dans le code) d'un mm compte. Maintenant, le système l’interdit, mais il n'y a pas de message (les nom des autre bénéficiaires n’apparaissent pas dans la liste.
- Il est impossible de faire une procuration à un compte avec un compteur <=-9h mais cela redevient possible dès que les heures sont rattrapées. Par contre ,un compte ayant -9h peut donner une procuration (mais il faudra que les heures soit rattrapé avant le jour J, sinon le vote sera indiqué comme impossible)

#### Ce qui était déjà dans le code (et qui n’a pas changé) 
- Il ne peut avoir qu’une procuration par compte. Si qq1 tente de donner une procuration à un compte qui en a déjà une, un message d’erreur apparait.


## 2022-10-06 (v1.34.0)

pas mal de changement pour passer de la v0.100.2 à la v1.1.0 (Issue https://github.com/elefan-grenoble/gestion-compte/issues/468)

## 2022-09-24 (v1.33.3)

Nouveauté :
- donner d'avantage de responsabilités aux `ROLE_PROCESS_MANAGER`

Bugfix :
- Semaine type : comportement d'un bouton sur Firefox

## 2022-09-10 (v1.33.2)

Sur tout le site
- généralisation de l'usage des `title` sur les pages
- généralisation et harmonisation des `breadcrumbs`

Sur la page Admin > Semaine type
- améliorer l'intéraction avec la carte créneau (expliciter que la carte est cliquable)

## 2022-09-06 (v1.33.1)



## 2022-09-06 (v1.33.0)

Sur la page principale (fiche membre) :
- Renommé le bouton "Je réserve un créneau" en "Je réserve un créneau volant"
- Affichage de son/ses créneau(x) fixe
- Indiquer pour chaque créneau passé si il a été effectué ou pas

Sur les pages Admin > Fiche membre :

- Affichage du/des créneau(x) fixe
- Indiquer pour chaque créneau passé si il a été effectué ou pas
- Ajout d'un bouton pour "Valider le créneau"
- Réparé le bouton "Invalider le créneau"

Sur la page Admin > Gérer les créneaux :

- Affichage par défaut de seulement 1 semaine (pour accélérer le chargement)
- Filtres additionnels : par numéro de semaine, par type de créneau, par remplissage
- Suppression du bouton "Voir les booker"
- Lors de l'assignation à un créneau, ajout d'une icône pour indiquer si le membre est volant :airplane: ou a un compte gelé :snowflake:
- On ne peut plus assigner un membre à un créneau si son compte est suspendu/fermé

Sur la page Admin > Semaine type :

- Ajout de filtres par type de créneau, par semaine et par remplissage
- Ajout d'une icône pour les membres inscrits sur un créneau fixe alors qu'ils sont volants, ou que leur compte est gelé ou suspendu/fermé
- Ajout d'un lien direct vers la fiche membre des bénéficiaire sur les carte de créneaux fixe
- Note : pour éditer un créneau fixe, cliquer sur le titre de la carte
- Amélioration de la mise en page

Technico-technique :

-  Mise à jour du paquet de gestion des code bar (pour la page fiche membre)

## 2022-03-27 (v1.32.3)

* Fix shift alert when two jobs are at the same time
* Fix issue with reserved shift
* Add mailcatcher in docker-compose for dev purposes

## 2022-01-28 (v1.32.2)

Fix default value in SendShiftAlertsCommand

## 2022-01-23 (v1.32.1)

- fix missing brackets in a if else condition

## 2022-01-23 (v1.32.0)

- Corrections de bugs (création de créneaux, gestion des codes postaux, envoi de messages si 2 types différents de créneaux en parallèle ...)
- Autorisation du cumul de créneaux
- Amélioration l'affichage des formations
- Amélioration de la documentation
- Ajout d'une option pour gérer des adhésions sur l'année civile (et non plus seulement glissantes sur toute l'année)

## 2021-12-24 (v1.31.3)

- Ajout des créneaux fixes dans la semaine type
- Amélioration de l'affichage pour la participation à un créneau
- Affichage des semaines A/B/C/D

:warning: cette release contient le commit https://github.com/elefan-grenoble/gestion-compte/commit/f074ada813a7f3475db63b2ff2b21d8c9d2faff9, qui contient entre autre la migration `Version20211223205749`, qui supprime la table de jointure `period_position_period`, et vide la table `period_position`.

## 2021-12-08 (v1.31.2)

- Fix account creation
- Fix shift invalidation

## 2021-12-06 (v1.31.1)

Fix a typo on  the booking controler preventing admin to invalidate a shift.

## 2021-12-05 (v1.31.0)

Fix several typos
Improve fixe shifts
Add attribute flying for each beneficiary 

## 2021-09-25 (v1.30.6)



## 2021-09-18 (v1.30.5)



## 2021-09-18 (v1.30.4)



## 2021-09-18 (v1.30.3)

- Spécifier le nombre minimun de bénévoles sur un créneau (si ce nombre n'est pas atteint une alerte peut-être envoyée)
- Ajout de la possibilité de spécifier un contenu dynamique pour l'envoi d'une alerte
- Ajout d'un champ type dans les contenus dynamiques pour un affichage plus lisible 

## 2021-09-12 (v1.30.2)

Pourvoir faire des procurations anonyme et nominative.

## 2021-06-14 (v1.30.1)



## 2021-04-25 (v1.30)

Improve week A-B-C-D in shifts planning
Do not allow to book a shift if it overlaps with an existing booked one

## 2021-04-25 (v1.29.9)



## 2021-04-09 (v1.29.8)

- Ajout d'une commande pour anonymisée les données (utilisation en preprod)
- Ajout d'un paramètre pour rendre la réservation de créneau optionnelle
- Changement de la police par défault pour la version light

## 2021-04-04 (v1.29.7)

- Amélioraiton des créneaux fixes (avec fly_and_fixed) non attribués
- Ajout de l'année dans les notes des ambassadeurs

## 2021-03-10 (v1.29.6)

- autorise les descriptions de jobs vides
- corrige les compteurs de temps inférieur à -24h
- supprime dans le twig de la badgeuse la vérification des droits

## 2021-03-01 (v1.29.5)

#415 
- Ajout d'un filtre sur les compteurs dans la partie admin.
- Nouvelle partie pour les ambassadeurs 'Relances créneaux'

## 2021-02-27 (v1.29.4)

- Seuls les compteurs des membres sont loggués par la badgeuse
- Changement du logo ninga
- Ajout de la description d'un job

## 2021-02-23 (v1.29.3)

#413 Ajout de fonctionnalités sur la badgeuse (/cardReader)
Listing des créneaux de la journée

## 2021-02-22 (v1.29.2)

#412 Traduction d'invalid message de AutocompleteBeneficiaryType.php

## 2021-02-14 (v1.29.1)

#410 Add the possibility to do more shifts if some are available in the next following days

## 2020-12-13 (v1.29)

- #405 Rajout de plusieurs fonctionnalités pour modifier facilement un créneau existant
- #407 Restore missing label on form fields

## 2020-11-15 (v1.28.1)

- Restore support for PHP 7.3

## 2020-11-15 (v1.28)

- Correction sur la gestion des badges en tant qu'un USER_MANAGER
- Correction de fautes d'orthographe
- Gestion de valeurs plus élevées sur les numéros de membre
- Support de Composer 2
- Correction sur le tri des utilisateurs par username

## 2020-07-20 (v1.27)

- Ajout d'une commande permettant d'intégrer manuellement des paiements en provenance de HelloAsso

## 2020-06-19 (v1.26.1)

Corrige une régression introduite en 1.26, il est maintenant de nouveau possible de libérer des créneaux, autant en tant qu'admin ou utilisateur simple.

## 2020-06-03 (v1.26)

- Ajout de la gestion des créneaux fixes (reportés toutes les 4 semaines). Possibilité de désactiver la fonctionnalité via le paramètre : use_fly_and_fixed. #366 
- Ajout d'un tableau de suivi des pré-adhésions avec possibilité de renotifier #383 
- Mise à jour des dépendances

## 2020-06-01 (v1.25)

* Ajout de la possibilité de poster les alertes créneaux sur Mattermost
* Possibilité de surcharger les templates d'alertes via les contenus dynamiques


## 2020-03-29 (v1.24)

#376 Correction de la gestion des timezones pour la commande de mise à jour d'un boitier IglooHome

## 2020-03-23 (v1.23)

#375 Commande pour gérer automatiquement un boitier connecté Igloohome
#374 Consultation planning en mode connecté
#373 Divers corrections configs d'emails
#372 Correction affichage monnaie locale

## 2020-03-13 (v1.22)

Correction de l'adresse d'envoi des mail d'alerte de remplissage de créneaux

## 2020-03-10 (v1.21)

Mise à jour de la commande pour envoyer des alertes de remplissage des créneaux:
* envoi lorsque moins de 2 bénévoles sont inscrits sur un créneau
* ajout de la possibilité d'envoi d'alertes pour différents types de créneaux (ids des jobs séparés par des virgules)
* ajout d'un paramètre pour le ou les destinaires de l'alerte (emails séparés par des virgules)

## 2019-12-27 (v1.13)

- Ajout d'un title sur bouton supprimer bénéficiaire
![image](https://user-images.githubusercontent.com/675464/71067511-f3e4c300-2174-11ea-8858-d2498f29a229.png)
- Ajouter les commissions dans la recherche rapide admin
![image](https://user-images.githubusercontent.com/675464/71067656-373f3180-2175-11ea-8496-44303237e9a5.png)
- Ajouter l'index des contenus dynamiques dans la recherche rapide admin
- Ajouter le browser helloasso dans la recherche rapide admin
- Envoyer le mail de début de créneaux à tous les bénéficiaires
- Refonte de la gestions des membres dans une commission (et fix d'un bug comme quoi le référent reste référent même en quittant la commission)
![image](https://user-images.githubusercontent.com/675464/71067736-5b9b0e00-2175-11ea-9905-9441b7e60f8a.png)
![image](https://user-images.githubusercontent.com/675464/71067808-871df880-2175-11ea-9516-105e2c6473f4.png)
- Code boitier à clefs visible 1h avant le créneau et 2h après.
- Afficher les 3 dernier ancien code, au cas où
- Simplification du dépot de clef (plus de smartphone/lightphone)
- Ajouter la date effective de l'adhésion sur la liste des adhésions
![image](https://user-images.githubusercontent.com/675464/71067971-e67c0880-2175-11ea-97a2-ab7f094fe99a.png)
- Ajout d'un mail quand paiement hello asso reçu
- Possibilité d'adhérer en payant par helloasso
![image](https://user-images.githubusercontent.com/675464/71068097-3d81dd80-2176-11ea-93d2-895e19a9d294.png)
![image](https://user-images.githubusercontent.com/675464/71068188-7c179800-2176-11ea-9948-5a656d0433e1.png)
![image](https://user-images.githubusercontent.com/675464/71068205-876ac380-2176-11ea-955c-0ac8d8c78631.png)
![image](https://user-images.githubusercontent.com/675464/71068227-92bdef00-2176-11ea-99fd-0c42901a69d7.png)
- Possibilité de ré-adhérer sur helloasso avant la fin de son adhésion

## 2019-11-21 (v1.11)

#342 L'administration des codes ne pouvait se faire qu'avec le ROLE_SUPER_ADMIN. 

## 2019-11-05 (1.10)

#340 Ajout de logs sur la callback HelloAsso
#341 Vérouillage créneau

## 2019-10-21 (v1.9)



## 2019-09-08 (v1.8)

- Amélioration de la gestion des mails et des templates
- Quelques améliorations cosmétiques
- Correction de bugs diverses

## 2019-08-11 (v1.7)

#326 Autogestion des problèmes HelloAsso
#328 Ajout des nouveautés dans le mail de rappel de créneau

## 2019-08-06 (v1.6)

#155 Liste de dernières nouveautés

## 2019-06-15 (v1.5)

#314 Fix admin removal
#313 Cacher les jobs désactivés
#312 Correction envoi d'email
#311 Suppression utilisateur admin
#310 Réorganisation des boutons de la page d'accueil

## 2019-06-02 (v1.3)

#309 Correction fautes d'orthographes et suppression référence à l'éléfàn
#308 Datepickers: JS refactoring et correction bug Chromium
#307 Ne pas afficher le code barre du badge sur la page d'accueil

## 2019-06-01 (v1.2)

#306 Amélioration de l'organisation des menus pour éditer son profil
#305 Amélioration du libellé sur le menu pour geler son compte
#304 Ne pas supprimer les logs de temps lors de la suppression d'un bénéficiaire

## 2019-05-16 (v1.1)

Les membres peuvent maintenant voir leur numéro de membres sur leur page d'accueil.

![Capture d’écran du 2019-05-16 20-15-35](https://user-images.githubusercontent.com/10038524/57888556-c6c4e080-7832-11e9-9138-3980c6b4a96e.png)

## 2019-05-12 (v1.0)

Mise en place des versions de l'espace membre
