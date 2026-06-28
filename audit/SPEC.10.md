# SPEC.10 — Glossaire métier

- [x] **SPEC.10** — Glossaire métier

**Objet** : référentiel unique des termes du domaine — pour chacun : définition claire, entité Doctrine (et table) associée, renvoi vers la SPEC qui le traite. **Méthode** : consolidation des définitions déjà posées au fil de SPEC.2-8 (lexiques SPEC.2 « Membership/Beneficiary/User », SPEC.3 « Créneaux », table d'entités SPEC.2, matrices CONFIG.3/SPEC.9) croisée avec `src/Entity/` (38 entités). Rien n'est réinventé ici ; les renvois pointent vers la définition de référence. Les **nommages trompeurs** déjà relevés sont signalés ⚠️ et récapitulés en fin de section.

**Convention de lecture** : `Terme` — *(entité `Entity` / table `table_name`)* — définition. `(calculé)` = notion non persistée. `(config)` = levier de configuration, pas une entité.

---

<a id="SPEC.10-A"></a>
### A — Personnes, identité et adhésion

| Terme | Entité / table | Définition | Réf. |
|-------|----------------|------------|------|
| **Adhésion / compte adhérent** | `Membership` / `membership` | Unité de **cotisation et de facturation**. Porte le `member_number`, l'historique des `Registration`, le statut (gelé/volant/fermé), les créneaux et l'épargne de temps. Une adhésion regroupe 1..N bénéficiaires. | SPEC.2 |
| **Bénéficiaire** | `Beneficiary` / `beneficiary` | **Personne physique** rattachée à une adhésion (nom, prénom, téléphone, adresse). Relation N-1 vers `Membership` ; l'un des bénéficiaires est le **principal** (`mainBeneficiary`, fallback automatique sur le 1er de la collection — `Membership.php:328`). C'est le `Beneficiary` qui réalise les créneaux (= **shifter**) et porte badges/commissions/formations. | SPEC.2 |
| **Compte de connexion** | `User` (FOSUserBundle) / `fos_user` | Identifiants : email/username, mot de passe, rôles. Relation **1-1** avec `Beneficiary` (NOT NULL). ⚠️ Table nommée `fos_user` conservée même après migration FOSUser→SF5 prévue (SF-PREP T2) pour éviter une migration DB. | SPEC.4 |
| **Pré-inscrit / bénéficiaire anonyme** | `AnonymousBeneficiary` / `anonymous_beneficiary` | Personne en **attente de finalisation d'adhésion** : seul l'email est connu, pas encore de `User`/`Beneficiary`. Transformé en adhésion via lien d'invitation (code Vigenère). Champs `amount`/`mode` = cotisation pré-saisie ; `recallDate` = relance. | SPEC.2 |
| **Numéro d'adhérent** | champ `Membership.member_number` (bigint) | Identifiant métier humain-lisible de l'adhésion, attribué par `max(member_number)+1` (⚠️ calcul **non atomique** dans le controller — AP.1, race condition possible). Sert de clé d'URL (`member_show`) et d'onboarding (`find_member_number`). | SPEC.2 |
| **Volant (flying) / Fixe** | champ `flying` sur `Beneficiary` **ou** `Membership` (config) | **Volant** = bénévole sans créneau attitré, qui réserve librement chaque cycle. **Fixe** = créneau récurrent attitré via `PeriodPosition.shifter`. L'entité qui *porte* le statut est déterminée par `FLY_AND_FIXED_ENTITY_FLYING` (`Beneficiary` par défaut, ou `Membership`). N'a de sens que si `USE_FLY_AND_FIXED=true` (Elefan probable ; cf. SPEC.9 🟡). | SPEC.3, CONFIG.3 |
| **Ambassadeur** | (rôle/usage, pas d'entité) — `AmbassadorController` | Bénévole référent qui suit les retardataires et les volants sans poste : `ambassador_shifttimelog_list`, `ambassador_beneficiary_fixe_without_periodposition_list`, relances email (`AmbassadorShiftTimeLogCommand`). | SPEC.3 |
| **Note / post-it** | `Note` / `note` | Commentaire interne sur une adhésion (`subject`→`Membership`, nullable), arborescent (self-ref `parent`/`children` pour les réponses). Auteur = `User`. | SPEC.2 |

---

<a id="SPEC.10-B"></a>
### B — Créneaux et planning

| Terme | Entité / table | Définition | Réf. |
|-------|----------------|------------|------|
| **Créneau** | `Shift` / `shift` | **Occurrence temporelle datée** d'une tâche bénévole (date + heure précises, durée). États : libre, réservé, pré-réservé, validé. Porte `fixe` (bool), `job`, `shifter` (Beneficiary), `booker` (User). | SPEC.3 |
| **Créneau type / modèle** | `Period` / `period` | Modèle **récurrent hebdomadaire** (jour de semaine, plage horaire, `job`). Sert à *générer* les `Shift` futurs (`admin_shifts_generation`). ⚠️ Le lexique SPEC.3 le qualifiait de « Slot » — préférer « Période / créneau type » pour éviter la confusion avec « slot » au sens place libre. | SPEC.3 |
| **Poste** | `PeriodPosition` / `period_position` | Une place de bénévole au sein d'un `Period` : 1 `Period` → N `PeriodPosition` (N bénévoles simultanés). Porte optionnellement un `weekCycle` (A/B/C/D), une `formation` requise, et un `shifter` attitré (mode fixe). | SPEC.3 |
| **Bucket** | `ShiftBucket` *(calculé, non persisté)* | DTO UI regroupant les `Shift` partageant le même horaire (start+end) et le même `Job`. Construit à la volée dans les controllers (⚠️ duplication `ShiftController:692`/`WidgetController:45` — AP). Sert l'affichage du planning de réservation. | SPEC.3 |
| **Shifter** | rôle de `Beneficiary` (champ `Shift.shifter`) | Le bénéficiaire **qui réalise** le créneau. | SPEC.3 |
| **Booker** | rôle de `User` (champ `Shift.booker`) | L'utilisateur **qui a enregistré** la réservation — peut différer du shifter (réservation par un tiers/admin). | SPEC.3 |
| **Cycle** | *(calculé)* | Période de bénévolat de référence (28 jours). Deux modes via `CYCLE_TYPE` : `abcd` = semaines A/B/C/D **synchrones** alignées sur la semaine ISO (toute la coop en phase) ; sinon = cycles **flottants** propres à chaque membre depuis `Membership.firstShiftDate`. ⚠️ Durée hardcodée `28` en 5 points de `MembershipService` malgré `CYCLE_DURATION` configurable — bug **dormant** si `CYCLE_TYPE=abcd` (CONFIG.3 🔴). | SPEC.3, CONFIG.3 |
| **Semaine ABCD** | champ `PeriodPosition.weekCycle` | Phase A/B/C/D d'un poste en mode `CYCLE_TYPE=abcd` ; filtre les positions actives selon la semaine ISO courante. | SPEC.3 |
| **Exemption (adhésion)** | `MembershipShiftExemption` / `membership_shift_exemption` | Plage de dates où **un membre donné** est dispensé de bénévolat (maladie, congé). Référence un type `ShiftExemption`. | SPEC.3 |
| **Type d'exemption** | `ShiftExemption` / `shift_exemption` | Référentiel des motifs d'exemption (name + description), réutilisable. | SPEC.3, SPEC.6 |
| **Alerte créneau** | `ShiftAlert` *(DTO non persisté)* | Signalement de sous-effectif sur un **bucket** de créneaux (`bucket` + `issue`), construit à la volée à partir de `job.min_shifter_alert` et notifié via `onShiftAlerts`. Comme `ShiftBucket`, vit dans `src/Entity/` mais sans mapping ORM. | SPEC.3, SPEC.7 |

---

<a id="SPEC.10-C"></a>
### C — Temps de bénévolat et comptabilité de cycle

| Terme | Entité / table | Définition | Réf. |
|-------|----------------|------------|------|
| **TimeLog** | `TimeLog` / `time_log` | **Journal d'entrées de temps** d'une adhésion. `type` (constantes) distingue : `TYPE_SHIFT_VALIDATED`(1)/`INVALIDATED`(10) (présence créneau), `TYPE_CYCLE_END`(2) et déclinaisons (`_FROZEN`/3, `_EXPIRED_REGISTRATION`/4, `_EXEMPTED`/6, `_SAVING`/7) (bilan de fin de cycle), `TYPE_SAVING`(20)/`TYPE_SHIFT_FREED_SAVING`(21) (épargne), `TYPE_REGULATE_OPTIONAL_SHIFTS`(5), `TYPE_CUSTOM`(0) (ajustement manuel admin). Porte aussi `requestRoute` (route d'origine, traçabilité). | SPEC.3 |
| **Épargne de temps (time saving / time banking)** | `TimeLog` types `*_SAVING` | Solde de temps capitalisable : l'excédent d'un cycle est reversé en épargne, mobilisable pour compenser un déficit ou libérer un créneau. Activée par `USE_TIME_LOG_SAVING=true` (instance-specific). Règles d'annulation : délai min (`TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS`) + solde suffisant (`..._ALLOW_ONLY_IF_ENOUGH_SAVING`). | SPEC.3, CONFIG.3 |
| **Bilan de cycle (cycle accounting)** | *(logique)* — `TimeLogEventListener` | Calcul de fin/début de cycle : soustraction de la cotisation due (`DUE_DURATION_BY_CYCLE`), redistribution de l'excédent en épargne, compensation des déficits selon éligibilité (créneaux ratés, libérations tardives). ⚠️ Logique comptable embarquée dans un listener (420 l. — AP.7, cible d'extraction `CycleAccountingService`). | SPEC.3 |
| **Shift free log** | `ShiftFreeLog` / `shiftfreelog` | Journal d'audit des **libérations de créneaux** : `shiftString` (libellé figé du créneau au moment de la libération), origine, flag de validité. | SPEC.3 |
| **PeriodPosition free log** | `PeriodPositionFreeLog` / `period_position_free_log` | Équivalent pour la libération d'un **poste fixe** attitré (`PeriodPosition.shifter` retiré). | SPEC.3 |
| **Retardataire (« en retard »)** | *(calculé)* — seuil `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS` | Membre dont le **solde de temps** est inférieur à N heures (N négatif = dette tolérée). ⚠️ **Nom trompeur** : suggère un délai après créneau, alors que c'est un seuil de solde comparé `SUM(timelog.time) < N×60` (CONFIG.3). | SPEC.3, CONFIG.3 |

---

<a id="SPEC.10-D"></a>
### D — Cotisations et paiements

| Terme | Entité / table | Définition | Réf. |
|-------|----------------|------------|------|
| **Cotisation / adhésion (paiement)** | `Registration` / `registration` | Un **paiement de cotisation** rattaché à une `Membership`. `mode` ∈ {CASH, CHECK, LOCAL, CREDIT_CARD, HELLOASSO, DEFAULT}. Lien 1-1 optionnel vers `HelloassoPayment`. `registrar` = User ayant saisi. | SPEC.5 |
| **Registration agrégée** | `AbstractRegistration` / **vue SQL** `view_abstract_registration` | Entité **read-only** mappée sur une vue SQL, utilisée pour les totaux par mode/date (`RegistrationsController`). `type` : 1=`TYPE_MEMBER`, 2=`TYPE_ANONYMOUS`. ⚠️ Requête sous-jacente et origine des enregistrements `TYPE_ANONYMOUS` à documenter (EXTRA SPEC.5 ; probablement achats caisse sans adhésion). | SPEC.5 |
| **Paiement Helloasso** | `HelloassoPayment` / `helloasso_payment` | Transaction importée du PSP Helloasso (webhook `helloasso_notify` + `UpdateHelloAssoPaymentsCommand`). Rapprochée d'un `User` par email (`linkPaymentToUser`) ; les paiements non rapprochés = **orphelins** (`helloasso_resolve_orphan`). Instance-specific (Elefan ; Scopeli à confirmer SPEC.9). | SPEC.5, SPEC.8 |
| **Réadhésion** | *(flux)* — event `HelloassoEvent::RE_REGISTRATION_SUCCESS` | Renouvellement de cotisation d'un membre existant. ⚠️ **Nom trompeur** : la constante vaut `helloasso.registration_success` et couvre **aussi la première adhésion** ; seule la branche `registrations.count > 1` dans `onHelloassoRegistrationSuccess` distingue réadhésion (`reregistration.html.twig`) et 1re adhésion (SPEC.7). | SPEC.5, SPEC.7 |

---

<a id="SPEC.10-E"></a>
### E — Contrôle d'accès physique

| Terme | Entité / table | Définition | Réf. |
|-------|----------------|------------|------|
| **Badge / Swipe (carte)** | `SwipeCard` / `swipe_card` | **Carte NFC** rattachée à un `Beneficiary` (N-1). Sert au pointage de présence (`card_reader_check`) et au **login passwordless** (`swipe_in`). `number` = identifiant carte, `code` = secret généré, `enable`/`disabled_at` = cycle de vie. Un bénéficiaire peut avoir plusieurs cartes (rotation/perte). | SPEC.3, SPEC.4 |
| **Swipe log** | `SwipeCardLog` / `swipe_card_log` | Journal des passages de badge (date), rattaché à une `SwipeCard`. | SPEC.4 |
| **Code (d'accès physique)** | `Code` / `code` | **Code d'ouverture de porte** (serrure connectée Igloohome). `value` = le code, `closed` = périmé/remplacé. Renouvelé via `code_generate`/`UpdateIgloohomeCodeCommand`. ⚠️ À ne pas confondre avec : le `SwipeCard.code` (secret badge), le code Vigenère d'invitation (onboarding pré-inscrit), ou le `_token` CSRF. Igloohome est instance-specific (SPEC.9). | SPEC.6, SPEC.8 |
| **Fenêtre de badge** | *(calculé)* — `CodeVoter:130-132` | Plage d'autorisation d'accès autour d'un créneau (−120 min après fin / +60 min avant début). ⚠️ Valeurs hardcodées + dupliquées (`UserVoter`/`MembershipVoter` `isLocationOk()` — D.2), devraient être en config (CONFIG.3). | SPEC.4, CONFIG.3 |

---

<a id="SPEC.10-F"></a>
### F — Organisation coopérative (référentiels)

Cinq entités voisines, souvent confondues. Distinction par **finalité** :

| Terme | Entité / table | Définition — ce qui le distingue | Réf. |
|-------|----------------|----------------------------------|------|
| **Job (poste de travail)** | `Job` / `job` | **Nature du travail réalisé sur un créneau** (caisse, accueil…). Porte `color` (affichage planning), `min_shifter_alert` (seuil sous-effectif). 1 `Job` → N `Shift`/`Period`. C'est l'axe *opérationnel* du bénévolat. | SPEC.6, SPEC.3 |
| **Formation** | `Formation` / `formation` | **Qualification/habilitation** d'un bénéficiaire (N-N `Beneficiary`). ⚠️ Double rôle : sert aussi de **groupe FOSUser** (`roles[]`, `group_class: App\Entity\Formation`, `User::getGroups()`) → porte des rôles applicatifs. Une `PeriodPosition` peut exiger une formation. | SPEC.6, SPEC.4 |
| **Commission** | `Commission` / `commission` | **Groupe de travail thématique** de la coop (N-N `Beneficiary` membres, `owners` référents, `email`, `next_meeting_*`). Axe *gouvernance/organisation interne*, sans lien aux créneaux. Porte des `Task`. | SPEC.6 |
| **Task (tâche)** | `Task` / `task` | **Action à faire** rattachée à des `Commission` (N-N) et des `owners` (Beneficiary). `priority` (5 niveaux URGENT→ANNEXE), `status`, `dueDate`, `closed`. Outil de suivi de TODO interne, sans rapport avec les créneaux. | SPEC.6 |
| **Service** | `Service` / `service` | **Service applicatif externe** présenté aux adhérents (nom, `icon`/`logo`, `slug`, `url`, `public`). 1 `Service` → N `Client` (OAuth). C'est une **brique du portail/SSO**, pas une activité bénévole. ⚠️ Homonymie avec les `*Service` PHP (couche métier) — sans rapport. | SPEC.6, SPEC.8 |

---

<a id="SPEC.10-G"></a>
### G — Gouvernance et assemblées générales *(détail en SPEC.11)*

| Terme | Entité / table | Définition | Réf. |
|-------|----------------|------------|------|
| **Événement** | `Event` / `event` | Événement associatif (typiquement une **AG**). Porte `kind`, `date`, `description`, image. 1 `Event` → N `Proxy`. | SPEC.11 |
| **Type d'événement** | `EventKind` / `event_kind` | Référentiel des catégories d'événements (name). | SPEC.11, SPEC.6 |
| **Procuration** | `Proxy` / `proxy` | Mandat de représentation à un `Event` : `giver` (`Membership` représentée) → `owner` (`Beneficiary` mandataire) → `event`. Plafond `MAX_EVENT_PROXY_PER_MEMBER` (défaut 1). ⚠️ Liste globale `Proxy::findAll()` sans pagination — N+1 et croissance non bornée (PERF cas #2). | SPEC.11 |

---

<a id="SPEC.10-H"></a>
### H — Contenu, configuration et référentiels transverses

| Terme | Entité / table | Définition | Réf. |
|-------|----------------|------------|------|
| **Contenu dynamique** | `DynamicContent` / `dynamic_content` | Bloc de contenu éditable (clé → HTML) injecté dans les pages publiques/templates, modifiable par l'admin sans déploiement. | SPEC.6 |
| **Modèle d'email** | `EmailTemplate` / `email_template` | Gabarit d'email éditable par l'admin (objet + corps), distinct des templates Twig transactionnels. | SPEC.6, SPEC.7 |
| **Horaire d'ouverture** | `OpeningHour` / `opening_hour` + `OpeningHourKind` / `opening_hour_kind` | Plages horaires d'ouverture du magasin par type (`kind`). Référentiel affiché publiquement. | SPEC.6 |
| **Exception de fermeture** | `ClosingException` / `closing_exception` | Date de fermeture exceptionnelle (jour férié, événement) avec libellé. | SPEC.6 |
| **Note de version** | `ProcessUpdate` / `process_update` | Annonce de changement applicatif affichée aux utilisateurs, avec compteur de lecture (`process_update_count_unread`). | SPEC.6 |
| **Réseau social** | `SocialNetwork` / `social_network` | Lien réseau social affiché (name, url, icône, `enabled`). | SPEC.6 |
| **Client OAuth** | `Client` / `client` | Application cliente du serveur OAuth/OIDC (FOSOAuthServer), rattachée à un `Service`. Brique SSO inter-applications de la coop. | SPEC.8 |

---

### Récapitulatif — nommages trompeurs et pièges de terminologie

Consolidation des ambiguïtés signalées ⚠️ ci-dessus et au fil de l'audit. À corriger (renommage/doc) ou au minimum à connaître :

| Terme / symbole | Piège | Réalité | Réf. |
|-----------------|-------|---------|------|
| `RE_REGISTRATION_SUCCESS` | « réadhésion » | Vaut `helloasso.registration_success`, couvre **aussi la 1re adhésion** ; distinction par `count>1` dans le listener, pas dans l'event | SPEC.5/7, EXTRA |
| `CycleStartCommand` (`app:user:cycle_start`) | « démarrer le cycle » | Dispatche `MemberCycleEndEvent` (fin) ; c'est `onMemberCycleEnd()` qui **re-dispatche** `MemberCycleStartEvent` en interne (chaîne implicite listener→listener, ordre-dépendante) | SPEC.3, AP.7 |
| `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS` | « délai après lequel… » | **Seuil de solde** de temps (heures, négatif = dette), comparé `SUM(time) < N×60`, pas une durée post-créneau | CONFIG.3 |
| `CYCLE_DURATION` | configurable | **Ignoré** : `28` hardcodé en 5 points de `MembershipService` ; bug dormant si `CYCLE_TYPE=abcd` | CONFIG.3 |
| `Service` (entité) | couche métier | Entité = **service applicatif du portail SSO** ; sans rapport avec les classes PHP `*Service` | SPEC.6 |
| `Formation` | simple qualification | Sert **aussi** de groupe FOSUser porteur de rôles (`group_class`) | SPEC.4/6 |
| `Code` (entité) | code générique | **Code de porte Igloohome** uniquement ; ≠ `SwipeCard.code`, ≠ code Vigenère d'invitation, ≠ `_token` CSRF | SPEC.6/8 |
| `Period` / « Slot » | place libre | **Modèle récurrent** de créneau, pas une place disponible | SPEC.3 |
| `fos_user` (table) | bundle FOSUser | Nom de table **conservé** post-migration SF5 pour éviter une migration DB | SPEC.4 |
| `Booker` vs `Shifter` | synonymes | `Booker` (`User`) **réserve** ; `Shifter` (`Beneficiary`) **réalise** — peuvent différer | SPEC.3 |

