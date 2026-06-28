# SPEC.6 — Spec : Administration & Configuration

- [x] **SPEC.6** — Spec : Administration & Configuration

Sources : `AdminController`, `CommissionController`, `ServiceController`, `TaskController`, `JobController`, `FormationController`, `DynamicContentController`, `EmailTemplateController`, `SocialNetworkController`, `ProcessUpdateController`, `ClientController`, `AdminClosingExceptionController`, `AdminOpeningHourController`, `AdminOpeningHourKindController` ; voters `TaskVoter`, `EmailTemplateVoter`, `ProcessUpdateVoter`, `DynamicContentVoter` ; `config/packages/security.yaml` (hiérarchie de rôles) ; `src/Command/*` (commandes d'administration) ; `config/services.yaml` (paramètres métier — détaillés en CONFIG.1-3).

#### Périmètre et frontières
Ce domaine couvre **l'administration de la configuration** : le panneau d'admin, la hiérarchie des rôles, et le CRUD des entités qui *paramètrent* le fonctionnement de la coopérative (commissions, services, postes, formations, motifs, contenus dynamiques, modèles d'email, réseaux sociaux, horaires d'ouverture, fermetures exceptionnelles, clients OAuth, tâches). Sont **explicitement renvoyés ailleurs** :
- **Codes d'accès physiques** (`CodeController`, `code_*`) → **SPEC.4** (domaine J, accès physique au local).
- **Semaines types / créneaux fixes / exemptions / logs de libération** (`AdminPeriodController`, `AdminShiftExemptionController`, `AdminMembershipShiftExemptionController`, `AdminShiftFreeLogController`, `AdminPeriodPositionFreeLogController`) → **SPEC.3** (créneaux).
- **Gestion fine des membres** (`user_index` / export CSV membres / envoi de mail depuis la liste) → **SPEC.2** (adhérents) ; seules les actions de pur **paramétrage** d'`AdminController` (rôles, admins, non-membres, import CSV) sont traitées ici.
- **Clients OAuth** : le CRUD `client_*` est documenté ici (c'est de la config admin) mais la **mécanique OAuth serveur** (grant types, flux d'autorisation, `ROLE_OAUTH_LOGIN`) → **SPEC.8** (API & intégrations).
- **Événements associatifs / procurations** (`EventController`, `AdminEventController`, `AdminEventKindController`, `Proxy`) → **SPEC.11** (gouvernance).
- **Paramètres par variables d'environnement** (~150 vars) : inventoriés et analysés en **CONFIG.1** (vars d'env), **CONFIG.2** (mécanisme multi-instance) et **CONFIG.3** (paramètres métier). Cette spec n'en redonne que la lecture fonctionnelle « côté admin ».

