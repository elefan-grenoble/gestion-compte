# SPEC.11 — Spec : Gouvernance & Assemblées générales

- [x] **SPEC.11** — Spec : Gouvernance & Assemblées générales

**✅ Décision (session 52)** : domaine H (gap SPEC.1) traité dans une spec dédiée plutôt que dilué dans SPEC.6.
Sources lues : `EventController` (441 l.), `AdminEventController` (494 l.), `AdminEventKindController` (126 l.) ; entités `Event` (698 l.), `EventKind`, `Proxy` ; `EventService`, `EventRepository`, `ProxyRepository` (vide), `EventExtension` (Twig) ; forms `EventType`, `ProxyType`, `EventKindType` ; event `EventProxyCreatedEvent` + listener `EmailingEventListener::onEventProxyCreated` (AP.7) ; relations `Membership::given_proxies` / `Beneficiary::received_proxies`.
Croisé avec : SPEC.10 cluster G (giver/owner/event), PERF cas #2 (`Proxy::findAll()` N+1, voir [PERF.2](audit/PERF.2.md)), AP.7 (emailing event-driven), CONFIG.3 (`max_event_proxy_per_member`, `registration_duration`), SPEC.2 (Membership/Beneficiary), SPEC.6 (CRUD admin).

---

## SPEC.11 — Gouvernance & Assemblées générales (domaine H)

### Vue d'ensemble

Ce domaine gère les **événements associatifs** (typiquement les **assemblées générales**) et le système de **procurations** (proxies) permettant à un membre absent de se faire représenter pour voter. Il était **absent du plan SPEC initial** (gap 1 de SPEC.1) et fait l'objet d'une spec dédiée.

Trois entités, trois controllers, deux niveaux d'accès (membre / admin). Le cœur métier n'est **pas** la gestion d'événements (CRUD simple) mais la **mécanique d'appariement des procurations** et les **règles d'éligibilité au vote**.

### Vocabulaire essentiel (rappel SPEC.10 cluster G — sens contre-intuitif)

| Terme | Entité / champ | Réalité |
|-------|----------------|---------|
| **Donneur (giver)** | `Proxy.giver` → `Membership` | L'adhésion **représentée** : le membre **absent** qui délègue son vote. ⚠️ pointe une `Membership`, pas un `Beneficiary`. |
| **Porteur (owner)** | `Proxy.owner` → `Beneficiary` | Le **mandataire présent** qui **votera** au nom du donneur. ⚠️ pointe un `Beneficiary`, pas une `Membership`. |
| **Procuration (proxy)** | `Proxy` | Le mandat liant `giver` → `owner` pour un `event`. Peut exister **incomplet** (giver sans owner, ou owner sans giver) en attente d'appariement. |
| **Événement** | `Event` | L'AG (ou autre). Porte `need_proxy`, `anonymous_proxy`, `max_date_of_last_registration`. |

Sens mnémotechnique : **« je donne (giver) ma voix → tu la portes (owner) »**.

### Acteurs

- **Membre** (`ROLE_USER`) : consulte les événements, donne / reçoit / annule une procuration.
- **Gestionnaire** (`ROLE_PROCESS_MANAGER`) : CRUD événements et types, consultation des procurations, génération de la liste d'émargement, générateur de widget.
- **Admin** (`ROLE_ADMIN`) : suppression d'événements et de types.
- **Super-admin** (`ROLE_SUPER_ADMIN`) : édition / suppression manuelle d'une procuration (appariement à la main).
- **Public** (aucun garde) : widget événements embarquable + ⚠️ détail d'un événement (voir gaps).

### Instances

Toutes. Comportement modulé par configuration (voir Données → config) :
- `MAX_EVENT_PROXY_PER_MEMBER` (défaut **1** dans tous les `.env*`) : nombre max de procurations qu'un porteur peut accepter par événement.
- `REGISTRATION_DURATION` (défaut **`'1 year'`**) : fenêtre d'éligibilité au vote (adhésion à jour).
- Par événement : `need_proxy` (active le système de procurations), `anonymous_proxy` (autorise les procurations anonymes).

