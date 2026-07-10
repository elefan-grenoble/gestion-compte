# gestion-compte — Documentation technique

> **Objet** : documentation d'architecture et d'exploitation consolidée, destinée à l'onboarding d'un développeur ou d'un administrateur d'instance. Elle décrit **comment le système fonctionne** et signale les comportements non-évidents (⚠️) au fil du texte.
>
> **Ce document n'est pas la TODO.** La liste priorisée des correctifs vit dans la synthèse d'audit (SYN.2). Ici, un ⚠️ documente un piège à connaître, pas une tâche à planifier.
>
> **Sources** : sections D.1-5, CONFIG.1-3, LOG.1-3, SPEC.1-11 de l'[audit technique](./README.md) (`./D.1.md`…`./SPEC.11.md`, audit juin 2026). En cas de divergence avec le `README.md` actuel, ce document fait foi — le README comporte des inexactitudes recensées en [§4](#4-installation--exploitation).

---

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Architecture](#2-architecture)
3. [Domaines fonctionnels & modèle de données](#3-domaines-fonctionnels--modèle-de-données)
4. [Installation & exploitation](#4-installation--exploitation)
5. [Configuration & variables d'environnement](#5-configuration--variables-denvironnement)
6. [Mécanisme multi-instance](#6-mécanisme-multi-instance)
7. [Authentification & autorisation](#7-authentification--autorisation)
8. [Tâches planifiées (crons)](#8-tâches-planifiées-crons)
9. [Notifications & emails](#9-notifications--emails)
10. [Observabilité](#10-observabilité)
11. [Gotchas transverses](#11-gotchas-transverses)

---

## 1. Vue d'ensemble

**gestion-compte** est l'application web de gestion d'une coopérative alimentaire (supermarché coopératif). Elle couvre l'adhésion et le cycle de vie des membres, la cotisation et les paiements, les créneaux de bénévolat, le contrôle d'accès physique (codes et badges), la gouvernance (assemblées générales et procurations), et l'authentification — locale ou déléguée à Keycloak.

Le dépôt est celui d'**Elefan** (Grenoble), l'instance d'origine. **Scopeli** et d'autres coopératives déploient le même tronc de code, différencié uniquement par leur fichier `.env`. Il n'existe pas de logique conditionnelle « si Elefan / sinon Scopeli » : voir [§6](#6-mécanisme-multi-instance).

| Instance | Helloasso | OIDC (Keycloak) | Cycle |
|----------|-----------|-----------------|-------|
| **Elefan** (canonique) | activé (hypothèse forte) | désactivé (hypothèse) | `abcd` |
| **Scopeli** | à confirmer | activé (hypothèse) | indéterminé |

> L'état réel par instance n'est pas dérivable du dépôt (les `.env` de prod sont des secrets). Les hypothèses ci-dessus s'appuient sur l'origine du projet et des indices de code ; elles restent à confirmer auprès des coops (cf. [§6](#6-mécanisme-multi-instance)).

---

## 2. Architecture

### 2.1 Stack technique (versions réelles)

> ⚠️ Le `README.md` annonce des versions erronées (Symfony 3.4, jQuery 3.6, Materialize 1.2.2). Valeurs réelles vérifiées dans `composer.json` / `package.json` :

| Composant | Version réelle | Note |
|-----------|---------------|------|
| PHP | **7.4** (contrainte `composer.json`) | image Docker de prod en **8.1** → écart CI/prod (CI valide du 7.4) |
| Symfony | **4.4** | README dit 3.4 |
| Doctrine ORM | via `symfony/orm-pack` | 38 entités |
| jQuery / Materialize / Stimulus | ^3.4.1 / ^1.0.0 / ^3.0.0 | build via Webpack Encore |
| Build assets | **Webpack Encore** | Assetic retiré (SF4) — `assetic:dump` n'existe plus |
| Tests | PHPUnit + Cypress ^13.6.4 (E2E, dont OIDC) | |
| Logs | Monolog 1.27.1 / `monolog-bundle` 3.8.0 | |
| Auth | FOSUserBundle + FOSOAuthServerBundle | **dépréciés** — migration SF5 à planifier (SF-PREP) |
| OIDC client | `knpu/oauth2-client` | Keycloak |
| Base | MariaDB (image sans tag) | |

**Licence** : GPLv3 (`package.json` indique ISC par erreur).

### 2.2 Couches applicatives

```
┌─────────────────────────────────────────────────────────────┐
│  HTTP                                                          │
│  ~43 Controllers · ~205 routes applicatives                   │
│  (web UI + admin + /api OAuth + webhooks)                     │
├─────────────────────────────────────────────────────────────┤
│  CLI                                                           │
│  ~22 Commands (crons : cycle, créneaux, adhésions, paiements) │
├─────────────────────────────────────────────────────────────┤
│  Événementiel                                                  │
│  EventDispatcher → EventListeners                             │
│  (Emailing, TimeLog, Commission, Mattermost, Helloasso, OAuth)│
├─────────────────────────────────────────────────────────────┤
│  Services métier (src/Service/)                               │
│  MembershipService, ShiftService, TimeLogService, SwipeCard…  │
├─────────────────────────────────────────────────────────────┤
│  Doctrine ORM — 38 entités · MariaDB                          │
└─────────────────────────────────────────────────────────────┘
```

> **Dette structurelle connue** (détail dans les items [`./AP.*.md`](./README.md#ap--antipatterns-analyse-uniquement)) : controllers volumineux portant de la logique métier ; `getParameter()` via `ContainerAwareTrait` (84 appels, anti-pattern depuis SF4) ; plusieurs EventListeners injectant le `Container` complet plutôt que des dépendances explicites ; logique de requête dispersée hors des Repositories. Ces points sont la cible du chantier de migration SF5 et ne bloquent pas l'exploitation actuelle.

### 2.3 Mécanique événementielle

Le cœur métier communique par événements Symfony. Trois patterns à connaître :

- **Action HTTP → event → email** : ex. `ShiftController` réserve un créneau → `shift.booked` → `EmailingEventListener::onShiftBooked` → email de confirmation.
- **Commande CLI → cascade d'events** : ex. `app:user:cycle_start` → `member.cycle.end` → `TimeLogEventListener` crée les TimeLog puis dispatche `member.cycle.start` → `EmailingEventListener` envoie l'email. ⚠️ Le nommage est trompeur (la commande « start » dispatche « end ») — voir [§8](#8-tâches-planifiées-crons).
- **Webhook entrant → event** : `/helloassoNotify` → `helloasso.payment_after_save` → linkage paiement↔membre.

---

## 3. Domaines fonctionnels & modèle de données

### 3.1 Domaines (~205 routes)

| # | Domaine | Controllers principaux | Routes (~) | Spec |
|---|---------|------------------------|:----------:|------|
| A | Adhérents / Bénéficiaires | `MembershipController`, `BeneficiaryController`, `NoteController` | 30 | SPEC.2 |
| B | Créneaux / Planning | `ShiftController`, `BookingController`, `PeriodController`, `TimeLogController`, `AdminPeriod*`, `AdminShift*` | 45 | SPEC.3 |
| C | Authentification & Autorisation | FOSUserBundle, `OAuthController`, `SwipeCardController`, `UserController` | 35 | SPEC.4 |
| D | Cotisations & Paiements | `HelloassoController`, `RegistrationsController` | 13 | SPEC.5 |
| E | Administration & Configuration | `AdminController`, `Code`, `Commission`, `Service`, `Task`, `Formation`, `DynamicContent`, `Client`… | 50 | SPEC.6 |
| F | Notifications & Emails | `MailController`, `EmailTemplateController` | 5 + event-driven | SPEC.7 |
| G | API & Intégrations externes | `ApiController`, FOSOAuthServer, OIDC, webhook Helloasso, Igloohome (CLI) | 10 | SPEC.8 |
| H | Gouvernance / Assemblées générales | `EventController`, `AdminEventController`, `AdminEventKindController` | **22** | SPEC.11 |
| I | Pages publiques & Widgets | `DefaultController`, `WidgetController` | 9 | transverse |
| J | Contrôle d'accès physique (Codes & Badges) | `CodeController`, `SwipeCardController`, `CardReaderController` | 18 | SPEC.3/4/6/8 |

> Le domaine H (gouvernance) n'était pas prévu au plan d'audit initial et a été ajouté en SPEC.11. Son décompte réel est **22 routes** (et non 16 comme estimé en SPEC.1) ; le total ~205 en tient compte.
>
> ⚠️ La colonne « Routes (~) » somme à plus de 205 : les domaines **I** (pages publiques/widgets) et **J** (accès physique) ainsi qu'une partie de **G** sont des **regroupements transverses** dont les routes sont déjà comptées dans A/B/C/E. Le total ~205 désigne les routes applicatives **distinctes** (hors profiler/wdt et redirect `root`).

### 3.2 Entités et vocabulaire clés

Référentiel complet en SPEC.10. Termes indispensables à l'onboarding :

| Terme | Entité | Sens |
|-------|--------|------|
| **User** | `User` | Compte de connexion (FOSUser). Porte les rôles. |
| **Membership** | `Membership` | Adhésion d'un foyer. Regroupe 1 à N bénéficiaires. Porte l'état `frozen`/`withdrawn`. |
| **Beneficiary** | `Beneficiary` | Personne physique rattachée à une adhésion. Le `MainBeneficiary` est le titulaire. Un `Beneficiary` peut avoir ou non un `User`. |
| **Registration** | `Registration` | Acte de cotisation (montant, mode, date). Une adhésion a un historique de registrations. |
| **AbstractRegistration** | `AbstractRegistration` | ⚠️ Mappée sur une **vue SQL read-only** — pas de persist possible. |
| **Shift** | `Shift` | Créneau de bénévolat (date, durée, poste). Réservable par un bénéficiaire. |
| **Period / PeriodPosition** | `Period`, `PeriodPosition` | Modèle récurrent de créneaux ; `ShiftGenerateCommand` matérialise les `Shift` depuis les `Period`. |
| **TimeLog** | `TimeLog` | Mouvement du compteur de temps de bénévolat d'un membre. |
| **Code** | `Code` | Code d'accès physique au local (porte). |
| **DynamicContent** | `DynamicContent` | Fragment HTML/Twig éditable en base, injecté dans certains emails (rendu via `twig::createTemplate`). |
| **Client** (OAuth) | `Client` | Application tierce enregistrée pouvant s'authentifier via gestion-compte (rôle Identity Provider). |
| **Event / Proxy** | `Event`, `Proxy` | Assemblée générale et procuration d'un membre à un autre (domaine H). |

> **Distinction fondamentale Membership ≠ Beneficiary ≠ User** : l'adhésion est l'unité de cotisation (le foyer) ; les bénéficiaires sont les personnes ; le User est l'identité de connexion. Un bénéficiaire « anonyme » (pré-inscrit) existe sans User.

---

## 4. Installation & exploitation

> Source : D.3 (audit des docs `doc/*.md`). Les erreurs des docs officielles sont corrigées ici.

### 4.1 Prérequis

- Docker Engine + **`docker compose` v2** (le binaire standalone `docker-compose` v1 est déprécié)
- `make`, GNU `sed`
- **Node.js** (build des assets via Webpack Encore — absent du README)

### 4.2 Démarrage local

```bash
cp .env.dist .env
cp docker-compose.symfony_server.yml.dist docker-compose.yml

# Workflow PRINCIPAL : build + up + composer install + schéma + fixtures + stubs Encore
make setup-test
```

> ⚠️ Ne pas faire `docker compose build && up` à la main en pensant que c'est équivalent : ces étapes manuelles n'incluent **pas** `composer install`, la création de schéma ni les stubs Encore. `make setup-test` fait tout — c'est le point d'entrée, pas un raccourci.

| Service | URL |
|---------|-----|
| Application | http://localhost:8000 |
| MailCatcher | http://localhost:1080 |
| PhpMyAdmin | http://localhost:**8081** (⚠️ doc officielle dit 8080 — faux, 8080 = Keycloak) |
| Keycloak | http://localhost:8080 |

Credentials super admin : se référer aux **fixtures** (`src/DataFixtures/`) — les docs `start.md` (`babar/password`) et `install.local.md` (`admin/password`) se contredisent.

### 4.3 Assets, logs, tests

```bash
npm ci && npm run build      # ou : make encore-build   (⚠️ PAS assetic:dump, inexistant)
tail -f var/log/dev.log      # ⚠️ var/log SANS « s » (doc dit var/logs, faux)
make test                    # PHPUnit
make test-e2e-oidc           # Cypress OIDC — nécessite Keycloak démarré séparément (make up ne le lance pas)
```

### 4.4 Déploiement serveur (corrections critiques)

> ⚠️ `doc/install.serveur.md` et `doc/maj*.md` contiennent des instructions obsolètes (ère Symfony 3). Procédure correcte :

1. **PHP ≥ 7.4** (la doc dit 7.2).
2. **Point d'entrée nginx** : `public/index.php` (la doc pointe `web/app.php`, SF3) :
   ```nginx
   try_files $uri /index.php$is_args$args;
   ```
3. **Migrations** : `doctrine:migrations:migrate` (avec **`s`** — la doc oublie le `s`).
4. **Serveur** : `symfony serve` (`server:start` supprimé en SF4).
5. **Composer prod** : `composer install --no-dev --optimize-autoloader`.
6. **Assets** : `npm ci && npm run build` (pas `assetic:dump`).
7. **Crontab** : définir `APP_ENV=prod` (sinon mode `dev` : debug, opcode off).

> 🔴 **`doc/maj-v2.0.md` : le diff nginx est inversé** (montre SF4→SF3 au lieu de SF3→SF4). Suivre ce guide à la lettre **dégrade** la config de prod. Ne pas l'appliquer.

### 4.5 Versioning (release-please)

`release-please` (config v4, `release-type: php`, sections FR) gère `CHANGELOG.md` et la version — **ne pas les éditer à la main**.

> ⚠️ `release-please-config.json` déclare `"extra-files": ["app/config/config.yml"]`, fichier d'**architecture SF3 qui n'existe plus**. La mise à jour de version dans le code est donc silencieusement ignorée. Quatre tags (v1.45.8/9, v1.46.0, v1.47.0) n'ont pas d'entrée CHANGELOG (période de transition vers l'outil).

---

## 5. Configuration & variables d'environnement

> Source : CONFIG.1-3. Fichier de référence : `.env.dist`.

### 5.1 Chaîne de configuration

```
.env / .env.local / secrets CI
        │
        ▼
config/services.yaml   — ~130 paramètres nommés via %env(TYPE:VAR)%
        │
        ├─► _defaults.bind     → injection auto par nom d'argument PHP (recommandé)
        ├─► getParameter('x')  → 84 appels directs (anti-pattern SF5)
        └─► config/packages/twig.yaml → ~60 globales Twig
```

> ⚠️ Certaines globales Twig lisent directement `%env()%` au lieu de passer par le paramètre nommé de `services.yaml`. Si un paramètre est redéfini côté `services.yaml` (cast, défaut), la globale Twig **ne reflète pas** ce changement. Source unique de vérité non garantie pour ces flags.

### 5.2 Trois familles de variables

**Applicatives** (consommées par Symfony) — la grande majorité.

**Infrastructure** (NON consommées par Symfony — `dploy.sh` / Docker) : `SYMFONY_ENV` (legacy SF3), `PHP_USER`, `PHP_MEMORY_LIMIT`, `PHP_SERVICE_NAME`, `PHP_IDE_CONFIG`. Présentes dans `.env.dist` mais sans effet applicatif.

**Mortes** (à ne pas reproduire dans un nouveau `.env`) :
| Variable | Statut |
|----------|--------|
| `DATABASE_TEST_HOST` | jamais consommée |
| `DEV_MODE_ENABLED` | absente du code ; mention trompeuse dans `install.local.md` et `flake.nix` |
| `HELLOASSO_API_KEY` / `HELLOASSO_API_PASSWORD` | reliquats API Helloasso v1 (dans `.env.oidc.test`) |
| `ROUTER_REQUEST_CONTEXT_SCHEME` | présente partout mais **non mappée** vers Symfony → le scheme est ignoré hors requête HTTP (URLs des emails/CLI en `http` même si l'instance est en `https`) |

### 5.3 Paramètres métier — valeurs et unités

> ⚠️ `.env.dist` ne documente pas les unités, qui varient selon la variable. Référence :

| Variable | Dist | Unité | Description |
|----------|------|-------|-------------|
| `CYCLE_DURATION` | `'28 days'` | PHP nat. lang. | Durée d'un cycle de bénévolat |
| `CYCLE_TYPE` | `abcd` | `abcd`\|`*` | `abcd` = cycles alignés semaine ISO ; `*` = cycles flottants depuis `firstShiftDate` |
| `DUE_DURATION_BY_CYCLE` | `180` | **minutes** | Temps de bénévolat dû par cycle |
| `MIN_SHIFT_DURATION` | `90` | **minutes** | Durée min comptabilisée |
| `FORBID_SHIFT_OVERLAP_TIME` | `30` | **minutes** | Marge anti-chevauchement |
| `MAX_TIME_AT_END_OF_SHIFT` | `0` | **minutes** | Fenêtre de validation après fin de créneau |
| `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY` | `7` | **jours** | 🔴 cassé : casté `bool:` en `services.yaml:147` → vaut `true` (1) au lieu de 7 |
| `MAX_TIME_IN_ADVANCE_TO_BOOK_EXTRA_SHIFTS` | `'3 days'` | PHP nat. lang. | Délai max pré-réservation extra |
| `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS` | `-9` | **heures (négatif)** | Seuil de dette de temps : négatif = dette acceptable. Nom trompeur (≠ durée après créneau) |
| `REGISTRATION_DURATION` | `'1 year'` | PHP nat. lang. | Validité d'une adhésion |
| `MAXIMUM_NB_OF_BENEFICIARIES_IN_MEMBERSHIP` | `2` | entier | Bénéficiaires max par adhésion |
| `TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS` | `null` | **jours** | Délai min libération via épargne ; `null` = libre |

> ⚠️ **`CYCLE_DURATION` n'est pas honoré par `MembershipService`** : 5 points de calcul de cycle hardcodent la durée (`28` aux lignes 146, 147, 156, 181 ; `27` ligne 170). Le bug est **dormant tant que `CYCLE_TYPE=abcd`** (branche dédiée qui calcule depuis la semaine ISO) ; il devient actif sur une instance en cycles flottants avec une durée ≠ 28 jours. *(La fenêtre `canRegister` `+28 j` ligne 75 est un réglage **distinct**, non lié à la durée de cycle.)*

> ⚠️ **`SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT`** : défaut hardcodé **`true`** dans `services.yaml:72`. Si l'instance ne définit pas la variable, chaque réservation envoie une copie admin — alors que le commentaire de `.env.dist` suggère `false`.

### 5.4 Emails — 6 boîtes + domaine

| Paramètre | Variables | Rôle |
|-----------|-----------|------|
| `emails.admin/contact/formation/member/noreply/shift` | `EMAILS_<X>_ADDRESS` + `_NAME` | 6 boîtes thématiques |
| `transactional_mailer_user` | `TRANSACTIONAL_MAILER_USER` | 1ère adhésion Helloasso + `shift_contact_form` |
| `emails.base_domain` | `EMAILS_BASE_DOMAIN` | détection des emails temporaires `membres+N@<domain>` |

### 5.5 Feature flags

~25 flags booléens pilotent l'activation de modules par instance (liste exhaustive en CONFIG.2). Les plus structurants :

| Flag | Effet | Domaine |
|------|-------|---------|
| `OIDC_ENABLE` | SSO Keycloak (auth déléguée, voir [§7](#7-authentification--autorisation)) | SPEC.4/8 |
| `USE_FLY_AND_FIXED` | Mode créneaux volant/fixe | SPEC.3 |
| `USE_TIME_LOG_SAVING` | Épargne de temps (time banking) | SPEC.3 |
| `CODE_GENERATION_ENABLED` | Génération de codes d'accès | SPEC.4 |
| `ENABLE_PLACE_LOCAL_IP_ADDRESS_CHECK` | Filtrage IP pour accès badge/code | SPEC.4 |
| `REGISTRATION_MANUAL_ENABLED` | Inscription hors Helloasso (⚠️ absent de `services.yaml`) | SPEC.5 |
| `DISPLAY_GAUGE` | Jauge canvas-gauges — ⚠️ dépendance CDN HS (EXTRA DEP.3) | SPEC.6 |
| `LOGGING_MATTERMOST_ENABLED` | Alertes Mattermost | LOG/SPEC.7 |

---

## 6. Mécanisme multi-instance

> Source : CONFIG.2, SPEC.9.

### 6.1 Principe : un seul code, différencié par `.env`

Il n'existe **ni table de configuration en base, ni feature-flag framework, ni variable `APP_INSTANCE`, ni logique conditionnelle par instance**. Elefan et Scopeli exécutent le même code source. Toute la différenciation passe par les variables d'environnement :

```
Elefan .env                     Scopeli .env
  OIDC_ENABLE=false        vs     OIDC_ENABLE=true
  HELLOASSO_CLIENT_ID=xxx         HELLOASSO_CLIENT_ID=        (vide → feature inerte)
  SITE_NAME="L'Elefan"            SITE_NAME="Scopeli"
  CYCLE_TYPE=abcd                 CYCLE_TYPE=?
```

### 6.2 Dégradation gracieuse

Les intégrations optionnelles (`HELLOASSO_*`, `IGLOOHOME_*`, `OIDC_*`) sont déclarées en `%env(default::VAR)%` → vide si non définie. L'application démarre sans erreur et la feature est simplement inactive. (Les 2 warnings `debug:router` sur `HelloassoClient`/`IgloohomeClient` confirment ce comportement sur l'instance de dev du repo, qui ne les configure pas.)

### 6.3 Ce qui reste indéterminable depuis le dépôt

L'état on/off réel des flags par instance **n'est pas dans le repo** (les `.env` de prod sont des secrets). Le seul outil capable de le révéler sans accès secret serait un **Route Usage Tracker** (`APP_INSTANCE` + table `route_usage` alimentée par un subscriber `kernel.terminate`), spécifié en RT.2 mais non implémenté.

Questions fermées à poser aux coops pour lever les ambiguïtés critiques :
- `OIDC_ENABLE` sur chaque instance ? (tranche toute la priorisation auth/SF5)
- Scopeli : Helloasso, inscription manuelle, ou autre PSP ?
- `IGLOOHOME_*` utilisé, et sur laquelle ?
- `DISPLAY_GAUGE` activé quelque part ? (décide du sort de la dépendance CDN morte)
- `CYCLE_TYPE` réellement `abcd` partout ? (si non, le bug `CYCLE_DURATION` est actif)

---

## 7. Authentification & autorisation

> Source : SPEC.4, SPEC.8. C'est le domaine le plus piégeux : « OAuth » y recouvre **trois mécaniques indépendantes**.

### 7.1 Les trois rôles OAuth

| # | Rôle de gestion-compte | Lib | Sens | Usage |
|---|------------------------|-----|------|-------|
| **1** | **Serveur OAuth2** (Identity Provider) | FOSOAuthServerBundle | expose son identité | Nextcloud / GitLab-like se connectent **avec** un compte gestion-compte |
| **2** | **Client OIDC** (Relying Party) | knpu/oauth2-client + `KeycloakAuthenticator` | délègue son login | les membres se connectent **via** Keycloak |
| **3** | **Client `client_credentials`** | league/oauth2-client | consomme des API | appels machine vers Helloasso & Igloohome |

Rôles 1 et 2 sont **mutuellement exclusifs en pratique** : quand `OIDC_ENABLE=true`, `OidcFirewallListener` redirige `/login → /oauth/login` (Keycloak) et le serveur OAuth2 (rôle 1) n'est plus le point d'entrée d'authentification. Le rôle 3 est orthogonal et coexiste avec n'importe quel mode.

### 7.2 Flux OIDC (rôle 2) — provisioning et autorité Keycloak

```
Membre → /login
  → OidcFirewallListener → /oauth/login → Keycloak
  → /oauth/callback (oauth_check) → KeycloakAuthenticator::getUser()
       ├─ Beneficiary.openid == keycloakUser.getId() ?
       │    ├─ trouvé      → updateBeneficiary + updateCoMembership
       │    ├─ email connu → lie openid au compte existant, enable(true)
       │    └─ inconnu     → crée Beneficiary + Membership (provisioning JIT)
       └─ updateBeneficiary : setRoles([]) puis re-mapping depuis les claims
```

> 🔴 **Keycloak fait autorité — RAZ des rôles à chaque login.** `updateBeneficiary` exécute `getUser()->setRoles([])` **à chaque connexion**, puis re-peuple rôles/formations/commissions depuis les claims (`oidc_roles_map` / `oidc_formations_map` / `oidc_commissions_map`). **Conséquence opérationnelle : toute attribution locale (rôle, formation, commission) est écrasée au prochain login OIDC du membre.** Sur une instance OIDC, ces attributions doivent se faire dans Keycloak, jamais dans l'UI gestion-compte.

> ⚠️ **Provisioning JIT — collision de numéro.** Si aucun `member_number` n'est fourni par Keycloak ni le bénéficiaire, `rand(10000, 100000)` est tiré **sans contrôle de collision** (même faiblesse que `code_generate`).

### 7.3 `OidcFirewallListener` — désactivation des outils locaux

Sous OIDC, la gestion d'identité locale est interdite via une **denylist de préfixes d'URI codée en dur** : `/profile/edit`, `/member/new|edit|join`, `/resetting/request`, `/registrations`, `/helloasso`, `/services`, `/admin/clients`, `/admin/importcsv`, `/user/quick_new|pre_users`, `/ambassador/*`, plus `str_contains(uri, 'removeRole')`.

> ⚠️ Protection **fragile** : un renommage de route ou un nouveau point d'écriture casse silencieusement la garde. Une liste blanche de routes (ou un attribut centralisé) serait plus robuste.

### 7.4 `ROLE_OAUTH_LOGIN` — dérivé du token, jamais stocké

Ce rôle n'est attribué par aucun code applicatif ni stocké sur le `User`. Le firewall `fos_oauth` le dérive du **scope** `oauth_login` du token (ou, en OIDC, d'un rôle Keycloak mappé via `OIDC_ROLE_OAUTH_LOGIN`). Hiérarchie : `ROLE_OAUTH_LOGIN → ROLE_USER`.

### 7.5 Pièges de sécurité connus (cf. SYN.2 pour la priorisation)

- ⚠️ **`access_control` — règle masquée** : dans `security.yaml`, `^/api → IS_AUTHENTICATED_FULLY` (L59) précède `^/api/oauth/ → ROLE_OAUTH_LOGIN` (L60). Premier match gagne → **L60 est inatteignable** ; la protection `ROLE_OAUTH_LOGIN` repose uniquement sur les annotations `@Security` des contrôleurs.
- ⚠️ **Tokens Vigenère sans expiration** dans les URLs d'emails (accept/reject créneau, fermeture de code, `member_new`) — chiffrement symétrique, rejouables indéfiniment.
- ⚠️ **Webhook `/helloassoNotify`** : POST public, non signé. Mitigé par re-fetch via l'API Helloasso + idempotence `savePayments` (dédup par `paymentId`).
- ⚠️ **Plusieurs routes mutantes en GET** sans CSRF (`user_add_role`, `shift_accept_reserved/reject_reserved`, `event_proxy_lite_delete`, badges `swipe_qr/br`…) — détail et correctifs en SYN.2.

---

## 8. Tâches planifiées (crons)

> Source : SPEC.7, SPEC.3, TC.5.

### 8.1 Inventaire des commandes

| Commande | Effet | Mutation |
|----------|-------|:--------:|
| `app:user:cycle_start [--date]` | Email cycle aux membres en retard (déclenche la cascade TimeLog) | via events |
| `app:user:cycle_half [--date]` | Email mi-cycle (MainBeneficiary seul) | — |
| `app:shift:generate` | Matérialise les `Shift` depuis les `Period` | ✓ |
| `app:shift:free <date>` | Libère les pré-réservations expirées + emails accept/reject | ✓ |
| `app:shift:reminder <date>` | Email rappel des créneaux du jour | — |
| `app:shift:send_alerts <date> <jobs>` | Email + Mattermost si créneaux insuffisants | — |
| `app:shift:send_late_shifters` | Email aux retardataires (membres en dette) | — |
| `app:shift:verify_change` | Email de vérification de changement de code | — |
| `app:shift:fix_missing_position` | Répare `Shift.position=null` — ⚠️ **échoue en `cycle_type=abcd`** (exit 1) | ✓ |
| `app:member:close` | Ferme les adhésions expirées | ✓ |
| `app:member:update_payments` | Synchronise les paiements Helloasso | — |
| `app:code:update_igloohome` | Pousse les codes vers les serrures Igloohome | ✓ |
| `app:user:mass_mail <from> <subject> <file>` | Envoi de masse (membres en BCC) | — |

> Aucune commande n'a d'option `--dry-run`, y compris les opérations irréversibles (`app:member:close`, `app:code:update_igloohome`, `app:shift:fix_missing_position`). Prudence en exploitation.

### 8.2 ⚠️ Coordination obligatoire : génération → libération

Quand `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER=true`, un créneau généré est d'abord **pré-réservé** à l'ancien bénéficiaire, puis libéré `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY` jours plus tard par `app:shift:free`.

```
Jour J     : app:shift:generate
Jour J + N : app:shift:free <date>      (N = RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY)
```

Un mauvais ordonnancement laisse des pré-réservations en suspens **indéfiniment**. Cette dépendance n'est documentée ni dans le README ni dans les commandes.

> 🔴 Rappel : `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY` est casté `bool:` (`services.yaml:147`) → vaut `true`/1 au lieu de la valeur en jours. À corriger avant de s'appuyer sur ce délai.

### 8.3 ⚠️ Chaîne cycle (nommage trompeur)

```
app:user:cycle_start
   └─ dispatche member.cycle.END (MemberCycleEndEvent)
        └─ TimeLogEventListener::onMemberCycleEnd : crée les TimeLog
             └─ dispatche member.cycle.start (si membre non gelé)
                  └─ EmailingEventListener::onMemberCycleStart : email à TOUS les bénéficiaires du foyer
```

La commande « cycle_start » dispatche un event « cycle.END ». C'est intentionnel (END → TimeLog → START par cascade) mais déroutant. Ne pas renommer sans tracer toute la chaîne de listeners. (Asymétrie à noter : `cycle_half` n'envoie qu'au `MainBeneficiary`, `cycle_start` à tout le foyer.)

### 8.4 Crontab de référence

> Adapter par instance. **Toujours** `APP_ENV=prod`.

```cron
0 2  * * *  APP_ENV=prod php bin/console app:member:close
0 18 * * *  APP_ENV=prod php bin/console app:shift:reminder $(date -d "+1 day" +%Y-%m-%d)
0 8  * * 1  APP_ENV=prod php bin/console app:shift:send_alerts $(date +%Y-%m-%d) --emails=admin@instance.coop
0 9  * * 1  APP_ENV=prod php bin/console app:user:cycle_start
0 9  * * 4  APP_ENV=prod php bin/console app:user:cycle_half
0 6  * * *  APP_ENV=prod php bin/console app:shift:free $(date +%Y-%m-%d)
*/15 * * * * APP_ENV=prod php bin/console app:member:update_payments
0 10 * * *  APP_ENV=prod php bin/console app:shift:verify_change
```

---

## 9. Notifications & emails

> Source : SPEC.7. Hub central : `EmailingEventListener` (13 handlers).

### 9.1 Trois canaux

- **Transactionnels** (HTTP) : déclenchés par une action utilisateur via event (adhésion, réservation/libération de créneau, code, procuration…). Expéditeur selon le contexte (`emails.member`, `emails.shift`, `transactional_mailer_user`).
- **Batch** (cron) : rappels, alertes, cycles, retardataires (cf. [§8](#8-tâches-planifiées-crons)).
- **Masse** (admin/CLI) : `MailController` (UI, ROLE_USER_MANAGER) et `app:user:mass_mail` (membres en BCC). Vérifient que l'expéditeur est dans la whitelist `emails.sendable`.

### 9.2 DynamicContent vs EmailTemplate

- **`DynamicContent`** : fragments injectés dans des emails par code (`WELCOME_EMAIL`, `SHIFT_REMINDER_EMAIL`, `SHIFT_ALERT_*`, `PRE_MEMBERSHIP_EMAIL`…), rendus via `twig::createTemplate()`.
- **`EmailTemplate`** : modèles nommés gérés en CRUD admin, utilisés **uniquement** par `MailController` pour envelopper un message Markdown. Aucun envoi automatique ne s'en sert.

> ⚠️ **DynamicContent absent → crash.** `onAnonymousBeneficiaryCreated`, `onAnonymousBeneficiaryRecall`, `onShiftReminder` et `MailerService::sendConfirmationEmailMessage` appellent `->findOneByCode(...)->getContent()` **sans null-guard**. Si le code attendu manque en base (ex. fixtures partielles), l'envoi lève une exception fatale.

> ⚠️ **SSTI potentielle.** `twig::createTemplate($dynamicContent->getContent())` exécute du Twig arbitraire stocké en base. Acceptable seulement si l'édition de `DynamicContent` est strictement réservée à des admins de confiance (ROLE_PROCESS_MANAGER).

### 9.3 Points d'attention

- 🔴 **`die($e->getMessage())` dans `onHelloassoTooEarly`** (l.256-258) : tue le process PHP sur exception de rendu lors d'un flux de paiement Helloasso → page blanche, aucun log.
- ⚠️ `member.created` est dispatché mais `EmailingEventListener::onMemberCreated()` est un **stub vide** (`// TODO ?`). L'email de bienvenue passe par le flux FOSUserBundle, pas par cet event.
- ⚠️ `HelloassoEvent::RE_REGISTRATION_SUCCESS` couvre aussi la **première** adhésion (nom trompeur) ; la distinction 1ère/renouvellement se fait sur `registrations.count > 1` dans le listener.

---

## 10. Observabilité

> Source : LOG.1-3.

### 10.1 Pipeline Monolog

| Env | Handlers |
|-----|----------|
| `dev`/`test` | `stream` (debug) + `stdout` + `console` + `server_log` (127.0.0.1:9911) |
| `prod` | `fingers_crossed` (action_level=**info**) → `grouped` → [`file` (warning), `mattermost`, `stdout`] |

Composants custom : `MonologUserProcessor` (enrichit chaque record avec l'utilisateur connecté, tous channels) et `ToggleableHandler` (flag enable/disable sur `stdout`/`mattermost`).

> ⚠️ `LOGGING_MATTERMOST_*` sert **à la fois** le handler Monolog (logs applicatifs) et `MattermostEventListener` (alertes créneaux) — distinction non évidente.

### 10.2 Limites actuelles

- 🔴 `die()` dans `EmailingEventListener` (voir [§9.3](#93-points-dattention)).
- ⚠️ `fingers_crossed` avec `action_level: info` : le buffer est flushé quasi immédiatement → l'effet protecteur du pattern est nul.
- ⚠️ Pas de rotation : `var/log/prod.log` croît indéfiniment (handler `stream`, pas `rotating_file`).
- ⚠️ 25 logs `info("XxxListener: onEvent")` sans contexte métier — bruit, et de toute façon filtrés (`file` est en `warning`).

### 10.3 ⚠️ Aucun audit trail

Aucune action sensible n'est tracée de façon exploitable. Concrètement, **aucun log** ne permet de reconstituer :

| Action | Route | Rôle |
|--------|-------|------|
| Ajout / retrait de rôle | `user_add_role` / `user_remove_role` | ROLE_ADMIN |
| Suppression User / Membership / Beneficiary | `user_delete`, `member_delete`, `beneficiary_delete` | SUPER_ADMIN / voter |
| Fermeture / réouverture de compte | `member_withdrawn` | ROLE_USER_MANAGER |
| Création / édition / suppression de paiement | `helloasso_payment_*`, `registration_*` | FINANCE_MANAGER / SUPER_ADMIN |
| Changement de mot de passe / email | `user_change_password`, `set_email` | authentifié |

Seule la fermeture de compte laisse une trace **en base** (`Membership.withdrawn_date`/`withdrawn_by_id`), et seulement pour l'état le plus récent (effacée à la réouverture). La fermeture en masse par `CloseMembershipCommand` n'est tracée que via `$output->writeln()` (silencieuse en cron). L'auth OIDC (création de compte SSO) n'est pas loggée non plus.

> Recommandation (SYN.2) : channel Monolog `security` dédié, avec contexte `[actor_id, target_id, action, old→new]`, priorité sur rôles et suppressions.

---

## 11. Gotchas transverses

Récapitulatif des pièges les plus susceptibles de surprendre, avec renvoi vers la section de détail.

| Sujet | Piège | Détail |
|-------|-------|--------|
| **Cycle** | `app:user:cycle_start` dispatche un event `cycle.END` | [§8.3](#83--chaîne-cycle-nommage-trompeur) |
| **Cycle** | `CYCLE_DURATION` ignoré (durée hardcodée, 5 points de cycle) — dormant si `abcd` | [§5.3](#53-paramètres-métier--valeurs-et-unités) |
| **Créneaux** | génération → libération à coordonner sur N jours | [§8.2](#82--coordination-obligatoire--génération--libération) |
| **Créneaux** | `app:shift:fix_missing_position` échoue en `cycle_type=abcd` | [§8.1](#81-inventaire-des-commandes) |
| **Email** | copie admin booking par défaut `true` (hardcodé) | [§5.3](#53-paramètres-métier--valeurs-et-unités) |
| **Email** | `DynamicContent` manquant → exception fatale | [§9.2](#92-dynamiccontent-vs-emailtemplate) |
| **Email** | `die()` sur exception Helloasso → page blanche | [§9.3](#93-points-dattention) |
| **OIDC** | Keycloak écrase les rôles locaux à chaque login | [§7.2](#72-flux-oidc-rôle-2--provisioning-et-autorité-keycloak) |
| **OIDC** | denylist de routes fragile | [§7.3](#73-oidcfirewalllistener--désactivation-des-outils-locaux) |
| **Sécurité** | règle `access_control` `/api/oauth/` inatteignable | [§7.5](#75-pièges-de-sécurité-connus-cf-syn2-pour-la-priorisation) |
| **Sécurité** | tokens Vigenère sans expiration | [§7.5](#75-pièges-de-sécurité-connus-cf-syn2-pour-la-priorisation) |
| **Données** | `AbstractRegistration` = vue SQL read-only | [§3.2](#32-entités-et-vocabulaire-clés) |
| **Provisioning** | `member_number = rand()` sans contrôle de collision | [§7.2](#72-flux-oidc-rôle-2--provisioning-et-autorité-keycloak) |
| **Config** | `ROUTER_REQUEST_CONTEXT_SCHEME` non mappé → URLs en `http` | [§5.2](#52-trois-familles-de-variables) |
| **Release** | `release-please` `extra-files` pointe un fichier SF3 inexistant | [§4.5](#45-versioning-release-please) |
| **Audit** | aucune traçabilité des actions sensibles | [§10.3](#103--aucun-audit-trail) |

---

*Produit le 2026-06-26 — audit technique gestion-compte. À maintenir au fil des évolutions d'architecture ou de configuration.*