#### Vocabulaire
- **Panneau d'administration** : la page `admin` (route `/admin/`, gabarit `admin/index.html.twig`), point d'entrée de toutes les fonctions de gestion ; visible dès `ROLE_ADMIN_PANEL`.
- **Rôle « manager » granulaire** : un des quatre rôles métier intermédiaires (`ROLE_USER_MANAGER`, `ROLE_SHIFT_MANAGER`, `ROLE_FINANCE_MANAGER`, `ROLE_PROCESS_MANAGER`) qui ouvre un sous-ensemble de l'admin sans donner `ROLE_ADMIN` complet.
- **Commission** : groupe de travail thématique. Possède des **bénéficiaires** (membres) et des **owners** (référents). Sert de pivot d'autorisation pour les `Task`.
- **Service** : module/onglet applicatif optionnel (`public` = affiché dans la navbar). Lié 1-1 à un **Client** OAuth (intégration externe).
- **Task** : tâche collaborative rattachée à des commissions — **outil de coopération, pas une fonction d'admin** : tout `ROLE_USER` peut voir la liste, les membres d'une commission peuvent créer/éditer.
- **Job (poste)** : type de poste de bénévolat (ex. « caisse », « accueil »), réutilisé par les `Period`/`Shift` (SPEC.3). CRUD ici, usage en SPEC.3.
- **Formation** : qualification attribuable à un bénéficiaire, exigible sur certaines positions de créneau (SPEC.3).
- **DynamicContent** : fragment de contenu éditable (par `type`) injecté dans des pages — CMS minimaliste.
- **EmailTemplate** : modèle d'email paramétrable (objet + corps) réutilisé par l'envoi de masse (cross SPEC.7).
- **ProcessUpdate** : entrée du fil « nouveautés / procédures » présenté aux membres ; compteur de non-lus relatif à la date du dernier créneau.
- **OpeningHour / OpeningHourKind** : horaires d'ouverture du local par jour, regroupés par *kind* (type d'ouverture) ; alimentent un widget public.
- **ClosingException** : fermeture exceptionnelle datée — **bloque la génération de créneaux** ce jour-là (`ShiftGenerateCommand`, cross SPEC.3).
- **Widget generator** : formulaire admin produisant une *query string* pour intégrer un fragment HTML embarquable (créneaux d'un poste, horaires, fermetures) sur un site externe via la route publique `widget`.

#### Acteurs — hiérarchie de rôles (`security.yaml`)
```
ROLE_USER
 └─ ROLE_ADMIN_PANEL                 (accède au panneau admin)
     ├─ ROLE_USER_VIEWER             (consultation membres)
     │   └─ ROLE_USER_MANAGER        (gestion membres, exemptions)
     ├─ ROLE_SHIFT_MANAGER           (créneaux/périodes — SPEC.3)
     ├─ ROLE_FINANCE_MANAGER         (paiements/adhésions — SPEC.5)
     └─ ROLE_PROCESS_MANAGER         (contenus, emails, nouveautés, kinds d'horaires)
 ROLE_ADMIN = [USER_MANAGER, FINANCE_MANAGER, SHIFT_MANAGER, PROCESS_MANAGER]   (agrège les 4)
 ROLE_SUPER_ADMIN ⊃ ROLE_ADMIN       (suppressions, création de commissions/clients, import CSV)
```
- **`ROLE_PROCESS_MANAGER`** : profil « éditeur de contenu » — gère `DynamicContent`, `EmailTemplate`, `ProcessUpdate`, `OpeningHourKind` et les `widget_generator`, sans toucher aux membres ni aux finances.
- **`ROLE_SUPER_ADMIN`** : seul habilité aux opérations les plus sensibles — suppression de la plupart des entités, création de commission/service/client, import CSV d'utilisateurs.
- **Owner de commission** (rôle « fonctionnel », pas Symfony) : un bénéficiaire référent d'une commission peut éditer cette commission et gérer ses membres sans être admin (check inline `getOwnedCommissions()->contains()`).

#### Instances
- **Tout est commun** Elefan/Scopeli au niveau du code : ces écrans existent dans les deux déploiements. La différenciation passe par les **données saisies** (commissions, services, horaires…) et par les **feature flags** (CONFIG.2) qui masquent certains modules : `DISPLAY_KEYS_SHOP`, `DISPLAY_SWIPE_CARDS_SETTINGS`, `DISPLAY_OPENING_HOUR_OPEN_CLOSED_HEADER`, `CODE_GENERATION_ENABLED`, etc.
- **OAuth `Client`** : pertinent surtout pour l'instance exposant des intégrations (Service ↔ Client). À confirmer par instance en SPEC.8.

#### Flux principal — structure CRUD
La quasi-totalité du domaine suit un patron Symfony répété ~12 fois : `list` → `new` (form) → `edit` (form) → `delete` (form DELETE + token CSRF). Spécificités notables :
```
Commission : list/new/edit/delete + add_beneficiary + remove_beneficiary
  edit : SUPER_ADMIN | ADMIN | owner de la commission
  add/remove_beneficiary : SUPER_ADMIN | owner (PAS de @Security, check inline)
       → dispatch CommissionJoinOrLeaveEvent (JOIN / LEAVE) (cross SPEC.7)
       → réponse JSON si XHR (chip Twig), sinon redirect

Task : list/new/edit/delete  — autorisation 100 % par TaskVoter (aucun @Security)
  VIEW   : toujours vrai (tout ROLE_USER connecté)
  CREATE : ADMIN | (membre d'au moins une commission)
  EDIT   : ADMIN | owner de la tâche | membre d'une commission liée
  DELETE : ADMIN | owner de la tâche | owner d'une commission liée

Service : list/navlist/new/edit/delete  (CRUD SUPER_ADMIN ; navlist = ROLE_USER, public=1)
  logo : VichUploader + LiipImagine (resolveLogo → cache 'service_logo')

Client (OAuth) : list/new/edit/delete   (cross SPEC.8)
  new/edit délèguent à fos_oauth_server.client_manager (redirect URIs, grant types)

Job : list(filtre enabled)/new/edit/delete + widget_generator
OpeningHour : index/new/edit/delete + widget_generator   (+ Kind : list/new/edit/delete)
ClosingException : index/list/new/delete + widget_generator
Formation, SocialNetwork, EmailTemplate, DynamicContent, ProcessUpdate : CRUD standard
```
Deux actions **sortent du CRUD** et exécutent une commande console *dans la requête HTTP* (anti-pattern, voir Gaps) :
- `user_import_csv` (`AdminController`) → instancie une `Console\Application` et lance `app:import:users` en synchrone.
- `admin_shifts_generation` (`AdminPeriodController`, cross SPEC.3) → lance `app:shift:generate` de même.

#### Données — entités de configuration

| Entité | Rôle fonctionnel | Champs/relations clés | Suppression |
|--------|------------------|-----------------------|-------------|
| `Commission` | Groupe de travail | `name`, `email`, `description`, M-N `beneficiaries`, M-N `owners` | SUPER_ADMIN |
| `Service` | Module/onglet | `name`, `public`, `logo` (Vich), 1-1 `Client` | SUPER_ADMIN |
| `Client` | Client OAuth2 | `redirectUris`, `allowedGrantTypes`, `service` (FOSOAuthServer) | SUPER_ADMIN |
| `Task` | Tâche collaborative | `title`, `dueDate`, `createdAt`, M-N `commissions`, `owners`, `registrar` | voter |
| `Job` | Type de poste | `name`, `enabled`, `createdBy` | SUPER_ADMIN |
| `Formation` | Qualification | `name`, `createdBy` | SUPER_ADMIN |
| `DynamicContent` | Fragment CMS | `type`, `content`, `updatedBy` | — (pas de delete) |
| `EmailTemplate` | Modèle d'email | `name`, objet/corps, `createdBy`/`updatedBy` | — (pas de delete) |
| `SocialNetwork` | Lien réseau social | `name`, `url`/`icon` | SUPER_ADMIN |
| `ProcessUpdate` | Nouveauté/procédure | `date`, `author`, contenu | voter (PROCESS_MANAGER) |
| `OpeningHour` | Créneau d'ouverture | `dayOfWeek`, `start`/`end` (null si `closed`), `kind` | ADMIN |
| `OpeningHourKind` | Type d'ouverture | `name`, détails, `createdBy`/`updatedBy` | ADMIN |
| `ClosingException` | Fermeture datée | `date`/période, `createdBy` ; `findFutures/Ongoing/Past` | ADMIN |

#### Règles métier
1. **Pivot d'autorisation des tâches** : l'accès aux `Task` ne dépend d'aucun rôle admin mais de l'appartenance/ownership de **commission**. `Commission` est donc une brique d'autorisation, pas seulement une donnée.
2. **Édition de commission par owner** : un owner non-admin peut éditer « sa » commission et y ajouter/retirer des membres ; `commission_add_beneficiary`/`remove_beneficiary` ne portent **pas** de `@Security` et reposent sur un check inline (`getOwnedCommissions()->contains()` ou `SUPER_ADMIN`).
3. **Asymétrie des droits de suppression** : la suppression est `SUPER_ADMIN` pour Commission/Service/Client/Job/Formation/SocialNetwork, mais seulement `ROLE_ADMIN` pour OpeningHour(/Kind)/ClosingException, et **par voter** (`PROCESS_MANAGER`) pour ProcessUpdate. `DynamicContent` et `EmailTemplate` **n'ont pas d'action de suppression** du tout. Incohérence à arbitrer (voir Gaps).
4. **Fermeture exceptionnelle ⇒ pas de créneaux** : un `ClosingException` à une date donnée fait que `ShiftGenerateCommand` n'émet aucun créneau ce jour-là (cross SPEC.3).
5. **OpeningHour fermé** : si la case `closed` est cochée, `start`/`end` sont mis à `null` (jour ouvré marqué « fermé » sans horaire).
6. **ProcessUpdate — compteur de non-lus** : `countFrom(date)` où `date` = début du dernier créneau effectué du membre (sinon `lastLogin`). La liste est visible par tout `ROLE_USER` ; seule l'édition/création est `PROCESS_MANAGER`.
7. **EmailTemplate — voter `edit`** : en plus du `@Security('ROLE_PROCESS_MANAGER')`, l'édition repasse par `denyAccessUnlessGranted('edit', $template)` (= ADMIN/SUPER_ADMIN via `EmailTemplateVoter`). Deux couches qui se recoupent.
8. **Service ↔ Client** : un `Service` peut porter un `Client` OAuth ; la création de client se fait via `fos_oauth_server.client_manager` (pas un `persist` Doctrine direct).
9. **Audit `createdBy`/`updatedBy`** : la plupart des entités tracent leur créateur/éditeur (`security.token_storage`), exploité dans les vues admin.
10. **Paramètres métier non éditables en UI** : tout le réglage « profond » (durées de cycle, règles d'adhésion, feature flags) passe par variables d'environnement (CONFIG.3) — **aucune** interface d'administration de ces paramètres ; un changement nécessite un redéploiement.

#### Cas limites
1. **`commission_remove_beneficiary` lit `$_POST` en direct** (`CommissionController:199` : `$em->...->find($_POST['beneficiary'])`) au lieu de l'objet `Request` Symfony — court-circuite l'abstraction HTTP et présume la clé présente (pas de garde si absente → `null` → `getId()` sur null possible).
2. **Suppression de `ShiftExemption` utilisée** : `// TODO: error 500 if shiftExemption is used in membershipShiftExemption` (cross SPEC.3) — pas de garde de clé étrangère.
3. **Import CSV synchrone** : `user_import_csv` exécute `app:import:users` *dans* la requête → risque de timeout HTTP / pic mémoire sur gros fichier ; sortie texte brute renvoyée telle quelle.
4. **Export CSV membres (cross SPEC.2)** : `getResult()` charge tous les membres en mémoire (~80 Mo / 400 lignes, NOTE inline) ; `iterate()` impossible à cause du fetch-join `membership`. Même pattern dans `AmbassadorController` (cross SPEC.3).
5. **`DynamicContent` sans contenu** : `dynamicContentEditAction` force `content = ''` si `null` (évite un NOT NULL en base).
6. **Pas de pagination** sur la majorité des listes admin (commissions, services, jobs, formations, contenus…) — `findAll()` direct ; seuls `user_index`, `admin_shiftfreelog`, `admin_membershipshiftexemption` paginent.

#### Routes (SPEC.6 — ~50 ; hors Code/Period/Event renvoyés ailleurs)

| Entité / groupe | Routes | Accès |
|-----------------|--------|-------|
| **Panneau** | `admin` `/admin/` | ROLE_ADMIN_PANEL |
| **Users (admin)** | `non_member_users_list`, `admin_users_list`, `roles_list` | ROLE_ADMIN |
| | `user_import_csv` `/admin/importcsv` | ROLE_SUPER_ADMIN |
| | `user_index` `/admin/users` *(cross SPEC.2)* | ROLE_USER_MANAGER |
| **Commission** `/commissions` | `admin_commissions` | ROLE_ADMIN |
| | `commission_new`, `commission_delete` | ROLE_SUPER_ADMIN |
| | `commission_edit` | ADMIN \| owner |
| | `commission_add_beneficiary`, `commission_remove_beneficiary` | *inline* SUPER_ADMIN \| owner |
| **Service** `/services` | `service_list`/`new`/`edit`/`delete` | ROLE_SUPER_ADMIN |
| | `service_navlist` | ROLE_USER (public=1) |
| **Client OAuth** `/admin/clients` | `client_new` | ROLE_ADMIN |
| | `client_list`/`edit`/`delete` | ROLE_SUPER_ADMIN |
| **Task** `/tasks` | `tasks_list`/`task_new`/`task_edit`/`task_delete` | `TaskVoter` (voir Flux) |
| **Job** `/admin/job` | `job_list`/`new`/`edit` | ROLE_ADMIN |
| | `job_delete` | ROLE_SUPER_ADMIN |
| | `job_widget_generator` | ROLE_PROCESS_MANAGER |
| **Formation** `/admin/formations` | `formation_list`/`new`/`edit` | ROLE_ADMIN |
| | `formation_delete` | ROLE_SUPER_ADMIN |
| **DynamicContent** `/content` | `dynamic_content_list`/`edit` | ROLE_PROCESS_MANAGER |
| **EmailTemplate** `/emailTemplate` | `email_template_list`/`new`/`edit` | ROLE_PROCESS_MANAGER (+voter edit) |
| **SocialNetwork** `/admin/socialnetworks` | `..._list`/`new`/`edit` | ROLE_ADMIN |
| | `admin_socialnetwork_delete` | ROLE_SUPER_ADMIN |
| **ProcessUpdate** `/process/updates` | `process_update_list`, `..._count_unread` | ROLE_USER |
| | `process_update_new`/`edit`/`delete` | ROLE_PROCESS_MANAGER (voter) |
| **OpeningHour** `/admin/openinghours` | `admin_openinghour_index`/`new`/`edit`/`delete`/`widget_generator` | ROLE_ADMIN |
| **OpeningHourKind** `/admin/openinghours/kinds` | `..._list`/`new`/`edit` | ROLE_PROCESS_MANAGER |
| | `admin_openinghour_kind_delete` | ROLE_ADMIN |
| **ClosingException** `/admin/closingexceptions` | `..._index`/`list`/`new`/`delete`/`widget_generator` | ROLE_ADMIN |

**Note de couverture firewall** : seules les entités physiquement sous `/admin/` (Job, Formation, SocialNetwork, Client, OpeningHour, ClosingException) bénéficient de la règle `access_control: ^/admin/ → ROLE_ADMIN_PANEL`. **Commission, Service, Task, DynamicContent, EmailTemplate, ProcessUpdate** ne sont **pas** sous `/admin/` et reposent **uniquement** sur le `@Security`/voter par action (voir Gaps).

#### Commandes d'administration (`src/Command/`)
Les commandes appartenant à d'autres domaines sont renvoyées ; listées ici pour cartographie complète de l'« administration ».

| Commande | Rôle | Domaine |
|----------|------|---------|
| `app:user:cycle_start` | Gel/dégel des membres + events début de cycle | SPEC.3 (cron) |
| `app:user:cycle_half` | Events à la moitié de cycle | SPEC.3 (cron) |
| `app:shift:generate` | Génère les créneaux depuis les `Period` | SPEC.3 (+UI admin) |
| `app:shift:reminder` / `:send_alerts` / `:send_late_shifters` | Rappels/alertes créneaux | SPEC.3/SPEC.7 (cron) |
| `app:shift:free` / `:fix_missing_position` | Maintenance créneaux | SPEC.3 |
| `app:member:close` | Clôt les adhésions non renouvelées | SPEC.2/SPEC.5 (cron) |
| `app:user:mass_mail` | Envoi d'email aux membres | SPEC.7 |
| `app:import:users` | Import CSV membres+adhésions | SPEC.2 (+UI `user_import_csv`) |
| `app:anonymize` | Anonymisation RGPD | SPEC.2 / SEC |
| `app:beneficiary:randomise` | Tirage aléatoire de membres à jour | SPEC.2 |
| `app:doc` | « Doctor » : corrige des données (téléphones, statuts, adhésions) | **admin / maintenance** |
| `app:user:fix_*` / `:init_*`, `app:shiftfreelog:init_*` | Migrations/réparations de données ponctuelles | **admin / one-shot** |
| `app:helloasso:payment`, `…:update_payments` | Paiements Helloasso | SPEC.5 |
| `app:code:update_igloohome`, `:verify_change` | Codes/serrures | SPEC.4 / SPEC.8 |
| *(sans nom explicite)* `CsvCommand`, `CustomPurgerCommand` | Export CSV / purge fixtures | utilitaires |

**Observations** : `app:doc` et la famille `fix_*`/`init_*` sont des scripts de **réparation de données** (souvent destinés à un run unique post-migration) qui modifient la base sans dry-run ni garde-fou — à isoler/documenter comme outillage de maintenance (cross SYN.2). Aucune commande n'expose `--dry-run`.

#### Événements
| Événement | Déclencheur (SPEC.6) | Listener |
|-----------|----------------------|----------|
| `CommissionJoinOrLeaveEvent` (JOIN/LEAVE) | Ajout/retrait d'un bénéficiaire à une commission | `commission_leave_or_join_listener` (cross SPEC.7) |
| `PeriodPositionFreedEvent` | *cross SPEC.3* (libération de poste fixe) | — |

Le reste du domaine est principalement du CRUD sans dispatch d'événement.

#### Tests existants
- **`SmokeTest`** : couvre la présence (HTTP 200) de routes admin majeures sans assertions métier.
- **Aucun test fonctionnel/unitaire** sur les voters `TaskVoter`, `EmailTemplateVoter`, `ProcessUpdateVoter`, `DynamicContentVoter`, ni sur les CRUD de configuration.
- **Aucun test** sur `app:doc` ni les commandes `fix_*`/`init_*` (réparations de données non couvertes — risque élevé vu leur effet direct en base).

#### Gaps / Findings

**Sécurité** :
- 🟠 **Périmètre `access_control: ^/admin/` trompeur** : six entités d'administration (Commission, Service, Task, DynamicContent, EmailTemplate, ProcessUpdate) sont hors du préfixe `/admin/` et ne sont protégées que par leur `@Security`/voter par action. Tant que chaque action porte son annotation, c'est correct — mais c'est une **défense en profondeur absente** : l'oubli d'un `@Security` sur une nouvelle action de ces controllers ne serait pas rattrapé par le firewall. **TODO SYN.2** : soit déplacer ces routes sous `/admin/`, soit ajouter des règles `access_control` ciblées (`^/commissions`, `^/tasks`, `^/content`, `^/emailTemplate`, `^/process/updates`, `^/services`).
- 🟠 **`commission_add_beneficiary` / `commission_remove_beneficiary` sans `@Security`** : autorisation uniquement par check inline. Couplé au point précédent (hors `/admin/`), ces deux routes mutantes n'ont aucune protection déclarative — uniquement la logique impérative dans le corps de l'action.
- 🟡 **`commission_remove_beneficiary` lit la superglobale `$_POST`** au lieu de `Request` → contourne l'abstraction Symfony, pas de validation de présence de clé.

**Cohérence / dette** :
- 🟠 **Asymétrie des droits de suppression** non documentée : SUPER_ADMIN pour les uns, ADMIN pour les autres (OpeningHour/ClosingException), voter pour ProcessUpdate, **inexistante** pour DynamicContent/EmailTemplate. À uniformiser ou à justifier explicitement.
- 🟠 **Exécution de commandes console dans la requête HTTP** (`user_import_csv`, `admin_shifts_generation`) : anti-pattern (instanciation `Console\Application` + `BufferedOutput` en plein contrôleur). Bloquant, gourmand en mémoire, pas de feedback de progression. **TODO** : passer en job asynchrone (Messenger) ou commande hors-ligne.
- 🟡 **`DynamicContentVoter`** : le controller `DynamicContent` utilise `@Security('ROLE_PROCESS_MANAGER')` directement et n'appelle jamais le voter → voter potentiellement mort (cross D — dead code à confirmer : usage possible dans des templates).
- 🟡 **Listes admin non paginées** (`findAll()` direct) : acceptable tant que les volumes restent faibles (commissions, services, postes…), mais `DynamicContent`/`EmailTemplate`/`ProcessUpdate` peuvent croître. À surveiller.
- 🟡 **Aucune administration en UI des paramètres métier** : tout réglage profond exige une modification d'env + redéploiement (CONFIG.3). C'est un choix d'architecture assumé (12-factor) mais limite l'autonomie des coopératives non techniques — à signaler dans la doc fonctionnelle.

**Maintenance / données** :
- 🟠 **Commandes de réparation (`app:doc`, `fix_*`, `init_*`) sans garde-fou** : mutations de masse en base, sans `--dry-run`, sans transaction explicite documentée, sans test. À isoler comme outillage de migration et documenter leur usage (one-shot historique vs récurrent). **TODO SYN.2**.
- 🟡 **`ShiftExemption` : delete sans garde de FK** (`// TODO: error 500…`, cross SPEC.3) — supprimer un motif utilisé par une exemption membre lève une 500.

**Non testé** :
- Les quatre voters du domaine, l'ensemble des CRUD de configuration, et toutes les commandes de maintenance de données.

**Ambigu / à clarifier** :
- **`Task`** est-il réellement utilisé en production (Elefan/Scopeli) ? Outil collaboratif à faible visibilité, candidat au tracking runtime RT.1-2 avant toute décision.
- **Clients OAuth** : quelles intégrations réelles existent par instance (Service ↔ Client) ? À croiser avec SPEC.8.
- **DynamicContent/EmailTemplate sans suppression** : choix délibéré (conservation/audit) ou oubli ? À confirmer avec les mainteneurs.