### Flux principal

**1. Création de l'événement (gestionnaire)**
`admin_event_new` → `EventType`. À la **création**, seuls les champs de base sont exposés (titre, type, dates, lieu, description, image, mise en avant). Les champs de gouvernance (`need_proxy`, `anonymous_proxy`, `max_date_of_last_registration`) n'apparaissent **qu'en édition** (`admin_event_edit`) — cf. `EventType` : `if ($userData && $userData->getId())`. ⚠️ Un événement AG nécessite donc **deux étapes** (créer puis éditer) pour activer les procurations.

**2. Don d'une procuration (membre absent)**
`event_proxy_give` (`GET|POST /events/{id}/proxy/give`). Le membre :
- soit soumet le formulaire vide → **réutilise** un proxy en attente (`findOneBy(event, giver=null)`, l.177) ou en crée un, puis y inscrit son `giver`. Si le proxy réutilisé avait déjà un `owner` (déposé par un porteur via `take`), il devient **complet immédiatement** et déclenche `EventProxyCreatedEvent` (l.188-190) ; sinon il reste en attente d'un porteur ;
- soit recherche un porteur par prénom via `event_proxy_find_beneficiary` (`POST`), choisit un bénéficiaire, et confirme via `ProxyType` → proxy complet (giver + owner).

**3. Prise d'une procuration (membre présent)**
`event_proxy_take` (`GET|POST /events/{id}/proxy/take`). Le porteur récupère un proxy **en attente** (owner null) et se déclare porteur via `ProxyType`.

**4. Notification**
Dès qu'un proxy a **à la fois** un giver et un owner, dispatch `EventProxyCreatedEvent` (`event.proxy.created`) → `EmailingEventListener::onEventProxyCreated` envoie **2 emails** : `emails/proxy_owner.html.twig` (au porteur) et `emails/proxy_giver.html.twig` (au donneur), chacun avec `replyTo` croisé. Cf. AP.7.

**5. Émargement (jour de l'AG)**
`admin_event_signatures` → liste imprimable des bénéficiaires **éligibles au vote** (non démissionnaires, adhésion dans la fenêtre), triée par nom. Sert de feuille d'émargement.

**6. Gestion / correction (admin)**
`admin_proxies_list` (toutes), `admin_event_proxies_list` (par événement), `admin_event_proxy_edit` / `admin_event_proxy_delete` (SUPER_ADMIN) pour apparier ou corriger les procurations incomplètes à la main.

### Règles métier

1. **Unicité du don** : un membre ne peut donner **qu'une** procuration par événement (`findOneBy(event, giver=membership)` rejette le doublon). Vérifié dans `give` et `take`.
2. **Plafond de portage** : un porteur accepte au plus `max_event_proxy_per_member` (défaut 1) procurations par événement (comptage sur **tous les bénéficiaires de l'adhésion** du porteur).
3. **Pas de cumul don+réception** : si un membre a déjà *reçu* une procuration, il ne peut pas en *donner* (et les proxies « en attente » sans donneur le concernant sont nettoyés). Cf. `giveProxyAction` l.119-141.
4. **Éligibilité au vote (donneur ET porteur)** : l'adhésion doit avoir une `lastRegistration` :
   - **après** `max_date_of_last_registration − registration_duration` (adhésion pas trop ancienne), **et**
   - **valide avant** `max_date_of_last_registration` (`hasValidRegistrationBefore`).
   `Event::getMaxDateOfLastRegistration()` retombe sur `date` (date de l'événement) si non renseignée.
5. **Exclusion des démissionnaires** : `m.withdrawn != 1` dans la recherche de porteurs et l'émargement.
6. **Exclusion des retardataires de créneaux** : `event_proxy_find_beneficiary` filtre en PHP les bénéficiaires dont `getShiftTimeCount() <= time_after_which_members_are_late_with_shifts × 60` (note CONFIG.3 : ce paramètre est un **seuil de solde d'heures**, pas une durée). Un message prévient que la liste est filtrée.
7. **Pas de procuration sur événement passé** : `giveProxyAction` rejette si `Event::getIsPast()`.
8. **Auto-exclusion** : la recherche de porteur exclut l'adhésion du demandeur (`m != :current_member`).

### Données

**`Event`** (`event`) — `EventRepository` :
- `title` (NotBlank), `kind` (`EventKind`, **fetch EAGER**, `onDelete SET NULL`), `description` (text nullable), `date` (NotNull), `end` (nullable), `location` (nullable), image Vich (`imgFile`/`img`/`imgSize`).
- Gouvernance : `max_date_of_last_registration` (nullable → fallback `date`), `need_proxy` (bool nullable, défaut 0), `anonymous_proxy` (bool nullable, défaut 0).
- `displayedHome` (bool, défaut 0, **NOT NULL**), `proxies` (OneToMany, cascade persist+remove), audit `createdAt`/`createdBy`/`updatedAt`/`updatedBy` (User).
- Logique : `getIsOngoing()`, `getIsPast()`, `getDuration()` (formatage FR), `isStartBeforeEnd()` (Assert), filtres collection `getProxiesByOwner()` / `getProxiesByGiver()` / `getProxiesByOwnerMembershipMainBeneficiary()`.
- Requêtes : `findFutures` / `findOngoing` / `findPast(limit)` / `findFutureOrOngoing` / `findAllDisplayedHome`. `findAll()` est **surchargée** (tri `date DESC`).

**`EventKind`** (`event_kind`) — référentiel borné (~3 lignes, données de config, cf. PERF.2) : `name` (NotBlank), `events` (OneToMany), `__toString = name`.

**`Proxy`** (`proxy`) — `ProxyRepository` **vide** :
- `event` (ManyToOne, `onDelete CASCADE`), `owner` (`Beneficiary`, `onDelete CASCADE`), `giver` (`Membership`, `onDelete CASCADE`), `createdAt` (PrePersist).
- **Aucune méthode de repository** : toute la logique de requête proxy est éparpillée — inline dans `EventController`, dans `EventService` (2 méthodes), et dans les filtres de collection de `Event`.

**Relations inverses** : `Membership::given_proxies` (mappedBy `giver`), `Beneficiary::received_proxies` (mappedBy `owner`).

**Service / Twig** : `EventService` (`getGivenProxyOfMembershipForAnEvent`, `getReceivedProxiesOfBeneficiaryForAnEvent`) exposé en Twig via `EventExtension` (filtres `|givenProxy`, `|receivedProxies`).

**Config** (`config/services.yaml`, `.env*`) : `max_event_proxy_per_member` (`MAX_EVENT_PROXY_PER_MEMBER`=1), `registration_duration` (`REGISTRATION_DURATION`='1 year'), `time_after_which_members_are_late_with_shifts`. Les deux premiers exposés à Twig (`twig.yaml`).

### Routes (22 — ⚠️ SPEC.1 estimait ~16 pour le domaine H)

| Route | Méthode | Chemin | Garde | Controller |
|-------|---------|--------|-------|------------|
| `event_widget` | GET | `/events/widget` | **aucune (public)** | `EventController` |
| `event_index` | GET | `/events/` | `ROLE_USER` | `EventController` |
| `event_detail` | GET | `/events/{id}` | **aucune (public)** ⚠️ | `EventController` |
| `event_proxy_give` | GET/POST | `/events/{id}/proxy/give` | `ROLE_USER` | `EventController` |
| `event_proxy_find_beneficiary` | POST | `/events/{id}/proxy/find_beneficiary` | `ROLE_USER` | `EventController` |
| `event_proxy_take` | GET/POST | `/events/{id}/proxy/take` | `ROLE_USER` | `EventController` |
| `event_proxy_lite_delete` | **GET** ⚠️ | `/events/{id}/proxy/{proxy}/remove` | `ROLE_USER` | `EventController` |
| `admin_event_index` | GET | `/admin/events/` | `ROLE_PROCESS_MANAGER` | `AdminEventController` |
| `admin_event_list` | GET/POST | `/admin/events/list` | `ROLE_PROCESS_MANAGER` | `AdminEventController` |
| `admin_event_new` | GET/POST | `/admin/events/new` | `ROLE_PROCESS_MANAGER` | `AdminEventController` |
| `admin_event_edit` | GET/POST | `/admin/events/{id}/edit` | `ROLE_PROCESS_MANAGER` | `AdminEventController` |
| `admin_event_delete` | DELETE | `/admin/events/{id}` | `ROLE_ADMIN` | `AdminEventController` |
| `admin_proxies_list` | GET | `/admin/events/proxies` | `ROLE_PROCESS_MANAGER` | `AdminEventController` |
| `admin_event_proxies_list` | GET | `/admin/events/{id}/proxies` | `ROLE_PROCESS_MANAGER` | `AdminEventController` |
| `admin_event_proxy_edit` | GET/POST | `/admin/events/{id}/proxies/{proxy}` | `ROLE_SUPER_ADMIN` | `AdminEventController` |
| `admin_event_proxy_delete` | DELETE | `/admin/events/{id}/proxies/{proxy}` | `ROLE_SUPER_ADMIN` | `AdminEventController` |
| `admin_event_signatures` | GET/POST | `/admin/events/{id}/signatures/` | `ROLE_PROCESS_MANAGER` | `AdminEventController` |
| `admin_event_widget_generator` | GET/POST | `/admin/events/widget_generator` | `ROLE_PROCESS_MANAGER` | `AdminEventController` |
| `admin_event_kind_list` | GET | `/admin/events/kinds/` | `ROLE_PROCESS_MANAGER` | `AdminEventKindController` |
| `admin_event_kind_new` | GET/POST | `/admin/events/kinds/new` | `ROLE_PROCESS_MANAGER` | `AdminEventKindController` |
| `admin_event_kind_edit` | GET/POST | `/admin/events/kinds/{id}/edit` | `ROLE_PROCESS_MANAGER` | `AdminEventKindController` |
| `admin_event_kind_delete` | DELETE | `/admin/events/kinds/{id}` | `ROLE_ADMIN` | `AdminEventKindController` |

### Tests existants

Couverture **quasi nulle sur le métier** :
- `tests/Functional/Controller/SmokeTest.php` : seulement des smoke-tests HTTP — `event_widget` (200 public), `event_index` (redirect login + 200 user), `admin_event_index` / `admin_event_list` / `admin_event_kind_list` (200 admin).
- `tests/Unit/Entity/MembershipTest.php` : `testAddAndRemoveGivenProxy` (collection d'entité uniquement, via réflexion).
- **Non testé** : tous les flux de procuration (give / take / find / lite_delete), l'appariement admin (`editEventProxyAction`), l'émargement, les règles d'éligibilité au vote, le plafond `max_event_proxy_per_member`, l'unicité du don, la notification email.

### Cas limites & Gaps

🐛 **Bugs confirmés** (→ EXTRA / SYN.2) :
1. **`acceptProxyAction` (`EventController` l.418-420)** : `findBy()` retourne un **array**, puis `$myproxy->getOwner()->getFirstname()` appelle une méthode **sur un array** → `Error: Call to a member function getOwner() on array`. Chemin atteint quand le porteur dépasse déjà le plafond. **Crash en production** dans le message d'erreur censé être informatif.
2. **`deleteProxyLiteAction` (`EventController` l.358)** : `$proxy->getOwner()->getUser()` sans null-guard → NPE si le proxy est **en attente** (owner null). Un membre supprimant un proxy anonyme via URL forgée déclenche l'erreur.
3. **`Event::getProxiesByOwnerMembershipMainBeneficiary()` (l.432-436)** : `$proxy->getOwner()->getMembership()->getMainBeneficiary()` sans null-guard → NPE sur les proxies en attente (owner null) présents dans la collection. (À distinguer de `getProxiesByOwner()` l.424-429 qui fait `$proxy->getOwner() === $beneficiary` — comparaison d'identité, sûre même si owner est null.)
4. **`EventExtension::receivedProxies()`** : signature `: array` mais `return null` si pas d'utilisateur connecté → `TypeError`. (Incohérent avec `givenProxy()` typé `: ?Proxy`.)

🔒 **Sécurité** (→ SYN.2) :
5. **`event_detail` (`GET /events/{id}`) sans `@Security`** alors que `event_index` exige `ROLE_USER` : le détail d'un événement (titre, description, date, **lieu**, image de l'AG) est **public**, incohérent avec la liste protégée. Fuite d'information. Ajouter `ROLE_USER` ou documenter l'intention publique.
6. **`event_proxy_lite_delete` en verbe GET sans CSRF** : suppression d'une procuration rejouable par simple lien. Passer en POST/DELETE + token. (Cohérent avec les findings GET-mutant SPEC.3/SPEC.4.)

🧹 **Dette / cohérence** (→ SYN.2) :
7. **PERF cas #2 (déjà tracé, voir [PERF.2](audit/PERF.2.md))** : `admin_proxies_list` appelle `Proxy::findAll()` sans pagination ni `JOIN FETCH` → N+1 (chaque proxy lazy-load event/owner/giver) et croissance non bornée (~100-500/an). Correctif : `ProxyRepository::findAllWithAssociations()` (JOIN FETCH) + `Paginator` (25/page), à l'image de `admin_event_list`.
8. **`ProxyRepository` vide → logique éparpillée** : les requêtes proxy vivent inline dans `EventController` (≥ 8 `findOneBy`/`findBy` répétés), dans `EventService` (2 méthodes pure délégation query builder, 47 l.) et dans les filtres de collection de `Event`. Candidat à **consolider dans `ProxyRepository`** (`findGivenBy`, `findReceivedBy`, `findWaiting`, `findAllWithAssociations`). `EventService` deviendrait une fine façade ou disparaîtrait.
9. **`editEventProxyAction` (`AdminEventController` l.265-347)** : ~80 lignes, 4 branches d'appariement quasi dupliquées, **flash messages en anglais** exposés à l'admin (« proxy 12 saved », « proxy 7 deleted ») — incohérent avec l'UI FR et avec la règle de langue. Candidat refactor + i18n.
10. **Création d'AG en deux temps** : `need_proxy` / `anonymous_proxy` / `max_date_of_last_registration` indisponibles à la création (`EventType` ne les ajoute que si `getId()`). UX piégeuse pour créer une AG. À documenter a minima.
11. **`anonymous_proxy` : sémantique floue** : le champ existe et gère des proxies « en attente » (giver ou owner null), mais aucune logique distincte évidente entre `anonymous_proxy=true/false` dans les controllers — la branche anonyme de `giveProxyAction` (form vide) ne vérifie pas `$event->getAnonymousProxy()`. À clarifier : le flag est-il réellement appliqué, ou dead config ? (À confirmer côté templates/EXTRA.)
12. **`getLastRegistration()->getDate()` sans null-guard** (`give`/`take`) : NPE potentielle si une adhésion sans aucune registration atteint le contrôle d'éligibilité.
13. **`onEventProxyCreated` (AP.7)** : pas de try/catch ; `getMainBeneficiary()->getEmail()` ou `getOwner()->getEmail()` null ferait échouer l'envoi (cf. pattern fragile EmailingEventListener déjà noté en AP.7).

### Synthèse domaine H

Domaine fonctionnellement cohérent et utile (gouvernance d'AG), mais **le moins mûr de l'application** : couverture de tests quasi nulle, deux bugs de null-safety/typage atteignables par un membre, une route de détail publique par omission, et une dette structurelle (`ProxyRepository` vide, logique d'appariement dupliquée et anglicisée). Aucune complexité métier insurmontable — c'est surtout un manque de consolidation. Priorité SYN.2 : bugs #1-#2 (atteignables, effort XS), puis PERF #7 et consolidation `ProxyRepository` #8.

---

