# Audit technique — gestion-compte
<!-- État d'avancement. Committé sur la branche d'audit à chaque session (checkpoint/rollback). À SUPPRIMER avant le merge de la branche, une fois la TODO et les specs générées. -->
<!-- Statuts : [ ] todo | [~] en cours | [x] fait | [!] bloquant / à décider -->

**Projet** : github.com/elefan-grenoble/gestion-compte
**Branche** : `chore/tech-debt-audit`
**Stack** : Symfony 4.4 / PHP 7.4
**Environnement** : Docker Compose — si les conteneurs sont arrêtés : `cd /home/claude/workspace/gestion-compte && make setup-test`
**Docker** : démon rootless, démarré par l'utilisateur. Vérifier avec `docker compose ps` avant tout `docker compose exec`.

---

## Objectif et livrables

Cet audit est un **état des lieux**. Il ne modifie pas le code, ne fait pas de migration, n'écrit pas de tests.

**Trois livrables finaux :**
1. **Documentation à jour** — README, guides d'installation, architecture, glossaire métier
2. **Specs fonctionnelles** — couverture complète du projet, format LLM-friendly (markdown structuré, domaines séparés, terminologie cohérente)
3. **TODO priorisée** — dead code à supprimer, antipatterns à corriger, tests à écrire, gaps de sécurité, chemin de migration Symfony

**Ce que l'audit NE fait PAS :** migrer Symfony, corriger le dead code, réécrire les tests, upgrader PHP (sauf si indispensable pour les outils d'analyse — voir P0.3).

---

## Contexte métier (critique pour l'audit)

Outil de gestion coopérative (créneaux de travail, adhérents, cotisations) utilisé par **plusieurs instances indépendantes** : Elefan (Grenoble), Scopeli (Nantes), et d'autres coopératives. Chaque instance déploie sa propre version. Toutes les features ne sont pas utilisées partout.

Conséquences pour l'audit :
- Une route "inutilisée" statiquement peut être active chez une instance. **Ne pas conclure à du dead code sans données runtime.**
- Les specs fonctionnelles devront noter quand une feature est potentiellement instance-spécifique.
- L'identification de l'instance se fait probablement via hostname ou variable d'environnement (à confirmer en CONFIG.2).

---

## Modèle à utiliser

**Sonnet par défaut.** Les sections marquées 🔀 nécessitent Opus — Claude rappellera de taper `/model opus` au début et `/model sonnet` à la sortie.

Sections Opus : **AP, SEC, SPEC, SYN**. Opus ponctuel : **SF-PREP.2**.

---

## P0 — Mise en place

- [x] **P0.1** — Environnement opérationnel
  > Docker up, DB healthy (healthcheck corrigé mariadb-admin), cache warmup dev OK.

- [x] **P0.2** — AUDIT.md créé (ce fichier)

- [x] **P0.3** — Évaluer si l'upgrade PHP 8 est nécessaire pour l'analyse
  > **Décision : OUI — upgrader le Dockerfile vers PHP 8.1 pour l'analyse.**
  >
  > Analyse menée :
  > - Rector (DC.1) : 23 fichiers, limités au scope **privé** (params inutilisés, propriétés privées, closures, dead returns). Couvre ~10 % du périmètre.
  > - Méthodes publiques dans `src/` : **1 884** (vs 204 privées/protégées). Rector ne les analyse pas du tout.
  > - `shipmonk/dead-code-detector` 0.15.1 (requiert `php ^8.1`, `phpstan/phpstan ^2.1.41`) : se résout sans conflit avec les dépendances existantes (`composer require --dry-run` OK). Avec les providers Symfony + Doctrine activés, les faux positifs (routes, listeners YAML, commandes, templates Twig) sont filtrés automatiquement.
  > - Compatibilité PHP 8 du code `src/` : aucune fonction supprimée en PHP 8 (`create_function`, `each()`, etc.) ; les usages de `match` dans `src/` sont dans des docblocks, pas du code.
  > - Risque production : zéro. Le Dockerfile est uniquement dev/CI.
  >
  > **Étapes d'implémentation (à réaliser avant DC.3)** :
  > 1. `.docker/Dockerfile` : `FROM php:7.4` → `FROM php:8.1`
  > 2. `composer config platform.php 8.1` (config Composer seulement, pas le contrainte du projet)
  > 3. `docker compose build php && docker compose up -d php`
  > 4. `composer require --dev shipmonk/dead-code-detector`
  > 5. Créer `phpstan-dead-code.neon` avec providers Symfony + Doctrine (voir DC.3)

---

## D — Documentation

- [x] **D.1** — README.md
  > Lire `README.md` en entier. Vérifier : versions (PHP, Symfony, Node), prérequis, étapes de setup, liens, description du projet. Pour chaque section : est-elle exacte, complète, à jour ? Lister toutes les inexactitudes et lacunes. **Livrable partiel** : liste exhaustive des corrections à apporter — elle alimentera la documentation finale (SYN.1).
  >
  > **Findings :**
  >
  > **1. Erreurs de version dans la section "Stack technique" (ligne 40-47)**
  > | Élément | README | Réalité |
  > |---------|--------|---------|
  > | PHP | 7.4 ✓ | 7.4 (`composer.json` platform) |
  > | Symfony | **3.4 ❌** | **4.4** (tous les packages `symfony/*` en `4.4.*`) |
  > | jQuery | **3.6 ❌** | **^3.4.1** (`package.json`) |
  > | Materialize | **1.2.2 ❌** | **^1.0.0** (`package.json`, pas de version précise) |
  > | MariaDB | mention sans version | image `mariadb` sans tag dans docker-compose |
  >
  > **2. Composants absents de la stack (section "Developpement")**
  > - Node.js / webpack-encore : build d'assets complet via Symfony Encore, absent
  > - Stimulus (`@hotwired/stimulus ^3.0.0`) : framework JS utilisé
  > - Cypress (`^13.6.4`) : tests E2E
  > - Keycloak : intégration OIDC (`.docker/keycloak/`, `KeycloakController`, `knpuniversity/oauth2-client-bundle`)
  >
  > **3. Incohérence de licence**
  > - `composer.json` → `"license": "GPLv3"` (correct, mentionné dans le texte du README)
  > - `package.json` → `"license": "ISC"` (incohérent — devrait être GPLv3)
  >
  > **4. Liens potentiellement morts**
  > - Board Kanban ligne 29 : `github.com/elefan-grenoble/gestion-compte/projects/5` → GitHub Projects v1 est fermé/migré, lien très probablement invalide
  > - Wiki (ligne 51) : lien externe non vérifié dans cet audit
  >
  > **5. Prérequis de développement manquants**
  > - Aucune section "Prérequis" listant : Docker (version minimale), `docker compose` v2 (vs `docker-compose` v1), `make`, GNU `sed`
  > - Node.js non mentionné alors qu'il est requis pour le build des assets
  >
  > **6. Pas de section "Quick Start"**
  > Un développeur doit lire plusieurs docs imbriquées pour lancer l'environnement. L'entrée minimale (`make setup-test`) n'est pas mentionnée dans le README.
  >
  > **7. Titre et description**
  > - Titre : "Espace adhérent super marché coopératifs" — accord douteux ("coopératifs" pluriel mais un seul marché ?)
  > - `composer.json` description : "Web site to manage the cooperative grocery shop l'Elefan" — non synchronisée avec le README
  >
  > → Toutes ces corrections alimenteront **SYN.1** (documentation mise à jour).

- [x] **D.2** — TODO.md existant
  > Lire `TODO.md` en entier. Pour chaque item : encore pertinent, obsolète, ou déjà traité ? Les items valides seront intégrés dans la TODO finale.
  >
  > **Findings :**
  >
  > Le fichier est très court (40 lignes) et ne couvre qu'un seul domaine : le **dead code**. Aucune entrée sur la sécurité, les tests, la performance, la configuration multi-instance ou l'architecture — ce qui confirme que cet audit est la première tentative systématique de couvrir ces sujets.
  >
  > **Item 1 — "Supprimer le code mort détecté par Rector" : valide, issu d'un run antérieur à DC.1**
  >
  > TODO.md mentionne ~35 fichiers / ~50 corrections (Rector antérieur). DC.1 (session 1 de cet audit) a trouvé 23 fichiers avec une version plus récente. Les deux runs ont un périmètre **partiellement différent** :
  >
  > | Finding | TODO.md | DC.1 |
  > |---------|---------|------|
  > | `ShiftBookedEvent::$fromAdmin` inutilisé | ✓ | ✓ |
  > | `Html2Pdf::$container` inutilisé | — | ✓ |
  > | `UserAdminType` + `UserWithBeneficiaryType` délégants | ✓ | ✓ |
  > | `BeneficiaryWithoutUserType` délégant | ✓ | — |
  > | `AuthenticationSuccessHandler` dead return | — | ✓ |
  > | `CommissionEventListener` null arg | — | ✓ |
  > | `SwipeCard::generateCode()` variable inutile | — | ✓ |
  > | `AmbassadorController::createNoteDeleteForm` méthode privée morte | ✓ | — |
  > | `CodeVoter::isLocationOk()` méthode privée morte | ✓ | — |
  > | 6 constructeurs vides d'entités | ✓ | — |
  >
  > **Vérifications manuelles des items exclusifs à TODO.md :**
  > - `AmbassadorController::createNoteDeleteForm` (ligne 313) : **confirmé mort** — défini mais jamais appelé dans le fichier (grep ne trouve aucun appel `$this->createNoteDeleteForm`).
  > - `CodeVoter::isLocationOk()` (ligne 151) : **confirmé mort** — jamais appelé via `$this->isLocationOk()` dans CodeVoter ; le voter délègue à `$this->container->get(PlaceIP::class)->isLocationOk()`. Le commentaire ligne 150 (`// DUPLICATED from UserVoter`) confirme l'intention.
  > - 6 constructeurs vides d'entités (`Code`, `DynamicContent`, `EmailTemplate`, `PeriodPosition`, `ProcessUpdate`, `Service`) : **tous confirmés présents et vides**.
  > - "3 cases switch dupliqués (voters)" : formulation imprécise dans TODO.md. Il s'agit en réalité de la **méthode `isLocationOk()` copiée-collée dans 3 voters** (CodeVoter, UserVoter, MembershipVoter, cf. commentaire DUPLICATED). Aucun `case:` dupliqué dans un même switch n'a été identifié. Cet item se réduit à la confirmation ci-dessus de `CodeVoter::isLocationOk`.
  >
  > → Ces findings complémentaires (méthodes privées mortes + constructeurs vides) alimenteront **DC.4** (consolidation TODO dead code).
  >
  > **Item 2 — "Ajouter un job CI dead-code" : valide, spec à mettre à jour**
  >
  > Le principe est pertinent (Rector en dry-run sur chaque PR). Cependant :
  > - La spec CI utilise `php-version: '7.4'` — à corriger en `8.1` (décision P0.3).
  > - Le prérequis noté dans TODO.md reste valide : supprimer d'abord tout le dead code existant, sinon le job échoue dès le premier run.
  > → Alimentera **SYN.2** (TODO priorisée), catégorie CI, après DC.4.

- [x] **D.3** — Documentation d'installation
  > Lire intégralement `doc/install.local.md`, `doc/install.serveur.md`, `doc/install.tests.linux.md` (et tout autre fichier dans `doc/`). Suivre mentalement chaque étape : cohérente avec l'état réel (Docker, Makefile, MariaDB 12+, Keycloak, variables d'env) ? Lister tous les écarts, étapes manquantes, commandes obsolètes.
  >
  > **Fichiers lus** : `install.local.md`, `install.serveur.md`, `install.tests.linux.md`, `dev.md`, `start.md`, `maj.md`, `maj-v2.0.md`.
  >
  > ---
  >
  > ### `doc/install.local.md`
  >
  > **1. Port PhpMyAdmin incorrect**
  > Ligne 84 : `http://localhost:8080` pour PhpMyAdmin. Dans le dist file (`docker-compose.symfony_server.yml.dist`), PhpMyAdmin est mappé sur **8081:80**, pas 8080. Le port 8080 est occupé par Keycloak (8080:8080). Le lien est donc cassé.
  >
  > **2. Message de démarrage PHP erroné**
  > Ligne 36 : « La ligne `PHP 7.4.27 Development Server (http://0.0.0.0:8000) started` indique que le déploiement est fonctionnel ». C'est faux : le container utilise `symfony serve --allow-all-ip` (Symfony CLI), dont le message de démarrage est différent. Le numéro de version `7.4.27` est de plus hardcodé.
  >
  > **3. Étapes de setup redondantes avec le Makefile**
  > Le guide décrit `docker compose build`, `docker compose up`, puis `make setup-test` en raccourci (ligne 90). En réalité, `make setup-test` fait tout (build, up, composer install, schéma, fixtures, cache). Les étapes manuelles induisent en erreur : elles n'incluent pas `composer install`, la création du schéma, ni les stubs Encore. La doc devrait présenter `make setup-test` comme le workflow **principal**, pas comme un raccourci.
  >
  > **4. `DEV_MODE_ENABLED` non documentée**
  > Ligne 38 : mention de la variable `DEV_MODE_ENABLED` sans explication sur sa valeur, son effet, ni son emplacement (`.env`). À documenter dans CONFIG.1.
  >
  > **5. Volume Docker — l'écart est géré silencieusement par le Makefile**
  > Le dist file contient un bind mount `./mysql:...`. Le Makefile le remplace par un volume nommé `db_data` via `sed`. Ce comportement n'est pas documenté dans `install.local.md` : l'utilisateur qui copie manuellement (`cp ... compose.yaml`) obtiendra un fichier différent de celui produit par `make`.
  >
  > **6. Section Nix — cohérente**
  > `flake.nix` existe, la section est valide.
  >
  > ---
  >
  > ### `doc/install.serveur.md`
  >
  > **1. Contrainte PHP sous-évaluée**
  > Ligne 5 : « PHP (version 7.2 et supérieure) ». La contrainte réelle dans `composer.json` est `"php": "7.4"` — minima = 7.4, pas 7.2.
  >
  > **2. `assetic:dump` — commande inexistante dans ce projet**
  > Ligne 42 : `php bin/console assetic:dump`. Assetic est abandonné depuis Symfony 4. Ce projet utilise **Webpack Encore** (`webpack.config.js`, `encore-build` dans le Makefile). La commande à utiliser pour compiler les assets est `npm ci && npm run build` (ou `make encore-build`). La commande `assetic:dump` n'existe pas et renverra une erreur.
  >
  > **3. Typo dans le nom de commande Doctrine**
  > Ligne 38 : `php bin/console doctrine:migration:migrate`. Le nom correct est **`doctrine:migrations:migrate`** (avec un `s`).
  >
  > **4. `server:start` supprimé en Symfony 4**
  > Lignes 48-55 : `php bin/console server:start`. Cette commande n'existe plus en Symfony 4.x. Le serveur de développement est maintenant lancé via **`symfony serve`** (Symfony CLI). Pour la production, le guide recommande correctement nginx/Apache, mais la commande de dépannage est invalide.
  >
  > **5. Rewrite nginx obsolète — mauvais point d'entrée**
  > Lignes 69-74 : la règle nginx pointe vers `/app.php` (point d'entrée Symfony 3.x — `web/app.php`). Depuis Symfony 4, le point d'entrée est `public/index.php`. La règle correcte est :
  > ```
  > rewrite ^/sw/(.*)/(qr|br)\.png$ /index.php/sw/$1/$2.png last;
  > ```
  >
  > **6. `composer install` sans flags de production**
  > Ligne 34 : `composer install` sans `--no-dev --optimize-autoloader`. En production, cela installe les dépendances de développement (PHPStan, Rector, fixtures, etc.), ce qui est incorrect.
  >
  > **7. Pas de mention des assets ni des variables d'env**
  > Aucune instruction pour : copier `.env.dist` → `.env`, configurer les variables, ni compiler les assets JS. La section Installation est incomplète pour un déploiement réel.
  >
  > **8. Crontab — `--env=prod` absent**
  > Les commandes cron ne spécifient pas `--env=prod`. Symfony defaulte sur `dev` si `APP_ENV` n'est pas défini, ce qui charge les services de debug, désactive le cache opcode, etc. Un serveur sans variable `APP_ENV=prod` dans son environnement shell cron lancera les commandes en mode dev.
  >
  > ---
  >
  > ### `doc/dev.md`
  >
  > **1. Chemin de log incorrect**
  > Ligne 55 : `tail -100 var/logs/dev.log`. Symfony 4.x utilise `var/log/` (sans `s`). Le fichier correct est `var/log/dev.log`.
  >
  > **2. Commandes Docker obsolètes**
  > Lignes 42-47 :
  > ```
  > docker exec -i php php bin/console --env=test doctrine:database:create
  > docker exec -i php php bin/console --env=test doctrine:schema:create
  > docker exec -i php php ./vendor/bin/phpunit
  > ```
  > Ces commandes utilisent `docker exec` (syntaxe ancienne, sans `compose`) et sont entièrement remplacées par les targets Makefile (`make db-reset`, `make test`). Elles ne sont plus à jour et ne correspondent pas au workflow documenté dans `install.tests.linux.md`.
  >
  > **3. Branche principale — vérifier si `master` ou `main`**
  > Ligne 5 : « La branche principale est `master` ». À confirmer (la norme GitHub récente est `main`). Cette incohérence peut dérouter les contributeurs.
  >
  > **4. PRs recommandées en français**
  > Ligne 9 : « en préférant le Français ». Contredit la pratique réelle (et `CLAUDE.md`) qui exige l'anglais pour tous les artefacts git.
  >
  > ---
  >
  > ### `doc/start.md`
  >
  > **1. Credentials super admin incohérents**
  > Ligne 5 : `babar/password`. `install.local.md` (ligne 59) indique `admin/password`. Les deux docs doivent s'accorder. À vérifier avec les fixtures (qui définit l'admin créé par `doctrine:fixtures:load`).
  >
  > **2. Lien FOSUserBundle mort**
  > Ligne 17 : `http://symfony.com/doc/2.0/bundles/FOSUserBundle/command_line_tools.html`. Lien vers la doc Symfony 2.0 qui n'est plus maintenue ni accessible.
  >
  > ---
  >
  > ### `doc/maj.md`
  >
  > **1. `assetic:dump` — même problème que `install.serveur.md`**
  > Ligne 50 : `php bin/console assetic:dump`. Commande inexistante dans ce projet depuis la migration vers Webpack Encore. À remplacer par `npm ci && npm run build`.
  >
  > **2. Opérations absentes**
  > La procédure de mise à jour ne mentionne pas :
  > - La recompilation des assets JS (`npm ci && npm run build`)
  > - La purge de l'Opcache / redémarrage PHP-FPM après remplacement des fichiers PHP
  > - La vérification de la version PHP si un upgrade est requis
  >
  > ---
  >
  > ### `doc/maj-v2.0.md`
  >
  > **1. Diff nginx inversé — BUG CRITIQUE**
  > Lignes 71-82 : le diff de configuration nginx est **dans le mauvais sens**. Il montre le passage de SF4 vers SF3 au lieu de SF3 vers SF4 :
  > ```diff
  > -    root   /elefan/public/;    ← retire le dossier SF4
  > +    root   /elefan/web/;       ← remet le dossier SF3
  > ```
  > Pour une migration 1.47 (SF3) → 2.0 (SF4), le diff correct est :
  > ```diff
  > -    root   /elefan/web/;
  > +    root   /elefan/public/;
  >     location / {
  > -        index  app.php;
  > +        index  index.php;
  > -        try_files $uri /app.php$is_args$args;
  > +        try_files $uri /index.php$is_args$args;
  > ```
  > En l'état, un admin qui suit ce guide à la lettre dégrade sa config nginx plutôt que de la mettre à jour.
  >
  > ---
  >
  > ### `doc/install.tests.linux.md`
  >
  > C'est le fichier le mieux maintenu. Deux points mineurs :
  >
  > **1. Package `docker-compose` v1 dans les prérequis**
  > Ligne 14 : `sudo apt install -y docker.io docker-compose make`. `docker-compose` est le binaire standalone v1, déprecié. Ubuntu 22.04+ et Debian 12+ proposent `docker-compose-v2`. Le Makefile supporte les deux (autodetect), mais mieux vaut guider vers la version actuelle.
  >
  > **2. Keycloak non démarré par `make up`**
  > `make test-e2e-oidc` nécessite Keycloak, mais `make up` ne démarre que `database`, `php`, `mailcatcher`. Aucune instruction pour démarrer Keycloak localement pour les tests OIDC.
  >
  > ---
  >
  > **Résumé des gravités :**
  > | Gravité | Finding |
  > |---------|---------|
  > | 🔴 Critique | `maj-v2.0.md` — diff nginx inversé (migration casse la prod) |
  > | 🟠 Important | `install.serveur.md` — `assetic:dump` inexistant ; `server:start` inexistant ; PHP >= 7.2 (réel : 7.4) ; rewrite nginx `/app.php` → `/index.php` ; `composer install` sans `--no-dev` |
  > | 🟠 Important | `install.local.md` — port PhpMyAdmin 8080 vs 8081 réel ; message de démarrage PHP faux |
  > | 🟠 Important | `dev.md` — `var/logs/` faux (→ `var/log/`) ; commandes Docker sans `compose` obsolètes |
  > | 🟡 Mineur | `maj.md` — `assetic:dump` + opérations manquantes post-update |
  > | 🟡 Mineur | `start.md` — credentials admin incohérents ; lien mort FOSUserBundle |
  > | 🟡 Mineur | `install.tests.linux.md` — `docker-compose` v1 dans prérequis ; Keycloak OIDC non documenté |
  >
  > → Toutes ces corrections alimenteront **SYN.1** (documentation mise à jour).

- [x] **D.4** — CHANGELOG.md
  > Géré par release-please ? Dernières entrées cohérentes avec git log ? Juste une vérification rapide.
  >
  > **Findings :**
  >
  > **1. release-please configuré et opérationnel**
  > Manifest `{".": "1.47.2"}` cohérent avec le dernier tag git. Config v4, `release-type: php`, sections françaises personnalisées (`feat` → Nouveautés, `fix` → Corrections, etc.). Aucun type Conventional Commit n'est masqué (`hidden: false` sur tous) — y compris `chore`, `ci`, `build` — ce qui donne des sections "Technique" très verbeuses.
  >
  > **2. 4 versions manquantes dans le CHANGELOG**
  > Les tags git v1.45.8, v1.45.9, v1.46.0, v1.47.0 existent mais n'ont pas d'entrée CHANGELOG. Ces versions couvrent la période 2023-2025 entre l'adoption de release-please (PR #1049, commit 58d5b0aa) et les premières releases entièrement gérées par l'outil. Les notes de release de cette période sont sur GitHub (onglet Releases) mais absentes du fichier CHANGELOG.md.
  >
  > **3. Trois formats coexistent dans le fichier**
  > | Période | Format | Exemple |
  > |---------|--------|---------|
  > | 1.47.1+ | release-please (liens comparatifs, sections par type, sans "v" dans le titre) | `## [1.47.2](...)` |
  > | v1.45.0–v1.45.7 | GitHub Release Notes (liste PR avec `@auteur`) | `## [v1.45.7](...)` |
  > | v1.44.x et avant | Date-first, sans lien comparatif | `## 2023-06-28 (v1.44.7)` |
  > Cette hétérogénéité est cosmétique et sans impact fonctionnel.
  >
  > **4. Doublons dans l'entrée 1.47.2**
  > Plusieurs items de la section 1.47.1 sont répétés dans 1.47.2 (ex: "ajout d'une Github Action pour assign automatiquement l'auteur", commit 2f64030, ligne 13 et ligne 32). Anomalie connue de release-please quand des PRs mergées avant la release sont incluses dans le prochain cycle de calcul.
  >
  > **5. `extra-files` référence un fichier inexistant — BUG SILENCIEUX**
  > `release-please-config.json` déclare `"extra-files": ["app/config/config.yml"]` pour y écrire la version à chaque release. Ce fichier est issu de la structure Symfony 3.x et **n'existe plus dans ce projet Symfony 4**. Conséquence : release-please ne met à jour aucun fichier de version dans le code — l'update est silencieusement ignoré. Le champ devrait être supprimé ou remplacé par le fichier réel qui porte la version (si un tel fichier existe).
  >
  > → Le finding 5 (extra-files obsolète) alimentera **SYN.2** (TODO priorisée), catégorie CI/Technique.

- [x] **D.5** — Annotations internes
  > `grep -rn "@deprecated\|@todo\|@fixme\|TODO\|FIXME\|HACK" src/` — inventaire complet. Certains révèlent des intentions non documentées ou des comportements à expliquer dans les specs.
  >
  > **25 annotations trouvées** dans 14 fichiers. Regroupées par type de problème.
  >
  > ---
  >
  > ### Groupe 1 — Bugs potentiels / comportements incorrects
  >
  > **1. `CloseMembershipCommand.php:62` — `setWithdrawnBy()` jamais appelé (🟠)**
  > `$member->setWithdrawnBy(); //TODO` est commenté. Quand la commande cron clôture automatiquement une adhésion expirée, l'attribut `withdrawnBy` reste `null`. Il est impossible de distinguer une clôture automatique (cron) d'une clôture manuelle par un admin. Traçabilité perdue. → **TODO priorisée SYN.2**
  >
  > **2. `AdminShiftExemptionController.php:104` — erreur 500 en cas de suppression référencée (🟠)**
  > `// TODO: error 500 if shiftExemption is used in membershipShiftExemption`. La suppression d'un motif d'exemption (`ShiftExemption`) référencé par une `MembershipShiftExemption` provoquera une violation de contrainte FK → erreur 500. Aucune vérification préalable ni gestion d'erreur. → **TODO priorisée SYN.2**
  >
  > **3. `MembershipService.php:155` — durée de cycle hardcodée à 28 jours (🟠)**
  > `// TODO should use cycle_duration instead of hardcoded 28`. La méthode `getStartOfCycle()` calcule les dates via `28 * $cycleOffset` jours. Si une instance configure `cycle_duration` différemment (ex: 14 jours), le calcul est faux. Potentiellement incorrect en multi-instance. → **CONFIG.3** + **TODO SYN.2**
  >
  > **4. `EmailingEventListener.php:178` — email de création de membre non implémenté (🟡)**
  > `onMemberCreated()` est déclaré, écouté, mais son corps est `// TODO ?`. L'événement `MemberCreatedEvent` ne déclenche aucun email. L'email de bienvenue n'existe pas, ou l'écouteur est une coquille oubliée. → **SPEC.7** (notifications) + **TODO SYN.2**
  >
  > ---
  >
  > ### Groupe 2 — Valeurs magiques non configurables
  >
  > **5. `CodeVoter.php:130-132` — fenêtres de badge hardcodées (🟡)**
  > `-120min` et `+60min` sont les bornes de la fenêtre de validation badge (accès swipe autorisé 2h après fin créneau, 1h avant début). Ces valeurs devraient être dans la config. Dupliquées dans `UserVoter` et `MembershipVoter` (méthode `isLocationOk()` copiée-collée, cf. D.2). → **CONFIG.3** + **TODO SYN.2**
  >
  > **6. `SearchUserFormHelper.php:79` — choix "nb bénéficiaires" hardcodés (🟡)**
  > Les choix `[1, 2]` sont fixes au lieu d'utiliser `maximum_nb_of_beneficiaries_in_membership`. Si un paramètre instance autorise 3+ bénéficiaires, le formulaire de recherche affiche des options incomplètes. → **CONFIG.3** + **TODO SYN.2**
  >
  > ---
  >
  > ### Groupe 3 — Duplication de code (confirmée par les TODOs)
  >
  > **7. `BookingController` + `MembershipController` + `ShiftController` — 7 méthodes `createShift*Form` dupliquées (🟠)**
  > Chaque TODO (`// TODO: how to avoid having same createShift*Form in ShiftController ?`) documente explicitement la duplication : `createShiftBookAdminForm`, `createShiftDeleteForm`, `createShiftFreeForm`, `createShiftFreeAdminForm`, `createShiftValidateInvalidateAdminForm` dans 3 controllers. Même pattern observé sur `getErrorMessages` (5 controllers : `BeneficiaryController`, `UserController`, `TaskController`, `ServiceController`, `CodeController`) et `redirectToShow` (4 controllers : `BeneficiaryController`, `MembershipController`, `NoteController`, `TimeLogController`). → **AP** section + **TODO SYN.2**
  >
  > ---
  >
  > ### Groupe 4 — Incertitudes architecturales et dette technique
  >
  > **8. `BeneficiaryController.php:187` — `getErrorMessages()` privée probablement morte (🟡)**
  > Le TODO original est `// TODO: check if this function is ever used ?!`. Grep de tous les appelants : aucun appel externe dans `BeneficiaryController`. Seule la récursion interne `$this->getErrorMessages($child)` existe. Candidat dead code non détecté par Rector (récursion self-référente). → **EXTRA** (vérification DC.3)
  >
  > **9. `BeneficiaryController.php:269` / `MembershipController.php:153` / `NoteController.php:140` — pattern `// FIXME` triplé (🟡)**
  > `$user = $member->getMainBeneficiary()->getUser(); // FIXME` dans 3 `redirectToShow()` différents. Le FIXME sans explication suggère un problème connu mais non résolu : null-safety (`getMainBeneficiary()` peut être `null` pour une adhésion sans bénéficiaire principal), ou duplication de logique token temporaire hors admin. Ces 3 copies seront probablement affectées par le même bug silencieux. → **TODO SYN.2**
  >
  > **10. `Beneficiary::isNew()` ligne 746 — seuil hardcodé à 3 créneaux (🟡)**
  > `TODO: move to Membership? Look at registration data instead?` — la notion de "nouveau bénéficiaire" (≤ 3 créneaux) est définie dans l'entité mais utilisée dans `CodeVoter` pour bloquer l'accès badge aux débutants. La règle métier n'est ni configurable, ni documentée. La question de localisation (entité vs `Membership`) n'est pas résolue. → **SPEC.4** (auth) + **CONFIG.3**
  >
  > **11. `Membership::getFrozen()` ligne 457 — `@deprecated` sans enforcement (🟡)**
  > `@deprecated illogic isFlying, isWithdrawn but getFrozen`. La méthode `getFrozen()` coexiste avec `isFrozen()`. Sans enforcement, des appelants peuvent utiliser l'ancienne API sans avertissement. → **TODO SYN.2** (nettoyage, effort S)
  >
  > **12. `ShiftService.php:255` — `shift_cycle` identifié à supprimer mais encore actif (🟡)**
  > `// TODO refactor code to remove shift_cycle` — le concept `shift_cycle` est utilisé dans `canBookShift()` comme intermédiaire pour `canBookDuration()`, alors que le commentaire indique qu'il devrait utiliser les shifts directement (via `TimeLog`). Refactoring attendu mais non planifié. → **TODO SYN.2**
  >
  > **13. `FixShiftMissingPositionCommand.php:52` — commande de réparation sans filtre weekCycle (🟡)**
  > `// TODO : add filter on weekCycle` — la commande applique la réparation à tous les cycles sans distinction. Pour un planning multi-cycles (A/B/C), l'absence de filtre peut affecter des shifts de cycles non concernés. → **TODO SYN.2**
  >
  > **14. `AdminPeriodController.php:381` — feature gap `use_fly_and_fixed` + copie période (🟡)**
  > `// TODO: if use_fly_and_fixed, give option to chose if shifter/booker is copied as well`. Lors de la duplication d'une période, l'option de copier le shifter/booker n'est pas proposée quand `use_fly_and_fixed` est activé. Feature incomplète. → **SPEC.3** (créneaux) + **TODO SYN.2**
  >
  > ---
  >
  > **Résumé des gravités :**
  > | Gravité | Count | Findings |
  > |---------|-------|---------|
  > | 🟠 Important | 4 | FK violation suppression exemption ; withdrawnBy manquant ; cycle_duration hardcodé ; duplication createShift*Form |
  > | 🟡 Mineur | 10 | Valeurs magiques non configurables ; FIXME triplé ; méthode @deprecated ; etc. |
  >
  > → Tous les items "→ TODO SYN.2" alimenteront la TODO priorisée finale.

---

## DEP — Dépendances

- [x] **DEP.1** — Audit sécurité
  > Outil : `symfony security:check` (dans container) pour PHP — `composer audit` indisponible en Composer 2.2 LTS.
  > JS : `npm audit` sur le host.
  >
  > ---
  >
  > ### PHP — 30 CVEs dans 14 packages (`symfony security:check`)
  >
  > #### 🔴 Critique — auth bypass, injection, exécution de code
  >
  > | Package | Version | CVE | Description |
  > |---------|---------|-----|-------------|
  > | `symfony/security-http` | v4.4.50 | CVE-2026-45063 | Usurpation d'identité via regex DN non ancrée dans `X509Authenticator` |
  > | `symfony/security-http` | v4.4.50 | CVE-2026-48489 | Bypass du firewall via sous-requête `failure_forward` → accès non authentifié aux routes protégées par `access_control` |
  > | `symfony/cache` | v4.4.48 | CVE-2026-45073 | SQL injection dans `PdoAdapter::doClear()` via `$prefix` non échappé |
  > | `symfony/http-foundation` | v4.4.49 | CVE-2025-64500 | Parsing incorrect de `PATH_INFO` → contournement partiel d'autorisation |
  > | `symfony/mailer` | v4.4.49 | CVE-2026-45068 | Argument injection dans `SendmailTransport` via destinataire avec tiret |
  > | `symfony/mime` | v4.4.47 | CVE-2026-45067 | Injection d'en-têtes email / commande SMTP via CRLF dans `Address` |
  > | `symfony/mime` | v4.4.47 | CVE-2026-45070 | Injection d'en-têtes via caractères non-token dans les noms de paramètre |
  > | `twig/twig` | v2.16.1 | CVE-2026-46633 | Injection de code PHP via nom de template contrôlé dans `{% use %}` |
  > | `twig/twig` | v2.16.1 | CVE-2026-46628 | Le filtre `spaceless` marque sa sortie comme sûre implicitement → XSS potentiel |
  >
  > **Note sandbox Twig** : les CVE CVE-2024-51754/55, CVE-2026-24425, CVE-2026-46627/35/36/38, CVE-2026-47732, CVE-2026-48805/06/07/08 **ne s'appliquent pas** — la sandbox Twig n'est pas activée dans ce projet.
  >
  > #### 🟠 Important — redirections, DoS, désérialisation
  >
  > | Package | Version | CVE | Description |
  > |---------|---------|-----|-------------|
  > | `symfony/http-foundation` | v4.4.49 | CVE-2024-50345 | Open redirect via URLs normalisées par le navigateur |
  > | `symfony/routing` | v4.4.44 | CVE-2026-45065 | `UrlGenerator` bypass regex non ancrée → injection d'URL hors-site |
  > | `symfony/routing` | v4.4.44 | CVE-2026-48784 | Encodage des segments `.` saute 1 sur 2 → URL s'effondre sous normalisation RFC 3986 |
  > | `symfony/dom-crawler` | v4.4.45 | CVE-2026-45071 | XXE dans `DomCrawler::addXmlContent()` si `validateOnParse = true` (opt-in) |
  > | `symfony/monolog-bridge` | v4.4.43 | CVE-2026-45077 | Désérialisation PHP non authentifiée dans le listener `server:log` (nécessite port exposé) |
  > | `symfony/yaml` | v4.4.45 | CVE-2026-45133 | Stack exhaustion parser YAML via blocs imbriqués non bornés |
  > | `symfony/yaml` | v4.4.45 | CVE-2026-45304 | Allocation mémoire exponentielle via alias récursifs ("Billion Laughs") |
  > | `symfony/yaml` | v4.4.45 | CVE-2026-45305 | ReDoS via backtracking catastrophique dans `Parser::cleanup()` |
  >
  > #### 🟡 Mineur — périmètre limité
  >
  > | Package | Version | CVE | Description | Limite |
  > |---------|---------|-----|-------------|--------|
  > | `symfony/validator` | v4.4.48 | CVE-2024-50343 | Réponse incorrecte quand l'input se termine par `\n` | Comportement edge-case |
  > | `symfony/polyfill-intl-idn` | v1.33.0 | CVE-2026-46644 | Labels `xn--` avec payload ASCII acceptés comme équivalents | IDN peu utilisé |
  > | `symfony/process` | v4.4.44 | CVE-2024-51736 | Hijack d'exécution via `Process` | **Windows uniquement** |
  > | `phpunit/phpunit` | 9.6.32 | CVE-2026-24765 | Désérialisation non sûre dans PHPT code coverage | **Dev uniquement** |
  >
  > **Contexte** : toutes ces CVEs ont une correction disponible dans les versions Symfony 4.4.x maintenues (pas de saut de version majeure requis). La contrainte bloquante est que ce projet est verrouillé à Symfony 4.4 — un `composer update symfony/*` est possible sans rompre la compatibilité SF4.
  >
  > ---
  >
  > ### JS — 47 vulnérabilités (`npm audit`)
  >
  > #### 🔴 Critique — production, sans correctif
  >
  > | Package | CVE/Advisory | Description | Correctif |
  > |---------|-------------|-------------|-----------|
  > | `simplemde` `*` | GHSA-wg85-p6j7-gp3w | XSS dans le rendu markdown — **aucun fix disponible**, projet abandonné | Remplacement requis (ex: EasyMDE, fork activement maintenu) |
  >
  > **Contexte** : `simplemde` est une dépendance de **production** (champ `dependencies` dans `package.json`), incluse dans le bundle final. Elle est utilisée comme éditeur markdown dans les formulaires (`assets/js/app.js`, `templates/form/fields.html.twig`). Le projet est archivé sur GitHub depuis 2017 — aucune mise à jour de sécurité à attendre.
  >
  > #### 🟠 Important — build/dev toolchain (hors bundle production)
  >
  > Les 46 autres vulnérabilités (dont 2 critiques `form-data` + `@babel/plugin-transform-modules-systemjs`) sont dans la chaîne de build :
  > - `@symfony/webpack-encore`, `webpack`, `webpack-dev-server`, `webpack-dev-middleware` : **serveur de dev uniquement**, non inclus dans le bundle de production
  > - `cypress` et ses transitives (`@cypress/request`, `form-data`, `uuid`) : **tests E2E uniquement**
  > - `@babel/*`, `lodash`, `serialize-javascript`, `terser-webpack-plugin` : **transpileur/minifieur**, non exposés en runtime
  >
  > **Exceptions notables dans la toolchain :**
  > | Package | Sévérité | Advisory | Impact pratique |
  > |---------|---------|---------|----------------|
  > | `form-data` < 2.5.4 | Critique | GHSA-fjxv-7rqg-78g4 | Aléatoire non cryptographique pour boundary multipart — via `@cypress/request` (Cypress dev) |
  > | `@babel/plugin-transform-modules-systemjs` | Haute | GHSA-fv7c-fp4j-7gwp | Code arbitraire si input malveillant au transpileur — risque CI si le code source est non maîtrisé |
  > | `webpack-dev-middleware` | Haute | GHSA-wr3j-pwj9-hqq6 | Path traversal — **dev serveur uniquement** |
  > | `lodash` | Haute | GHSA-xxjr-mmjv-4gpg | Prototype pollution — dans la chaîne webpack (dev) |
  >
  > **`npm audit fix`** : 43 vulnérabilités corrigeables sans breaking change. 4 restantes nécessitent `--force` (upgrade `@symfony/webpack-encore` 1.x → 6.0, breaking change).
  >
  > ---
  >
  > ### Synthèse et priorisation
  >
  > | Priorité | Action | Effort |
  > |----------|--------|--------|
  > | 🔴 Immédiat | `composer update symfony/*` : patch toutes les CVEs SF4.4 sans rupture | S |
  > | 🔴 Court terme | Remplacer `simplemde` par EasyMDE (fork maintenu, API compatible) | M |
  > | 🟠 Build | `npm audit fix` sur la toolchain (43 fixes sans breaking change) | S |
  > | 🟡 Optionnel | Upgrade `@symfony/webpack-encore` 1.x → 6.0 (breaking, évaluer au cas par cas) | L |
  >
  > → Toutes les actions de priorité 🔴 alimenteront **SYN.2** (TODO priorisée), catégorie Sécurité.

- [x] **DEP.2** — Packages abandonnés
  > Évaluer l'impact de chacun sur la migration future :
  > - `sensio/framework-extra-bundle` — remplacé par attributs Symfony natifs (bloquant SF6)
  > - `friendsofsymfony/user-bundle` — incompatible SF5+ (bloquant majeur)
  > - `friendsofsymfony/oauth-server-bundle` — incompatible SF5+ (bloquant majeur)
  > - `doctrine/cache`, `doctrine/reflection`, `ornicar/gravatar-bundle`, `symfony/debug`, `symfony/inflector` — dépendances transitives ou remplacements disponibles
  > Pour chacun : utilisé directement dans `src/` ? Effort estimé de remplacement (S/M/L/XL) ? Résultat → TODO finale.
  >
  > **Findings :**
  >
  > ---
  >
  > ### 1. `sensio/framework-extra-bundle` v6.2.10 — DIRECT, **bloquant SF6**
  >
  > | Usage | Volume |
  > |-------|--------|
  > | `@Security` (Sensio) dans les controllers | **160 occurrences dans 36 fichiers** |
  > | `@Route` + `@Method` (ancien style Sensio) | **2 fichiers** : `AdminShiftFreeLogController`, `AdminPeriodPositionFreeLogController` |
  > | `@Template`, `@ParamConverter` | **0** (déjà non utilisés) |
  >
  > **Status** : Archivé/abandonné par SensioLabs. Incompatible SF6 (le reader d'annotations doctrine est supprimé en SF6, tout doit passer aux attributs PHP 8). Supporté en SF4/SF5 uniquement.
  >
  > **Migration** : `@Security("is_granted('ROLE_X')")` → `#[IsGranted('ROLE_X')]` (natif Symfony depuis SF5.2). Rector automatise cette conversion (`AnnotationsToAttributesRector`). Les 2 fichiers avec `@Route`/`@Method` Sensio doivent être migrés vers `Symfony\Component\Routing\Attribute\Route`.
  >
  > **Effort** : **M** — 36 fichiers, migration automatisable via Rector, vérification manuelle requise.
  >
  > ---
  >
  > ### 2. `friendsofsymfony/user-bundle` v2.2.4 — DIRECT, **bloquant majeur SF5+**
  >
  > **Profondeur d'ancrage dans le projet :**
  > - `src/Entity/User.php` étend `FOS\UserBundle\Model\User as BaseUser` — l'entité User hérite directement des champs et méthodes du bundle (password, username, email, roles, etc.)
  > - **14 templates overrridés** dans `templates/bundles/FOSUserBundle/` (login, registration, profile, resetting, password change)
  > - Routes auth entièrement issues de FOSUserBundle : `fos_user_security_login`, `fos_user_security_check`, `fos_user_registration_*`, `fos_user_resetting_*`, `fos_user_change_password` — référencées dans **7 templates** et **1 controller**
  > - `security.yaml` : provider `fos_user.user_provider.username_email`, firewall `check_path`/`login_path` sur des routes FOS
  > - Events `FOS\UserBundle\Event\*` utilisés dans 2 listeners custom
  > - 12 imports `use FOS\UserBundle\...` dans `src/`
  >
  > **Status** : Pas de version compatible SF5+. Le bundle est en maintenance minimale depuis 2019 et officiellement non supporté au-delà de SF4.
  >
  > **Migration** : Remplacement complet par le SecurityBundle natif :
  > 1. Entité `User` : rapatrier tous les champs de `BaseUser` dans la classe elle-même, implémenter `UserInterface` + `PasswordAuthenticatedUserInterface` natifs SF5
  > 2. Provider : `InMemoryUserProvider` ou implémentation de `UserProviderInterface` custom
  > 3. Authentication : migrer vers `FormLoginAuthenticator` natif SF5
  > 4. Registration/Profile/Password reset : réécrire en controllers custom (plus de bundle pour ça)
  > 5. Remplacer les events FOS par les events natifs ou custom
  >
  > **Effort** : **XL** — c'est le plus gros chantier de migration. L'entité User est au cœur du modèle de données, les flows auth/registration/reset sont exposés aux utilisateurs finaux.
  >
  > ---
  >
  > ### 3. `friendsofsymfony/oauth-server-bundle` v1.6.2 — DIRECT, **bloquant majeur SF5+**
  >
  > **Profondeur d'ancrage :**
  > - 4 entités OAuth qui étendent des classes du bundle : `AccessToken`, `RefreshToken`, `AuthCode`, `Client`
  > - `src/EventListener/OAuthEventListener.php` utilise `FOS\OAuthServerBundle\Event\OAuthEvent`
  > - Routes exposées via `fos_oauth_server.yaml` (token endpoint, authorize endpoint)
  > - Configuration `security.yaml` : firewall OAuth avec `fos_oauth: true`
  > - `src/Entity/Membership.php` importe `ClientInterface` du bundle
  >
  > **Status** : Incompatible SF5+. Remplacé dans l'écosystème par `thephpleague/oauth2-bundle` (maintenu, supporte SF5+/SF6+).
  >
  > **Migration** : `thephpleague/oauth2-bundle` + `league/oauth2-server` est le remplacement standard :
  > 1. Remplacer les 4 entités (AccessToken, RefreshToken, AuthCode, Client) par les interfaces `league/oauth2-server`
  > 2. Reconfigurer les routes (token, authorize) via `thephpleague/oauth2-bundle`
  > 3. Adapter `OAuthEventListener` aux nouveaux events
  >
  > **Effet de bloc** : dépend aussi de la migration FOSUserBundle (l'identité utilisateur dans OAuth est liée au `User` FOS). Les deux doivent être migrés ensemble ou séquentiellement (FOSUserBundle d'abord).
  >
  > **Effort** : **L** — 4 entités + listener + config. Bloqué par la migration FOSUserBundle.
  >
  > ---
  >
  > ### 4. `ornicar/gravatar-bundle` v1.3.0 — DIRECT, **non-bloquant, remplaçable**
  >
  > **Usage :**
  > - Filtre Twig `{{ gravatar(email) }}` utilisé dans **12 templates** (avatars partout dans l'UI)
  > - `AdminController` et `RegistrationsController` : imports `GravatarApi`/`GravatarHelper` présents **mais aucune instantiation** — imports morts
  > - `ApiController.php:111` : `new GravatarHelper(new GravatarApi())` — seul usage réel en PHP
  > - Configuration : `ornicar_gravatar.yaml` (rating `g`, size `80`, default `robohash`)
  >
  > **Status** : Dernière version publiée en 2018. Pas de release SF5/SF6 officielle. Cependant, l'API Gravatar est triviale (MD5 de l'email + paramètres URL) — le bundle est un wrapper minimal.
  >
  > **Migration** : Une Twig Extension custom de ~25 lignes (`GravatarExtension`) reproduit le filtre sans dépendance. Aucun changement de template requis.
  >
  > **Effort** : **S** — 1 fichier à créer, 2 imports morts à supprimer dans AdminController et RegistrationsController, 1 instantiation dans ApiController à adapter.
  >
  > ---
  >
  > ### 5–8. Dépendances transitives (non bloquantes)
  >
  > | Package | Requis par | Status | Impact migration |
  > |---------|-----------|--------|-----------------|
  > | `doctrine/cache` v1.13.0 | `doctrine/common`, `doctrine/dbal`, `doctrine/orm`, `doctrine/persistence` | Remplacé par adaptateurs `symfony/cache` en Doctrine 3.x | Disparaît lors de l'upgrade Doctrine 2.x → 3.x. Aucune action directe. |
  > | `doctrine/reflection` v1.2.4 | Packages Doctrine internes | Toujours maintenu par Doctrine Project | Non bloquant. |
  > | `symfony/debug` v4.4.44 | `symfony/error-handler` | Shim de compat SF4→SF5. Supprimé en SF5. | Disparaît lors de l'upgrade SF4 → SF5. Aucune action directe. |
  > | `symfony/inflector` v4.4.44 | `symfony/property-access` | Fusionné dans `symfony/string` en SF5. | Disparaît lors de l'upgrade SF4 → SF5. Aucune action directe. |
  >
  > Aucune de ces 4 dépendances n'est importée directement dans `src/`.
  >
  > ---
  >
  > ### Synthèse et priorisation
  >
  > | Priorité | Package | Effort | Remarque |
  > |----------|---------|--------|---------|
  > | 🔴 Bloquant SF5+ | `friendsofsymfony/user-bundle` | **XL** | À traiter EN PREMIER — toutes les migrations auth en dépendent |
  > | 🔴 Bloquant SF5+ | `friendsofsymfony/oauth-server-bundle` | **L** | Après FOSUserBundle |
  > | 🟠 Bloquant SF6 | `sensio/framework-extra-bundle` | **M** | Rector automatise 95 % de la migration |
  > | 🟡 Non-bloquant | `ornicar/gravatar-bundle` | **S** | Bundle abandonné, remplacement trivial |
  > | ✅ Transparent | `doctrine/cache`, `symfony/debug`, `symfony/inflector`, `doctrine/reflection` | — | Disparaissent automatiquement lors des upgrades Doctrine/Symfony |
  >
  > → **FOSUserBundle et FOSOAuthServerBundle** sont les deux bloquants majeurs de toute migration SF5+. Ils devront faire l'objet d'une estimation détaillée en **SF-PREP.2** (item Opus).
  > → `sensio/framework-extra-bundle` alimentera **SF-PREP.3** (inventaire annotations) et la **TODO SYN.2**.
  > → `ornicar/gravatar-bundle` → **TODO SYN.2**, catégorie refactoring mineur.

- [x] **DEP.3** — Dépendances JS
  > Lire `package.json`. Packages inutilisés ou vulnérables.
  >
  > **Findings :**
  >
  > ---
  >
  > ### Production dependencies
  >
  > **1. `canvas-gauges` ^2.1.5-radial — PHANTOM + CDN CASSÉ (🔴)**
  >
  > Le package npm est installé (2.1.7 en lock) mais **jamais importé** dans `assets/js/`. Il est chargé exclusivement via une balise `<script>` CDN externe dans `templates/layout.html.twig:80` :
  > ```html
  > <script src="https://cdn.rawgit.com/Mikhus/canvas-gauges/..."></script>
  > ```
  > **rawgit.com a fermé en octobre 2019** — l'URL ne répond plus. La jauge radiale est rendue dans `templates/booking/home_dashboard.html.twig:9` (`{% if display_gauge %}`). La feature "jauge de remplissage" du dashboard est **cassée en production** pour toutes les instances.
  >
  > Fix : remplacer le tag CDN par `require('canvas-gauges')` dans `app.js` (package npm déjà installé).
  >
  > **2. `jquery` ^3.4.1** — Utilisé ✅ (importé dans `app.js`). Lock résout à 3.7.1.
  >
  > **3. `material-icons-css` ^1.0.1 — INUTILISÉ (🟡)**
  >
  > Package installé mais **jamais importé** dans les JS ou LESS. Les icônes Material Design sont servies via des polices locales dans `assets/fonts/iconfont/`, déclarées dans `assets/less/material-icons.less` (importé via `custom.less`). Le package npm est un doublon inutile. Supprimable de `package.json`.
  >
  > **4. `materialize-css` ^1.0.0** — Utilisé ✅ (importé dans `app.js`).
  >
  > **5. `simplemde` ^1.11.2** — Utilisé. Vulnérable/abandonné, déjà documenté en DEP.1.
  >
  > ---
  >
  > ### Dev dependencies
  >
  > **6. `@babel/plugin-proposal-class-properties` ^7.18.6 — DÉPRÉCIÉ (🟡)**
  >
  > Utilisé dans `webpack.config.js:55`. Marqué comme deprecated dans le package-lock : _"This proposal has been merged to the ECMAScript standard […] Please use @babel/plugin-transform-class-properties instead."_ Remplacement direct : `@babel/plugin-transform-class-properties`.
  >
  > **7. `@hotwired/stimulus` ^3.0.0 — INUTILISÉ (🟡)**
  >
  > `.enableStimulusBridge()` est **commenté** dans `webpack.config.js`. Aucun import Stimulus dans les fichiers JS, aucun `assets/bootstrap.js` ni `assets/controllers.json`. Supprimable.
  >
  > **8. `@symfony/stimulus-bridge` ^3.0.0 — INUTILISÉ (🟡)** — même raison que ci-dessus.
  >
  > **9. `@symfony/webpack-encore` ^1.7.0** — Utilisé ✅. Lock résout à 1.8.2 — version ancienne (encore actuel ~4.x), fonctionnelle.
  >
  > **10. `core-js` ^3.0.0** — Utilisé implicitement ✅ (via Babel `useBuiltIns: 'usage'` + `corejs: 3`).
  >
  > **11. `cypress` ^13.6.4** — Utilisé ✅ (tests E2E).
  >
  > **12. `cypress-dotenv` ^2.0.0 — INUTILISÉ (🟡)**
  >
  > Non référencé dans `cypress.config.js` ni dans les fichiers `cypress/support/`. Supprimable.
  >
  > **13. `file-loader` ^6.2.0** — Utilisé ✅ (webpack-encore v1.x l'utilise pour `copyFiles()`).
  >
  > **14. `less` ^4.2.0 + `less-loader` ^11.1.3** — Utilisés ✅ (`.enableLessLoader()` + imports LESS dans `app.js`).
  >
  > **15. `regenerator-runtime` ^0.13.2 — PROBABLEMENT INUTILISÉ (🟡)**
  >
  > Non importé dans `assets/js/`. Babel avec `@babel/preset-env` + `useBuiltIns: 'usage'` + `corejs: 3` gère automatiquement les polyfills async/generator. Supprimable si le build passe sans.
  >
  > **16. `webpack-notifier` ^1.8.0** — Utilisé ✅ (`.enableBuildNotifications()`).
  >
  > ---
  >
  > ### Fichiers parasites committés
  >
  > **17. `assets/js/jquery-3.6.js`** — Copie locale complète de jQuery 3.6.1 (10 909 lignes). Non importée dans `app.js` ni dans aucun template. Stale artifact, supprimable.
  >
  > **18. `assets/less/card.css`, `custom.css`, `custom_animation.css`** — CSS pré-compilés committés aux côtés des sources LESS. Artefacts de l'ère pré-webpack. Non importés par webpack (qui compile les `.less` directement). Supprimables. Voir EXTRA : `custom_animation.less` non bundlée + lien cassé dans `period/index.html.twig`.
  >
  > ---
  >
  > ### Synthèse
  >
  > | Priorité | Finding | Action |
  > |----------|---------|--------|
  > | 🔴 Feature cassée | `canvas-gauges` CDN rawgit.com HS | Remplacer CDN par import npm dans `app.js` |
  > | 🟡 Nettoyage | `material-icons-css` inutilisé | Supprimer de `package.json` |
  > | 🟡 Nettoyage | `@hotwired/stimulus` + `@symfony/stimulus-bridge` inutilisés | Supprimer de `package.json` |
  > | 🟡 Nettoyage | `cypress-dotenv` inutilisé | Supprimer de `package.json` |
  > | 🟡 Nettoyage | `regenerator-runtime` probablement inutilisé | Supprimer + vérifier le build |
  > | 🟡 Dépréciation | `@babel/plugin-proposal-class-properties` | Remplacer par `plugin-transform-class-properties` |
  > | 🟡 Artefacts | `jquery-3.6.js` + CSS pré-compilés dans `less/` | Supprimer du dépôt |
  >
  > → Les items 🔴 et 🟡 nettoyage alimenteront **SYN.2** (TODO priorisée), catégorie JS/Frontend.

---

## DC — Dead code (analyse uniquement)

- [x] **DC.1** — Rector DeadCode dry-run
  > **23 fichiers** identifiés. Catégories :
  > - `RemoveUnusedConstructorParamRector` : `ShiftBookedEvent` ($fromAdmin), `Html2Pdf` ($container)
  > - `RemoveUnusedPrivatePropertyRector` + `RemoveEmptyClassMethodRector` : `Html2Pdf`
  > - `RemoveParentDelegatingConstructorRector` : `UserAdminType`, `UserWithBeneficiaryType`
  > - `RemoveUnusedClosureVariableUseRector` : plusieurs Form types
  > - `RemoveDeadReturnRector` : `AuthenticationSuccessHandler`
  > - `RemoveNullArgOnNullDefaultParamRector` : `CommissionEventListener`
  > - `RemoveUselessParamTagRector` / `RemoveUselessReturnTagRector` : `EmailingEventListener` + autres
  > - `SimplifyUselessVariableRector` : `SwipeCard::generateCode()`
  > Résultat complet → TODO finale (DC.4).

- [x] **DC.2** — Vérification manuelle des call sites à risque
  > Pour `ShiftBookedEvent($fromAdmin)` : grep des appelants pour confirmer que l'argument n'est jamais passé. Note le résultat ici.
  >
  > **Findings :**
  >
  > ---
  >
  > ### 1. `ShiftBookedEvent($fromAdmin)` — BUG silencieux, PAS du dead code (🟠)
  >
  > **Situation réelle (inverse de ce que Rector suggère) :**
  > - Le constructeur `__construct(Shift $shift, bool $fromAdmin)` reçoit `$fromAdmin` mais **ne l'assigne jamais** — la ligne `$this->fromAdmin = $fromAdmin;` est absente du corps.
  > - Les appelants passent l'argument de façon intentionnelle :
  >   | Fichier | Ligne | Valeur | Contexte |
  >   |---------|-------|--------|---------|
  >   | `ShiftController.php` | 179 | `false` | booking utilisateur (route admin) |
  >   | `ShiftController.php` | 235 | `true` | booking admin |
  >   | `ShiftController.php` | 518 | `false` | booking utilisateur (route self-service) |
  > - `isFromAdmin()` existe et retourne `$this->fromAdmin` — mais cette propriété est toujours non-initialisée → retourne toujours `null`.
  > - `TimeLogEventListener::onShiftBooked()` et `EmailingEventListener::onShiftBooked()` : aucun des deux n'appelle `isFromAdmin()`. **Le bug est silencieux** : les listeners n'ont actuellement aucun comportement conditionnel selon l'origine admin/user.
  >
  > **Classification** : bug (assignment manquant dans le constructeur), pas dead code.
  > **Action recommandée** : ajouter `$this->fromAdmin = $fromAdmin;` dans le constructeur, OU supprimer le paramètre ET la méthode si la distinction admin/user n'est réellement jamais exploitée. À décider selon le comportement voulu.
  > → **SYN.2** (TODO, catégorie bugs, effort XS)
  >
  > ---
  >
  > ### 2. `App\Helper\Html2Pdf($container)` — classe entière dead code (🟠)
  >
  > - `$container` est assigné dans le constructeur (`$this->container = $container`) mais `$this->container` n'est jamais lu dans aucune méthode de la classe.
  > - La classe `App\Helper\Html2Pdf` n'est **jamais importée, jamais autowirée, jamais instanciée** dans le projet. La seule référence `Html2Pdf` dans `MembershipController.php` est un `use Spipu\Html2Pdf\Tag\Html\U` (tag twig de la lib tierce, sans rapport).
  > - L'`import` `Container` et la propriété `$container` sont donc inutiles par voie de conséquence.
  >
  > **Classification** : classe entière dead code, sûre à supprimer.
  > → **SYN.2** (TODO, catégorie dead code, effort XS)
  >
  > ---
  >
  > ### 3. `UserAdminType` + `UserWithBeneficiaryType` — constructeurs délégants (✅ sûrs)
  >
  > Les deux étendent `UserType` et leur constructeur ne fait que `parent::__construct($tokenStorage)` sans aucune logique propre. Rector (`RemoveParentDelegatingConstructorRector`) peut les supprimer — PHP héritera automatiquement du constructeur parent.
  >
  > **Classification** : dead code cosmétique, sûr à supprimer. Aucun appelant à vérifier.
  >
  > ---
  >
  > ### 4. `AuthenticationSuccessHandler` — `return;` terminal (✅ sûr)
  >
  > `onAuthenticationSuccess()` se termine par `return;` après un `return new RedirectResponse(...)` dans un `if`. Le `return;` final est redondant (fin de fonction, comportement identique). Supprimable sans impact.
  >
  > **Note** : la méthode `onAuthenticationSuccess()` implémente `AuthenticationSuccessHandlerInterface` qui exige un retour `Response`. Quand `$target` est absent, la méthode retourne implicitement `null` — ce qui violerait l'interface. Bug potentiel secondaire (cf. EXTRA).
  >
  > **Classification** : cosmétique sûr pour le `return;`. Le retour null reste un sujet distinct.
  > → **EXTRA** : ajouter un finding sur le retour null implicite (violation d'interface).
  >
  > ---
  >
  > ### 5. `CommissionEventListener` — `setOwn(null)` et `$container` inutilisé (✅ sûr)
  >
  > - `$beneficiary->setOwn(null)` au `onLeave()` (ligne 38) : `setOwn(Commission $own = null)` a `null` comme valeur par défaut. Rector (`RemoveNullArgOnNullDefaultParamRector`) simplifie en `$beneficiary->setOwn()`. Cosmétique, sûr.
  > - Bonus : `Container $container` est reçu dans le constructeur, assigné à `$this->container`, mais jamais lu dans aucune méthode de la classe. Dead property.
  >
  > **Classification** : cosmétique sûr. Le `Container $container` est un candidat supplémentaire pour DC.3 (dead properties via dead-code-detector).
  >
  > ---
  >
  > ### Résumé DC.2
  >
  > | Item | Classification | Action | Effort |
  > |------|---------------|--------|--------|
  > | `ShiftBookedEvent.$fromAdmin` | 🟠 Bug silencieux | Ajouter assignment ou supprimer param+méthode | XS |
  > | `App\Helper\Html2Pdf` | 🟠 Classe entière dead | Supprimer la classe | XS |
  > | `UserAdminType` constructeur | ✅ Dead cosmétique | Rector safe | XS |
  > | `UserWithBeneficiaryType` constructeur | ✅ Dead cosmétique | Rector safe | XS |
  > | `AuthenticationSuccessHandler` return | ✅ Dead cosmétique | Rector safe | XS |
  > | `CommissionEventListener` setOwn(null) | ✅ Dead cosmétique | Rector safe | XS |
  > | `CommissionEventListener.$container` | 🟡 Dead property | Supprimer + retirer dépendance Container | XS |

- [x] **DC.3** — Méthodes publiques mortes (si P0.3 = upgrade validé)
  > **Outil** : `shipmonk/dead-code-detector` 1.2.0, `phpstan-dead-code.neon` (providers Symfony + Doctrine + Twig activés, `paths: src/`, `ignoreErrors` sur Controller + EventListener + Form + Security).
  >
  > **Limitation critique — annotations PHP 7 non supportées :**
  > Le `SymfonyUsageProvider` ne détecte que les attributs PHP 8 natifs (`#[Route]`, `#[AsEventListener]`), pas les annotations docblock (`@Route`, tags `kernel.event_listener` en YAML). Conséquence : toutes les actions de controller et méthodes d'event listener sont "mortes" dans le graphe interne, créant une cascade de faux positifs sur leurs callees. Le `TwigUsageProvider` ne détecte pas les appels de services via variable Twig (`shift_service.method()`), uniquement les fonctions/filtres d'extensions Twig. **DC.3 devra être refait post-migration PHP 8 avec attributs natifs.**
  >
  > **Upgrade PHP 8.1 du container :** effectué. 350 tests passent. Seuls nouveaux warnings : `strlen(null)` dans `FOSUserBundle\PasswordUpdater` (dépréciations PHP 8.1, non bloquantes — seront fatales en PHP 9, confirme DEP.2). Modification `netcat` → `netcat-openbsd` dans le Dockerfile (Debian Bookworm).
  >
  > ---
  >
  > ### Findings haute confiance (grep confirmé — non appelés depuis PHP ni Twig)
  >
  > | Méthode | Gravité | Notes |
  > |---------|---------|-------|
  > | `Helper\Html2Pdf::create` | 🟠 Mort | Déjà confirmé DC.2. Classe entière inutilisée. |
  > | `Helper\Html2Pdf::generatePdf` | 🟠 Mort | Idem. |
  > | `Helper\SwipeCard::generateCode` | 🟡 Mort | Déjà confirmé DC.1. Variable locale inutile. |
  > | `BeneficiaryRepository::findFromAutoComplete` | 🟡 Probablement mort | Grep vide dans src/ + templates/. Les controllers utilisent `getDoctrine()->getRepository()` directement. |
  > | `CommissionRepository::findByString` | 🟡 Probablement mort | Aucun appelant trouvé. |
  > | `EventRepository::findAllDisplayedHome` | 🟡 Probablement mort | Aucun appelant trouvé. |
  > | `PeriodPositionRepository::findByBeneficiary` | 🟡 Probablement mort | Aucun appelant trouvé. |
  > | `ShiftRepository::findFirst` | 🟡 Probablement mort | Aucun appelant trouvé. |
  > | `ShiftRepository::findReservedBefore` | 🟡 Probablement mort | Aucun appelant trouvé. |
  > | `BeneficiaryService::getAutocompleteBeneficiaries` | 🟡 Probablement mort | Aucun appelant trouvé dans src/ ni templates/. |
  > | `MembershipService::getAutocompleteMemberships` | 🟡 Probablement mort | Aucun appelant trouvé. |
  > | `OpeningHourService::isClosed` | 🟡 Probablement mort | Templates utilisent `isOpen`, pas `isClosed`. Aucun appelant PHP. |
  > | `FixtureGroupConsoleService::setInput` | 🟡 Probablement mort | Aucun appelant trouvé. |
  >
  > ### Faux positifs identifiés (cascade depuis les controllers)
  >
  > Les méthodes suivantes sont flagguées mais **réellement utilisées** — elles sont appelées depuis `isShiftBookable()` (lui-même appelé depuis ShiftController + ShiftVoter, mais "mort" dans le graphe interne à cause des false positives des controllers) :
  > - `ShiftService::canBookShift`, `::isShiftEmpty`, `::canBookDuration`, `::canBookExtraShift`
  >
  > Les méthodes suivantes sont appelées **depuis des templates Twig via variable de service** (`shift_service.method()`) — non détectées par le TwigUsageProvider :
  > - `ShiftService::getBeneficiaryShiftCount`, `::getBeneficiaryShiftFreedCount`, `::remainingToBook`, `::shiftTimeByCycle`, `::getMinimalShiftDuration`
  > - `MembershipService::getShiftFreeLogs`, `::getPeriodPositionFreeLogs`
  > - `OpeningHourKindService::hasEnabled`, `PeriodService::getDaysOfWeekArray`, `BeneficiaryService::hasWarningStatus`
  >
  > Les méthodes d'entités (`PeriodPositionFreeLog::*`, `ShiftFreeLog::*`, `ShiftAlert::*`) sont utilisées via notation Twig pointée (`entity.property`) — même limitation TwigUsageProvider.
  >
  > Les méthodes `ShiftService::canBookExtraShiftBucket`, `::getBeneficiariesWhoCanBook`, `::getBeneficiariesWhoCanBookForCycle`, `::getFirstBookable`, `::getShiftsForBeneficiary`, `::removeEmptyShift` nécessitent une vérification manuelle approfondie. Probables faux positifs liés à la cascade, mais non confirmés.
  >
  > → Les findings haute confiance alimenteront **DC.4** (consolidation TODO).

- [x] **DC.4** — Consolider en TODO
  > Tous les findings DC.1 + DC.2 + DC.3 → section TODO finale, avec flag "sûr à supprimer" vs "à vérifier manuellement".
  >
  > **Méthode** : re-run `rector-dead-code.php` (config temporaire `SetList::DEAD_CODE` explicite) sur PHP 8.1 → **17 fichiers / 12 fichiers**. Comparaison avec DC.1 (run initial PHP 7.4), DC.2 (vérifications manuelles), DC.3 (dead-code-detector). Note : le run DC.1 reportait 23 fichiers avec un rector.php différent — le run actuel (PHP 8.1, `SetList::DEAD_CODE` explicite) est la référence pour cette consolidation.
  >
  > ---
  >
  > ### A — Sûr à supprimer : Rector automatise (run `rector-dead-code.php`)
  >
  > Ces changements sont **non-ambigus, cosmétiques ou mécaniquement vérifiés**. Un seul `vendor/bin/rector process src --config rector-dead-code.php` les applique tous, sauf les items B et C ci-dessous.
  >
  > **A.1 — Docblocks @param / @return redondants** (`RemoveUselessParamTagRector` / `RemoveUselessReturnTagRector`)
  >
  > | Fichier | Tags supprimés | Effort |
  > |---------|---------------|--------|
  > | `Repository/ShiftFreeLogRepository.php` | `@param` ×2 | XS |
  > | `Repository/ShiftRepository.php` | `@param` ×3 | XS |
  > | `Security/KeycloakAuthenticator.php` | `@param` + `@return` ×4 | XS |
  > | `Service/FixtureGroupConsoleService.php` | `@return` | XS |
  > | `Service/MailerService.php` | `@param` + `@return` ×3 | XS |
  > | `Service/MembershipService.php` | `@param` ×6, `@return` ×1 | XS |
  > | `Service/Picture/BasePathPicture.php` | `@param` ×2 | XS |
  > | `Service/ShiftService.php` | `@param` ×6 | XS |
  > | `Service/TimeLogService.php` | `@param` ×12 + trailing spaces | XS |
  >
  > **A.2 — Variables intermédiaires inutiles** (`SimplifyUselessVariableRector`)
  >
  > | Fichier | Méthode | Detail |
  > |---------|---------|--------|
  > | `Service/BeneficiaryService.php` | `getAutocompleteLabel()` | `$label .=` simplifié en return inline |
  > | `Service/TimeLogService.php` | `initCurrentCycleBeginningTimeLog()` | `$log = …; return $log;` → `return …;` |
  > | `Twig/Extension/AppExtension.php` | `markdown()` | `$html = …; return $html;` → `return …;` |
  > | `Security/SwipeCard::generateCode()` (DC.1) | — | variable locale inutile |
  >
  > **A.3 — Appels null redondants** (`RemoveNullArgOnNullDefaultParamRector`)
  >
  > | Fichier | Méthode | Detail |
  > |---------|---------|--------|
  > | `Repository/ShiftRepository.php` | `findInProgressAndUpcomingShiftsForMembership()` | `findShiftsForBeneficiaries($m->getBeneficiaries(), $now, null)` → sans `null` |
  > | `Security/KeycloakAuthenticator.php` | `updateCoMembership()` | `$oldMembership->setMainBeneficiary(null)` → `setMainBeneficiary()` |
  > | `EventListener/CommissionEventListener.php` (DC.1/DC.2) | `onLeave()` | `$beneficiary->setOwn(null)` → `setOwn()` |
  >
  > **A.4 — Variable locale inutilisée** (`RemoveUnusedVariableAssignRector`)
  >
  > | Fichier | Méthode | Detail |
  > |---------|---------|--------|
  > | `Security/UserVoter.php` | `canView()` | `$user = $token->getUser()` assigné mais jamais lu dans cette méthode |
  >
  > **A.5 — Paramètre de constructeur + propriété inutilisés** (`RemoveUnusedConstructorParamRector` + `RemoveUnusedPrivatePropertyRector`)
  >
  > | Fichier | Detail |
  > |---------|--------|
  > | `Service/PeriodService.php` | `EntityManagerInterface $em` reçu et assigné (`$this->em = $em`) mais jamais lu — ni dans les méthodes, ni ailleurs dans la classe. Rector retire le param et la property. **Sûr.** |
  > | `EventListener/CommissionEventListener.php` (DC.2) | `Container $container` — même pattern, dead property |
  > | `EventListener/CodeEventListener.php` (AP.4) | `Container $container` — injecté, assigné à `$this->container`, jamais lu dans aucune méthode. Nouveau finding identifié en AP.4. |
  >
  > **A.6 — Constructeurs délégants** (`RemoveParentDelegatingConstructorRector` — DC.1/DC.2)
  >
  > Ces constructeurs ne font que `parent::__construct($param)` sans logique propre. PHP hérite automatiquement du parent si le constructeur est absent.
  >
  > | Fichier |
  > |---------|
  > | `Form/UserAdminType.php` |
  > | `Form/UserWithBeneficiaryType.php` |
  >
  > **A.7 — Constructeurs vides d'entités** (DC.2/D.2)
  >
  > 6 entités ont un `__construct() {}` totalement vide (confirmé visuellement). Sans propriétés à initialiser, PHP n'en a pas besoin.
  >
  > | Entité |
  > |--------|
  > | `Entity/Code.php` |
  > | `Entity/DynamicContent.php` |
  > | `Entity/EmailTemplate.php` |
  > | `Entity/PeriodPosition.php` |
  > | `Entity/ProcessUpdate.php` |
  > | `Entity/Service.php` |
  >
  > **A.8 — Switch cases dupliqués** (`RemoveDuplicatedCaseInSwitchRector`) — **vérifiés manuellement, sûrs**
  >
  > | Fichier | Detail |
  > |---------|--------|
  > | `Security/SwipeCardVoter.php` | `case DISABLE:` et `case ENABLE:` ont des corps **identiques** (tous deux retournent `$this->own($swipeCard, $user)`). Rector merge en fallthrough. Sûr. |
  > | `Security/SwipeCardVoter.php` | `canPair(SwipeCard $swipeCard, User $user)` — `$swipeCard` n'est jamais utilisé dans le corps de la méthode (seul `$user` est exploité). Rector retire le param. Sûr. |
  > | `Security/MembershipVoter.php` | `case self::FLYING:` est seul et retourne `$this->canEdit()` — identique au groupe `FREEZE/OPEN/CLOSE/ROLE_ADD/ROLE_REMOVE/EDIT` qui précède. Rector le merge dans le groupe. Sûr. |
  >
  > **A.9 — Méthode privée morte** (`RemoveUnusedPrivateMethodRector` — DC.2/D.2)
  >
  > | Fichier | Méthode | Detail |
  > |---------|---------|--------|
  > | `Security/CodeVoter.php` | `isLocationOk()` | Commentaire explicite `// DUPLICATED from UserVoter`. La méthode n'est jamais appelée en interne — le voter délègue à `PlaceIP::isLocationOk()`. Rector la supprime. |
  > | `Security/CodeVoter.php` | `canDelete(Code $code, User $user)` | Les deux paramètres sont inutilisés dans le corps (qui retourne `false`). Rector retire les params. Corps à 1 ligne. |
  >
  > ---
  >
  > ### B — Sûr à supprimer manuellement (grep confirmé, pas de règle Rector)
  >
  > Ces items nécessitent une suppression manuelle de fichier ou de méthode, non couverts par le run Rector.
  >
  > **B.1 — Classe entière morte (DC.2)**
  >
  > | Fichier | Detail |
  > |---------|--------|
  > | `Helper/Html2Pdf.php` | Jamais importée, jamais autowirée, jamais instanciée dans `src/`. L'`use Spipu\Html2Pdf\Tag\Html\U` dans `MembershipController` n'a aucun lien. La propriété `$container` injected dans le constructeur est inutilisée (dead property bonus). **Supprimer le fichier entier.** |
  >
  > **B.2 — Méthodes privées mortes (DC.2/D.2/EXTRA)**
  >
  > | Fichier | Méthode | Detail |
  > |---------|---------|--------|
  > | `Controller/AmbassadorController.php` | `createNoteDeleteForm()` | Définie mais aucun appel `$this->createNoteDeleteForm` dans le fichier. Confirmé par grep. |
  > | `Controller/BeneficiaryController.php` | `getErrorMessages()` | Méthode private. Seul appel : `$this->getErrorMessages($child)` dans sa propre récursion. Aucun appelant externe. Rector ne la détecte pas (récursion self-référente crée un faux positif de vivacité). |
  >
  > ---
  >
  > ### C — À vérifier manuellement (risque sémantique ou faux positifs multi-instance)
  >
  > **C.1 — ⚠️ Risque d'escalade d'autorisation — ShiftVoter** (`RemoveDuplicatedCaseInSwitchRector`)
  >
  > Rector veut merger `case self::VALIDATE:` dans `case self::LOCK:`. **Ce n'est PAS un vrai doublon** :
  > - VALIDATE actuel : `if (admin) return true; else return false;` (non-admin = toujours refusé)
  > - LOCK : `if (admin) return true; else return $this->canAccept($shift, $user);` (non-admin = logique d'acceptation)
  >
  > Après merge Rector, VALIDATE retournerait `canAccept()` pour les non-admins — **élargissement potentiel de l'autorisation**. À vérifier avec les mainteneurs avant d'appliquer.
  >
  > **C.2 — Repository methods "probablement mortes" (DC.3)**
  >
  > Non appelées dans `src/` ni `templates/` (grep confirmé). Peuvent être utilisées par une instance externe ou une intégration API. Nécessitent tracking runtime (RT.1-2) avant suppression définitive.
  >
  > | Méthode | Fichier |
  > |---------|---------|
  > | `findFromAutoComplete()` | `Repository/BeneficiaryRepository.php` |
  > | `findByString()` | `Repository/CommissionRepository.php` |
  > | `findAllDisplayedHome()` | `Repository/EventRepository.php` |
  > | `findByBeneficiary()` | `Repository/PeriodPositionRepository.php` |
  > | `findFirst()` | `Repository/ShiftRepository.php` |
  > | `findReservedBefore()` | `Repository/ShiftRepository.php` |
  >
  > **C.3 — Service methods "probablement mortes" (DC.3)**
  >
  > | Méthode | Fichier | Note |
  > |---------|---------|------|
  > | `getAutocompleteBeneficiaries()` | `Service/BeneficiaryService.php` | Nom corrélé à `findFromAutoComplete()` (C.2) |
  > | `getAutocompleteMemberships()` | `Service/MembershipService.php` | Idem |
  > | `isClosed()` | `Service/OpeningHourService.php` | Templates n'utilisent que `isOpen()` |
  > | `setInput()` | `Service/FixtureGroupConsoleService.php` | Aucun appelant trouvé |
  >
  > **C.4 — ShiftService methods à vérification approfondie requise (DC.3)**
  >
  > `canBookExtraShiftBucket`, `getBeneficiariesWhoCanBook`, `getBeneficiariesWhoCanBookForCycle`, `getFirstBookable`, `getShiftsForBeneficiary`, `removeEmptyShift` — probables faux positifs liés à la cascade controllers/Twig du DC.3, mais non confirmés par grep simple. Vérification manuelle cas par cas recommandée avant toute suppression.
  >
  > ---
  >
  > ### D — Bugs déguisés en dead code (ne pas supprimer — corriger)
  >
  > **D.1 — Assignment manquant (DC.2)**
  >
  > `Event/ShiftBookedEvent.php` : le constructeur reçoit `bool $fromAdmin` mais ne l'assigne jamais (`$this->fromAdmin = $fromAdmin;` est absent). `isFromAdmin()` retourne toujours `null`. Les 3 appelants dans `ShiftController` passent `true`/`false` intentionnellement. **Fix** : ajouter l'assignment — OU supprimer param + getter si la distinction admin/user n'est jamais exploitée par les listeners.
  >
  > **D.2 — Violation d'interface (EXTRA/DC.2)**
  >
  > `Security/AuthenticationSuccessHandler::onAuthenticationSuccess()` : quand `$target` est absent, la méthode retourne `null` implicitement. `AuthenticationSuccessHandlerInterface` exige un `Response`. **Fix** : ajouter un fallback (`new RedirectResponse('/')` ou exception).
  >
  > ---
  >
  > ### Résumé consolidé
  >
  > | Catégorie | Items | Effort total | Risque |
  > |-----------|-------|-------------|--------|
  > | A — Rector safe | 9 groupes, ~20 fichiers | XS (1 commande) | Nul |
  > | B — Manuel safe | 3 suppressions | XS | Nul |
  > | C — À vérifier | 4 groupes, ~16 méthodes | M | Moyen (multi-instance, sécurité) |
  > | D — Bugs | 2 correctifs | XS | Bas |

---

## AP — Antipatterns (analyse uniquement)
> 🔀 **Modèle : Opus.** Rappeler à l'utilisateur : `/model opus` avant AP.1, `/model sonnet` après AP.9.

- [x] **AP.1** — Controllers fat
  > `find src/Controller -name "*.php" | xargs wc -l | sort -rn | head -20`. Lire les 5 plus longs. Controllers > 150 lignes ou avec logique métier directe → TODO.
  >
  > **Vue d'ensemble** : 35 controllers, 10 949 lignes total. 19 controllers dépassent 150 lignes — seuil habituel pour qualifier de "fat". Les 5 plus longs : `MembershipController` (1 242), `ShiftController` (828), `BookingController` (718), `AdminPeriodController` (545), `AdminEventController` (494).
  >
  > ---
  >
  > ### 1. Service locator généralisé — 3 patterns, 90+ occurrences, 24 controllers (🔴)
  >
  > Ces appels contournent l'injection de dépendances et rendent les controllers non testables sans conteneur Symfony complet.
  >
  > | Pattern | Occurrences | Alternative correcte |
  > |---------|-------------|----------------------|
  > | `$this->get('security.token_storage')` | **53** dans 24 controllers | `$this->getUser()` (disponible depuis SF2.6 via `AbstractController`) |
  > | `$this->get('security.authorization_checker')` | **26** dans 14+ controllers | `$this->isGranted()` / `$this->denyAccessUnlessGranted()` |
  > | `$this->get('twig')` | **11** dans plusieurs controllers | `$this->renderView()` (intégré à `AbstractController`) |
  >
  > Distribution du pattern token_storage par controller : `ShiftController` (×8), `AdminPeriodController` (×6), `MembershipController` (×5), `EventController` (×4), puis 20 autres controllers.
  >
  > → **TODO SYN.2** — effort M (35 fichiers, mécanique, vérification manuelle requise pour les cas où `getUser()` ne suffit pas)
  >
  > ---
  >
  > ### 2. Logique métier directe dans les controllers (🟠)
  >
  > **a) `MembershipController::newAction` — auto-increment du numéro adhérent**
  > Lignes 747-751 : `findOneBy([], ['member_number' => 'DESC'])` + `getMemberNumber() + 1` directement dans le controller. Ce calcul est non atomique (race condition possible en concurrent), non testé, et devrait être dans `MembershipService`. (60 appels ORM directs au total dans `MembershipController`.)
  >
  > **b) `MembershipController::joinAction` — fusion de deux adhésions**
  > Lignes 940-969 : boucle sur les bénéficiaires, `removeBeneficiary`/`addBeneficiary`/`setMembership`, suppression de l'adhésion source. Logique de fusion entière dans le controller — aucune transaction, pas de service dédié, non testable.
  >
  > **c) `MembershipController::exportEmails` — CSV inline**
  > Lignes 1034-1055 : filtrage et formatage CSV des emails directement dans le controller (boucle, filtre `isTemporaryEmail`, `filter_var`, concaténation). Devrait être dans un service d'export ou une query Doctrine dédiée.
  >
  > **d) `ShiftController` — logique `firstShiftDate` dupliquée**
  > Blocs identiques aux lignes 170-175 (bookShiftAction) et 227-232 (bookShiftAdminAction) :
  > ```php
  > if ($member->getFirstShiftDate() == null) {
  >     $firstDate = new \DateTime('now');
  >     $firstDate->setTime(0, 0, 0);
  >     $member->setFirstShiftDate($firstDate);
  >     $em->persist($member);
  > }
  > ```
  > Ce comportement appartient à `ShiftService::bookShift()`.
  >
  > **e) `ShiftController::contactFormAction` — email construit et envoyé directement**
  > Lignes 614-666 : `MailerInterface` injecté, objet `Email` construit inline, `->bcc(...)`, `->html(renderView(...))`, `mailer->send()`. Bypass total de `MailerService`. Devrait passer par une méthode dédiée dans `MailerService`.
  >
  > **f) `AdminPeriodController::generateShiftsForDateAction` — console command appelée depuis une action web**
  > Lignes 429-459 : `new Application($kernel)` + `ArrayInput` + `$application->run()` pour exécuter `app:shift:generate`. Couplage fort couche web → couche CLI. La logique de génération devrait être dans un service appelé à la fois depuis la commande et depuis le controller.
  >
  > → **TODO SYN.2** — items a, b, c, e : effort S-M chacun ; d : XS ; f : M
  >
  > ---
  >
  > ### 3. Duplication des `createShift*Form` entre 3 controllers (🟠)
  >
  > Documentée dans D.5 (TODO comments), confirmée ici. 5 méthodes copiées-collées :
  >
  > | Méthode | BookingController | ShiftController | MembershipController |
  > |---------|:-----------------:|:---------------:|:--------------------:|
  > | `createShiftBookAdminForm` | ✓ | ✓ | |
  > | `createShiftDeleteForm` | ✓ | ✓ | |
  > | `createShiftFreeForm` | ✓ | ✓ | |
  > | `createShiftFreeAdminForm` | ✓ | ✓ | ✓ |
  > | `createShiftValidateInvalidateAdminForm` | ✓ | ✓ | ✓ |
  >
  > **Solution** : un `ShiftFormFactory` service (ou un Symfony Form type avec options) centralise ces 5 méthodes. Les 3 controllers l'injectent et appellent les méthodes nommées.
  >
  > → **TODO SYN.2** — effort S (1 service à créer, 3 controllers à simplifier)
  >
  > ---
  >
  > ### 4. Duplication de `getErrorMessages` et `redirectToShow` (🟠)
  >
  > | Méthode | Controllers concernés |
  > |---------|----------------------|
  > | `private function getErrorMessages(Form $form)` | BeneficiaryController, CodeController, ServiceController, TaskController, UserController — **5 copies identiques** |
  > | `private function redirectToShow(Membership\|User $member)` | BeneficiaryController, MembershipController, NoteController, TimeLogController, UserController — **5 copies** (légèrement variantes) |
  >
  > `getErrorMessages` : corps strictement identique dans les 5 controllers. Candidat évident pour un trait ou une méthode `protected` dans un `AbstractAppController` (extension d'AbstractController).
  >
  > `redirectToShow` : variations mineures (type de l'entité, gestion du token temporaire). Un trait avec 2 surcharges (Membership, User) suffit.
  >
  > → **TODO SYN.2** — effort S
  >
  > ---
  >
  > ### 5. `MembershipController::showAction` — 18 formulaires construits dans une seule action (🟡)
  >
  > La méthode `showAction` (lignes 83-214 = 131 lignes) construit : `flyingForm`, `freezeForm`, `unfreezeForm`, `freezeChangeForm`, `withdrawnForm`, `deleteForm`, `noteNewForm`, `noteEditForms[n]`, `noteDeleteForms[n]`, `new_notes_form[n]`, `registrationForm`, `detachBeneficiaryForms[b]`, `deleteBeneficiaryForms[b]`, `beneficiaryForm`, `timeLogNewForm`, `timeLogDeleteForms[t]`, `shiftFreeForms[s]`, `shiftValidateInvalidateForms[s]`. Ce volume de préparation de vue dans le controller est le symptôme le plus visible du problème structurel : pas de séparation "préparation de formulaire" / "logique d'action".
  >
  > → **TODO SYN.2** — effort L (refactoring structurant, dépend d'abord de ShiftFormFactory)
  >
  > ---
  >
  > ### 6. 36 méthodes `private createXxxForm()` au total (🟡)
  >
  > 36 méthodes de ce type réparties dans les controllers. Elles ne sont pas testables isolément, non réutilisables entre controllers, et gonflent mécaniquement la taille de chaque classe. L'émergence naturelle de ce problème est le pattern de duplication documenté en point 3. La solution systémique est de les sortir dans des Form types ou des services dédiés.
  >
  > → **TODO SYN.2** — effort L (transversal, à traiter par domaine)
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🔴 | Service locator (token_storage, auth_checker, twig) — 90+ occurrences, 24 controllers | M |
  > | 🟠 | Logique métier directe (member number, join, CSV, firstShiftDate, email, console command) | S–M chacun |
  > | 🟠 | `createShift*Form` × 5 méthodes × 3 controllers | S |
  > | 🟠 | `getErrorMessages` + `redirectToShow` × 5 controllers | S |
  > | 🟡 | `showAction` de MembershipController — 18 formulaires dans une action | L |
  > | 🟡 | 36 méthodes `createXxxForm` en tout | L |

- [x] **AP.2** — Instanciations directes dans les controllers
  > `grep -rn "new [A-Z]" src/Controller/` (hors Response, RedirectResponse, JsonResponse). Services/entités instanciés au lieu d'être injectés → TODO.
  >
  > **Méthode** : grep complet sur `src/Controller/`, puis filtrage des patterns légitimes (HTTP responses, domain events, value objects `DateTime`/`Address`/`ArrayCollection`, entités dans les actions CRUD de création où le form binding est standard, contraintes Symfony).
  >
  > **Patterns légitimes (non listés comme antipatterns)** :
  > - `Response`, `RedirectResponse`, `JsonResponse`, `StreamedResponse` — exclus par définition de l'item
  > - Domain events (`*Event`) — créés inline avant dispatch, pattern standard Symfony
  > - `DateTime`, `Address`, `ArrayCollection`, `Email` — value objects
  > - `new EntityName()` dans les actions `newAction`/`createAction` qui initialisent l'objet pour le formulaire — pattern form binding standard
  > - `BeneficiaryCanHost`, `FormEvent` — usage constraint/form standard Symfony
  >
  > ---
  >
  > ### 1. `new Application($kernel)` — console runner dans la couche web (🟠)
  >
  > | Fichier | Ligne | Commande lancée |
  > |---------|-------|----------------|
  > | `AdminController.php` | 284 | `app:import:users` (import CSV d'adhérents) |
  > | `AdminPeriodController.php` | 444 | `app:shift:generate` (génération de créneaux) |
  >
  > Les deux actions instancient `new Application($kernel)` + `new ArrayInput([...])` + `new BufferedOutput()` pour exécuter une commande console depuis un controller web. Ce pattern couple fortement la couche HTTP à la couche CLI et empêche de tester la logique métier indépendamment du transport.
  >
  > `AdminPeriodController::generateShiftsForDateAction` est déjà référencé en AP.1 (finding 2f). `AdminController::importAction` est un cas identique.
  >
  > **Pattern correct** : extraire la logique dans un service (`ShiftGeneratorService`, `UserImportService`) appelé à la fois par le controller et par la commande. Aucun `Application` dans le controller.
  >
  > → **TODO SYN.2** — effort M par cas (2 cas)
  >
  > ---
  >
  > ### 2. `new UsernamePasswordToken(...)` — fabrication manuelle de token d'authentification (🟠)
  >
  > | Fichier | Ligne | Contexte |
  > |---------|-------|---------|
  > | `SwipeCardController.php` | 68 | Login passwordless par badge — `$token = new UsernamePasswordToken($user, $user->getPassword(), "main", $user->getRoles())` |
  > | `CodeController.php` | 252 | Impersonation temporaire pour confirmation de changement de code — `new UsernamePasswordToken($current_app_user, null, "main", $current_app_user->getRoles())` |
  >
  > Les deux cas injectent un token directement dans `security.token_storage` en contournant le flux d'authentification Symfony normal (pas de `LoginManager`, pas de session guard, pas d'événement `security.interactive_login` correctement typé).
  >
  > **Problèmes spécifiques** :
  > - `SwipeCardController` : utilise `$user->getPassword()` comme credentials du token — en SF4 ce paramètre est le "credentials" (non-null), pas le mot de passe haché, ce qui est ambigu et source de confusion.
  > - `SwipeCardController` dispatch bien `security.interactive_login` via `InteractiveLoginEvent` — mais le type de l'event est une string (ancienne API), pas la constante.
  > - `CodeController` : l'impersonation n'est jamais révoquée explicitement dans le code lisible — le token du contexte précédent est sauvegardé dans `$previousToken` mais aucune restauration trouvée dans le scope visible.
  >
  > **Pattern correct en SF5+** : `LoginManager` ou `UserAuthenticatorInterface` (natifs). En SF4 : la voie recommandée est `security.token_storage` + event, mais via le `TokenStorageInterface` injecté, pas via `$this->get(...)`.
  >
  > → **SEC** section à croiser (SEC.1) + **TODO SYN.2** — effort M (sécurité, risque de régression auth)
  >
  > ---
  >
  > ### 3. `new QrCode($url)` — bibliothèque QR dans le controller (🟡)
  >
  > `SwipeCardController::_getQr($url)` (ligne 280) instancie `Endroid\QrCode\QrCode` directement, configure 6 paramètres et retourne un data URI base64. Cette logique (choix de taille, correction d'erreur, couleurs, encoding) n'est pas configurable et est non testable isolément.
  >
  > **Pattern correct** : un `QrCodeService` injecté avec les paramètres dans la config. La méthode de controller appelle `$this->qrCodeService->generateDataUri($url)`.
  >
  > → **TODO SYN.2** — effort S
  >
  > ---
  >
  > ### 4. `new Markdown` — parser Markdown dans le controller (🟡)
  >
  > `MailController.php:148` instancie `Michelf\Markdown` directement, configure `$parser->hard_wrap = true`, et transforme le contenu. Cette configuration est non-centralisée — si un autre endroit utilise Markdown, les options divergeront.
  >
  > Note : `Michelf\Markdown` n'est pas autowirable par défaut (pas de tag Symfony). Un alias de service dans `services.yaml` avec `arguments: { hard_wrap: true }` résoudrait l'injection.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 5. `new GravatarHelper(new GravatarApi())` — service externe sans injection (🟡)
  >
  > `ApiController.php:111` : instanciation chaînée d'un service tiers déjà partiellement abandonné (DEP.2 — `ornicar/gravatar-bundle`). Les imports `GravatarApi`/`GravatarHelper` dans `AdminController` et `RegistrationsController` sont déjà identifiés comme morts (DEP.2). Ici, c'est l'unique usage réel en PHP.
  >
  > Si le bundle est remplacé par une extension Twig custom (effort S selon DEP.2), ce contrôleur bénéficiera de la même refonte.
  >
  > → **TODO SYN.2** — résolu en même temps que le remplacement `ornicar/gravatar-bundle` (DEP.2)
  >
  > ---
  >
  > ### 6. `new Paginator($qb)` — infrastructure répétée dans 6 controllers (🟡)
  >
  > | Controller | Occurrences |
  > |-----------|-------------|
  > | `AmbassadorController.php` | 4 |
  > | `AdminController.php` | 1 |
  > | `AdminEventController.php` | 1 |
  > | `AdminMembershipShiftExemptionController.php` | 1 |
  > | `AdminPeriodPositionFreeLogController.php` | 1 |
  > | `AdminShiftFreeLogController.php` | 1 |
  >
  > 9 occurrences de `new Paginator($qb)` en tout. La configuration est identique à chaque fois (pas de paramètre `fetchJoinCollection` explicite). Un trait ou une méthode helper `paginate(QueryBuilder $qb): Paginator` dans un `AbstractAppController` éliminerait le couplage à la classe Doctrine.
  >
  > → **TODO SYN.2** — effort XS (1 trait, 9 appels à remplacer)
  >
  > ---
  >
  > ### 7. `new ShiftBucket()` dupliqué entre controllers (🟡)
  >
  > `ShiftController.php:692` et `WidgetController.php:45` créent tous deux des `ShiftBucket` dans des boucles identiques. Le DTO est instancié inline dans chaque controller au lieu de passer par un service de construction du planning. Ce point est lié à AP.1 (logique métier dans les controllers).
  >
  > → **TODO SYN.2** — couvert par la refonte générale des fat controllers (AP.1)
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Finding | Controllers | Effort |
  > |---------|---------|-------------|--------|
  > | 🟠 | `new Application($kernel)` — console runner en HTTP | 2 | M par cas |
  > | 🟠 | `new UsernamePasswordToken(...)` — fabrication token auth | 2 | M (sécurité) |
  > | 🟡 | `new Paginator($qb)` — infrastructure répétée | 6 | XS |
  > | 🟡 | `new QrCode($url)` — lib QR non injectée | 1 | S |
  > | 🟡 | `new Markdown` — parser non injecté | 1 | XS |
  > | 🟡 | `new GravatarHelper(new GravatarApi())` — résolu via DEP.2 | 1 | XS |

- [x] **AP.3** — Requêtes hors Repository
  > `grep -rn "createQuery\|createNativeQuery\|getConnection\|createQueryBuilder" src/` hors `src/Repository/`. SQL/DQL dans controllers ou services → TODO.
  >
  > **Périmètre analysé** : 55 occurrences dans 28 fichiers hors `src/Repository/`. Après filtrage des patterns légitimes (voir ci-dessous), 4 catégories d'antipatterns identifiées.
  >
  > ---
  >
  > ### Patterns légitimes (non listés)
  >
  > | Catégorie | Fichiers | Motif |
  > |-----------|---------|-------|
  > | `src/Form/*.php` — callbacks `query_builder` | 5 fichiers, 10 occurrences | Pattern Symfony standard pour les champs `EntityType` : la closure reçoit le repository en paramètre et retourne un `QueryBuilder`. Pas un antipattern. |
  > | `src/Migrations/*.php` | 2 fichiers | Les migrations ont un accès direct à la connexion par conception. Attendu et correct. |
  > | `src/DataFixtures/Purger/CustomPurger.php:30` | 1 fichier | Purger de fixtures dev/test. Accès connexion acceptable. |
  >
  > ---
  >
  > ### 1. SQL brut avec concaténation de table dans un controller (🔴)
  >
  > `RegistrationsController.php:118-159` — deux requêtes SQL brutes (`$connection->prepare()`) avec le nom de table concaténé dans la chaîne SQL :
  > ```php
  > $table_name = $em->getClassMetadata('App:AbstractRegistration')->getTableName();
  > $connection->prepare("SELECT ... FROM ".$table_name." WHERE date >= :from ...");
  > ```
  > Les paramètres utilisateurs (`:from`, `:to`) sont bien paramétrés — pas d'injection SQL via les inputs. Cependant :
  > - Le nom de table est concaténé (non paramétrable, même si issu de Doctrine metadata)
  > - Ce sont des requêtes d'agrégation pivot complexes (`SUM(IF(mode='1',...))` × 6 modes par date) qui appartiennent au Repository
  > - La logique est dupliquée entre les deux requêtes (même filtre `date >= :from`, même structure pivot)
  >
  > **Migration cible** : `AbstractRegistrationRepository::getSumsByDateRange(DateTimeInterface $from, ?DateTimeInterface $to)` et `getGrandTotalByDateRange()`. Supprime le raw SQL du controller et découple de la structure MariaDB.
  >
  > → **TODO SYN.2** — effort M (logique SQL complexe, 2 méthodes Repository, tests unitaires recommandés)
  >
  > ---
  >
  > ### 2. `$em->createQueryBuilder()` sans passer par le Repository (🟠)
  >
  > Ces occurrences construisent des requêtes depuis l'EntityManager directement, contournant entièrement la couche Repository :
  >
  > | Fichier | Ligne | Entité | Type de requête |
  > |---------|-------|--------|----------------|
  > | `RegistrationsController.php` | 91 | `AbstractRegistration` | COUNT avec filtre date |
  > | `EventController.php` | 299 | `Beneficiary` | Recherche fulltext firstname + joins membership/registration |
  > | `HelloassoController.php` | 39 | `HelloassoPayment` | COUNT simple (pagination) |
  > | `BeneficiaryInitializationSubscriber.php` | 70 | `User` | Recherche par préfixe username pour unicité |
  >
  > Le cas `BeneficiaryInitializationSubscriber` est particulièrement problématique : un event listener qui construit directement une requête DQL bypasse les deux couches (controller ET repository). La requête devrait être dans `UserRepository::findByUsernamePrefix(string $prefix): array`.
  >
  > → **TODO SYN.2** — effort S (4 méthodes Repository à créer, chaque cas est simple)
  >
  > ---
  >
  > ### 3. Logique de filtrage complexe construite dans les controllers (🟠)
  >
  > Ces controllers appellent `$em->getRepository(X)->createQueryBuilder()` puis enchaînent des clauses WHERE dynamiques directement dans l'action. La construction de requête est une responsabilité du Repository, pas du controller.
  >
  > | Controller | Entité | Complexité |
  > |-----------|--------|-----------|
  > | `AdminShiftFreeLogController.php:107` | `ShiftFreeLog` | Tri + 2 filtres conditionnels (date création, date shift via LEFT JOIN) |
  > | `AdminMembershipShiftExemptionController.php:87` | `MembershipShiftExemption` | Filtres multi-colonnes |
  > | `AdminEventController.php:113` | `Event` | Filtres + tri dynamiques |
  > | `AdminEventController.php:385` | `Beneficiary` | Joins membership/registration, filtres, tri |
  > | `AdminPeriodPositionFreeLogController.php:86` | `PeriodPositionFreeLog` | Filtres conditionnels |
  >
  > Ces 5 controllers correspondent tous à des écrans d'administration avec filtres dynamiques. Le pattern récurrent est : construire le `QueryBuilder` dans le controller, puis l'alimenter dans un `Paginator` (antipattern AP.2 finding 6). Une méthode Repository `findFiltered(array $filters): QueryBuilder` par entité résoudrait les deux problèmes.
  >
  > Note : `AdminEventController.php:55` est dans un callback `query_builder` d'un formulaire inline — pattern légitime, non listé dans les antipatterns.
  >
  > → **TODO SYN.2** — effort S (5 méthodes Repository, migration mécanique)
  >
  > ---
  >
  > ### 4. DQL brut dans une commande (🟡)
  >
  > `FixShiftMissingPositionCommand.php:108` :
  > ```php
  > $this->em->createQuery("UPDATE App:Shift s SET s.position = :position WHERE s.id in (:ids)")
  > ```
  > Seul cas de DQL littéral (non via QueryBuilder) hors Repository. Le contexte justifie partiellement l'approche — il s'agit d'un UPDATE bulk conditionnel, difficilement exprimable avec les méthodes Repository existantes. Mais la requête devrait être dans `ShiftRepository::setPositionForIds(PeriodPosition $position, array $shifts): void`.
  >
  > → **TODO SYN.2** — effort XS (1 méthode Repository)
  >
  > ---
  >
  > ### 5. Commandes construisant des QueryBuilders inline (🟡)
  >
  > 13 occurrences dans 8 commandes, toutes via `$this->em->getRepository(X)->createQueryBuilder()`. Ces requêtes pourraient être des méthodes Repository nommées :
  >
  > | Commande | Entité requêtée | Logique inline |
  > |---------|----------------|----------------|
  > | `ShiftReminderCommand` | `Shift` | Filtrage sur date + statut |
  > | `RandomSortMembersCommand` | `Beneficiary` | Filtres sur adhésion active |
  > | `SendMassMailCommand` | `Membership` | Filtres complexes multi-critères |
  > | `FixShiftMissingPositionCommand` (×2) | `PeriodPosition`, `Shift` | Filtres période + position null |
  > | `ShiftGenerateCommand` | `Period` | Filtres date + statut |
  > | `FixBeneficiariesWithoutAddressCommand` | `Beneficiary` | Filtre adresse null |
  > | `UpdateIgloohomeCodeCommand` | `Code` | Filtres type + statut |
  > | `AnonymizeDataCommand` (×4) | plusieurs | Filtres anonymisation |
  > | `VerifyCodeChangeCommand` | `Code` | Filtre vérification |
  >
  > Nuance multi-instance : certaines de ces requêtes sont spécifiques à des commandes de maintenance ou migration, avec une logique qui ne sera probablement jamais réutilisée. Le ratio coût/bénéfice d'extraire vers le Repository est faible pour ces cas.
  >
  > → **TODO SYN.2** — effort S global (prioriser les requêtes réutilisables, ignorer les commandes de migration one-shot)
  >
  > ---
  >
  > ### 6. Services avec QueryBuilder via Repository (🟡)
  >
  > | Service | Occurrences | Note |
  > |---------|-------------|------|
  > | `EventService.php:25,37` | 2 | Service "métier" qui construit ses propres requêtes via `$this->em->getRepository(Proxy::class)->createQueryBuilder()`. Ces 2 méthodes sont candidates à `ProxyRepository`. |
  > | `SearchUserFormHelper.php:386` | 1 | Helper de formulaire de recherche qui construit la query de base — c'est un service de présentation qui pilote la couche données. La méthode `initSearchQuery()` retourne un `QueryBuilder` pour que les filtres dynamiques puissent être chaînés. Pattern hybride discutable, mais moins urgent que les controllers. |
  >
  > → **TODO SYN.2** — effort XS (EventService : 2 méthodes à déplacer dans ProxyRepository)
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Finding | Fichiers | Effort |
  > |---------|---------|---------|--------|
  > | 🔴 | SQL brut + concaténation table dans controller | 1 (RegistrationsController) | M |
  > | 🟠 | `$em->createQueryBuilder()` sans Repository | 4 | S |
  > | 🟠 | Filtrage complexe inline dans controllers | 5 | S |
  > | 🟡 | DQL brut dans une commande | 1 (FixShiftMissingPositionCommand) | XS |
  > | 🟡 | QueryBuilder inline dans commandes | 8 (13 occurrences) | S |
  > | 🟡 | QueryBuilder dans services | 2 (EventService) | XS |

- [x] **AP.4** — Container injecté comme service locator
  > `grep -rn "ContainerInterface\|DependencyInjection\\\\Container" src/` hors `Kernel.php` → TODO.
  >
  > **Périmètre** : 31 fichiers non-migration retournés par le grep. Après dépouillement, 3 catégories distinctes identifiées.
  >
  > ---
  >
  > ### Catégorie A — Imports orphelins (7 fichiers — dead `use`, aucun service locator)
  >
  > Ces fichiers importent `ContainerInterface`, `Container`, ou `ContainerBuilder` dans leur `use` mais ne l'injectent pas dans le constructeur et ne l'utilisent pas dans le corps de la classe.
  >
  > | Fichier | Import mort | Remarque |
  > |---------|------------|---------|
  > | `Security/TaskVoter.php` | `ContainerInterface` | Non injecté — constructeur reçoit uniquement `AccessDecisionManagerInterface` |
  > | `Security/NoteVoter.php` | `ContainerInterface` | Idem |
  > | `Security/SwipeCardVoter.php` | `ContainerInterface` | Idem |
  > | `Twig/Extension/AppExtension.php` | `Container` | Non utilisé — `AppExtension` n'injecte aucun container |
  > | `Service/ShiftService.php` | `Container` | Non utilisé — `ShiftService` n'injecte aucun container |
  > | `Controller/AdminController.php` | `ContainerBuilder` | Classe de compilation DI (≠ service locator runtime) — import orphelin |
  > | `Controller/RegistrationsController.php` | `ContainerBuilder` | Idem |
  >
  > **Action** : supprimer les 7 `use` statements. Effort XS (mécanique).
  >
  > ---
  >
  > ### Catégorie B — Propriétés container mortes (2 fichiers — cross-référence DC.4)
  >
  > | Fichier | Situation |
  > |---------|---------|
  > | `EventListener/CodeEventListener.php` | `Container $container` reçu dans le constructeur, assigné à `$this->container`, jamais lu dans aucune méthode. **Nouveau finding** — à ajouter à DC.4 catégorie A.5. |
  > | `EventListener/CommissionEventListener.php` | Idem — déjà documenté en DC.4.A.5. |
  >
  > **Action** : supprimer le param constructeur + la propriété (même pattern que DC.4.A.5). Effort XS.
  >
  > ---
  >
  > ### Catégorie C — Vrai service locator (22 fichiers actifs — antipattern à corriger)
  >
  > Ces classes injectent `ContainerInterface` ou `Container` comme dépendance constructeur et appellent `$this->container->get()` ou `$this->container->getParameter()` à l'intérieur. Elles rendent le code non testable sans conteneur Symfony complet et masquent les vraies dépendances.
  >
  > #### C.1 — `getParameter()` uniquement → remplacer par `ParameterBagInterface` (🟠)
  >
  > Ces classes utilisent le container exclusivement pour lire des paramètres de configuration. Le remplacement est mécanique : injecter `ParameterBagInterface $params` et appeler `$params->get('name')`, ou binder les scalaires dans `services.yaml` (`bind: $projectName: '%project_name%'`).
  >
  > | Fichier | Paramètres lus | Via |
  > |---------|---------------|-----|
  > | `EventListener/OidcFirewallListener.php` | `oidc_enable` (×1) | `getParameter()` dans `onKernelRequest` |
  > | `Security/KeycloakAuthenticator.php` | `oidc_roles_claim`, `oidc_roles_map`, `oidc_formations_claim`, `oidc_formations_map`, `oidc_commissions_claim`, `oidc_commissions_map`, `oidc_user_attributes_map` (×7) | `getParameter()` dans les méthodes auth |
  > | `Service/MembershipService.php` | `registration_duration`, `registration_every_civil_year`, `cycle_type`, `use_fly_and_fixed`, `fly_and_fixed_entity_flying` (×5) | `getParameter()` dans le constructeur |
  > | `Service/PeriodService.php` | `use_fly_and_fixed`, `fly_and_fixed_entity_flying` (×2) | `getParameter()` dans le constructeur |
  > | `Service/BeneficiaryService.php` | `use_fly_and_fixed`, `fly_and_fixed_entity_flying`, `member_withdrawn_icon`, `member_frozen_icon`, `beneficiary_flying_icon`, `member_flying_icon`, `member_exempted_icon`, `member_registration_missing_icon` (×8) | `getParameter()` constructeur + méthodes |
  > | `Service/SearchUserFormHelper.php` | `use_fly_and_fixed`, `fly_and_fixed_entity_flying`, `maximum_nb_of_beneficiaries_in_membership`, `member_withdrawn_icon`, `member_frozen_icon`, `member_exempted_icon`, `member_registration_missing_icon`, `user_account_enabled_icon`, `member_flying_icon`, `beneficiary_flying_icon` (×10) | `getParameter()` constructeur + méthodes |
  >
  > → **TODO SYN.2** — effort S par fichier (injection directe scalaire ou `ParameterBagInterface`)
  >
  > #### C.2 — `get(service)` en constructeur → injection directe possible (🟠)
  >
  > Ces classes résolvent leurs dépendances **immédiatement dans le constructeur** via `$container->get()`. Il n'y a pas de lazy loading, donc pas de problème de dépendance circulaire justifiant le pattern. Le service peut être injecté directement.
  >
  > | Fichier | Service(s) résolu(s) | Alternative directe |
  > |---------|---------------------|-------------------|
  > | `Twig/Extension/MembershipExtension.php` | `'membership_service'` → `MembershipService` | Injecter `MembershipService $membershipService` directement |
  > | `Twig/Extension/BeneficiaryExtension.php` | `'beneficiary_service'` → `BeneficiaryService` | Injecter `BeneficiaryService $beneficiaryService` directement |
  > | `Security/ShiftVoter.php` | `'shift_service'` → `ShiftService` dans le constructeur | Injecter `ShiftService $shiftService` directement. `RequestStack` encore résolu via `->get('request_stack')` en runtime (voir C.3). |
  > | `Validator/Constraints/BeneficiaryCanHostValidator.php` | `'membership_service'` → `MembershipService` dans le constructeur ; `maximum_nb_of_beneficiaries_in_membership` via `getParameter()` | Injecter `MembershipService` directement + scalaire bindé |
  >
  > → **TODO SYN.2** — effort XS–S par fichier
  >
  > #### C.3 — `get(service)` à l'exécution (lazy) → EventListeners et Voters (🔴)
  >
  > Ces classes appellent `$this->container->get()` **dans les méthodes d'event handling ou de vote**, pas dans le constructeur. Ce pattern est l'anti-pattern historique de Symfony 2/3 pour contourner les dépendances circulaires entre EventListeners et les services qu'ils déclenchent. En Symfony 4.4+, l'injection directe suffit : le DI container résout les cycles via des proxies générés automatiquement.
  >
  > **`EmailingEventListener.php` — cas extrême (🔴)**
  > 6 services différents résolus par `->get()` en runtime : `'router'`, `'twig'`, `'App\Helper\SwipeCard'`, `'membership_service'`, `'templating'` (legacy fallback Symfony 3), plus `'fos_oauth_server.client_manager'` (absent du code mais bundle toujours chargé). En plus, 7 paramètres lus dans le constructeur via `->getParameter()`. Le listener compte ~700 lignes — c'est à la fois le plus gros service locator et le plus gros listener du projet.
  >
  > | Fichier | Services résolus lazily | Paramètres lus |
  > |---------|------------------------|---------------|
  > | `EmailingEventListener.php` | `router`, `twig`, `App\Helper\SwipeCard`, `membership_service`, `templating` (×legacy) | `due_duration_by_cycle`, `emails.member`, `emails.shift`, `wiki_keys_url`, `reserve_new_shift_to_prior_shifter_delay`, `locale`, `project_name`, `transactional_mailer_user` (×8) |
  > | `TimeLogEventListener.php` | `time_log_service`, `membership_service`, `event_dispatcher` | `due_duration_by_cycle`, `cycle_duration`, `registration_duration`, `max_time_at_end_of_shift`, `use_card_reader_to_validate_shifts`, `use_time_log_saving`, `time_log_saving_shift_free_min_time_in_advance_days` (×7) |
  > | `HelloassoEventListener.php` | `router`, `twig`, `App\Helper\SwipeCard`, `membership_service`, `event_dispatcher`, `templating` (×legacy) | `emails.member`, `project_name` (×2) |
  > | `MattermostEventListener.php` | `twig` | `locale` (×1) |
  > | `ShiftFreeLogEventListener.php` | `shift_free_log_service` | — |
  > | `PeriodPositionFreeLogEventListener.php` | `period_position_free_log_service` | — |
  >
  > **Voters avec get() lazy (🟠)**
  >
  > | Fichier | Services résolus lazily | Paramètres lus |
  > |---------|------------------------|---------------|
  > | `Security/CodeVoter.php` | `PlaceIP::class`, `shift_service` | `code_generation_enabled` (×2) |
  > | `Security/MembershipVoter.php` | `PlaceIP::class` | `oidc_enable` (×2) |
  > | `Security/UserVoter.php` | `PlaceIP::class` | `oidc_enable` |
  > | `Security/ShiftVoter.php` | `request_stack` (runtime, en plus du constructor) | — |
  >
  > **Helper avec get() lazy (🟡)**
  >
  > | Fichier | Services résolus lazily | Paramètres lus |
  > |---------|------------------------|---------------|
  > | `Helper/PlaceIP.php` | `request_stack` | `enable_place_local_ip_address_check`, `place_local_ip_address` (×2) |
  > | `Twig/Extension/ProcessUpdateExtension.php` | `doctrine` (→ ManagerRegistry) | — |
  >
  > **Note sur `'templating'`** : le pattern `if ($this->container->has('templating')) … else if ($this->container->has('twig'))` dans `EmailingEventListener` et `HelloassoEventListener` est un vestige de compatibilité Symfony 3→4. `templating` n'existe plus en Symfony 4+. Ce code mort peut être simplifié en `$this->twig->render(...)` directement.
  >
  > → **TODO SYN.2** — effort M–L pour les EventListeners (EmailingEventListener en particulier), S pour les Voters et helpers.
  >
  > ---
  >
  > ### Migration path recommandé
  >
  > | Pattern | Remplacement | Effort |
  > |---------|-------------|--------|
  > | `->getParameter('foo')` | `ParameterBagInterface $params` + `$params->get('foo')` | S |
  > | `->get(ServiceFoo::class)` en constructeur | Injecter `ServiceFoo $foo` directement | XS |
  > | `->get('router')` / `->get('twig')` en runtime | Injecter `RouterInterface $router` / `Environment $twig` directement | XS–S |
  > | `->get('service_id_string')` en runtime | Identifier le FQCN → injection directe | S |
  > | Bloc `has('templating')` legacy | Supprimer + injecter `Environment $twig` directement | XS |
  > | Imports orphelins | Supprimer les `use` | XS |
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Catégorie | Fichiers | Effort |
  > |---------|----------|---------|--------|
  > | 🟡 Nettoyage | A — imports orphelins | 7 | XS |
  > | 🟡 Nettoyage | B — propriétés mortes (cross DC.4) | 2 | XS |
  > | 🟠 Important | C.1 — getParameter() → ParameterBagInterface | 6 | S |
  > | 🟠 Important | C.2 — get() en constructeur → injection directe | 4 | S |
  > | 🔴 Critique | C.3 — get() lazy EventListeners | 6 | M–L |
  > | 🟠 Important | C.3 — get() lazy Voters + helpers | 5 | S |
  >
  > **Total affecté** : 22 classes avec service locator actif + 7 imports orphelins + 2 propriétés mortes = **31 fichiers**.

- [x] **AP.5** — Services avec état mutable
  > Services singleton qui ont des propriétés écrites après construction (risque entre requêtes).
  >
  > **Contexte** : tous les services `src/Service/` sont des singletons Symfony (scope par défaut). Sous PHP-FPM (runtime actuel), chaque requête instancie un conteneur neuf → aucune fuite d'état entre requêtes. Le risque ne se matérialiserait qu'avec un runtime long-running (Roadrunner, FrankenPHP, Swoole).
  >
  > **Finding 1 — `FixtureGroupConsoleService` : setter post-construction (et dead code)**
  > - Fichier : `src/Service/FixtureGroupConsoleService.php`
  > - Seul setter `$this->` en dehors d'un constructeur dans tout `src/Service/` : `setInput(InputInterface $input)` affecte `$this->input` après construction.
  > - La propriété `$input` est `null` par défaut ; `getGroups()` protège ce null, mais si `setInput()` était appelé l'état persisterait pour toute la durée de vie du service.
  > - **Critique** : `setInput()` n'est jamais appelé ailleurs dans le code. `FixtureGroupConsoleService` n'est injecté dans aucune Command, fixture ou autre classe — c'est du **dead code complet**.
  > - → **TODO** : supprimer `FixtureGroupConsoleService` (cross-référence DC).
  >
  > **Finding 2 — Visibilité `protected` sur les propriétés de dépendance**
  > - Plusieurs services déclarent leurs dépendances `protected` au lieu de `private` :
  >   | Classe | Props `protected` |
  >   |---|---|
  >   | `MembershipService` | `$em`, `$registration_duration`, `$registration_every_civil_year`, `$cycle_type`, `$use_fly_and_fixed`, `$fly_and_fixed_entity_flying` (6) |
  >   | `TimeLogService` | `$em`, `$requestStack` (2) |
  >   | `ShiftFreeLogService` | `$em`, `$requestStack` (2) |
  >   | `PeriodPositionFreeLogService` | `$em`, `$requestStack` (2) |
  >   | `OpeningHourService` | `$em` (1) |
  >   | `OpeningHourKindService` | `$em` (1) |
  > - Aucune de ces classes n'est sous-classée dans le projet.
  > - La visibilité `protected` sans sous-classe est une fuite d'encapsulation : un futur sous-classe pourrait réassigner ces dépendances sans passer par le constructeur.
  > - → **TODO** : passer toutes ces propriétés en `private`.
  >
  > **Finding 3 — EventListeners : même pattern `protected` généralisé**
  > - L'ensemble des 10+ EventListeners (`CodeEventListener`, `EmailingEventListener`, `TimeLogEventListener`, `CommissionEventListener`, `MattermostEventListener`, `ShiftFreeLogEventListener`, `PeriodPositionFreeLogEventListener`, `HelloassoEventListener`, `OidcFirewallListener`) déclare leurs dépendances `protected`.
  > - Aucun n'est sous-classé.
  > - Exception notable : `EmailingEventListener` mélange les styles — `$swipeCardHelper` est `private SwipeCard` (PHP 7.4 typé), tous les autres sont `protected` non typés. Incohérence sans risque runtime mais significative.
  > - → **TODO** : homogénéiser en `private` typé (PHP 7.4 typed properties).
  >
  > **Verdict** :
  > | Risque | Sévérité | Impact runtime actuel |
  > |---|---|---|
  > | Setter post-construction (`FixtureGroupConsoleService`) | 🟢 Nul (dead code) | Aucun — jamais appelé |
  > | `protected` sur dépendances (services) | 🟡 Faible | Aucun — pas de sous-classe |
  > | `protected` sur dépendances (listeners) | 🟡 Faible | Aucun — pas de sous-classe |
  >
  > Aucun état mutable actif inter-requêtes dans le stack PHP-FPM. Les antipatterns relevés sont des dettes de conception, pas des bugs.

- [x] **AP.6** — Couplage Request → Service
  > `grep -rn "Request \$request" src/Service/` — services qui dépendent de HTTP directement → TODO.
  >
  > **Périmètre** : aucun service n'injecte `Request $request` directement (injection de scope obsolète depuis SF3). Les 3 cas identifiés injectent `RequestStack $requestStack` (pattern correct SF4) pour appeler `getCurrentRequest()` dans leurs méthodes.
  >
  > ---
  >
  > ### Services avec `RequestStack` — 3 fichiers (🟡)
  >
  > | Service | Méthode qui accède au Request | Usage |
  > |---------|------------------------------|-------|
  > | `ShiftFreeLogService` | `initShiftFreeLog()` ligne 46 | `$request->get('_route')` → `ShiftFreeLog::requestRoute` |
  > | `TimeLogService` | `initTimeLog()` ligne 53 | `$request->get('_route')` → `TimeLog::requestRoute` |
  > | `PeriodPositionFreeLogService` | `initPeriodPositionFreeLog()` ligne 45 | `$request->get('_route')` → `PeriodPositionFreeLog::requestRoute` |
  >
  > **Intention** : tracer dans les logs d'audit quelle route HTTP a déclenché la création de l'entrée. Le champ `requestRoute` est affiché en clair dans les vues admin (`templates/member/_partial/shift_free_logs.html.twig:56`, `period_position_free_logs.html.twig:52`, `timelog/_partial/table.html.twig:46`). L'intention est légitime et le `RequestStack` est l'injection canonique pour accéder à la requête courante dans un service SF4.
  >
  > **Couplage implicite** : ces services deviennent HTTP-aware alors qu'ils n'ont pas de raison structurelle de l'être. Cela complique les tests unitaires (nécessité de mocker le `RequestStack` ou de pousser une requête factice dans la pile).
  >
  > ---
  >
  > ### Bug latent — 2 services sans null guard (🟠)
  >
  > `TimeLogService::initTimeLog()` protège correctement :
  > ```php
  > if ($request) {
  >     $log->setRequestRoute($request->get('_route'));
  > }
  > ```
  >
  > `ShiftFreeLogService::initShiftFreeLog()` (ligne 46) et `PeriodPositionFreeLogService::initPeriodPositionFreeLog()` (ligne 45) n'ont **pas ce garde** :
  > ```php
  > $log->setRequestRoute($request->get('_route'));  // $request peut être null (CLI)
  > ```
  >
  > `getCurrentRequest()` retourne `null` hors contexte HTTP (commandes CLI, workers long-running). En l'état, aucun appel CLI de ces deux services n'existe — le bug est latent. Mais si un `InitShiftFreeLogCommand` ou `FixPeriodPositionCommand` venait à les utiliser, cela produirait une `BadMethodCallException` (appel sur `null`).
  >
  > **Contexte des appelants actuels** :
  > - `ShiftFreeLogEventListener` et `PeriodPositionFreeLogEventListener` appellent ces services via le service locator (AP.4 catégorie C.3) — toujours depuis un contexte HTTP → pas de crash.
  > - `InitTimeLogCommand` et `FixTimeLogCommand` utilisent `TimeLogService` (qui est protégé) → OK.
  >
  > ---
  >
  > ### Patterns corrects
  >
  > **Option A — Paramètre explicite (recommandé)** : passer `?string $routeName = null` aux méthodes `init*()`. L'appelant (controller ou event listener) extrait lui-même `$request->get('_route')` et le passe. Les services deviennent entièrement HTTP-agnostiques et testables sans mock `RequestStack`.
  >
  > **Option B — Null guard minimal** : ajouter `if ($request)` dans `ShiftFreeLogService` et `PeriodPositionFreeLogService`. Corrige le bug latent sans refactorer l'interface. Services restent HTTP-aware.
  >
  > ---
  >
  > ### Cross-référence AP.4 — Voters et Helper
  >
  > Les accès `request_stack` via service locator dans `ShiftVoter`, `MembershipVoter`, `CodeVoter` et `Helper/PlaceIP` sont du même registre mais classifiés en AP.4 (catégorie C.3). Non redoublés ici.
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🟠 Bug latent | `ShiftFreeLogService` + `PeriodPositionFreeLogService` : appel sans null guard | XS (Option B) |
  > | 🟡 Couplage | 3 services HTTP-aware via `RequestStack` | S (Option A) / XS (Option B) |
  > | — Cross-ref | Voters + PlaceIP : `request_stack` via service locator | Voir AP.4 |
  >
  > → **TODO SYN.2** — bug latent : `ShiftFreeLogService` et `PeriodPositionFreeLogService`, ajouter null guard (XS, effort minimal) ; refactoring Option A à envisager lors de la migration SF5 (passage des services à injection pure).

- [x] **AP.7** — Event listeners surchargés
  > Lire `src/EventListener/`. Listeners > 50 lignes de logique métier → TODO.
  >
  > **Périmètre** : 15 fichiers dans `src/EventListener/`, 1 864 lignes au total. Inventaire par taille :
  >
  > | Fichier | Lignes | Statut |
  > |---------|--------|--------|
  > | `EmailingEventListener.php` | 713 | 🔴 Bloaté — God listener |
  > | `TimeLogEventListener.php` | 420 | 🟠 Bloaté — logique métier embarquée |
  > | `HelloassoEventListener.php` | 142 | 🟡 Logique enregistrement dans listener |
  > | `SetFirstPasswordListener.php` | 83 | 🟡 3 types d'événements mixés |
  > | `BeneficiaryInitializationSubscriber.php` | 82 | ✅ Acceptable |
  > | `OidcFirewallListener.php` | 76 | 🟡 URLs codées en dur |
  > | `MattermostEventListener.php` | 61 | ✅ Acceptable |
  > | `CommissionEventListener.php` | 54 | 🟡 onJoin() vide |
  > | *Autres (7 fichiers)* | ≤ 43 | ✅ OK |
  >
  > ---
  >
  > ### 1. `EmailingEventListener` — God listener, 713 lignes (🔴)
  >
  > Centralise l'envoi de 13 types d'emails différents dans une seule classe : `onAnonymousBeneficiaryCreated`, `onAnonymousBeneficiaryRecall`, `onBeneficiaryAdd`, `onMemberCreated`, `onHelloassoRegistrationSuccess`, `onHelloassoTooEarly`, `onShiftReserved`, `onShiftBooked`, `onShiftFreed`, `onShiftReminder`, `onShiftDeleted`, `onShiftAlerts`, `onMemberCycleStart`, `onMemberCycleHalf`, `onEventProxyCreated`, `onCodeNew`.
  >
  > **Bug critique — `die()` en production (🔴)** : `onHelloassoTooEarly()` ligne 257 :
  > ```php
  > } catch (\Exception $e) {
  >     die($e->getMessage());   // tue le process PHP
  > }
  > ```
  > Un `Exception` lors de la construction de l'email (ex. template Twig manquant, SMTP non joignable) tue immédiatement le process PHP, sans log ni réponse HTTP propre. En production, cela se manifeste par une page blanche ou une erreur 500 sans trace.
  >
  > **`strftime()` dépréciée PHP 8.1+ (🟡)** : utilisée à la ligne 277 (`strftime("%e %B", ...)`) et ligne 483 (`strftime("%A %e %B", ...)`). Dépréciée depuis PHP 8.1, supprimée en PHP 9. Le container tourne sur PHP 8.1 — des `E_DEPRECATED` sont émis silencieusement.
  >
  > **`renderView()` dupliquée (🟡)** : méthode `renderView()` (L701–712) identique dans `EmailingEventListener` et `HelloassoEventListener` — même implémentation copy-pasteé pour déléguer au container Twig.
  >
  > **Container injecté (AP.4 cross-ref)** : accès `$this->container->get('router')`, `get('twig')`, `get('App\Helper\SwipeCard')` inline dans les méthodes.
  >
  > **Refactoring cible** : extraire des services dédiés (`ShiftEmailService`, `MemberEmailService`, `HelloassoEmailService`, `MiscEmailService`) avec injection directe de `MailerInterface`, `UrlGeneratorInterface`, `Environment` (Twig). Le listener ne fait que router les événements vers le bon service.
  >
  > ---
  >
  > ### 2. `TimeLogEventListener` — Logique comptable embarquée, 420 lignes (🟠)
  >
  > Contient la logique métier du bilan de cycle (cycle turn-over accounting). `createCycleBeginningLog()` (L310–374, 65 lignes) calcule : soustraction de la cotisation due, redistribution de l'excédent en épargne, compensation de l'épargne en cas de déficit (avec règles d'éligibilité : créneaux ratés, libérations tardives). Cette logique n'appartient pas à un listener.
  >
  > **Chaine implicite listener→listener** : `onMemberCycleEnd()` dispatche lui-même `MemberCycleStartEvent` (L259–260) :
  > ```php
  > $dispatcher = $this->container->get('event_dispatcher');
  > $dispatcher->dispatch(new MemberCycleStartEvent(...), MemberCycleStartEvent::NAME);
  > ```
  > L'exécution de `onMemberCycleStart` dans `EmailingEventListener` dépend implicitement de l'ordre d'exécution des listeners — dépendance invisible sans lire le code.
  >
  > **Container injecté (AP.4 cross-ref)** : `time_log_service`, `membership_service`, `event_dispatcher` accédés via `$this->container->get()`.
  >
  > **Refactoring cible** : extraire `CycleAccountingService` (logique de bilan) avec injection directe de `TimeLogService` et `MembershipService`. Le listener délègue à ce service et dispatche `MemberCycleStartEvent` explicitement depuis le controller ou command déclencheur.
  >
  > ---
  >
  > ### 3. `HelloassoEventListener` — Logique d'enregistrement dans le listener, 142 lignes (🟡)
  >
  > `linkPaymentToUser()` (L95–141) crée une entité `Registration`, vérifie `canRegister()`, ajuste la date d'adhésion, rouvre un compte clôturé, et dispatche `HelloassoEvent::RE_REGISTRATION_SUCCESS` — tout dans le listener. Erreurs silencieuses : deux `throw new \LogicException` commentés (L60, L100) ; les cas d'erreur sont ignorés silencieusement.
  >
  > **Container injecté (AP.4 cross-ref)** : `membership_service`, `event_dispatcher`.
  >
  > **Refactoring cible** : extraire `HelloassoRegistrationService::linkPaymentToUser()`. Effort S.
  >
  > ---
  >
  > ### 4. `SetFirstPasswordListener` — 3 types d'événements mixés, 83 lignes (🟡)
  >
  > Combine trois responsabilités dans une classe : listener Doctrine `prePersist` (L44), listener FOS UserBundle `onPasswordChanged` (L59), listener Kernel `forcePasswordChange` (L67). Les imports `FilterResponseEvent` et `GetResponseEvent` (L14-15) sont des noms de classes supprimés en SF5 — marqueurs de code SF4 pré-migration.
  >
  > ---
  >
  > ### 5. `OidcFirewallListener` — URLs hardcodées, 76 lignes (🟡)
  >
  > Liste de 14 chemins d'URL hardcodés (L43–62) pour le contrôle d'accès OIDC. Aucune référence aux noms de routes Symfony — si une route est renommée, la protection ne suit pas. Concerne une fonctionnalité instance-specific (Scopeli utilise OIDC, Elefan non). PHPDoc erroné ligne 28–30 (`@param PeriodPositionFreedEvent`) — copie de l'autre listener.
  >
  > ---
  >
  > ### 6. `CommissionEventListener.onJoin()` — Stub vide (🟡)
  >
  > `onJoin()` (L49–52) n'a aucun corps : seulement `$this->logger->info("Commission Listener: onJoin")`. Code jamais implémenté. Cross-ref DC.1.
  >
  > ---
  >
  > ### 7. `CodeEventListener.onCodeNew()` — Corps entièrement commenté (EXTRA)
  >
  > ```php
  > public function onCodeNew(CodeNewEvent $event) {
  >     $this->logger->info("Code Listener: onCodeNew");
  >     //  $code = $event->getCode();
  >     //  $display = $event->getDisplay();
  > }
  > ```
  > Listener enregistré mais sans logique — candidat à la suppression ou au TODO d'implémentation.
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🔴 Bug critique | `EmailingEventListener::onHelloassoTooEarly()` L257 : `die()` en production | XS (remplacer par throw ou log) |
  > | 🔴 God listener | `EmailingEventListener` 713 lignes, 13 types d'emails | L (split en services) |
  > | 🟠 Logique métier | `TimeLogEventListener` 420 lignes, bilan de cycle | M (extraire CycleAccountingService) |
  > | 🟡 Dépréciation | `strftime()` PHP 8.1+ dans `EmailingEventListener` L277, L483 | XS |
  > | 🟡 Duplication | `renderView()` copieée dans `EmailingEventListener` + `HelloassoEventListener` | XS (trait ou service) |
  > | 🟡 Logique métier | `HelloassoEventListener::linkPaymentToUser()` | S (extraire service) |
  > | 🟡 Chaîne implicite | `TimeLogEventListener::onMemberCycleEnd()` dispatche `MemberCycleStartEvent` | À documenter |
  > | 🟡 URLs hardcodées | `OidcFirewallListener` 14 chemins d'URL hardcodés | S |
  > | 🟡 Code mort | `CommissionEventListener::onJoin()` vide, `CodeEventListener::onCodeNew()` commenté | XS (supprimer) |
  >
  > → **TODO AP.7.1** — `EmailingEventListener` L257 : remplacer `die($e->getMessage())` par `throw $e` ou logging + réponse propre. Priorité haute — peut tuer le process en production.
  >
  > → **TODO AP.7.2** — `EmailingEventListener` L277, L483 : remplacer `strftime()` dépréciée par `\IntlDateFormatter` ou `\DateTime::format()` + conversion locale explicite.
  >
  > → **TODO AP.7.3** — Long terme : éclater `EmailingEventListener` en services dédiés (`ShiftEmailService`, `MemberEmailService`, `HelloassoEmailService`) lors de la migration SF5. Effort L.
  >
  > → **TODO AP.7.4** — Long terme : extraire `CycleAccountingService` depuis `TimeLogEventListener`. Supprimer le dispatch interne de `MemberCycleStartEvent`. Effort M.
  >
  > → **TODO AP.7.5** — `HelloassoEventListener` : extraire `linkPaymentToUser()` dans un service dédié. Effort S.

- [x] **AP.8** — Commandes sans délégation service
  > Lire `src/Command/`. Commandes > 30 lignes dans `execute()` sans déléguer → TODO.
  >
  > **Périmètre** : 25 fichiers dans `src/Command/`, 2 743 lignes au total.
  >
  > ---
  >
  > ### Classification par niveau de délégation
  >
  > **Bien structurées (délèguent correctement) :**
  >
  > | Commande | Patron |
  > |----------|--------|
  > | `UpdateHelloAssoPaymentsCommand` | `execute()` 10 lignes → `HelloassoPaymentHandler::savePayments()` + pagination récursive |
  > | `AmbassadorShiftTimeLogCommand` | délègue à `sendAlertsByEmail()` méthode privée + `MailerInterface` |
  > | `SendShiftAlertsCommand` | extrait `computeAlerts()` (27 lignes), reste un dispatcher d'événements |
  > | `CycleStartCommand` / `CycleHalfCommand` | boucle légère + `EventDispatcher` + `MembershipService` |
  > | `HelloassoPaymentCommand` | acceptable (37 lignes, 2 branches simples) |
  > | `ShiftReminderCommand` | acceptable (33 lignes, délègue via dispatcher) |
  >
  > **Surchargées — logique métier inline dans `execute()` :**
  >
  > ---
  >
  > #### 1. `ImportUsersCommand` — execute() ≈ 195 lignes (🔴)
  >
  > Hérite de `CsvCommand` pour la gestion CSV, mais `execute()` contient intégralement la logique de création d'entités : lookup/création `Membership`, création `Beneficiary`, `User`, `Address`, `Registration`, affectation des commissions — tout en boucle inline. Aucune délégation à un service.
  >
  > **`utf8_encode()` dépréciée (🟡)** : L117 `array_map("utf8_encode", $data)`. Dépréciée depuis PHP 8.1, supprimée en PHP 8.2. Remplacement : `mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1')` ou détection d'encodage source.
  >
  > **Refactoring cible** : extraire `ImportMemberService::importFromRow()`. Effort L.
  >
  > ---
  >
  > #### 2. `AnonymizeDataCommand` — execute() ≈ 140 lignes (🟠)
  >
  > Logique d'anonymisation entièrement inline : boucle sur `Beneficiary`, `Commission`, `Event`, suppressions en bulk via `createQueryBuilder(...)->delete()`. Seul helper : `randomValue()` (private, 4 lignes). Acceptable pour un outil one-shot de maintenance, mais la logique de génération de données factices n'appartient pas au runner de commande.
  >
  > ---
  >
  > #### 3. `ShiftGenerateCommand` — execute() ≈ 113 lignes (🟠)
  >
  > Boucle date × période × position inline : requête `Period::findBy`, création `Shift`, gestion des créneaux fixés/pré-réservés/libres, flush par date. `PeriodService` est injecté mais n'est utilisé que pour `getWeekCycleArray()` — la génération elle-même n'y est pas déléguée.
  >
  > **Constante hardcodée (🟡)** : `lastCycleDate()` (L168–173) calcule 28 jours fixés en dur. Ce délai correspond à `cycle_duration` qui est un paramètre de configuration — il devrait utiliser `$this->params->get('cycle_duration')`.
  >
  > **Refactoring cible** : déplacer la logique de génération dans `PeriodService::generateShiftsForDate()`. Effort M.
  >
  > ---
  >
  > #### 4. `DoctorCommand` — execute() ≈ 100 lignes (🟡)
  >
  > Trois branches de correction inline (phone, status, registration). Acceptable pour un outil "doctor" de maintenance ad hoc, mais rend difficile le test unitaire de chaque fix.
  >
  > ---
  >
  > #### 5. `VerifyCodeChangeCommand` — execute() ≈ 58 lignes (🟠)
  >
  > Manipule le `TokenStorageInterface` en CLI (L90) pour forcer un contexte de sécurité fictif :
  > ```php
  > $token = new UsernamePasswordToken($last->getRegistrar(), ..., $last->getRegistrar()->getRoles());
  > $this->token_storage->setToken($token);
  > ```
  > Anti-pattern : le `TokenStorage` est conçu pour le contexte HTTP (stackable request scope). En CLI, injecter un token manuellement pour appeler un Voter (`CodeVoter::VIEW`) est fragile — le résultat dépend de l'implémentation interne du Voter. L'appel à `$this->authorization_checker->isGranted(CodeVoter::VIEW, $code)` devrait être remplacé par un appel direct à la logique de visibilité du code (méthode de service ou de l'entité).
  >
  > Construction email inline (L106–116).
  >
  > **Refactoring cible** : extraire la logique de visibilité de code dans une méthode `Code::isVisibleTo(User $user)` ou `CodeService::isVisible()`. Effort S.
  >
  > ---
  >
  > #### 6. `FixShiftMissingPositionCommand` — execute() ≈ 82 lignes (🟡)
  >
  > QueryBuilder DQL inline (L63–91) + boucle de déduplication par jour (L96–105) directement dans `execute()`. Requête de correction `UPDATE App:Shift s SET s.position` via DQL inline (L108). Logique de matching de position sans abstraction.
  >
  > ---
  >
  > #### 7. `RandomSortMembersCommand` — execute() ≈ 65 lignes (🟡)
  >
  > QueryBuilder avec 4 joins inline + génération CSV ligne par ligne dans `execute()`. `echo $csv` utilisé à la place de `$output->writeln()` si pas de fichier (L109) — mélange de canaux de sortie.
  >
  > ---
  >
  > #### 8. `UpdateIgloohomeCodeCommand` — execute() ≈ 50 lignes (🟡)
  >
  > Délègue la création du code API à `IgloohomeClient::regenerateCode()` (bon), mais création et fermeture des entités `Code` inline dans execute(). Logique de persistance hors Repository.
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🔴 God execute | `ImportUsersCommand` 195 lignes, entités créées inline | L |
  > | 🟠 God execute | `AnonymizeDataCommand` 140 lignes, anonymisation inline | M |
  > | 🟠 God execute | `ShiftGenerateCommand` 113 lignes, génération shifts inline | M |
  > | 🟠 Anti-pattern | `VerifyCodeChangeCommand` : TokenStorage manipulé en CLI | S |
  > | 🟡 Dépréciation | `ImportUsersCommand` L117 : `utf8_encode()` PHP 8.2 removed | XS |
  > | 🟡 Constante hardcodée | `ShiftGenerateCommand::lastCycleDate()` : 28 jours fixes au lieu de `cycle_duration` | XS |
  > | 🟡 Mélange sortie | `RandomSortMembersCommand` L109 : `echo` au lieu de `$output->write()` | XS |
  > | 🟡 Logic inline | `DoctorCommand`, `FixShiftMissingPositionCommand`, `UpdateIgloohomeCodeCommand` | S–M |
  >
  > → **TODO AP.8.1** — `ImportUsersCommand::execute()` : extraire la logique de création d'adhérent dans `ImportMemberService`. Priorité M (lisibilité + testabilité). Effort L.
  >
  > → **TODO AP.8.2** — `ImportUsersCommand` L117 : remplacer `utf8_encode()` par `mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1')`. Effort XS — correction avant PHP 8.2.
  >
  > → **TODO AP.8.3** — `ShiftGenerateCommand::lastCycleDate()` : remplacer 28 jours hardcodés par `$this->params->get('cycle_duration')`. Effort XS.
  >
  > → **TODO AP.8.4** — `VerifyCodeChangeCommand` : supprimer la manipulation du `TokenStorage` en CLI ; extraire la logique de visibilité dans `Code::isVisibleTo(User)` ou un service dédié. Effort S.

- [x] **AP.9** — Providers externes (src/Providers/)
  > Lire les 7 fichiers. Interface + implémentation correctement séparées ? Couplage fort ? → TODO.
  >
  > **Périmètre** : 7 fichiers répartis en 3 sous-domaines.
  >
  > | Sous-domaine | Fichiers | Usage |
  > |---|---|---|
  > | OAuth shared | `OauthAuthenticatorInterface`, `ClientCredentialOauthAuthenticator`, `CacheOauthAuthenticatorDecorator` | Partagé par Helloasso et Igloohome |
  > | Helloasso | `HelloassoClient`, `HelloassoNotificationRequest`, `HelloassoPaymentHandler` | Paiements adhésion (instance-specific) |
  > | Igloohome | `IgloohomeClient` | Serrures connectées (instance-specific) |
  >
  > Tous les fichiers déclarent `strict_types=1`.
  >
  > ---
  >
  > ### Points positifs
  >
  > - `OauthAuthenticatorInterface` propre — une méthode, séparée de ses implémentations. Les deux clients l'injectent via l'interface (pas la classe concrète).
  > - Pattern Decorator correctement câblé dans `services.yaml` : `CacheOauthAuthenticatorDecorator` décore `ClientCredentialOauthAuthenticator` ; l'alias `OauthAuthenticatorInterface` → `ClientCredentialOauthAuthenticator` passe bien par le décorateur (Symfony remplace le service décoré sous l'ID original). **Le cache OAuth est actif.**
  > - `HelloassoPaymentHandler` : injection directe de `EntityManagerInterface`, `EventDispatcherInterface`, `LoggerInterface` — aucun service locator. C'est le service le mieux structuré du projet.
  > - Toutes les variables provider (`HELLOASSO_*`, `IGLOOHOME_*`) utilisent `%env(default::VAR)%` → graceful degradation si l'instance n'utilise pas le provider.
  >
  > ---
  >
  > ### 1. `CacheOauthAuthenticatorDecorator` — cache non injecté : `new FilesystemAdapter()` (🟠)
  >
  > ```php
  > public function __construct(OauthAuthenticatorInterface $authenticator)
  > {
  >     $this->authenticator = $authenticator;
  >     $this->cache = new FilesystemAdapter();   // ← instanciation directe
  > }
  > ```
  >
  > Le cache est créé inline et non injecté. Conséquences :
  > - **Non purgeable** via `php bin/console cache:pool:clear` (non enregistré dans le DIC Symfony).
  > - **Non testable** : les tests doivent toucher le système de fichiers — impossible de mocker `CacheInterface`.
  > - **Namespace non configuré** : `FilesystemAdapter()` utilise le namespace vide par défaut (`sf_cache_` en préfixe), risque de collision avec d'autres usages du cache filesystem si les clés sont similaires.
  > - **TTL hardcodé** : `CACHE_DEFAULT_TTL = 600` s est non configurable sans toucher le code.
  >
  > **Fix** : injecter `CacheInterface $cache` en argument du constructeur + binder `cache.app` dans `services.yaml`.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 2. `CacheOauthAuthenticatorDecorator` — clé de cache = `$clientId` seulement (🟡)
  >
  > ```php
  > return $this->cache->get($clientId, function (ItemInterface $item) use (...) { ... });
  > ```
  >
  > Si deux providers distincts utilisent le même `$clientId` avec des `$authUrl` différentes (improbable mais non protégé), ils partagent le même token en cache. La clé robuste serait : `sha1($clientId . '|' . $authUrl)`.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 3. `CacheOauthAuthenticatorDecorator` — TTL négatif non gardé (🟡)
  >
  > ```php
  > $item->expiresAfter($expires - time());   // peut être négatif ou zéro
  > ```
  >
  > Si `$token->getExpires()` retourne un timestamp dans le passé (token déjà expiré), `expiresAfter()` reçoit une valeur négative. Symfony cache l'interprète comme expiration immédiate, mais le comportement est non documenté pour les valeurs négatives et peut varier selon l'adaptateur. Un token systématiquement expiré forcerait une régénération à chaque appel.
  >
  > **Fix** : `$item->expiresAfter(max(0, $expires - time()))` ou refus du token expiré.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 4. `HelloassoClient` / `IgloohomeClient` — `new GuzzleHttp\Client()` à chaque appel API (🟡)
  >
  > ```php
  > private function getClient(): Client {
  >     return new Client([
  >         'headers' => ['Authorization' => 'Bearer '.$this->authenticator->getToken(...)],
  >     ]);
  > }
  > ```
  >
  > `getClient()` est appelé dans **chaque méthode publique**. Pour `HelloassoController::helloassoCampaignDetailsAction()` (lignes 99–100), deux appels consécutifs (`getFormPayments()` + `getFormDetails()`) créent deux clients Guzzle distincts et deux appels `getToken()`. Le cache OAuth évite deux appels réseau vers le serveur OAuth, mais deux connexions TCP vers l'API Helloasso sont ouvertes.
  >
  > **Fix** : initialiser le `Client` Guzzle en lazy-init dans une propriété privée, ou le créer dans le constructeur (en passant le token en paramètre de `__construct` si nécessaire).
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 5. `HelloassoNotificationRequest::createFromRequest()` — validation JSON absente (🟠)
  >
  > ```php
  > $requestData = json_decode($request->getContent(), true);  // retourne null si JSON invalide
  > $eventType = $requestData['eventType'];   // Warning PHP si $requestData est null
  > ```
  >
  > Si le body est vide ou non-JSON, `json_decode()` retourne `null`. L'accès `$requestData['eventType']` sur `null` génère un `Warning: Trying to access array offset on null` (PHP 8+) puis `$eventType = null` → l'`InvalidArgumentException` est levée, mais le message d'erreur ("cannot find eventType") est trompeur. La véritable cause (JSON invalide) n'est pas signalée.
  >
  > **Fix** :
  > ```php
  > $requestData = json_decode($request->getContent(), true);
  > if (!is_array($requestData)) {
  >     throw new \InvalidArgumentException('invalid JSON in helloasso notification body');
  > }
  > ```
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 6. `HelloassoPaymentHandler` — repository non injecté directement (🟡)
  >
  > ```php
  > $this->helloassoPaymentRepository = $entityManager->getRepository(HelloassoPayment::class);
  > ```
  >
  > Repository récupéré via l'EntityManager dans le constructeur, au lieu d'être injecté directement. Le handler dépend implicitement de l'EntityManager uniquement pour obtenir le repository — couplage inutile si le repository est disponible comme service Symfony (autowirable via `EntityRepository`).
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 7. `HelloassoPaymentHandler::savePayments()` — pas de transaction explicite (🟡)
  >
  > La méthode `persist()`e tous les paiements en mémoire puis appelle `flush()` une fois. Si le `flush()` échoue (contrainte DB, connexion perdue), aucun paiement n'est sauvegardé mais **aucun rollback explicite n'est effectué** — Doctrine maintient l'EntityManager dans un état corrompu (entités marquées `NEW` mais déjà en erreur). Un `wrapInTransaction()` ou `beginTransaction()`/`rollback()` garantirait l'atomicité et l'état propre de l'EM en cas d'échec.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 8. `HelloassoPaymentHandler` — typos "payement" récurrents (🟡)
  >
  > `$existingPayement` (L44), `$payementEntity` (L51-52), message de log "payement #%d" (L59) : le mot anglais correct est **"payment"**. La faute est présente 4 fois dans la même classe et se retrouve dans le nom de la méthode d'entité `HelloassoPayment::createFromPayementObject()` (probablement — non vérifié, car nommée dans `HelloassoPaymentHandler`).
  >
  > → **TODO SYN.2** — effort XS (renommage + tests)
  >
  > ---
  >
  > ### 9. `HelloassoClient` et `IgloohomeClient` — pas d'interface (🟡)
  >
  > Contrairement à `OauthAuthenticatorInterface`, les deux clients HTTP n'ont pas d'interface. Mocker `HelloassoClient` dans un test nécessite PHPUnit `MockBuilder` sur la classe concrète (qui fait des appels Guzzle). Une `HelloassoClientInterface` / `IgloohomeClientInterface` simplifierait les tests et permettrait un double de test propre. Cohérence avec le pattern déjà établi dans le sous-domaine OAuth.
  >
  > → **TODO SYN.2** — effort S (interfaces + mise à jour des typehints)
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🟠 | Cache non injecté dans `CacheOauthAuthenticatorDecorator` (`new FilesystemAdapter()`) | XS |
  > | 🟠 | `HelloassoNotificationRequest::createFromRequest()` — validation JSON absente | XS |
  > | 🟡 | Clé de cache = `$clientId` seulement (risque collision) | XS |
  > | 🟡 | TTL négatif non gardé dans le décorateur | XS |
  > | 🟡 | `new GuzzleHttp\Client()` à chaque appel API (deux connexions par action) | XS |
  > | 🟡 | Repository non injecté directement dans `HelloassoPaymentHandler` | XS |
  > | 🟡 | Pas de transaction explicite dans `savePayments()` | XS |
  > | 🟡 | Typos "payement" × 4 dans `HelloassoPaymentHandler` | XS |
  > | 🟡 | `HelloassoClient` et `IgloohomeClient` sans interface | S |

---

## SEC — Sécurité (analyse uniquement)
> 🔀 **Modèle : Opus.** Rappeler à l'utilisateur : `/model opus` avant SEC.1, `/model sonnet` après SEC.7.

- [x] **SEC.1** — Configuration sécurité Symfony
  > Lire `config/packages/security.yaml`. Firewalls, access_control, voters. Gaps → TODO.
  >
  > **Fichiers lus** : `config/packages/security.yaml`, `config/packages/framework.yaml`, `src/EventListener/OidcFirewallListener.php`, `src/Helper/SwipeCard.php`, `src/Controller/ShiftController.php` (actions accept/reject/contactForm/widget), `src/Controller/DefaultController.php` (helloassoNotify), `src/Controller/BookingController.php` (bucket_show), `src/Controller/SwipeCardController.php` (swipe_in, qr, br), `src/Controller/CardReaderController.php`.
  >
  > ---
  >
  > ### 1. Absence de règle default-deny — modèle "opt-in" fragile (🔴)
  >
  > La liste `access_control` couvre uniquement :
  > - `/oauth/v2/token` et `/oauth/v2/auth` (OAuth)
  > - `/login`, `/register`, `/resetting` (pages FOS publiques)
  > - `/admin/` → `ROLE_ADMIN_PANEL`
  > - `/api` → `IS_AUTHENTICATED_FULLY`
  >
  > **Il n'existe aucune règle catch-all.** Toute route hors de ces préfixes est accessible à un utilisateur anonyme, sauf si le controller ajoute explicitement une annotation `@Security` ou un appel `denyAccessUnlessGranted()`. Ce modèle "opt-in" signifie qu'un controller sans annotation est silencieusement public. Le projet compte 42 controllers — la totalité de la surface d'exposition dépend de la vigilance des développeurs, sans filet de sécurité framework.
  >
  > **Pattern recommandé** : ajouter une règle terminale `{ path: ^/, role: IS_AUTHENTICATED_REMEMBERED }` (ou `ROLE_USER`), puis créer des exceptions explicites pour les routes publiques connues (`^/$`, `^/login`, `^/sw/` pour les badges, `^/helloassoNotify`, etc.). Ce pattern "default-deny" est l'inverse de l'état actuel.
  >
  > → **TODO SYN.2** — effort M (audit de toutes les routes + ajout règle terminale + liste d'exceptions)
  >
  > ---
  >
  > ### 2. `switch_user` sans protection CSRF (🟠)
  >
  > ```yaml
  > switch_user:
  >   role: ROLE_ADMIN
  >   parameter: _login_as
  > ```
  >
  > La fonctionnalité d'impersonation est activée sur le firewall principal. Symfony 4.4 ne protège pas automatiquement les liens `switch_user` par CSRF — la protection est optionnelle (`check_csrf_token: true`). En l'état, n'importe quel lien GET avec `?_login_as=<username>` peut impersonater un utilisateur si l'admin est authentifié. Une faille XSS ou un lien piégé dans un email suffisent pour déclencher une impersonation.
  >
  > Les templates utilisent `switch_user` comme liens GET standard (`beneficiary_card.html.twig:53` : `path('homepage', {'_login_as': beneficiary.user.username})`), sans formulaire POST ni token CSRF.
  >
  > **Fix** : ajouter `check_csrf_token: true` dans la configuration `switch_user`. Le link Twig doit devenir un formulaire POST avec `{{ csrf_token('switch_user') }}`.
  >
  > → **TODO SYN.2** — effort XS (config + templates)
  >
  > ---
  >
  > ### 3. `/shift/{id}/contact_form` — envoi d'email sans authentification (🟠)
  >
  > `ShiftController::contactFormAction` n'a ni `@Security` ni `denyAccessUnlessGranted`. Un utilisateur anonyme peut POST cette route et déclencher l'envoi d'un email via l'infrastructure SMTP de la coopérative. Le `$from` est extrait des données du formulaire et résolu via la DB, mais rien n'empêche un attaquant de fabriquer des requêtes répétées.
  >
  > Vecteur : spam abuse de l'SMTP coopératif, usurpation partielle d'identité (le `from` visible dans l'email est le nom du bénéficiaire récupéré en DB, mais l'expéditeur est `transactional_mailer_user`).
  >
  > **Fix** : ajouter `@Security("is_granted('ROLE_USER')")` sur l'action.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 4. `/helloassoNotify` — webhook sans authentification ni rate-limit (🟠)
  >
  > `DefaultController::helloassoNotify` (route `POST /helloassoNotify`) n'a aucune vérification d'authenticité. Le commentaire dans le code l'explique : Helloasso ne fournit la signature de webhook qu'aux "partenaires", donc le projet compense en refaisant un appel API pour vérifier le payload. Ce pattern est correct pour éviter les données forgées, mais :
  > - L'endpoint peut être spammé (chaque requête déclenche un appel API sortant vers Helloasso → DoS indirect via rate-limit de l'API Helloasso).
  > - Sans IP allowlist ni authentification basique, tout acteur peut interroger l'endpoint.
  >
  > **Mitigation recommandée** : IP allowlist des serveurs Helloasso (documentés dans leur API) ou `Authorization: Bearer` secret partagé en attendant l'accès à la signature de webhook (disponible pour partenaires selon le commentaire).
  >
  > → **TODO SYN.2** — effort S (allowlist IP ou secret partagé)
  >
  > ---
  >
  > ### 5. `/shift/{id}/accept` et `/shift/{id}/reject` — voter seul comme guard (🟠)
  >
  > Ces deux actions n'ont ni `@Security` ni `denyAccessUnlessGranted` préalable. La protection repose uniquement sur `$this->isGranted('accept'/'reject', $shift)` vérifié en milieu d'action, après que `$current_user` soit déjà extrait du `token_storage`. Pour un utilisateur anonyme :
  > - `getToken()->getUser()` retourne la chaîne `"anon."` (SF4 anonymous token)
  > - Si le `ShiftVoter` renvoie `ACCESS_ABSTAIN` pour un user anonyme, le résultat avec la stratégie `affirmative` et `allow_if_all_abstain: false` (défaut) est un refus — la protection tient.
  > - Mais ce comportement dépend d'une implémentation correcte du voter et d'une configuration non modifiée de l'access_decision_manager. C'est fragile.
  >
  > En pratique : l'action `acceptReservedShiftAction` modifie un shift et dispatche un événement. La redirection avec flash error en cas de refus du voter est le seul filet visible. Un annotateur oublié dans un refactor futur pourrait briser ce filet.
  >
  > **Fix** : ajouter `@Security("is_granted('ROLE_USER')")` sur les deux actions.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 6. `has_role()` déprécié — supprimé en SF5 (🟠)
  >
  > `HelloassoController.php:93` :
  > ```php
  > @Security("has_role('ROLE_FINANCE_MANAGER')")
  > ```
  > La fonction `has_role()` est **dépréciée depuis SF 4.0 et supprimée en SF5**. Le remplacement est `is_granted()`. Cette annotation ne compilera pas lors de la migration. C'est la seule occurrence dans le projet.
  >
  > **Fix** : remplacer par `@Security("is_granted('ROLE_FINANCE_MANAGER')")`.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 7. Chiffrement Vigenère pour les codes badge — non cryptographiquement sécurisé (🟠)
  >
  > `src/Helper/SwipeCard.php` utilise le chiffre de Vigenère pour encoder/décoder les codes de badge dans les QR codes et URL. Deux problèmes cumulatifs :
  >
  > **a) Vigenère n'est pas un chiffrement cryptographique**
  > - La clé est répétée cycliquement (`str_pad('', $length, $key)`). Si la clé est courte (ex. 16 chars) et les codes longs, la répétition est triviale à détecter via l'indice de coïncidence.
  > - La sécurité repose entièrement sur la confidentialité de `swipeCardSecret`. Si ce paramètre fuite (logs, erreur de config, compromission env), **tous les QR codes peuvent être forgés** — permettant l'accès physique à la coopérative (la route `/sw/in/{code}` authentifie l'utilisateur directement sans autre vérification).
  >
  > **b) `rand()` au lieu de `random_int()` pour la génération des codes**
  > ```php
  > $code = rand(0, pow(10, self::PADLENGTH));  // PADLENGTH = 8
  > ```
  > `rand()` utilise le PRNG système (Mersenne Twister sur Linux) — **non cryptographiquement sécurisé**. Avec des timestamps connus, l'espace est prédictible. `random_int()` doit être utilisé.
  >
  > **Note de contexte** : le QR code est scanné physiquement → le risque pratique dépend de la menace. Mais si un attaquant obtient un code badge légitime (ex. photo), il peut déduire la clé et forger d'autres codes.
  >
  > **Remplacement recommandé** : HMAC-SHA256(`swipeCardSecret`, `code`) tronqué, ou un token aléatoire sécurisé (`random_bytes(16)`) stocké en DB. Effort M.
  >
  > → **TODO SYN.2** — effort M (remplacement cryptographique + migration des codes existants)
  >
  > ---
  >
  > ### 8. `KeycloakAuthenticator` actif même quand `OIDC_ENABLE=false` (🟡)
  >
  > Le guard authenticator `App\Security\KeycloakAuthenticator` est enregistré inconditionnellement dans le firewall `main`. La note commentée (`#- keycloak_authenticator`) suggère une hésitation passée. Même avec `OIDC_ENABLE=false`, le `KeycloakAuthenticator` tente de gérer chaque requête. Si son `supports()` retourne vite `false` pour les requêtes sans contexte OIDC, l'overhead est négligeable, mais :
  > - C'est une surface de code active même sur les instances non-OIDC.
  > - Un bug futur dans `KeycloakAuthenticator` pourrait affecter Elefan (OIDC=false).
  >
  > **Recommandation** : conditionner l'enregistrement du guard à `OIDC_ENABLE=true` (via `services.yaml` + tags conditionnels, ou deux fichiers de configuration d'environnement). Effort S.
  >
  > → **TODO SYN.2** — effort S (configuration conditionnelle)
  >
  > ---
  >
  > ### 9. `oauth_token` firewall — `security: false` (🟡)
  >
  > Le firewall `oauth_token` (pattern `^/oauth/v2/token`) a `security: false` — toute la couche sécurité Symfony est désactivée pour ce endpoint. FOSOAuthServerBundle gère sa propre authentification client (client_id + client_secret dans le corps POST). Ce pattern est courant pour les OAuth token endpoints. Cependant, sans la couche Symfony, les listeners de sécurité (notamment `OidcFirewallListener`) ne s'y appliquent pas non plus. À documenter dans la configuration finale pour les mainteneurs.
  >
  > ---
  >
  > ### 10. Session — `cookie_secure` non configuré (🟡)
  >
  > `config/packages/framework.yaml` — session configurée avec `name: USERSSID` et `cookie_domain: "%env(ROUTER_REQUEST_CONTEXT_HOST)%"`, mais **sans `cookie_secure`**. La valeur par défaut PHP est `false` — les cookies de session sont transmis en HTTP clair. Sur une instance HTTPS (production), cela expose le cookie à un attaquant capable d'intercepter le trafic HTTP (ex. réseau local).
  >
  > **Fix** : ajouter `cookie_secure: auto` (Symfony transmet le cookie en HTTPS uniquement si la requête est HTTPS, sinon HTTP). Effort XS.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 11. Encodeur `bcrypt` sans coût explicite (🟡)
  >
  > ```yaml
  > encoders:
  >   FOS\UserBundle\Model\UserInterface: bcrypt
  > ```
  >
  > Sans `cost:` spécifié, PHP utilise son défaut (10). Aucun problème en SF4 avec bcrypt. Lors de la migration SF5+ (remplacement FOSUserBundle), l'encodeur `bcrypt` deviendra un `password_hasher`. L'absence de `migrate_on_login: true` dans ce contexte signifie qu'un changement d'algorithme (vers Argon2id, recommandé) nécessiterait un rehash manuel. Anticiper ce besoin dans le plan de migration.
  >
  > → Note pour **SF-PREP** — aucune action immédiate
  >
  > ---
  >
  > ### 12. `bucket_show` — données d'adhérent conditionnellement exposées (🟡)
  >
  > `BookingController::showBucketAction` (route `GET /booking/bucket/{id}/show`) est publique. Elle rend un partial de créneau. Le code conditionne `display_names` à l'authentification :
  > ```php
  > 'display_names' => !is_null($this->security->getUser())
  > ```
  > Mais le partial lui-même (`booking/_partial/bucket.html.twig`) doit être vérifié pour s'assurer qu'aucune donnée personnelle n'est exposée en mode anonyme. À croiser avec **SEC.2**.
  >
  > ---
  >
  > ### 13. `swipe_in` — authentification par GET sans CSRF (🟡)
  >
  > La route `GET /sw/in/{code}` authentifie directement un utilisateur en injectant un token Symfony. Une requête GET suffisant à déclencher l'authentification est vulnérable si le code est prévisible ou si une image embarquée dans une page externe peut déclencher l'accès (navigateurs chargent les images GET automatiquement). Voir finding 7 (Vigenère) pour la cryptographie des codes. Le risque pratique est limité par la possession physique du badge.
  >
  > ---
  >
  > ### Résumé
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🔴 Critique | Pas de règle default-deny en `access_control` — routes non annotées accessibles anonymement | M |
  > | 🟠 Important | `switch_user` sans CSRF — impersonation via lien GET cliquable | XS |
  > | 🟠 Important | `/shift/{id}/contact_form` — envoi email sans authentification | XS |
  > | 🟠 Important | `/helloassoNotify` — webhook sans auth ni rate-limit | S |
  > | 🟠 Important | `/shift/{id}/accept` et `reject` — voter seul comme guard, pas de `@Security` | XS |
  > | 🟠 Important | `has_role()` déprécié (SF5-removed) dans `HelloassoController` | XS |
  > | 🟠 Important | Vigenère + `rand()` pour les codes badge — non cryptographique | M |
  > | 🟡 Mineur | `KeycloakAuthenticator` actif même avec `OIDC_ENABLE=false` | S |
  > | 🟡 Mineur | `oauth_token` firewall `security: false` — à documenter | — |
  > | 🟡 Mineur | `cookie_secure` absent — sessions HTTP sur HTTPS possible | XS |
  > | 🟡 Mineur | Encodeur `bcrypt` sans coût explicite — à anticiper pour SF5 migration | — |
  > | 🟡 Info | `bucket_show` — exposition conditionnelle des noms (à vérifier en SEC.2) | — |
  > | 🟡 Info | `swipe_in` — auth GET sans CSRF (risque limité par possession physique badge) | — |
  >
  > **Cross-références** :
  > - Trouver **SEC.2** pour compléter l'inventaire des routes sans vérification (gap access_control)
  > - Finding 7 (Vigenère) croise avec AP.2 (finding 2 — `UsernamePasswordToken` manuel)
  > - Finding 3 (`contact_form`) croise avec AP.1 (finding 2e — email construit inline)
  > - Finding OIDC Listener (finding 8) croise avec AP.7 (finding 5 — OidcFirewallListener)

- [x] **SEC.2** — Autorisation dans les controllers
  > `grep -rn "denyAccessUnlessGranted\|IsGranted\|isGranted" src/Controller/`. Actions sans vérification → TODO.
  >
  > **Fichiers lus** : tous les fichiers `src/Controller/*.php` (43 controllers), `templates/beneficiary/confirm.html.twig`.
  >
  > **Périmètre** : 43 controllers, ~180 actions publiques. Les 11 controllers `Admin*` utilisent tous le préfixe `admin/...` et sont couverts par la règle `access_control: ^/admin/ → ROLE_ADMIN_PANEL`. L'analyse ci-dessous porte sur les 32 controllers hors préfixe admin.
  >
  > ---
  >
  > ### 1. `setEmailAction` — modification d'email sans authentification (🔴)
  >
  > `MembershipController::setEmailAction` (POST `/member/{id}/set_email`) n'a **aucune vérification d'autorisation** — ni `@Security`, ni `denyAccessUnlessGranted`, ni `isGranted`. N'importe quel utilisateur anonyme peut envoyer un POST avec un ID de bénéficiaire valide et une adresse email, et modifier l'adresse si l'email courant est un "email temporaire".
  >
  > **Vecteur d'attaque** : énumération des IDs de bénéficiaires → changement de l'email temporaire vers une adresse contrôlée par l'attaquant → déclenchement du reset de mot de passe FOS (`/resetting/send-email`) → prise de contrôle du compte.
  >
  > Le template `confirm.html.twig` (lui-même accessible publiquement) affiche le formulaire `set_email` pour les utilisateurs non authentifiés dont l'email est temporaire, ce qui rend le vecteur encore plus direct.
  >
  > **Fix** : ajouter `@Security("is_granted('ROLE_USER')")` ou `denyAccessUnlessGranted('ROLE_USER')` en début d'action. La logique d'activation pour les utilisateurs sans compte doit passer par un token à usage unique (actuellement le flow Vigenère est utilisé pour l'invitation initiale).
  >
  > → **TODO SYN.2** — effort XS (ajout contrôle auth) ; reconsidérer le flow d'activation anonyme (effort M)
  >
  > ---
  >
  > ### 2. `CardReaderController::checkAction` — validation de créneau sans authentification (🟠)
  >
  > `CardReaderController::indexAction` (GET `/card_reader/`) est protégé par `denyAccessUnlessGranted('card_reader', $this->getUser())`. En revanche, `checkAction` (POST `/card_reader/check`) **n'a aucune vérification d'autorisation**. La route est accessible sans être authentifié sur l'interface web.
  >
  > Avec un code EAN13 valide (obtenu en photographiant un badge, ou en le déduisant via l'espace de codes prévisible — voir SEC.1 finding 7, `rand()` non sécurisé), un attaquant peut directement valider la participation d'un adhérent à un créneau sans passer par le terminal de pointage.
  >
  > **Fix** : ajouter `denyAccessUnlessGranted('card_reader', $this->getUser())` en début de `checkAction`, identique à `indexAction`.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 3. `CommissionController` — auth custom non-Symfony, fatal error anonyme (🟠)
  >
  > `addBeneficiaryAction` (POST `/commissions/{id}/add_beneficiary/`) et `removeBeneficiaryAction` (POST `/commissions/{id}/remove_beneficiary/`) utilisent un pattern d'autorisation maison :
  >
  > ```php
  > $current_app_user = $this->get('security.token_storage')->getToken()->getUser();
  > if (! $current_app_user->hasRole('ROLE_SUPER_ADMIN') && ! $current_app_user->getBeneficiary()->getOwnedCommissions()->contains($commission)) {
  >     throw $this->createAccessDeniedException();
  > }
  > ```
  >
  > Pour un utilisateur anonyme, `getToken()->getUser()` retourne la chaîne `"anon."` en SF4. Appeler `->hasRole()` sur une chaîne produit un **PHP Fatal Error** → réponse 500, ce qui bloque l'accès mais expose un stack trace en `APP_ENV=dev` et crée une erreur de log inutile en production.
  >
  > De plus, `removeBeneficiaryAction` utilise `$_POST['beneficiary']` (superglobal PHP) au lieu de `$request->request->get('beneficiary')` — un antipattern qui contourne l'abstraction Symfony.
  >
  > **Fix** : remplacer par `@Security("is_granted('ROLE_USER')")` en annotation de méthode + `denyAccessUnlessGranted` avec un voter dédié ou un check `isGranted` standard. Remplacer `$_POST` par `$request->request->get()`.
  >
  > → **TODO SYN.2** — effort XS (migration auth) ; cross-ref AP section (superglobal `$_POST`)
  >
  > ---
  >
  > ### 4. `BookingController::indexByDayAction` — auth conditionnelle (🟡)
  >
  > `indexByDayAction` (GET+POST `/booking/day/{day}/{beneficiary}/{cycle}`) protège les données de bénéficiaire conditionnellement :
  >
  > ```php
  > if (!is_null($beneficiary))
  >     $this->denyAccessUnlessGranted('ROLE_USER');
  > ```
  >
  > Sans paramètre `{beneficiary}`, la route est accessible anonymement et rend les créneaux du jour (noms des bénéficiaires inclus si `display_name_shifters=true`). Croiser avec **CONFIG.2** pour savoir si `display_name_shifters` est activé chez Elefan/Scopeli.
  >
  > → Note pour **SYN.2** (mineur, à confirmer selon config)
  >
  > ---
  >
  > ### 5. `UserController::installAdminAction` — bootstrap sans auth (🟡)
  >
  > `installAdminAction` (GET+POST `/user/install_admin`) n'a pas de `@Security`. Son comportement dépend de l'état de la base :
  >
  > - Si aucun `ROLE_SUPER_ADMIN` en DB → crée le super admin depuis les paramètres de config (`super_admin.initial_password`, `super_admin.username`) **sans authentification**.
  > - Si un super admin existe → vérifie `isGranted('ROLE_ADMIN')`.
  >
  > En production non initialisée, **n'importe qui peut POST sur cette route et déclencher la création du super admin** avec les credentials du fichier de config (potentiellement des valeurs par défaut connues). Ce risque est temporaire (limité à la fenêtre entre déploiement et première initialisation), mais non documenté et non protégé.
  >
  > **Recommandation** : protéger via un secret one-time passé en query param, ou via une commande Symfony CLI uniquement (supprimer la route en prod). Documenter dans **SYN.2**.
  >
  > → **TODO SYN.2** — effort S
  >
  > ---
  >
  > ### 6. Actions publiques by design — inventaire et risques résiduels (🟡)
  >
  > Les routes suivantes sont intentionnellement publiques (flow d'activation de compte, widgets, OAuth) :
  >
  > | Route | Controller::action | Justification | Risque résiduel |
  > |-------|-------------------|--------------|-----------------|
  > | GET `/` | `DefaultController::indexAction` | Homepage | Faible (display conditionnel) |
  > | GET `/about` | `DefaultController::aboutAction` | Info publique | Aucun |
  > | GET `/events/widget` | `EventController::widgetAction` | Widget embarqué | Aucun |
  > | GET `/events/{id}` | `EventController::detailAction` | Event public | Faible (données événement) |
  > | GET `/closingexceptions/widget` | `ClosingExceptionController::widgetAction` | Widget embarqué | Aucun |
  > | GET `/openinghours/widget` | `OpeningHourController::widgetAction` | Widget embarqué | Aucun |
  > | GET `/widget/` | `WidgetController::widgetAction` | Widget embarqué | Aucun |
  > | GET `/oauth/login`, `/oauth/callback`, `/oauth/logout` | `OAuthController` | OAuth flow | Aucun |
  > | GET+POST `/beneficiary/find_member_number` | `BeneficiaryController::findMemberNumberAction` | Onboarding (trouver son numéro) | Moyen — énumération de bénéficiaires par prénom |
  > | POST `/beneficiary/{id}/confirm` | `BeneficiaryController::confirmAction` | Onboarding step | Moyen — expose nom + email anonymisé pour tout ID |
  > | GET+POST `/member/find_me` | `MembershipController::activeUserAccountAction` | Onboarding | Moyen — idem confirm |
  > | GET+POST `/member/add_beneficiary` | `MembershipController::addBeneficiaryAction` | Invitation link (token Vigenère) | Moyen — dépend de la sécurité du token (SEC.1 finding 7) |
  > | GET+POST `/member/new` | `MembershipController::newAction` | Invitation link OU voter (admin) | Moyen — même remarque |
  > | GET `/sw/in/{code}` | `SwipeCardController::swipeInAction` | Badge auth | Documenté SEC.1 finding 13 |
  > | GET `/sw/{code}/qr.png` | `SwipeCardController::qrAction` | QR code affiché après auth | Faible (Vigenère requis) |
  > | GET `/sw/{code}/br.png` | `SwipeCardController::brAction` | Barcode affiché après auth | Faible (Vigenère requis) |
  > | GET `/ambassador/phone/{member_number}` | `AmbassadorController::showAction` | Simple redirect vers member_show (protégé) | Aucun |
  >
  > **Risque transversal — énumération de membres** : `findMemberNumberAction`, `confirmAction`, et `activeUserAccountAction` forment un flow public qui permet à n'importe qui de rechercher un adhérent par prénom, obtenir son ID, et voir son nom complet + email masqué. Ce flow est intentionnel (activation de compte) mais constitue une surface d'énumération. L'ajout d'un rate-limit (ex. Symfony RateLimiter) réduirait ce risque.
  >
  > → Note pour **SYN.2** (recommandation rate-limit, effort S)
  >
  > ---
  >
  > ### 7. Pattern `denyAccessUnlessGranted` sans `@Security` — surface fragile (🟡)
  >
  > Plusieurs actions protègent via voter (`denyAccessUnlessGranted`) en milieu de méthode sans annotation `@Security` en amont. La protection tient si le voter lève une exception, mais le point d'entrée n'est pas déclaratif et peut être fragilisé par un refactor. Exemples :
  >
  > | Controller | Action | Pattern |
  > |------------|--------|---------|
  > | `NoteController` | `noteEditAction`, `deleteNoteAction` | `denyAccessUnlessGranted` seul |
  > | `MembershipController` | `freezeAction`, `unfreezeAction`, `freezeChangeAction`, `newRegistration`, `newBeneficiary` | `denyAccessUnlessGranted` seul |
  > | `BeneficiaryController` | `editBeneficiaryAction`, `setAsMainBeneficiaryAction`, `detachBeneficiaryAction`, `deleteBeneficiaryAction` | `denyAccessUnlessGranted` seul |
  > | `ShiftController` | `acceptReservedShiftAction`, `rejectReservedShiftAction` | `isGranted` conditionnel seul (SEC.1 finding 5) |
  > | `ShiftController` | `contactFormAction` | Aucune vérification (SEC.1 finding 3) |
  > | `BookingController` | `indexByDayAction` | `denyAccessUnlessGranted` conditionnel |
  >
  > Ce pattern se dissoudrait automatiquement si la recommandation SEC.1 finding 1 (règle default-deny en `access_control`) était appliquée.
  >
  > ---
  >
  > ### Résumé SEC.2
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🔴 Critique | `setEmailAction` — email modifiable sans auth → vecteur account takeover | XS |
  > | 🟠 Important | `card_reader/check` — validation de créneau sans auth | XS |
  > | 🟠 Important | `CommissionController` — auth custom → fatal error anonyme + `$_POST` direct | XS |
  > | 🟡 Mineur | `installAdminAction` — bootstrap sans auth (fenêtre temporaire) | S |
  > | 🟡 Mineur | `indexByDayAction` — auth conditionnelle, données créneaux visibles anonymement | S |
  > | 🟡 Info | Flow onboarding public (find_member_number, confirm, find_me) — énumération membres | Rate-limit S |
  > | 🟡 Info | Pattern `denyAccessUnlessGranted` sans `@Security` — résolu par default-deny SEC.1 | — |
  >
  > **Cross-références** :
  > - Finding 1 (`setEmailAction`) croise avec SEC.1 finding 1 (no default-deny) et SEC.1 finding 7 (Vigenère)
  > - Finding 2 (`card_reader/check`) croise avec SEC.1 finding 7 (`rand()` codes prévisibles)
  > - Finding 7 (pattern fragile) résolu par SEC.1 finding 1 (default-deny)
  > - `confirmAction` / `find_member_number` croisent avec SEC.1 finding 12 (`bucket_show` exposition conditionnelle)

- [x] **SEC.3** — CSRF
  > `grep -rn "csrf_protection.*false\|'csrf'.*false" src/`. Formulaires non protégés → TODO.
  >
  > **Fichiers lus** : `config/packages/framework.yaml`, `config/packages/security.yaml`, `src/Controller/ShiftController.php`, `src/Controller/CardReaderController.php`, `src/Controller/MembershipController.php`, `src/Controller/SwipeCardController.php`, `src/Controller/ProcessUpdateController.php`, `src/Controller/HelloassoController.php`, `src/Controller/BeneficiaryController.php`, `src/Controller/AmbassadorController.php`, `assets/js/barcode.js`, `templates/booking/_partial/shift_alone.html.twig`, `templates/booking/index.html.twig`, `templates/beneficiary/confirm.html.twig`, `templates/swipeCard/_partial/add_modal.html.twig`, `templates/swipeCard/_partial/list.html.twig`, `templates/admin/helloasso/browser.html.twig`.
  >
  > ---
  >
  > ### 1. Protection CSRF Symfony active — périmètre couvert (✅)
  >
  > `framework.yaml` : `csrf_protection: ~` (activé avec les paramètres par défaut). La configuration `security.yaml` active `csrf_token_generator: security.csrf.token_manager` pour les firewalls `form_login`.
  >
  > **Conséquence** : tout formulaire créé via `$this->createForm(Type::class)` ou `$this->createFormBuilder()->getForm()` inclut automatiquement un champ `_token` vérifié lors de `$form->isValid()`. Ce pattern couvre la très grande majorité des actions avec état : toutes les routes `DELETE` (via `createDeleteForm()`), les routes `freeze`, `unfreeze`, `withdrawn`, `flying`, `free`, `validate_admin`, `lock/unlock`, etc. (~30+ actions protégées).
  >
  > La suite de ce finding documente les **exceptions** — routes qui échappent à ce mécanisme.
  >
  > ---
  >
  > ### 2. `shift_book` (POST `/shift/{id}/book`) — endpoint JSON sans CSRF (🟡)
  >
  > `ShiftController::bookShiftAction` (ligne 139) lit le corps de la requête directement :
  > ```php
  > $content = json_decode($request->getContent());
  > $beneficiaryId = $content->beneficiaryId;
  > ```
  > Aucun formulaire Symfony, aucun token CSRF.
  >
  > **Templates** :
  > - `booking/_partial/shift_alone.html.twig` (ligne 9) : formulaire HTML brut `<form method="post">` sans `{{ csrf_token() }}`, envoie `beneficiaryId` en form-encoded. → **Broken** : le controller attend du JSON et ignorera ce payload (le `json_decode` retournera `null`). Ce formulaire semble non fonctionnel (letoff buggy).
  > - `booking/index.html.twig` (ligne 229) : XHR `xhttp.send(JSON.stringify(body))` — pas de header `X-CSRF-Token`.
  >
  > **Exploitabilité réduite** : un attaquant cross-site ne peut pas envoyer un `Content-Type: application/json` sans déclencher un preflight CORS (les navigateurs modernes bloquent les requêtes cross-origin avec Content-Type non simple). Toutefois :
  > - Aucun SameSite explicite sur le cookie de session (finding 7 ci-dessous).
  > - Le formulaire `shift_alone.html.twig` soumet en form-encoded vers un endpoint JSON → incohérence qui mérite correction indépendamment du CSRF.
  >
  > → **TODO SYN.2** — effort XS (ajouter vérification CSRF côté controller ou passer via Symfony Form ; corriger le formulaire broken)
  >
  > ---
  >
  > ### 3. `card_reader_check` (POST `/card_reader/check`) — formulaire JS sans CSRF (🟡)
  >
  > `CardReaderController::checkAction` (ligne 62) lit le code badge via `$request->get('swipe_code')` — aucun formulaire Symfony, aucun token CSRF.
  >
  > Le fichier `assets/js/barcode.js` crée dynamiquement un formulaire HTML au runtime :
  > ```js
  > var form = $('<form ... action="' + barcode_submit_url + '" method="post">' +
  >     '<input type="text" name="swipe_code" value="' + barcode + '" />' +
  >     '</form>');
  > ```
  > Aucun token CSRF dans ce formulaire généré.
  >
  > **Action** : valide la participation d'un adhérent à un créneau en cours (`shift.validateShiftParticipation()` + `em->flush()`). Écriture en base.
  >
  > **Contexte atténuant** : l'accès à la page card_reader est contrôlé par le voter `card_reader`, et le badge EAN-13 valide doit être connu de l'attaquant. La surface est donc limitée au terminal dédié. Mais si ce terminal est utilisé dans un navigateur généraliste, un attaquant qui connaît un code badge valide (prédictible via `rand()` — see SEC.1 finding 7) peut forger la requête.
  >
  > Cross-référence : SEC.2 finding 2 pour l'absence d'auth, SEC.1 finding 7 pour la prévisibilité des codes.
  >
  > → **TODO SYN.2** — effort XS (ajouter `csrf_token` au formulaire JS, valider côté controller)
  >
  > ---
  >
  > ### 4. `set_email` (POST `/member/{id}/set_email`) — CSRF exploitable (🔴)
  >
  > `MembershipController::setEmailAction` (ligne 425) lit l'email directement depuis le body :
  > ```php
  > $email = $request->request->get('email');
  > $user->setEmail($email);
  > $em->flush();
  > ```
  > Aucun formulaire Symfony, aucun token CSRF.
  >
  > Template `beneficiary/confirm.html.twig` (ligne 106) :
  > ```html
  > <form action="{{ path('set_email', {'id': beneficiary.id}) }}" method="post">
  >     <input type="email" name="email" placeholder="mon-email@..." />
  >     <button type="submit">Définir mon email</button>
  > </form>
  > ```
  >
  > **Attaque** : une page externe forge un formulaire POST vers `/member/{id}/set_email` avec un email contrôlé par l'attaquant. Si la victime est authentifiée et clique (ou si la page soumet automatiquement via JS), l'email en base est remplacé. L'attaquant peut ensuite déclencher un reset de mot de passe sur l'email qu'il contrôle → **account takeover**.
  >
  > Note : l'`id` dans l'URL est l'ID Beneficiary, que l'attaquant doit connaître. Ces IDs sont séquentiels et exposés dans les URLs du profil.
  >
  > Cross-référence : SEC.2 finding 1 (même action, flaggée pour l'absence de contrôle d'authentification robuste).
  >
  > → **TODO critique SEC-CSRF-1** — effort XS (convertir en Symfony Form avec CSRF, ou `isCsrfTokenValid()` explicite)
  >
  > ---
  >
  > ### 5. Badges SwipeCard — 4 routes POST sans CSRF (🟠)
  >
  > Quatre actions dans `SwipeCardController` lisent les paramètres directement depuis `$request->get()` sans Symfony Form ni token CSRF :
  >
  > | Route | Action | Effet |
  > |-------|--------|-------|
  > | POST `/swipe_card/activate` | `activateSwipeCardAction` | Associe un badge à un bénéficiaire |
  > | POST `/swipe_card/enable` | `enableSwipeCardAction` | Réactive un badge existant |
  > | POST `/swipe_card/disable` | `disableSwipeCardAction` | Désactive un badge |
  > | POST `/swipe_card/delete` | `deleteAction` (ROLE_ADMIN) | Supprime un badge |
  >
  > Templates correspondants (`swipeCard/_partial/add_modal.html.twig`, `_partial/list.html.twig`, `_partial/disable_modal.html.twig`) : formulaires HTML bruts, aucun token CSRF.
  >
  > **Risque** : un attaquant peut désactiver le badge d'une victime (déni de service sur l'accès coopératif) ou associer son propre badge à un compte victime. Nécessite l'ID bénéficiaire (séquentiel) et, pour `activate`, un code EAN-13 valide.
  >
  > → **TODO SYN.2** — effort S (4 actions + 3 templates à corriger)
  >
  > ---
  >
  > ### 6. `helloasso_manual_paiement_add` (POST) — sans CSRF, admin uniquement (🟡)
  >
  > `HelloassoController::helloassoManualPaimentAddAction` (ligne 120) : `$request->get("formType")` et `$request->get("slug")` sont lus sans formulaire Symfony. Le vrai traitement (`getPayment($paymentId)`) est récupéré côté API HelloAsso (le `paymentId` vient de l'URL, pas du body), donc le risque concret est limité à forcer l'enregistrement d'un paiement existant dans la base.
  >
  > Accès : `@Security("is_granted('ROLE_FINANCE_MANAGER')")` — exposition réduite aux gestionnaires financiers.
  >
  > Template `admin/helloasso/browser.html.twig` (ligne 78) : formulaire HTML brut, pas de token CSRF.
  >
  > → **TODO SYN.2** — effort XS
  >
  > ---
  >
  > ### 7. Session `cookie_samesite` non configuré — défense en profondeur absente (🟡)
  >
  > `framework.yaml` session :
  > ```yaml
  > session:
  >     handler_id: session.handler.native_file
  >     name: USERSSID
  >     cookie_domain: "%env(ROUTER_REQUEST_CONTEXT_HOST)%"
  > ```
  > Absence de `cookie_samesite: lax` (ou `strict`).
  >
  > En 2024+, les navigateurs Chromium appliquent `SameSite=Lax` par défaut aux cookies sans attribut explicite — ce qui atténue les CSRF classiques sur les navigateurs modernes. Mais :
  > - Pas de garantie sur les navigateurs anciens (terminaux card-reader).
  > - `Lax` autorise les navigations top-level GET mais ne couvre pas les POSTs cross-site déclenchés sans navigation.
  > - L'absence d'un attribut explicite signifie que le comportement dépend du navigateur, pas de la configuration serveur.
  >
  > **Fix** : ajouter `cookie_samesite: lax` (ou `strict`) dans `framework.yaml`. Complément, pas substitut, aux tokens CSRF.
  >
  > → **TODO SYN.2** — effort XS (une ligne de config)
  >
  > ---
  >
  > ### 8. `process_update_count_unread` (POST) — read-only, devrait être GET (🔵 Info)
  >
  > `ProcessUpdateController::countUnreadAction` — route POST qui ne fait que lire un compteur (SELECT). Pas d'écriture en base, donc pas de risque CSRF proprement dit. Mais sémantiquement incohérent : une opération idempotente de lecture ne devrait pas utiliser POST.
  >
  > Le AJAX dans `layout.html.twig` envoie `date` en POST alors qu'un GET avec query param serait plus correct et plus cacheable.
  >
  > → **TODO SYN.2** — effort XS (changer en GET + query param)
  >
  > ---
  >
  > ### Résumé SEC.3
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🔴 Critique | `set_email` POST — CSRF exploitable → account takeover | XS |
  > | 🟠 Important | SwipeCard (activate/enable/disable/delete) — 4 routes sans CSRF, manipulation badges | S |
  > | 🟡 Mineur | `card_reader_check` — formulaire JS sans CSRF (atténué : voter + code EAN13) | XS |
  > | 🟡 Mineur | `helloasso_manual_paiement_add` — sans CSRF (atténué : ROLE_FINANCE_MANAGER) | XS |
  > | 🟡 Mineur | `shift_book` — JSON sans CSRF (atténué : CORS preflight ; formulaire template broken) | XS |
  > | 🟡 Mineur | Session sans `cookie_samesite` — défense en profondeur manquante | XS |
  > | 🔵 Info | `process_update_count_unread` POST read-only → devrait être GET | XS |
  > | ✅ OK | ~30+ routes avec état — protégées via Symfony Form CSRF automatique | — |
  >
  > **Cross-références** :
  > - Finding 4 (`set_email`) croise SEC.2 finding 1 (même action, auth insuffisante)
  > - Finding 3 (`card_reader_check`) croise SEC.2 finding 2 (même action, auth insuffisante) et SEC.1 finding 7 (codes prédictibles)
  > - Finding 2 (`shift_book`) : le formulaire broken dans `shift_alone.html.twig` mérite un TODO séparé (bug fonctionnel, pas seulement sécurité)

- [x] **SEC.4** — Requêtes non paramétrées
  > `grep -rn "\"SELECT\|'SELECT\|createNativeQuery" src/Repository/`. Concaténation de variables dans du SQL → TODO critique.
  >
  > **Périmètre analysé** : tous les fichiers `src/` (Repository, Controller, Service, Command, EventListener, DataFixtures, Migrations) — pas uniquement `src/Repository/`.
  >
  > **Résultat global** : aucune injection SQL exploitable détectée. La codebase utilise massivement le QueryBuilder Doctrine (paramétré par construction). 3 usages non idiomatiques de `expr()->literal()` identifiés ; 2 requêtes natives avec concaténation de constante.
  >
  > ---
  >
  > ### 1. `expr()->literal()` avec input utilisateur — 3 occurrences (🟡)
  >
  > `$qb->expr()->literal()` inline la valeur échappée directement dans la chaîne DQL au lieu d'utiliser un paramètre lié (`setParameter()`). Doctrine appelle `PDO::quote()` en interne, ce qui fournit un échappement correct pour la version actuelle — **pas d'injection exploitable**. Mais ce pattern :
  > - bypass le mécanisme de prepared statement (protection liée à l'implémentation du driver, pas au protocole)
  > - est fragile face aux changements de charset ou de driver
  > - est explicitement déconseillé par la documentation Doctrine au profit de `setParameter()`
  >
  > | Fichier | Ligne | Source de la valeur | Input HTTP ? |
  > |---------|-------|---------------------|--------------|
  > | `src/Repository/BeneficiaryRepository.php` | 92 | `$firstname` passé par `BeneficiaryController:239` depuis `$form->get('firstname')->getData()` | **Oui** (POST form) |
  > | `src/Controller/EventController.php` | 304 | `$firstname` depuis `$search_form->get('firstname')->getData()` | **Oui** (POST form) |
  > | `src/EventListener/BeneficiaryInitializationSubscriber.php` | 72 | `$username` dérivé de `User::makeUsername($firstname, $lastname)` | Non (donnée interne Doctrine) |
  >
  > Dans les deux premiers cas, la valeur vient d'un `TextType` Symfony validé (`isSubmitted() && isValid()`), ce qui n'empêche pas la valeur de contenir des caractères SQL. L'échappement de `PDO::quote()` les neutralise, mais le pattern reste fragile.
  >
  > **Fix recommandé** (exemple pour `BeneficiaryRepository.php:92`) :
  > ```php
  > // Avant (non idiomatique) :
  > ->where($qb->expr()->like('b.firstname', $qb->expr()->literal('%' . $firstname . '%')))
  >
  > // Après (paramétré) :
  > ->where($qb->expr()->like('b.firstname', ':firstname'))
  > ->setParameter('firstname', '%' . $firstname . '%')
  > ```
  >
  > → **TODO SYN.2** — effort XS × 3 (remplacer `expr()->literal()` par `setParameter()` dans les 3 occurrences)
  >
  > ---
  >
  > ### 2. SQL natif avec `$table_name` concaténée — `RegistrationsController` (🔵 Info)
  >
  > `RegistrationsController` (lignes 119–130 et 144–153) construit deux requêtes DBAL avec `$connection->prepare()` en concaténant `$table_name` :
  >
  > ```php
  > $table_name = $em->getClassMetadata('App:AbstractRegistration')->getTableName();
  > $statement = $connection->prepare("SELECT ... FROM " . $table_name . " WHERE date >= :from ...");
  > $statement->bindValue('from', $from->format('Y-m-d'));
  > ```
  >
  > - `$table_name` vient de `getClassMetadata()` — **valeur statique de métadonnées Doctrine**, non contrôlable par l'utilisateur. **Pas d'injection.**
  > - Les paramètres utilisateur (`$from`, `$to`) sont liés correctement via `bindValue()`.
  > - Le recours au SQL natif (DBAL brut) à la place du DQL est dû à l'usage de `IF()` et `date_format()` MySQL spécifiques — justification valide.
  >
  > → **Pas de TODO sécurité.** Annotation maintenance possible si la codebase migre vers une base non-MySQL.
  >
  > ---
  >
  > ### 3. Autres cas examinés — OK
  >
  > | Fichier | Ligne | Constat |
  > |---------|-------|---------|
  > | `DataFixtures/Purger/CustomPurger.php` | 36 | `sprintf('TRUNCATE TABLE %s', $tableName)` — table name issu du schéma DB (non user input) ; code fixtures uniquement |
  > | `Migrations/Version20190218130524_job_id_not_null.php` | 26 | `exec('INSERT INTO job ...')` — valeurs littérales hardcodées |
  > | `Command/FixShiftMissingPositionCommand.php` | 108 | `createQuery("UPDATE ... WHERE s.id in (:ids)")` — DQL paramétré avec `:ids` |
  > | Tous les autres Repository | — | QueryBuilder avec `setParameter()` / `bindValue()` — safe |
  >
  > ---
  >
  > ### Résumé SEC.4
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🟡 Mineur | 3 × `expr()->literal()` avec input utilisateur — non idiomatique, échappement présent mais fragile | XS × 3 |
  > | 🔵 Info | SQL natif dans `RegistrationsController` — `$table_name` Doctrine (non user-input), params bindés | — |
  > | ✅ OK | Ensemble du codebase — QueryBuilder paramétré, aucune concaténation user-input dans SQL | — |

- [x] **SEC.5** — Upload fichiers
  > Config VichUploader : validation MIME, extension, taille max ? → TODO si manquant.
  >
  > **Périmètre analysé** : deux entités avec upload (`Service::$logoFile`, `Event::$imgFile`), les `Form/ServiceType` et `Form/EventType`, `AdminController::csvImportAction()`, `config/packages/vich_uploader.yaml`, `web/.htaccess`, `php.ini` container.
  >
  > ---
  >
  > **F1 — 🔴 CRITIQUE : Aucune validation MIME ni extension sur les uploads d'images**
  >
  > `src/Entity/Service.php:77` et `src/Entity/Event.php:76` — les champs `@Vich\UploadableField` n'ont aucune annotation `@Assert\File` ou `@Assert\Image`. Les `Form/ServiceType.php:49` et `Form/EventType.php:84` n'ajoutent pas de contraintes (`constraints` option absente sur `VichImageType`). N'importe quel type de fichier est accepté à l'upload.
  >
  > **Fix recommandé** : ajouter sur les deux propriétés :
  > ```php
  > * @Assert\Image(
  > *     mimeTypes={"image/jpeg","image/png","image/gif","image/webp"},
  > *     maxSize="2M",
  > *     maxSizeMessage="L'image ne doit pas dépasser 2 Mo."
  > * )
  > ```
  >
  > ---
  >
  > **F2 — 🔴 CRITIQUE : Fichiers stockés dans le document root sans protection d'exécution**
  >
  > `config/packages/vich_uploader.yaml:6,13` — destinations : `web/uploads/service/logo` et `web/uploads/event`, tous deux sous `web/` (document root). `web/.htaccess` ne contient aucune règle bloquant l'exécution de scripts dans `/uploads/`. Un administrateur compromis (ou une erreur de validation) peut uploader un fichier `webshell.php` accessible et exécutable via `/uploads/service/logo/webshell.php`.
  >
  > Facteur atténuant : seuls les rôles `ROLE_ADMIN`/`ROLE_SUPER_ADMIN` ont accès aux routes d'upload de `ServiceController` et `EventController`. Le risque réel passe par la compromission d'un compte admin.
  >
  > **Fix recommandé** : créer `web/uploads/.htaccess` (Apache) :
  > ```apache
  > # Prevent script execution in upload directory
  > <FilesMatch "\.php$">
  >     Require all denied
  > </FilesMatch>
  > Options -Indexes
  > ```
  > Pour Nginx : ajouter dans le bloc `location /uploads/` → `deny all;` pour toute extension exécutable.
  >
  > ---
  >
  > **F3 — 🟡 MOYEN : `namer_origname` conserve l'extension d'origine**
  >
  > `config/packages/vich_uploader.yaml:10,17` — `namer: vich_uploader.namer_origname` préserve le nom de fichier original (extension comprise). En l'absence de validation MIME/extension (F1), un fichier nommé `webshell.php` sera stocké sous ce nom exact, amplifiant F2.
  >
  > **Fix recommandé** : remplacer par `vich_uploader.namer_hash` (hash + extension d'origine), ou `vich_uploader.namer_uniqid`. Stoppe la prédictibilité du chemin et réduit la surface même si l'extension reste.
  >
  > ---
  >
  > **F4 — 🟡 MOYEN : Import CSV sans validation MIME**
  >
  > `src/Controller/AdminController.php:260` — champ `submitFile` de type `FileType::class` sans contrainte (`mimeTypes`, `extensions`, `maxSize` absents). Le fichier est transmis directement au kernel Symfony via `$file->getData()->getPathName()` (chemin temporaire PHP, pas de traversal possible). Le traitement est délégué à la commande `app:import:users` qui parse le contenu ligne par ligne : risque de CSV injection si les données sont ré-exportées vers Excel sans sanitization, et pas de garde-fou si un non-CSV est envoyé.
  >
  > Facteur atténuant : route protégée par `@Security("is_granted('ROLE_SUPER_ADMIN')")`.
  >
  > **Fix recommandé** : ajouter au champ `submitFile` :
  > ```php
  > 'constraints' => [new Assert\File(['mimeTypes' => ['text/csv', 'text/plain', 'application/csv']])]
  > ```
  >
  > ---
  >
  > **F5 — 🔵 INFO : `.docker/php.ini` non chargé — limites PHP par défaut actives**
  >
  > `.docker/php.ini` (non utilisé) contient `upload_max_filesize=1024M` et `post_max_size=1024M`. Le Dockerfile copie `php.ini` (racine, `memory_limit = 512M` seulement) dans le container. Valeurs actives à runtime : `upload_max_filesize=2M`, `post_max_size=8M` (défauts PHP). Le `.docker/php.ini` est un artefact trompeur — à documenter ou supprimer.
  >
  > ---
  >
  > **Tableau récapitulatif SEC.5**
  >
  > | Sévérité | Finding | Fichier |
  > |---|---|---|
  > | 🔴 Critique | Aucune validation MIME/extension sur `$logoFile` et `$imgFile` | `Service.php:77`, `Event.php:76`, `ServiceType.php:49`, `EventType.php:84` |
  > | 🔴 Critique | Uploads dans document root sans protection d'exécution | `vich_uploader.yaml:6,13`, `web/.htaccess` |
  > | 🟡 Moyen | `namer_origname` conserve l'extension d'origine | `vich_uploader.yaml:10,17` |
  > | 🟡 Moyen | Import CSV sans validation MIME | `AdminController.php:260` |
  > | 🔵 Info | `.docker/php.ini` 1 GB non chargé — artefact trompeur | `.docker/php.ini` |

- [x] **SEC.6** — Twig `|raw`
  > `grep -rn "|raw" templates/`. Inventaire et justification pour chaque usage.
  >
  > **Périmètre analysé** : deux occurrences trouvées — `templates/form/fields.html.twig:142` et `templates/layout.html.twig:53`. Inventaire complet des origines des données injectées : `src/Form/MarkdownEditorType.php`, tous les `addFlash()` du codebase (UserController, AdminPeriodController, TimeLogController, et autres), `src/Command/ShiftGenerateCommand.php`.
  >
  > ---
  >
  > **Occurrence A — `templates/form/fields.html.twig:142` : `{{ form.vars.editor_config|raw }}`**
  >
  > **F1 — 🔵 INFO : usage justifié avec opportunité de durcissement**
  >
  > `src/Form/MarkdownEditorType.php:80` — `$view->vars['editor_config'] = json_encode($editor_config);`
  >
  > La valeur est construite entièrement à partir d'options de type Symfony déclarées en PHP (développeur-contrôlé, pas input utilisateur) : `hideIcons`, `placeholder`, `showIcons`, `tabSize`, `spellChecker`, `forceSync`, etc. Le `|raw` est correct ici : du JSON injecté dans un bloc `<script>` ne doit pas subir l'échappement HTML (les `"` deviendraient `&quot;`, cassant le JSON).
  >
  > Opportunité de durcissement : `json_encode()` sans flags ne transforme pas `<`, `>` et `&` en séquences Unicode. Si un développeur ajoute un jour une option `placeholder` contenant `</script>`, le script block serait cassé. Ajouter les flags `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS` serait une défense en profondeur.
  >
  > **Fix recommandé (optionnel)** :
  > ```php
  > // MarkdownEditorType.php:80
  > $view->vars['editor_config'] = json_encode($editor_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
  > ```
  >
  > ---
  >
  > **Occurrence B — `templates/layout.html.twig:53` : `{{ message|trans({}, 'FOSUserBundle')|raw }}`**
  >
  > Ce `|raw` s'applique à **tous** les flash messages de l'application (type `success`, `error`, `warning`) via `app.session.flashBag.all`. Le filtre `|trans` est utilisé en premier pour traduire les clés FOSUserBundle — si aucune traduction ne correspond (cas de tous les messages dynamiques buildés par concaténation), la chaîne est retournée telle quelle, puis rendue sans échappement.
  >
  > Raison probable de ce `|raw` : des messages FOSUserBundle traduits contiendraient du HTML (liens, balises `<strong>`, etc.). Effet de bord : l'intégralité des flash messages applicatifs — dont certains contiennent des données dynamiques — est rendue comme HTML brut.
  >
  > ---
  >
  > **F2 — 🟡 MOYEN : Stored XSS potentiel via noms d'entités dans les flash messages**
  >
  > Plusieurs flash messages concatènent le résultat de `__toString()` d'entités dont les noms sont saisis par des administrateurs :
  >
  > - `AdminPeriodController.php:288` — `$position->getFormation()->getName()` : nom de formation (admin-saisi)
  > - `AdminPeriodController.php:302` — `$position->getShifter()` → `Beneficiary::__toString()` → `getDisplayNameWithMemberNumber()` : nom de bénéficiaire
  > - `AdminPeriodController.php:119,157,359` — `$period->__toString()` → `Job::getName() . ' - ' . ...` : nom de poste
  > - `UserController.php:196,209,229,242` — `$user->__toString()` : username ou prénom/nom du bénéficiaire
  >
  > Si un administrateur enregistre un nom d'entité contenant du HTML (ex. `<img src=x onerror=alert(1)>`), cette charge est stockée en base, puis réinjectée telle quelle dans un flash message rendu avec `|raw`. Toute action déclenchant ces messages expose ensuite les admins qui la réalisent.
  >
  > Facteur atténuant : seuls les `ROLE_ADMIN` et `ROLE_SUPER_ADMIN` peuvent créer ces entités et déclencher ces actions. Dans le contexte d'une coopérative, les admins sont des membres de confiance.
  >
  > **Fix recommandé** : supprimer `|raw` dans `layout.html.twig:53` (voir F5). Si des messages HTML traduits sont nécessaires, les traiter séparément (voir F5).
  >
  > ---
  >
  > **F3 — 🟡 MOYEN : Markup console Symfony injecté comme HTML brut**
  >
  > `AdminPeriodController.php:455-457` :
  > ```php
  > $content = $output->fetch();
  > $this->addFlash('success', $content);
  > ```
  >
  > L'output de la commande `app:shift:generate` (voir `ShiftGenerateCommand.php`) utilise la syntaxe de décoration Symfony Console : `<fg=yellow;>`, `<fg=cyan;>`, `<fg=red;>`, `</>`. Ces balises sont injectées telles quelles dans le flash message, qui est ensuite rendu avec `|raw`. Résultat : le markup console apparaît tel quel dans le HTML de la page (`<fg=cyan;>`, `</>` comme élément HTML vide anonyme, etc.).
  >
  > Ce n'est pas une vulnérabilité exploitable (les balises console ne forment pas du HTML valide permettant l'injection de scripts), mais c'est un **bug d'affichage** confirmé : les messages de succès de la génération de créneaux affichent du bruit markup en production.
  >
  > **Fix recommandé** : utiliser `strip_tags()` ou un `OutputFormatter::stripDecoration()` sur `$content` avant de le passer à `addFlash`, ou utiliser un `NullOutput` et composer un message de résumé propre.
  >
  > ---
  >
  > **F4 — 🔵 INFO : Artefact de debug — `<>` littéral dans un flash message**
  >
  > `src/Controller/TimeLogController.php:85` :
  > ```php
  > $this->addFlash('error', $timeLog->getMembership() . '<>' . $member);
  > ```
  >
  > Le séparateur `<>` est du HTML brut intentionnellement écrit comme séparateur visuel lors d'un développement ou debug. Rendu avec `|raw`, `<>` forme un élément HTML vide anonyme (ignoré par les navigateurs mais symptomatique). Ce message d'erreur apparaît quand un `TimeLog` appartient à une `Membership` différente du `$member` courant — cas normalement défensif, mais la présence de `<>` confirme qu'il s'agit d'un artefact de développement non nettoyé.
  >
  > **Fix recommandé** : remplacer `<>` par un séparateur texte (ex. `' ≠ '` ou `' / '`).
  >
  > ---
  >
  > **F5 — 🟡 MOYEN : `|raw` global sur tous les flash messages — cause racine**
  >
  > `templates/layout.html.twig:53` — le `|raw` est appliqué inconditionnellement à tous les flash messages, quelle que soit leur origine. La cause probable est la présence de HTML dans les traductions FOSUserBundle (liens, mise en forme). Ce pattern rend toute la surface des flash messages vulnérable à l'injection HTML si du contenu dynamique y est intégré.
  >
  > **Fix recommandé** : supprimer `|raw` sur le chemin par défaut et utiliser un type de flash dédié pour les messages qui nécessitent du HTML :
  > ```twig
  > {# layout.html.twig #}
  > {% for type, messages in app.session.flashBag.all %}
  >     {% for message in messages %}
  >         {# 'flash_html' = type réservé aux messages avec HTML intentionnel #}
  >         {% if type == 'flash_html' %}
  >             <span class="white-text">{{ message|raw }}</span>
  >         {% else %}
  >             <span class="white-text">{{ message|trans({}, 'FOSUserBundle') }}</span>
  >         {% endif %}
  >     {% endfor %}
  > {% endfor %}
  > ```
  > Cette approche supprime le `|raw` sur tous les messages ordinaires (qui bénéficient alors de l'échappement automatique de Twig) et réserve le rendu HTML brut aux messages explicitement marqués `flash_html`.
  >
  > ---
  >
  > **Tableau récapitulatif SEC.6**
  >
  > | Sévérité | Finding | Fichier |
  > |---|---|---|
  > | 🔵 Info | `editor_config\|raw` justifié ; `JSON_HEX_TAG` manquant (defense-in-depth) | `fields.html.twig:142`, `MarkdownEditorType.php:80` |
  > | 🟡 Moyen | Stored XSS potentiel via noms d'entités (admin-saisis) dans flash messages | `layout.html.twig:53`, `UserController.php:196,209,229,242`, `AdminPeriodController.php:288,302,119,157,359` |
  > | 🟡 Moyen | Markup console Symfony (`<fg=…>`, `</>`) injecté brut dans la page | `AdminPeriodController.php:457`, `ShiftGenerateCommand.php` |
  > | 🟡 Moyen | `\|raw` global sur tous les flash messages — cause racine (F2, F3) | `layout.html.twig:53` |
  > | 🔵 Info | Artefact debug : `<>` littéral dans flash message d'erreur | `TimeLogController.php:85` |

- [x] **SEC.7** — Secrets hardcodés
  > `grep -rn "password\s*[=:]\s*['\"][^$'\"]" src/ config/` (hors .env.example et commentaires) → TODO critique si trouvé.
  >
  > **Périmètre** : `src/` (PHP), `config/` (YAML), fichiers `.env*` committé (`.env.dist`, `.env.test`, `.env.oidc.test`, `.envrc`). Scan gitleaks (historique git + working tree). Recherche manuelle de patterns `password`, `secret`, `api_key`, `token`, `private_key`, PEM.
  >
  > ---
  >
  > ### F1 — `APP_SECRET` réel dans l'historique git public 🟡
  >
  > **Localisation** : commit `a408661e` (2020-03-29, "Symfony 4 migration"), fichier `.env.dist`, ligne 21.
  > **Valeur commitée** : `APP_SECRET=4814f742d29ec73fd902ad2a0d360b76` (hex 32 chars, entropie élevée).
  > **Correctif existant** : commit `c30e1f36` (2023-12-20) remplace la valeur par le placeholder `ThisTokenIsNotSoSecretChangeIt`. L'état actuel (HEAD) est sain.
  > **Risque résiduel** : le secret reste accessible dans l'historique git public (`git show a408661e:.env.dist`). Toute instance qui a cloné entre 2020 et 2023 sans régénérer `APP_SECRET` utilise cette valeur publique. L'`APP_SECRET` Symfony sert à signer les CSRF tokens, les URLs signées, les cookies "remember-me" et les sessions.
  > **Recommandation TODO** : documenter dans le README/guide d'installation que `APP_SECRET` doit être régénéré à chaque déploiement (`openssl rand -hex 32`). Alerter les instances existantes (Elefan, Scopeli) de vérifier leur valeur deployée. L'historique ne peut pas être réécrit sans coordination.
  >
  > ### F2 — Credentials de test faibles dans les fichiers CI committé 🔵
  >
  > **Fichiers** : `.env.test`, `.env.oidc.test` (tous deux committé, utilisés par GitHub Actions).
  > **Valeurs concernées** :
  > - `DATABASE_URL="mysql://root:secret@..."` — mot de passe DB `secret`
  > - `SUPER_ADMIN_INITIAL_PASSWORD=password` — mot de passe super-admin `password`
  > - `APP_SECRET='$ecretf0rt3st'` — placeholder lisible, non ambigu
  >
  > **Contexte** : ces credentials sont intentionnellement faibles pour l'environnement CI (Docker isolé, réseau interne). Ils ne représentent pas de risque direct.
  > **Risque résiduel** : si un développeur copie un fichier `.env.test` en base pour un déploiement staging accessible réseau, les credentials triviaux ouvrent un vecteur d'accès.
  > **Recommandation TODO** : ajouter un commentaire en tête des fichiers `# CI ONLY — do not use in staging/prod`.
  >
  > ### F3 — `.env.dist` : `SUPER_ADMIN_INITIAL_PASSWORD=password` dans le template 🟡
  >
  > **Localisation** : `.env.dist` (template HEAD), valeur `SUPER_ADMIN_INITIAL_PASSWORD=password`.
  > **Risque** : un développeur qui copie `.env.dist` → `.env` sans changer ce paramètre expose le compte super-admin avec le mot de passe `password` dès que l'app est joignable sur le réseau. Le mot de passe est défini par `UserAdmin::setInitialSuperAdmin()` qui l'utilise à l'initialisation.
  > **Recommandation TODO** : remplacer la valeur template par `<change-me>` ou `SUPER_ADMIN_INITIAL_PASSWORD=changeme_immediately` avec un commentaire explicite. Ajouter une validation au setup qui refuse `password` ou `changeme` en `APP_ENV=prod`.
  >
  > ### F4 — Absence de `.gitleaks.toml` (faux positifs CI) 🔵
  >
  > **Constat** : `gitleaks detect --no-git` remonte 1 482 faux positifs, tous dans `var/phpstan-dead-code/cache/` (clés internes PHPStan de type `variableKey => v2-<hex>-7.4`). Aucun vrai secret dans ce répertoire.
  > **Impact** : un job CI qui lancerait `gitleaks detect --no-git` (working tree) échouerait sur ces faux positifs. Le scan historique (`gitleaks detect`, sans `--no-git`) ne fait remonter que F1.
  > **Recommandation TODO** : créer `.gitleaks.toml` avec une règle d'exclusion de `var/` (non committé, mais utile en CI). Exemple :
  > ```toml
  > [allowlist]
  > paths = ["var/"]
  > ```
  >
  > ---
  >
  > ### Posture globale — SEC.7
  >
  > | Statut | Finding | Fichiers |
  > |--------|---------|---------|
  > | 🟡 Moyen | `APP_SECRET` hex réel dans l'historique git (2020–2023) | commit `a408661e`, `.env.dist` ligne 21 |
  > | 🟡 Moyen | `SUPER_ADMIN_INITIAL_PASSWORD=password` dans le template dev | `.env.dist` |
  > | 🔵 Info | Credentials faibles dans les fichiers CI (intentionnel, isolé) | `.env.test`, `.env.oidc.test` |
  > | 🔵 Info | Absence de `.gitleaks.toml` → faux positifs CI | — |
  > | ✅ OK | Aucun secret hardcodé dans `src/` (PHP) | — |
  > | ✅ OK | Aucun secret hardcodé dans `config/` (YAML) | — |
  > | ✅ OK | `.env` (live credentials) gitignored | `.gitignore:56` |
  > | ✅ OK | Template HEAD utilise des placeholders clairs | `.env.dist` HEAD |
  > | ✅ OK | Tous les services utilisent `%env(...)%` | `config/services.yaml` |
  >
  > → `/model sonnet` peut être repris dès TC.1.

---

## TC — Couverture de tests (analyse uniquement)

- [x] **TC.1** — Rapport de couverture
  > `docker compose exec -T php composer test-coverage 2>&1`. % global et par namespace. Résultat → TODO (zones non couvertes).
  >
  > **Méthode** : `composer test-coverage` génère uniquement du HTML (via xdebug) ; or xdebug **n'est pas installé** dans le container PHP 8.1 actuel (`.docker/Dockerfile`). Couverture obtenue via `pcov` installé temporairement : `php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text`. 350 tests verts, aucune régression.
  >
  > ### Résultat global
  >
  > | Métrique | Couvert | Total | % |
  > |----------|---------|-------|---|
  > | Méthodes | 621 | 1 783 | **34.8%** |
  > | Lignes   | 3 405 | 12 474 | **27.3%** |
  >
  > ### Couverture par namespace
  >
  > | Namespace | Méthodes | Lignes |
  > |-----------|----------|--------|
  > | `App\Command` | 54.8% (46/84) | 28.3% (369/1 305) |
  > | `App\Controller` | 13.7% (43/313) | 18.4% (918/4 992) |
  > | `App\Entity` | 48.1% (377/784) | 46.3% (680/1 470) |
  > | `App\Event` | 2.7% (2/74) | 2.0% (2/99) |
  > | `App\EventListener` | 10.0% (7/70) | 6.1% (48/787) |
  > | `App\Form` | 18.4% (26/141) | 17.6% (172/980) |
  > | `App\Helper` | 10.0% (1/10) | 2.8% (1/36) |
  > | `App\Monolog` | 60.0% (3/5) | 88.9% (16/18) |
  > | `App\Providers` | 5.9% (1/17) | 1.6% (2/125) |
  > | `App\Repository` | 27.5% (19/69) | 41.7% (303/727) |
  > | `App\Security` | 27.0% (17/63) | 20.1% (115/573) |
  > | `App\Service` | 52.9% (55/104) | 56.7% (656/1 156) |
  > | `App\Twig` | 52.3% (23/44) | 65.7% (115/175) |
  > | `App\Validator` | 20.0% (1/5) | 25.8% (8/31) |
  >
  > ### Zones non couvertes — priorité haute (0% lignes, classe substantielle)
  >
  > | Classe | Lignes | Priorité |
  > |--------|--------|----------|
  > | `App\EventListener\EmailingEventListener` | 410 | 🔴 Critique |
  > | `App\Controller\ShiftController` *(4.8%)* | 439 | 🔴 Critique |
  > | `App\Controller\AmbassadorController` | 185 | 🔴 |
  > | `App\EventListener\TimeLogEventListener` | 146 | 🔴 |
  > | `App\Controller\UserController` | 159 | 🔴 |
  > | `App\Controller\SwipeCardController` | 154 | 🔴 |
  > | `App\Security\KeycloakAuthenticator` *(2.7%)* | 221 | 🔴 |
  > | `App\Controller\MailController` | 117 | 🟠 |
  > | `App\Controller\HelloassoController` | 116 | 🟠 |
  > | `App\Controller\BeneficiaryController` | 115 | 🟠 |
  > | `App\Controller\AdminMembershipShiftExemptionController` | 113 | 🟠 |
  > | `App\EventListener\HelloassoEventListener` | 65 | 🟠 |
  > | `App\Repository\MembershipRepository` | 67 | 🟠 |
  > | `App\Providers\Helloasso\HelloassoClient` | 51 | 🟠 |
  > | `App\Providers\Igloohome\IgloohomeClient` | 26 | 🟡 |
  >
  > ### Observation sur la structure de couverture
  >
  > - `App\Event` à 2% : les classes Event sont des data-carriers (constructeur + getters) ; les 2% couverts sont les champs accédés par les tests indirects. Faible valeur à tester unitairement.
  > - `App\EventListener` à 6.1% : **vrai angle mort**. `EmailingEventListener` (410L) et `TimeLogEventListener` (146L) orchestrent l'envoi de mails et la gestion des logs de temps — critique pour l'intégrité des données.
  > - `App\Controller` à 18.4% : les tests fonctionnels existants couvrent peu de routes ; `ShiftController` (439L, 4.8%) et `BookingController` (366L, 13.7%) sont les classes les plus massives sans couverture significative.
  > - `App\Providers` à 1.6% : `HelloassoClient` et `IgloohomeClient` (intégrations tierces) ne sont pas testés. Justifiable par le besoin de mocks HTTP, mais risqué pour les montées de version.
  > - `App\Service` à 56.7% : le namespace le mieux couvert avec `App\Monolog`. `SearchUserFormHelper` (558L) est couvert à 48% via les tests d'intégration.
  >
  > ### Finding TC.1 — Résumé
  >
  > | Statut | Finding |
  > |--------|---------|
  > | 🔴 Critique | Couverture globale à 27% lignes sur 12 474 lignes de code source |
  > | 🔴 Critique | `EmailingEventListener` (410L) et `ShiftController` (439L) à <5% — chemins métier critiques sans filet |
  > | 🔴 Critique | `KeycloakAuthenticator` (221L) à 2.7% — authentification quasi non testée |
  > | 🟠 Majeur | `App\EventListener` (787L) à 6.1% — event-driven logic non testée |
  > | 🟠 Majeur | `App\Providers` (125L) à 1.6% — intégrations Helloasso et Igloohome sans test |
  > | 🟡 Moyen | xdebug absent du Dockerfile PHP 8.1 — `composer test-coverage` ne fonctionne pas en l'état |
  > | 🔵 Info | `App\Event` à 2% : normal pour des data-carriers, pas une priorité |

- [x] **TC.2** — Controllers sans test fonctionnel
  > Croiser `ls src/Controller/` avec `ls tests/Functional/`. Lister les controllers sans couverture → TODO.
  >
  > **Méthode** : inventaire `src/Controller/` (43 fichiers) croisé avec `tests/Functional/` (3 classes de test) et `debug:router` pour confirmer les routes couvertes par `SmokeTest.php`.
  >
  > ---
  >
  > ### Vue d'ensemble
  >
  > | Catégorie | Controllers | % |
  > |-----------|------------|---|
  > | Classe de test dédiée | 2 | 4.7 % |
  > | SmokeTest uniquement (HTTP status, aucune logique métier) | 28 | 65.1 % |
  > | Couverture nulle (ni smoke, ni dédié) | 13 | 30.2 % |
  > | **Total** | **43** | **100 %** |
  >
  > **Tests fonctionnels existants** :
  > - `AdminControllerTest.php` — couvre uniquement l'import CSV via commande Symfony (pas de route HTTP directe)
  > - `MembershipControllerTest.php` — couvre partiellement : `find_me`, `office_tools`, `emails_csv`, `member_show` (GET uniquement), restrictions de méthodes HTTP (405). Les actions avec mutation d'état (`freeze`, `withdraw`, `join`, `flying`) n'ont aucun test POST.
  > - `SmokeTest.php` — vérifie les codes de retour HTTP (200/302/403/405) pour les routes principales. Aucune assertion sur le contenu, le comportement métier, les formulaires ou les mutations d'état.
  >
  > ---
  >
  > ### Groupe A — Couverture nulle (0 % de lignes) — 13 controllers
  >
  > Confirmé par TC.1 pour les items marqués *(TC.1)* ; les autres sont vérifiés par l'absence dans tous les providers de test et `debug:router`.
  >
  > #### 🔴 Critique — findings sécurité actifs sans filet de test
  >
  > | Controller | Lignes | Findings | Routes clés |
  > |-----------|--------|---------|-------------|
  > | `BeneficiaryController` | 115+ | SEC.2.1 (account takeover via `setEmailAction` sans auth) *(TC.1)* | GET/POST `/beneficiary/*` |
  > | `SwipeCardController` | 154+ | SEC.1.13 (auth badge par GET), SEC.3.5 (4 routes sans CSRF) *(TC.1)* | GET/POST `/swipe_card/*`, `/sw/*` |
  > | `UserController` | 159+ | SEC.2.5 (bootstrap admin sans auth) *(TC.1)* | GET/POST `/user/install_admin`, `/user/*` |
  > | `ShiftController` | 439+ | SEC.1.3 (`contact_form` sans auth), SEC.1.5 (`accept`/`reject` voter seul) — 4.8 % *(TC.1)* | POST `/shift/{id}/book`, `/shift/{id}/free`, `/shift/{id}/accept` |
  >
  > #### 🟠 Important — logique métier ou intégration tierce sans test
  >
  > | Controller | Lignes | Findings | Routes clés |
  > |-----------|--------|---------|-------------|
  > | `CommissionController` | ~80 | SEC.2.3 (fatal error anonyme + `$_POST` direct) | POST `/commissions/{id}/add_beneficiary`, `remove_beneficiary` |
  > | `AmbassadorController` | 185+ | 0 % *(TC.1)* | GET/POST `/ambassador/*` |
  > | `MailController` | 117+ | 0 % *(TC.1)* | GET/POST `/admin/mail/*` |
  > | `HelloassoController` | 116+ | 0 % *(TC.1)*, paiement Helloasso | GET/POST `/admin/helloasso/*`, `/helloassoNotify` |
  > | `TimeLogController` | ~60 | SEC.6 (flash `<>` debug artifact) | GET/POST `/time_log/*` |
  > | `NoteController` | ~60 | — | GET/POST/DELETE `/note/note/*` |
  > | `AdminMembershipShiftExemptionController` | 113+ | 0 % *(TC.1)* | GET/POST `/admin/membershipshiftexemption/*` |
  > | `AdminPeriodPositionFreeLogController` | ~60 | AP.6 (null guard manquant dans `PeriodPositionFreeLogService`) | GET/POST `/admin/period/positionfreelogs/` |
  >
  > #### 🟡 Mineur
  >
  > | Controller | Lignes | Note |
  > |-----------|--------|------|
  > | `ApiController` | ~80 | Endpoints OAuth API (`/api/oauth/user`, `/api/v4/user`), 0 % |
  > | `OAuthController` | ~40 | Flow OAuth OIDC (`/oauth/login`, `/oauth/callback`, `/oauth/logout`) — instance-specific (Scopeli), difficile à tester sans stub Keycloak |
  >
  > ---
  >
  > ### Groupe B — SmokeTest uniquement — 28 controllers
  >
  > Ces controllers sont "verts" au sens HTTP mais n'ont aucun test de logique métier, de formulaire, ou de mutation d'état.
  >
  > **Sous-groupe prioritaire (logique métier ou findings actifs) :**
  >
  > | Controller | Couverture TC.1 | Priorité test |
  > |-----------|----------------|--------------|
  > | `BookingController` | 13.7 % | 🟠 — logique de réservation complexe, action `showBucketAction` publique (SEC.1.12) |
  > | `RegistrationsController` | smoke | 🟠 — SQL brut avec `$connection->prepare()` (AP.3.1) |
  > | `CardReaderController` | smoke | 🟠 — POST `/card_reader/check` (SEC.2.2) couvert en smoke GET uniquement |
  > | `CommissionController` | smoke | 🟠 — fatal error anonyme (SEC.2.3) non testée par le smoke |
  > | `CodeController` | ~0 % | 🟡 — 1 seul test (redirect), logique OIDC non testée |
  > | `AdminPeriodController` | smoke | 🟡 — génération de créneaux (AP.1.2f, AP.8.3), seulement GET `/admin/period/` en smoke |
  > | `EventController` | smoke | 🟡 — recherche bénéficiaire avec `expr()->literal()` (SEC.4.1) |
  >
  > **Sous-groupe CRUD admin (faible priorité — logique simple, accès restreint `ROLE_ADMIN`) :**
  > `AdminClosingExceptionController`, `AdminEventController`, `AdminEventKindController`, `AdminOpeningHourController`, `AdminOpeningHourKindController`, `AdminShiftExemptionController`, `AdminShiftFreeLogController`, `ClientController`, `FormationController`, `JobController`, `SocialNetworkController`, `ServiceController`, `DynamicContentController`, `EmailTemplateController`, `TaskController`, `PeriodController`, `ProcessUpdateController`, `OpeningHourController`, `ClosingExceptionController`, `WidgetController`, `DefaultController`
  >
  > ---
  >
  > ### Résumé et priorisation
  >
  > | Priorité | Action | Controllers | Effort |
  > |----------|--------|------------|--------|
  > | 🔴 Immédiat | Tests fonctionnels couvrant les failles SEC actives | `BeneficiaryController`, `SwipeCardController`, `UserController` | M |
  > | 🔴 Immédiat | Tests POST pour `ShiftController` (book, free, contact_form) | `ShiftController` | M |
  > | 🟠 Court terme | Tests d'autorisation pour les actions publiques identifiées | `CommissionController`, `CardReaderController` (POST check), `HelloassoController` | S–M |
  > | 🟠 Court terme | Tests logique métier | `AmbassadorController`, `MailController`, `TimeLogController`, `NoteController` | S chacun |
  > | 🟡 Moyen terme | Tests POST pour MembershipController (actions avec mutation) | `MembershipController` (compléter) | S |
  > | 🟡 Moyen terme | Tests logique métier smoke-only prioritaires | `BookingController`, `RegistrationsController` | M chacun |
  > | 🟢 Backlog | CRUD admin simples | 21 controllers smoke-only CRUD | L global |
  >
  > → **TODO SYN.2** — catégorie Tests : prioriser dans l'ordre du tableau ci-dessus. Les 🔴 sont des tests à écrire avant tout correctif des failles SEC correspondantes.

- [x] **TC.3** — Services sans test unitaire
  > Croiser `src/Service/` avec `tests/Unit/`. Lister les gaps → TODO.
  >
  > **Méthode** : inventaire exhaustif de `src/Service/` (15 fichiers) croisé avec `tests/Unit/Service/` (4 fichiers) et `tests/Integration/Service/` (1 fichier). Lecture du contenu de chaque service non couvert pour évaluer testabilité et valeur métier.
  >
  > ---
  >
  > ### Vue d'ensemble
  >
  > | Catégorie | Services | % |
  > |-----------|----------|---|
  > | Tests unitaires existants | 5 | 33 % |
  > | Sans aucun test unitaire | 10 | 67 % |
  > | **Total** | **15** | **100 %** |
  >
  > **Services couverts** :
  > - `BeneficiaryService` → `tests/Unit/Service/BeneficiaryServiceTest.php`
  > - `MembershipService` → `tests/Unit/Service/MembershipServiceTest.php`
  > - `PeriodService` → `tests/Unit/Service/PeriodServiceTest.php`
  > - `ShiftService` → `tests/Unit/Service/ShiftServiceUnitTest.php` + `tests/Integration/Service/ShiftServiceTest.php`
  > - `TimeLogService` → `tests/Unit/Service/TimeLogServiceTest.php`
  >
  > ---
  >
  > ### Groupe A — Testables, valeur métier haute (🔴 priorité)
  >
  > #### `MailerService` (144L) — logique pure extractible
  >
  > | Méthode | Testabilité | Valeur |
  > |---------|------------|--------|
  > | `isTemporaryEmail(string $email): bool` | **Pure logic**, aucun mock requis. Regex sur `$baseDomain`. | Critique — détermine si un email est temporaire ; bug silencieux non détectable sans test |
  > | `getAllowedEmails(): array` | **Pure transformation** du tableau de config `$sendableEmails`. Aucun mock requis. | Moyen — format d'affichage dans le select "from" des mails |
  > | `sendConfirmationEmailMessage()` / `sendResettingEmailMessage()` | Dépend de `$mailer`, `$router`, `$templating`, `$entity_manager`. Test d'intégration seulement. | — |
  >
  > → Tests unitaires faisables **sans aucun mock** pour `isTemporaryEmail` et `getAllowedEmails` : instancier `MailerService` avec les 3 paramètres scalaires + des dummies pour les dépendances lourdes inutilisées par ces deux méthodes.
  >
  > #### `ShiftFreeLogService` (50L) — pattern identique à `TimeLogService`
  >
  > | Méthode | Testabilité | Valeur |
  > |---------|------------|--------|
  > | `generateShiftString(Shift $shift): string` | Pure string : `$shift->getJob()->getName() . ' - ' . $shift->getDisplayDateSeperateTime()`. Mock `Shift` + `Job`. | Moyen — string stockée dans `ShiftFreeLog::shiftString`, visible dans les logs d'audit |
  > | `initShiftFreeLog(Shift, Beneficiary, bool, ?string): ShiftFreeLog` | Suit exactement le pattern `TimeLogService::initTimeLog()`. Mock `TokenStorage`, `RequestStack`. | Haut — construit le log d'audit de libération de créneau ; couvrir le chemin user auth vs anonyme |
  >
  > → Pattern déjà éprouvé dans `TimeLogServiceTest`. Effort d'écriture minimal.
  >
  > #### `PeriodPositionFreeLogService` (49L) — miroir de `ShiftFreeLogService`
  >
  > | Méthode | Testabilité | Valeur |
  > |---------|------------|--------|
  > | `generatePeriodPositionString(PeriodPosition): string` | Délègue à `(string) $periodPosition` (`__toString`). Mock `PeriodPosition`. | Faible — wrapper trivial |
  > | `initPeriodPositionFreeLog(PeriodPosition, Beneficiary, ?DateTime): PeriodPositionFreeLog` | Pattern identique à `initShiftFreeLog`. | Haut — log d'audit de libération de position |
  >
  > → Copier-adapter `ShiftFreeLogServiceTest` : effort < 30 min.
  >
  > ---
  >
  > ### Groupe B — Testables, valeur moyenne (🟡)
  >
  > #### `OpeningHourService` (52L) — logique métier avec dépendance EM
  >
  > `isOpen(\DateTime $date)` orchestre deux appels EM + un `array_filter` sur les plages horaires. `isClosed()` est l'inverse.
  >
  > Testable avec mocks du repository `OpeningHour` et `ClosingException`. Trois chemins distincts à couvrir :
  > 1. Aucun `OpeningHour` pour ce jour → `false`
  > 2. `OpeningHour` présent mais hors plage horaire → `false`
  > 3. `OpeningHour` dans plage + pas de `ClosingException` → `true`
  > 4. `OpeningHour` dans plage + `ClosingException` active → `false`
  >
  > → Valeur réelle : `isOpen()` est appelé dans les vues et controllers pour conditionner l'affichage et l'accès. Un bug sur les edge-cases horaires (exactement à l'heure d'ouverture/fermeture) serait invisible sans test.
  >
  > #### `FixtureGroupConsoleService` (27L) — helper de fixtures
  >
  > `getGroups()` retourne `$this->input->getOption('group')` ou `[]`. Trivial à tester, mais c'est un helper de commande de fixtures (infrastructure de test elle-même). **Valeur quasi nulle** — skip justifié.
  >
  > #### `OpeningHourKindService` (24L) — délégation EM pure
  >
  > `hasEnabled()` → `count($em->getRepository(OpeningHourKind::class)->findEnabled()) > 0`. Une ligne effective. Testable avec mock EM, mais valeur marginale : si le repository retourne [] ou [kind], le test ne teste que le mock, pas la logique métier.
  >
  > ---
  >
  > ### Groupe C — Non unitairement testables / valeur faible (🔵 skip ou intégration)
  >
  > | Service | Raison | Alternative |
  > |---------|--------|-------------|
  > | `EventService` (47L) | 2 méthodes = 100% query builder delegation vers `ProxyRepository`. Aucune logique pure. | Test d'intégration avec vraie DB |
  > | `PeriodFormHelper` (87L) | Couplé `FormBuilder` + `EntityType` + `JobRepository`. `createFilterForm()` = `getFilterForm()` + setData. Aucune logique extractible. | Couvert indirectement par les tests fonctionnels des controllers qui l'utilisent |
  > | `Picture/BasePathPicture` (31L) | Délégation pure à `UploaderHelper::asset()` puis `CacheManager::getBrowserPath()`. La méthode `getPicturePath` n'a aucune logique propre. | N/A — le mock ne teste que les mocks |
  > | `SearchUserFormHelper` (749L) | God class avec `ContainerInterface` injection. Construit des form builders et des query builders Doctrine. Pas de logique pure isolable. Déjà couverte à ~48% via les smoke tests (TC.1). | Refactoring préalable requis (extraire la logique pure) avant tout test unitaire utile |
  >
  > ---
  >
  > ### Résumé et priorisation
  >
  > | Priorité | Action | Service | Effort estimé |
  > |----------|--------|---------|--------------|
  > | 🔴 Immédiat | Tests unitaires `isTemporaryEmail` + `getAllowedEmails` | `MailerService` | XS — aucun mock |
  > | 🔴 Immédiat | Tests `generateShiftString` + `initShiftFreeLog` | `ShiftFreeLogService` | S — pattern TC.1 |
  > | 🔴 Immédiat | Tests `initPeriodPositionFreeLog` | `PeriodPositionFreeLogService` | XS — copier-adapter |
  > | 🟡 Moyen terme | Tests `isOpen()` avec 4 chemins | `OpeningHourService` | S — 3 mocks |
  > | 🔵 Skip | Délégation pure, valeur nulle en unitaire | `EventService`, `OpeningHourKindService`, `PeriodFormHelper`, `BasePathPicture`, `FixtureGroupConsoleService` | — |
  > | 🔵 Post-refactoring | God class, refactoring requis | `SearchUserFormHelper` | — |
  >
  > → **TODO SYN.2** — ajouter en catégorie Tests : `MailerService::isTemporaryEmail`, `ShiftFreeLogService`, `PeriodPositionFreeLogService` en priorité 🔴 immédiat ; `OpeningHourService::isOpen` en 🟡.

- [x] **TC.4** — Qualité des tests existants
  > **Périmètre analysé** : 14 fichiers de tests (4 entités, 5 services unitaires, 1 service intégration, 3 controllers fonctionnels + DatabasePrimer + FunctionalTestCase). Suite verte à 350 tests / 477 assertions.
  >
  > ---
  >
  > ### 🔴 Haute priorité — faux positifs / bugs masqués
  >
  > **TC.4.1 — Noms trompeurs dans ShiftServiceUnitTest (2 tests)**
  >
  > `testRemainingToBookPartiallyBooked` et `testCanBookDurationWhenAlreadyFullyBooked` ne testent ni le cas "partiellement réservé" ni le cas "totalement réservé". Dans les deux cas, `getShiftTimeCount()` retourne 0 parce qu'aucun `TimeLog` n'est injecté dans l'entité. Le test se comporte comme le cas "rien de réservé". Les commentaires en ligne l'admettent eux-mêmes (`"the result will be 180 - 0 = 180"`). Ces tests donnent une fausse confiance sur le cas critique des quotas.
  >
  > **TC.4.2 — `testHasPreviousValidShiftsWithDismissedShift` (Integration/ShiftServiceTest)**
  >
  > Le test utilise une date future (`+10 days`) et est censé tester un shift "dismissed" (annulé). C'est identique à `testHasPreviousValidShiftsWithShiftInTheFuture`. Un shift dismissed devrait avoir `wasCarriedOut = false` — ce critère n'est jamais testé. Le test passe pour la mauvaise raison.
  >
  > **TC.4.3 — Reflection hacks pour collections non initialisées (5 occurrences)**
  >
  > `BeneficiaryTest` (`swipe_cards`), `MembershipTest` (`notes`, `given_proxies`, `membershipShiftExemptions`), `ShiftTest` (`timeLogs`) utilisent `ReflectionClass` pour injecter un `ArrayCollection` vide dans des propriétés que le constructeur n'initialise pas. Ces tests passent, mais masquent un bug réel : en dehors de Doctrine (tests, fixtures, factory), appeler `getSwipeCards()` / `getTimeLogs()` etc. sur une entité non persistée itérerait sur `null` → TypeError. C'est le symptôme direct des 6 constructeurs vides identifiés en DC.1.
  > → Ces tests deviendraient valides si les constructeurs étaient corrigés (TODO DC.1).
  >
  > ---
  >
  > ### 🟡 Priorité moyenne — valeur réduite ou fragilité
  >
  > **TC.4.4 — `testGetRemainderReturnsDateInterval` (MembershipServiceTest)**
  >
  > Vérifie uniquement que `getRemainder()` retourne un `DateInterval` — aucune vérification de la valeur calculée (nombre de jours restants). Le test passe même si la méthode retourne `new DateInterval('P0D')`.
  >
  > **TC.4.5 — Magic number `67` sans explication (AdminControllerTest::testCsvImportForCommissionFilledBase)**
  >
  > `$this->assertEquals(67, $count)` compte les liens bénéficiaire↔commission après import CSV. Le nombre 67 provient de la fixture CSV + commission, mais n'est nulle part documenté. Si les fixtures changent, le test casse sans indice sur ce qui est attendu.
  >
  > **TC.4.6 — Output non capturé (AdminControllerTest::testCsvImportForCommissionFilledBase)**
  >
  > Le premier test `testCsvImportForEmptyBase` capture `$output` et vérifie `'Dealing with 50 lines'`. Le second test ne passe pas de `$output` à `$application->run($input)` : impossible de vérifier la sortie de la commande.
  >
  > **TC.4.7 — Assertion trop vague dans MembershipControllerTest::testFindMeWithNonExistentMemberNumber**
  >
  > Soumet un numéro d'adhérent inexistant (99999) et vérifie HTTP 200. Ne vérifie pas l'affichage d'un message d'avertissement/flash. Un 200 sans flash message serait aussi un test réussi.
  >
  > **TC.4.8 — Mock inutile dans Integration/ShiftServiceTest::doTestHasPreviousValidShifts**
  >
  > Utilise `getMockBuilder(ShiftService::class)->disableOriginalConstructor()->onlyMethods([])` pour accéder à la méthode réelle `hasPreviousValidShifts()`. `onlyMethods([])` ne mocke rien — c'est un appel inutilement complexe. Instancier directement le service serait équivalent et plus lisible.
  >
  > **TC.4.9 — Mock de `EntityRepository` au lieu de `ShiftRepository` (BeneficiaryServiceTest)**
  >
  > `getMockBuilder(EntityRepository::class)->addMethods(['findShiftsForBeneficiary'])` mocke une classe qui n'a pas cette méthode. La vérification de type est contournée : si `findShiftsForBeneficiary` était renommée, le mock compilerait toujours mais le test testerait un contrat inexistant.
  >
  > ---
  >
  > ### 🔵 Priorité basse — style / incohérences
  >
  > **TC.4.10 — Mauvais nom : `testShiftTimeByCycle` teste `canBookOnCycle` (Integration/ShiftServiceTest)**
  >
  > Le test appelle `$this->shiftService->canBookOnCycle($beneficiary, 0)` — pas `shiftTimeByCycle`. Nom trompeur depuis l'origine.
  >
  > **TC.4.11 — Args hardcodés dans testCanBookSomethingDelegatesToCanBookOnCycle (ShiftServiceUnitTest)**
  >
  > Les arguments du constructeur sont répliqués manuellement au lieu de passer par le helper `createService()`. Si la signature change, ce test ne sera pas mis à jour automatiquement.
  >
  > **TC.4.12 — Setup partagé incohérent dans Integration/ShiftServiceTest**
  >
  > `setUp()` crée `$this->shiftService` partagé entre tous les tests, mais les helpers privés `doIsShiftBookableTest`, `doTestIsBeginner`, `doTestHasPreviousValidShifts` ignorent ce service et créent leur propre instance. Deux patterns coexistent dans la même classe.
  >
  > **TC.4.13 — `testLoginWithInvalidCredentials` ne vérifie pas le message d'erreur (SmokeTest)**
  >
  > Vérifie redirect puis HTTP 200, pas l'affichage d'un message "Identifiants incorrects" sur la page.
  >
  > ---
  >
  > ### Récapitulatif TODO
  >
  > | Ref | Priorité | Action |
  > |-----|----------|--------|
  > | TC.4.1 | 🔴 | Réécrire `testRemainingToBookPartiallyBooked` et `testCanBookDurationWhenAlreadyFullyBooked` avec de vrais `TimeLog` |
  > | TC.4.2 | 🔴 | Corriger `testHasPreviousValidShiftsWithDismissedShift` — tester `wasCarriedOut=false` |
  > | TC.4.3 | 🔴 | Résolu par la correction des constructeurs vides (DC.1) — puis retirer les `ReflectionClass` |
  > | TC.4.4 | 🟡 | Ajouter une assertion sur la valeur de `getRemainder()` |
  > | TC.4.5 | 🟡 | Documenter le magic number 67 avec un commentaire explicatif |
  > | TC.4.6 | 🟡 | Passer un `$output` à `run()` dans le second test CSV et vérifier la sortie |
  > | TC.4.7 | 🟡 | Vérifier la présence du flash/message d'avertissement dans la réponse |
  > | TC.4.8 | 🔵 | Remplacer le mock inutile par une vraie instance de `ShiftService` |
  > | TC.4.9 | 🔵 | Mocker `ShiftRepository` au lieu de `EntityRepository` |
  > | TC.4.10 | 🔵 | Renommer `testShiftTimeByCycle` → `testCanBookOnCyclePossibleWithNoFlying` |
  > | TC.4.11 | 🔵 | Refactoriser avec `createService()` |
  > | TC.4.12 | 🔵 | Unifier le pattern : soit tout passe par `$this->shiftService`, soit tout passe par des factories |
  > | TC.4.13 | 🔵 | Ajouter `assertStringContainsString` sur le message d'erreur login |
  >
  > → Alimentera **SYN.2** (TODO priorisée), catégorie Tests.

- [x] **TC.5** — Commandes non testées
  > 25 fichiers dans `src/Command/` : 1 classe abstraite (`CsvCommand`), 2 commandes indisponibles en dev (credentials API absents), 22 commandes enregistrées.
  >
  > **Couverture actuelle : 1/24 commandes réelles testées (4,2 %)**
  >
  > Seule `app:import:users` (`ImportUsersCommand`) est exercée, via `AdminControllerTest` (4 runs via `@dataProvider`).
  >
  > ### Inventaire complet
  >
  > | Commande | Fichier | Lignes | DB écrits | Mail | Events | Tests |
  > |---|---|---|---|---|---|---|
  > | `app:import:users` | ImportUsersCommand | 252 | ✅ flush | — | ✅ dispatch | ✅ AdminControllerTest |
  > | `app:shift:generate` | ShiftGenerateCommand | 182 | ✅ persist+flush | — | ✅ dispatch | ❌ |
  > | `app:anonymize` | AnonymizeDataCommand | 198 | ✅ persist+flush | — | — | ❌ |
  > | `app:doc` | DoctorCommand | 142 | ✅ flush ×3 | — | — | ❌ |
  > | `app:user:mass_mail` | SendMassMailCommand | 152 | — | ✅ direct | — | ❌ |
  > | `app:user:cycle_start` | CycleStartCommand | 78 | — | — | ✅ dispatch | ❌ |
  > | `app:user:cycle_half` | CycleHalfCommand | 87 | — | — | ✅ dispatch | ❌ |
  > | `app:shift:send_alerts` | SendShiftAlertsCommand | 117 | — | — | ✅ dispatch | ❌ |
  > | `app:shift:send_late_shifters` | AmbassadorShiftTimeLogCommand | 101 | — | ✅ direct | — | ❌ |
  > | `app:shift:reminder` | ShiftReminderCommand | 74 | — | — | ✅ dispatch | ❌ |
  > | `app:member:close` | CloseMembershipCommand | 78 | ✅ persist+flush | — | — | ❌ |
  > | `app:shift:free` | FreeReservedShiftsCommand | 74 | ✅ persist+flush | — | — | ❌ |
  > | `app:helloasso:payment` | HelloassoPaymentCommand | 83 | — | — | ✅ dispatch | ❌ (API ext.) |
  > | `app:shift:fix_missing_position` | FixShiftMissingPositionCommand | 124 | ✅ DQL UPDATE | — | — | ❌ |
  > | `app:user:fix_time_log` | FixTimeLogCommand | 74 | ✅ flush | — | — | ❌ |
  > | `app:user:fix_beneficiary_addresses` | FixBeneficiariesWithoutAddressCommand | 64 | ✅ flush | — | — | ❌ |
  > | `app:shiftfreelog:init_shift_string_field` | InitShiftFreeLogShiftStringFieldCommand | 60 | ✅ flush | — | — | ❌ |
  > | `app:user:init_time_log` | InitTimeLogCommand | 82 | ✅ flush | — | — | ❌ |
  > | `app:user:init_first_shift_date` | InitUsersFirstShiftDateCommand | 56 | ✅ flush | — | — | ❌ |
  > | `app:beneficiary:randomise` | RandomSortMembersCommand | 114 | — | — | — | ❌ |
  > | `app:custom-purge` | CustomPurgerCommand | 43 | ✅ purge | — | — | ❌ (indirectement via DatabasePrimer) |
  > | `app:code:verify_change` | VerifyCodeChangeCommand | 131 | — | ✅ direct | — | ❌ |
  > | `app:member:update_payments` | UpdateHelloAssoPaymentsCommand | 77 | — | — | — | ❌ (API ext., non dispo dev) |
  > | `app:code:update_igloohome` | UpdateIgloohomeCodeCommand | 107 | ✅ flush | ✅ direct | — | ❌ (API ext., non dispo dev) |
  >
  > ### Commandes indisponibles en dev
  >
  > `app:member:update_payments` et `app:code:update_igloohome` échouent à l'enregistrement Symfony en dev : `HelloassoClient::__construct()` et `IgloohomeClient::__construct()` reçoivent `null` pour les credentials. Ces commandes sont instance-spécifiques (Elefan utilise Helloasso, Igloohome est un système de codes d'accès physique).
  >
  > ### Patterns récurrents
  >
  > - **Toutes les commandes sont des orchestrateurs** : elles appellent des repositories et des services, n'implémentent pas de logique métier propre. Cela réduit la valeur des tests unitaires purs — les tests fonctionnels (CommandTester + DB de test) sont le pattern approprié.
  > - **Aucune commande n'a de `--dry-run`**, même pour les opérations irréversibles (`app:anonymize`, `app:member:close`, `app:shift:generate`). C'est un manque d'ergonomie opérationnelle (voir EXTRA).
  > - `CycleStartCommand` a déjà un `--date` option avec le commentaire `//useful for tests` : intention de tester jamais concrétisée.
  > - `AnonymizeDataCommand` demande une confirmation interactive — nécessite `setInputStream()` pour être testée.
  > - `SendMassMailCommand` contient une logique de filtrage des destinataires (membres actifs, gelés, non-membres) qui mériterait des tests fonctionnels (vérification BCC recipients).
  >
  > ### Priorisation des quick wins
  >
  > | Priorité | Commande | Rationale |
  > |---|---|---|
  > | 🔴 1 | `app:member:close` | Logique de date critique, état irréversible (`withdrawn=true`), aucune dépendance externe. Pattern : fixture membre expiré → run → assert withdrawn. |
  > | 🔴 2 | `app:user:cycle_start` | Core métier, `--date` déjà présent, pas de DB write directe (events). Pattern : fixture membres → run avec date fixe → assert events dispatched. |
  > | 🔴 3 | `app:shift:generate` | Commande la plus complexe (182L, ABCD cycle, pré-réservation). Core business. Pattern : fixtures Period + PeriodPosition → run date → assert Shift créés en base. |
  > | 🟡 4 | `app:shift:free` | Simple, DB writes directs, logique date-based. Pattern : fixture Shift réservé en passé → run → assert libéré. |
  > | 🟡 5 | `app:beneficiary:randomise` | Aucune dépendance externe, output-only. Le plus simple à tester. |
  > | 🔵 6 | `app:doc` | 3 options indépendantes, toutes testables avec fixtures. Valeur surtout régressive. |

---

## PERF — Performance (analyse uniquement)

- [x] **PERF.1** — N+1 queries potentielles

  **Méthodologie** : grep sur `findAll()` / `findBy([])` + vérification des associations accédées dans la boucle ou le template. Annotations `fetch="EAGER"` inventoriées séparément.

  **Cas confirmés :**

  | # | Sévérité | Fichier | Route | Pattern | Queries supplémentaires |
  |---|----------|---------|-------|---------|------------------------|
  | 1 | 🔴 | `MembershipController:1036` | `GET /emails_csv` | `Beneficiary::findAll()` (hérité Doctrine, pas de JOIN FETCH) puis `->getMembership()->isWithdrawn()` dans le foreach | N requêtes membership (N = tous les bénéficiaires, potentiellement 800+) |
  | 2 | 🟡 | `AdminEventController:234` | `GET /proxies` | `Proxy::findAll()` sans JOIN FETCH puis template accède `proxy.event`, `proxy.giver`, `proxy.owner`, `proxy.owner.membership` | 4N requêtes ; atténué par faible volume (~10–50 proxies) |
  | 3 | 🟡 | `CommissionController:41` | `GET /` (admin commissions) | `Commission::findAll()` sans JOIN FETCH puis template accède `commission.beneficiaries`, `commission.owners`, `owner.user.beneficiary.membership` | 4N requêtes ; atténué par faible volume (~5–20 commissions) |
  | 4 | 🔵 | `AnonymizeDataCommand:108` | commande | `Beneficiary::findAll()` puis `->getMembership()->getRegistrations()` | 2N requêtes ; commande de maintenance, rarement exécutée |

  **Détails cas #1 (🔴 critique)** — `MembershipController::exportEmails` :
  ```php
  $beneficiaries = $this->getDoctrine()->getRepository(Beneficiary::class)->findAll(); // 1 requête
  foreach ($beneficiaries as $beneficiary) {
      $beneficiary->getMembership()->isWithdrawn();  // lazy load : +1 requête par bénéficiaire
  }
  ```
  `BeneficiaryRepository` n'a pas de `findAll()` surchargée avec JOIN FETCH. Correctif : créer `findAllWithMembership()` dans `BeneficiaryRepository` avec `->leftJoin('b.membership', 'm')->addSelect('m')`.

  **Détails cas #2 (🟡) — template proxy list** (`templates/admin/event/proxy/list.html.twig`) :
  ```twig
  {% for proxy in proxies %}
      {{ proxy.event.title }}                                {# lazy Event #}
      {{ proxy.giver.memberNumberWithBeneficiaryListString }} {# lazy Membership → getBeneficiaries() #}
      {{ proxy.owner.membership.memberNumberWithBeneficiaryListString }} {# lazy Beneficiary → Membership → getBeneficiaries() #}
  {% endfor %}
  ```
  Correctif : `ProxyRepository::findAllWithAssociations()` avec JOIN FETCH event, giver + giver.beneficiaries, owner + owner.membership + owner.membership.beneficiaries.

  **Détails cas #3 (🟡) — template commission list** (`templates/admin/commission/list.html.twig`) :
  ```twig
  {{ commission.beneficiaries | length }}   {# lazy collection #}
  {% for owner in commission.owners %}       {# lazy collection #}
      {{ owner.user.beneficiary.membership.memberNumber }} {# 3 niveaux lazy #}
  {% endfor %}
  ```
  Correctif : `CommissionRepository::findAllWithAssociations()` avec JOIN FETCH beneficiaries, owners, owners.user.

  **Déjà correctement mitigé :**
  - `ShiftRepository::findFutures` / `findFrom` → JOIN FETCH job + formation ✓
  - `SearchUserFormHelper::initSearchQuery` → 7 JOIN FETCH (beneficiaries, user, registrations, helloassoPayment, membershipShiftExemptions, commissions, formations) ✓
  - `MembershipRepository::findAllActive($prefetchBeneficiaries=true)` → JOIN FETCH beneficiaries ✓
  - `BeneficiaryRepository::findAllActive` → JOIN FETCH membership + user ✓
  - `PeriodRepository` → deep JOIN FETCH (job, positions, shifter, user, membership, registrations, helloassoPayments) ✓
  - `OpeningHourRepository` → JOIN FETCH kind ✓

  **Annotations EAGER (compromis structurel) :**
  Ces associations sont chargées automatiquement à chaque hydratation de l'entité parent — évitent le N+1 en liste mais augmentent le coût des requêtes unitaires :

  | Entité | Champ | Cible |
  |--------|-------|-------|
  | `User` | `beneficiary` | `Beneficiary` |
  | `Event` | `kind` | `EventKind` |
  | `PeriodPosition` | `period` | `Period` |
  | `PeriodPosition` | `formation` | `Formation` |
  | `Shift` | `job` | `Job` |
  | `Registration` | `helloassoPayment` | `HelloassoPayment` |
  | `HelloassoPayment` | `registration` | `Registration` |
  | `OpeningHour` | `kind` | `OpeningHourKind` |
  | `MembershipShiftExemption` | `shiftExemption` | `ShiftExemption` |
  | `Period` | `job` | `Job` |
  | `AnonymousBeneficiary` | `beneficiary` | `Beneficiary` |
  | `AnonymousBeneficiary` | `user` | `User` |

  Net-positif pour les patterns d'accès habituels : ces associations sont quasi-systématiquement nécessaires. Pas de refactoring recommandé.

  **Recommandations TODO (par priorité) :**
  1. 🔴 `BeneficiaryRepository::findAllWithMembership()` — correctif direct pour `/emails_csv`
  2. 🟡 `ProxyRepository::findAllWithAssociations()` — correctif pour la liste globale des procurations
  3. 🟡 `CommissionRepository::findAllWithAssociations()` — correctif pour la liste des commissions

- [x] **PERF.2** — Collections non paginées

  **Méthodologie** : recensement de tous les `findAll()` (natifs Doctrine + surchargés) dans les controllers web ; classification par cardinalité attendue en production et accès utilisateur.

  **Patron de pagination existant** (`Doctrine\ORM\Tools\Pagination\Paginator`, 25 items/page) :
  - `AdminController` → liste membres ✓
  - `AdminShiftFreeLogController` → shift free logs ✓
  - `AdminMembershipShiftExemptionController` → exemptions ✓
  - `AdminEventController::listAction()` → événements ✓

  **Cas problématiques identifiés :**

  | # | Sévérité | Fichier | Route | Entité | Cardinalité production | Accès |
  |---|----------|---------|-------|--------|----------------------|-------|
  | 1 | 🔴 | `MembershipController:1036` | `GET /emails_csv` | `Beneficiary` | ~3 000+ (Elefan) | `ROLE_SUPER_ADMIN` |
  | 2 | 🟡 | `AdminEventController:234` | `GET /admin/events/proxies` | `Proxy` | Croît avec chaque événement (potentiellement 100–500/an) | `ROLE_PROCESS_MANAGER` |
  | 3 | 🟡 | `AdminClosingExceptionController:64` | `GET /admin/closingexceptions/list` | `ClosingException` | Croît ~10–50/an | `ROLE_ADMIN` |

  **Détails cas #1 (🔴) — export CSV `/emails_csv`** :
  Charge tous les bénéficiaires en mémoire PHP (objets Doctrine hydratés), puis filtre en PHP `isWithdrawn()` et `filter_var($email)`. En production Elefan : potentiellement 3 000+ objets instanciés pour un export ponctuel.
  Correctifs possibles :
  - Filtrer côté SQL (`WHERE m.withdrawn = 0 AND b.email != ''`) pour ne charger que les bénéficiaires actifs avec email valide.
  - Utiliser `StreamedResponse` avec requête DBAL chunked (ex : `setMaxResults(500)` + itération) pour éviter le pic mémoire.
  Note : déjà couvert en PERF.1 pour le N+1 (`->getMembership()->isWithdrawn()`) ; ici le problème est orthogonal — volume en mémoire, pas nombre de requêtes.

  **Détails cas #2 (🟡) — liste globale des procurations `/admin/events/proxies`** :
  `Proxy::findAll()` sans filtre temporel ni pagination. Les proxies s'accumulent dans le temps (un proxy = un adhérent remplacé à un événement). Après 3–5 ans d'utilisation, la liste pourrait dépasser 500–2000 lignes sans limite.
  `AdminEventController::listAction()` (liste des événements) utilise déjà `Paginator` — cohérence manquante sur la liste des proxies.
  Correctif recommandé : ajouter un filtre par défaut (proxies des N derniers mois ou de la saison courante) ou appliquer le `Paginator` (25/page).

  **Détails cas #3 (🟡) — liste complète des exceptions `/admin/closingexceptions/list`** :
  `ClosingException::findAll()` retourne toutes les exceptions triées DESC — sans plafond.
  Incohérence interne : `indexAction` (route principale) limite déjà les exceptions passées à 10 via `findPast(null, 10)` — la route `/list` duplique sans cette limite.
  Correctif recommandé : supprimer la route `/list` (redondante avec `indexAction`) ou la limiter à la saison courante.

  **Données référentielles — aucun risque (cardinalité bornée) :**
  En dev et en production, ces entités restent < 50 lignes par design (données de configuration) :
  `Commission` (10), `Formation` (4), `EventKind` (3), `OpeningHour` (21), `Client` (3), `Service` (3), `EmailTemplate`, `SocialNetwork`, `ShiftExemption`, `DynamicContent`, `OpeningHourKind`.
  Accès admin uniquement ; pas de pagination requise.

  **Commandes batch — `findAll()` légitime :**
  `InitTimeLogCommand`, `FixTimeLogCommand`, `DoctorCommand`, `InitShiftFreeLogShiftStringFieldCommand`, `AnonymizeDataCommand` — chargement complet intentionnel pour traitement offline.

  **Surcharges `findAll()` filtrées — pas de risque :**
  - `TimeLogRepository::findAll(member, shift, type)` — filtré, utilisé en ShiftController pour vérifier un TimeLog précis.
  - `OpeningHourRepository::findAll(kind)` — filtré + entité bornée.
  - `ClosingExceptionRepository::findAll()` — renvoie tout (voir cas #3 ci-dessus).

  **Recommandations TODO (par priorité) :**
  1. 🔴 `/emails_csv` — filtrer côté SQL + `StreamedResponse` chunked pour éviter le pic mémoire
  2. 🟡 `/admin/events/proxies` — filtre temporel (saison courante) ou `Paginator` (25/page)
  3. 🟡 `/admin/closingexceptions/list` — supprimer la route ou la limiter à la saison courante

- [x] **PERF.3** — Cache applicatif

  **Infrastructure cache en place**

  - **`cache.yaml`** : `cache.app` utilise le FilesystemAdapter par défaut (adaptateurs Redis/APCu en commentaire — non configurés).
  - **`config/packages/prod/doctrine.yaml`** : deux pools déclarés — `doctrine.system_cache_pool` (metadata + query cache → `cache.system`) et `doctrine.result_cache_pool` (result cache → `cache.app`). Config **prod uniquement**, pas de dev.

  **Usages existants**

  | Fichier | Mécanisme | TTL | Problème |
  |---|---|---|---|
  | `src/Providers/CacheOauthAuthenticatorDecorator.php:22` | `new FilesystemAdapter()` direct | 600 s ou expiry token | Hors DI — ne suit pas `cache.app` |
  | `src/Repository/ShiftRepository.php:32` | `new FilesystemAdapter()` static | 5 s | Hors DI — ne suit pas `cache.app` |

  **Anomalie #1 — `new FilesystemAdapter()` hors DI (2 occurrences)**
  `CacheOauthAuthenticatorDecorator` (L22) et `ShiftRepository::functionsResultCache()` (L32) instancient `FilesystemAdapter` directement, sans injection de dépendance. Conséquences :
  - Ne suivent pas la configuration `cache.app` (si l'opérateur passe à Redis/APCu, ces caches restent sur le filesystem).
  - Pas purgés par `php bin/console cache:pool:clear`.
  - Namespace par défaut vide → risque de collision de clés avec d'autres usages filesystem.

  **Anomalie #2 — Doctrine result cache configuré mais jamais activé**
  La `result_cache_driver` déclarée dans `prod/doctrine.yaml` crée le pool mais n'active pas automatiquement le cache sur les requêtes. Il faut appeler explicitement `->enableResultCache()` (Doctrine 2.6+) ou l'ancien `->useResultCache(true)` sur chaque `Query`. Grep sur tout `src/` : **aucun appel**. La configuration est du dead config.

  **Anomalie #3 — Autocomplete lourd embarqué inline, sans cache**
  Les widgets Twig (`templates/form/fields.html.twig:19,37,86`) injectent la liste complète des adhérents/membres en JSON inline dans chaque page HTML contenant un champ autocomplete :
  ```twig
  data: {{ beneficiary_service.autocompleteBeneficiaries | json_encode(...) | raw }}
  data: {{ membership_service.autocompleteMemberships   | json_encode(...) | raw }}
  ```
  Ces appels déclenchent `BeneficiaryRepository::findAllActive()` et `MembershipRepository::findAllActive()` — full table reads (~3 000+ lignes, cf. PERF.2). **Aucune mise en cache.** 8+ contrôleurs utilisent `AutocompleteBeneficiaryType` ; si une page contient deux champs autocomplete différents, `findAllActive()` est appelé deux fois dans la même requête.

  **Recommandations TODO (par priorité)**
  1. 🟡 **Autocomplete data** — Mettre en cache `getAutocompleteBeneficiaries()` et `getAutocompleteMemberships()` via `CacheInterface` injecté (TTL ~5 min, ou invalidation sur événement membership write). Gain : supprime les full table reads répétés sur toutes les pages admin avec formulaire.
  2. 🟡 **Corriger les deux `new FilesystemAdapter()`** — Injecter `CacheInterface $cache` (ou un pool nommé) dans `CacheOauthAuthenticatorDecorator` et `ShiftRepository` pour qu'ils utilisent le pool central configuré.
  3. 🔵 **Activer Doctrine result cache sur les requêtes read-mostly** — Listes de référentiel stables (formations, jobs, opening hours) : ajouter `->enableResultCache(3600, 'cache_key')` sur les queries concernées pour tirer parti du pool déjà configuré en prod.

---

## CONFIG — Configuration multi-instance

- [x] **CONFIG.1** — Variables d'environnement
  > Lire `.env.dist`. Toutes les variables documentées ? `grep -rn "getenv\|\$_ENV\|->get('" src/ config/` pour trouver celles utilisées dans le code mais absentes du `.dist`. Résultat → documentation finale.

  **Résultat**

  Méthode : extraction de toutes les variables `%env(VAR)%` dans `config/` + inventaire `.env.dist` (via `git show HEAD:.env.dist`), diff croisé.

  **Couverture globale : bonne.** Les ~130 variables consommées par Symfony via `%env()%` ont toutes une entrée dans `.env.dist`. Deux variables optionnelles sont intentionnellement commentées dans le dist (`SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT` utilise `default:true`, `TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS` utilise `default::` pour retourner `null` si absente — mécanisme documenté dans le dist par commentaire).

  **Findings :**

  #### 🔴 Bug : `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY` casté `bool:` au lieu d'entier
  `config/services.yaml:147` : `'%env(bool:RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY)%'`
  La variable vaut `7` dans `.env.dist` (nombre de jours). Avec le cast `bool:`, `7` → `true`. Le paramètre est passé en template email (`EmailingEventListener.php:295`) et référencé comme nombre de jours dans `FreeReservedShiftsCommand.php:15`. En production, la valeur reçue est `true` (1) au lieu de `7`. **TODO : retirer `bool:` dans `services.yaml:147`.**

  #### 🟡 `ROUTER_REQUEST_CONTEXT_SCHEME` défini mais non mappé vers Symfony
  Présent dans `.env.dist`, `.env`, `.env.test` et `.env.oidc.test` (valeurs `https`/`http`), mais **aucun** `router.request_context.scheme: '%env(ROUTER_REQUEST_CONTEXT_SCHEME)%'` dans `config/services.yaml`. Le scheme est donc ignoré par Symfony → les URLs générées hors requête HTTP (commandes CLI, emails) utilisent le scheme par défaut (`http`), même si l'instance est en `https`. Croiser avec **LOG.1** et la génération d'URLs en commandes. **TODO : ajouter le mapping dans `services.yaml` ou supprimer la variable du dist si le scheme est géré autrement (proxy, `trusted_proxies`).**

  #### 🟡 Variables infrastructure mélangées aux variables applicatives dans `.env.dist`
  Les variables suivantes sont dans `.env.dist` mais **ne sont pas consommées par Symfony** (`%env()%`) — elles sont utilisées par `dploy.sh` (déploiement bare-metal) ou par Docker/IDE :
  - `SYMFONY_ENV` — Legacy Symfony 3.x, utilisée uniquement dans `dploy.sh:65`. Inutile pour Docker/SF4+.
  - `PHP_USER`, `PHP_MEMORY_LIMIT`, `PHP_SERVICE_NAME`, `PHP_IDE_CONFIG` — Variables runtime Docker/système, consommées par `dploy.sh` ou le container PHP, pas par l'application.
  Ces variables pourraient être déplacées dans un `.env.infra.dist` ou documentées explicitement comme "variables de déploiement".

  #### 🟡 `DATABASE_TEST_HOST` — variable morte
  Définie dans `.env.dist` (valeur `127.0.0.1`) mais jamais consommée ni par du code PHP, ni par un `%env()%` dans les configs. Utilisée nulle part. **TODO : à supprimer du dist.**

  #### 🟡 `DEV_MODE_ENABLED` — référence morte dans la documentation
  Mentionnée dans `doc/install.local.md:38` ("N'oubliez pas de définir la variable d'environnement `DEV_MODE_ENABLED`") et dans `flake.nix:29` (vide). **Absente de `.env.dist` et d'aucun code PHP ni config Symfony.** La variable n'a aucun effet dans l'application actuelle. La mention dans la doc est trompeuse. **TODO : supprimer la mention dans `install.local.md` et le stub dans `flake.nix`.**

  #### 🔵 Variables legacy dans `.env.oidc.test` — mortes
  `HELLOASSO_API_KEY` et `HELLOASSO_API_PASSWORD` sont définies dans `.env.oidc.test` mais jamais consommées par du code ou de la config. Probablement des reliquats de l'API HelloAsso v1 (auth par clé/mot de passe) remplacée par OAuth v5. **TODO : supprimer de `.env.oidc.test`.**

- [x] **CONFIG.2** — Mécanisme de personnalisation par instance
  > Comment Elefan et Scopeli configurent-ils leur instance différemment ? Paramètres Symfony, table de config en base, feature flags ? `grep -rn "getParameter\|ParameterBagInterface" src/`. Résultat → documentation finale + specs (SPEC.9).

  **Résultat**

  Méthode : lecture de `config/services.yaml`, `config/packages/twig.yaml`, `.env.dist` ; grep exhaustif `getParameter` (84 appels) et `ParameterBagInterface` dans `src/`.

  ### Architecture de configuration : 3 couches, tout par variables d'environnement

  Il n'existe **aucune table de configuration en base** (pas d'entité `Parameter`/`Config`/`Setting` dans `src/Entity/`), **aucun feature flag framework** (Flipper, Unleash, etc.), et **aucune variable `APP_INSTANCE`** ou mécanisme d'identification de déploiement au runtime. Elefan et Scopeli sont deux déploiements indépendants du même code, différenciés uniquement par leur fichier `.env` (ou équivalent CI/CD).

  **Couche 1 — Variables d'environnement** : définies dans `.env` / `.env.local` / secrets CI.
  **Couche 2 — Paramètres Symfony** : `config/services.yaml` déclare ~130 paramètres nommés (ex. `cycle_type`, `oidc_enable`) mappés depuis les vars via `%env(TYPE:VAR)%`.
  **Couche 3 — Consommation** par deux vecteurs :
  - **`_defaults.bind`** dans `services.yaml` (lignes 176–222) : injection automatique par nom d'argument PHP dans les services/controllers.
  - **`getParameter('name')`** : 84 appels directs depuis controllers (`AbstractController`), event listeners et services via `ContainerAwareTrait` (anti-pattern Symfony 5+ : préférer injection constructeur).
  - **Globales Twig** : `config/packages/twig.yaml` expose ~60 paramètres directement dans les templates.

  ### Catégories fonctionnelles (8 groupes)

  | Catégorie | Variables clés | Exemple de différenciation |
  |---|---|---|
  | **Infrastructure** | `APP_ENV`, `DATABASE_URL`, `MAILER_DSN`, `PHP_*` | Docker/déploiement, non applicatif |
  | **Identité coopérative** | `SITE_NAME`, `PROJECT_NAME`, `MAIN_COLOR`, `LOCAL_CURRENCY_NAME` | Branding Elefan vs Scopeli |
  | **Membres/adhésion** | `REGISTRATION_DURATION`, `MAXIMUM_NB_OF_BENEFICIARIES_IN_MEMBERSHIP`, `REGISTRATION_EVERY_CIVIL_YEAR` | Règles d'adhésion propres à chaque coop |
  | **Créneaux** | `CYCLE_DURATION`, `CYCLE_TYPE`, `DUE_DURATION_BY_CYCLE`, `MIN_SHIFT_DURATION`, `FORBID_SHIFT_OVERLAP_TIME` | Règles métier des créneaux |
  | **Feature flags booléens** | voir tableau ci-dessous | Activation/désactivation de modules |
  | **Intégrations externes** | `HELLOASSO_*`, `IGLOOHOME_*`, `OIDC_*` | Services tiers différents par instance |
  | **Sécurité** | `ENABLE_PLACE_LOCAL_IP_ADDRESS_CHECK`, `SWIPE_CARD_SECRET`, `SUPER_ADMIN_*` | Configuration sécurité par déploiement |
  | **UI / icônes** | `MEMBER_*_ICON`, `MEMBER_*_BACKGROUND_COLOR`, `BENEFICIARY_*_ICON` | Personnalisation visuelle des statuts |

  ### Feature flags booléens (on/off par instance)

  Ce sont les vrais leviers de comportement différencié entre instances :

  | Variable | Fonctionnalité |
  |---|---|
  | `OIDC_ENABLE` | Authentification SSO Keycloak |
  | `USE_FLY_AND_FIXED` | Mode créneaux volant/fixe |
  | `USE_CARD_READER_TO_VALIDATE_SHIFTS` | Validation par lecteur de carte |
  | `USE_TIME_LOG_SAVING` | Épargne de temps (time banking) |
  | `CODE_GENERATION_ENABLED` | Génération de codes d'accès physiques |
  | `DISPLAY_GAUGE` | Jauge canvas-gauges (dépendance CDN HS — cf. EXTRA [DEP.3]) |
  | `DISPLAY_KEYS_SHOP` | Module boutique de clés |
  | `DISPLAY_FREEZE_ACCOUNT` | Option gel de compte membre |
  | `DISPLAY_SWIPE_CARDS_SETTINGS` | Interface gestion cartes de swipe |
  | `ALLOW_EXTRA_SHIFTS` | Créneaux supplémentaires autorisés |
  | `REGISTRATION_MANUAL_ENABLED` | Inscription manuelle (non HelloAsso) |
  | `LOGGING_MATTERMOST_ENABLED` | Notifications Mattermost |
  | `DISPLAY_OPENING_HOUR_OPEN_CLOSED_HEADER` | Bandeau ouvert/fermé en tête |
  | `DISPLAY_NAME_SHIFTERS` | Noms des bénéficiaires visibles publiquement |
  | `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER` | Réservation prioritaire aux anciens bénéficiaires |
  | `ENABLE_PLACE_LOCAL_IP_ADDRESS_CHECK` | Filtrage IP pour accès lieu |
  | `NEW_USERS_START_AS_BEGINNER` | Nouveaux membres = statut débutant |
  | `REGISTRATION_EVERY_CIVIL_YEAR` | Cotisation calée sur l'année civile |
  | `FLY_AND_FIXED_ALLOW_FIXED_SHIFT_FREE` | Libération de créneaux fixes autorisée |
  | `TIME_LOG_SAVING_SHIFT_FREE_ALLOW_ONLY_IF_ENOUGH_SAVING` | Libération conditionnelle au solde |
  | `SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT` | Copie email confirmation créneau à l'admin |
  | `SWIPE_CARD_LOGGING` / `SWIPE_CARD_LOGGING_ANONYMOUS` | Journalisation des passages carte |
  | `PROFILE_DISPLAY_TASK_LIST` / `_TIME_LOG` / `_SHIFT_FREE_LOG` / `_PERIOD_POSITION_FREE_LOG` | Sections affichées dans le profil membre |
  | `ADMIN_MEMBER_DISPLAY_SHIFT_FREE_LOG` / `_PERIOD_POSITION_FREE_LOG` | Colonnes affichées côté admin |
  | `FORBID_OWN_SHIFT_BOOK/FREE/VALIDATE_ADMIN` / `FORBID_OWN_TIMELOG_NEW_ADMIN` | Restrictions des admins sur leurs propres actions |

  ### Findings

  #### 🟡 Inconsistance dans `twig.yaml` : accès direct env vs paramètre nommé
  Dans `config/packages/twig.yaml`, certaines globales Twig court-circuitent le paramètre Symfony défini dans `services.yaml` et lisent directement `%env()%` :
  ```yaml
  # twig.yaml L63 — court-circuite le paramètre services.yaml L19
  display_swipe_cards_settings: '%env(bool:DISPLAY_SWIPE_CARDS_SETTINGS)%'
  # vs pattern cohérent :
  display_freeze_account: '%display_freeze_account%'  # passe par le paramètre
  ```
  Autres globales concernées (ligne Twig / ligne services.yaml) : `cycle_type` (L17/L12), `registration_manual_enabled` (L19/absent de services.yaml), `use_card_reader_to_validate_shifts` (L68/L162), `fly_and_fixed_entity_flying` (L70/L48), `fly_and_fixed_allow_fixed_shift_free` (L71/L49), `display_name_shifters` (L67/L17), `oidc_enable` (L104/L107), `oidc_issuer`/`oidc_client_id` (L105-106/absents de services.yaml), `oidc_formations_map`/`oidc_commissions_claim`/`oidc_commissions_map` (L111-113/définis en paramètre). Si un paramètre nommé est redéfini en `services.yaml` (cast, valeur par défaut), la globale Twig ne reflète pas le changement. **TODO mineur : uniformiser pour passer systématiquement par le paramètre Symfony dans twig.yaml.**

  #### 🟡 Paramètre `registration_manual_enabled` absent de `services.yaml`
  Consommé comme globale Twig (`twig.yaml:19`) mais **non déclaré en paramètre Symfony** dans `services.yaml`. La var d'env `REGISTRATION_MANUAL_ENABLED` est accessible uniquement via Twig, pas via `getParameter()` dans le code PHP. Si du code PHP en a besoin, il doit faire `$this->getParameter('env(bool:REGISTRATION_MANUAL_ENABLED)')` (syntaxe non standard) — pas de bug actuel mais fragilité. **TODO mineur : déclarer en paramètre nommé dans `services.yaml`.**

  #### 🔵 Aucun mécanisme d'identification d'instance au runtime
  Conséquence directe pour RT.1 : il faudra créer une variable (`APP_INSTANCE=elefan|scopeli`) pour alimenter le tracking de routes recommandé en RT.2.

  #### 🔵 Anti-pattern `getParameter()` via `ContainerAwareTrait` (84 appels)
  L'injection par `getParameter()` depuis le container est un anti-pattern déprécié depuis Symfony 4 (et interdit dans Symfony 5+). La migration vers SF5 (SF-PREP) devra remplacer les 84 appels par injection constructeur ou `_defaults.bind`. À noter dans **SF-PREP.2**.

- [x] **CONFIG.3** — Paramètres métier configurables
  > Comportements paramétrables (durée créneaux, règles adhésion, seuils, emails) — documentés ? Résultat → documentation finale.

  **Résultat**

  Méthode : lecture de `config/services.yaml` (couche de mapping exhaustive) + `git show HEAD:.env.dist` pour les valeurs par défaut + grep sur `src/Service/MembershipService.php` et usages dans les controllers/repositories.

  ### Inventaire des paramètres métier (hors feature flags, infra, UI/icônes)

  **Cycle & durées de créneaux**

  | Variable env | Valeur dist | Unité | Description |
  |---|---|---|---|
  | `CYCLE_DURATION` | `'28 days'` | PHP nat. lang. | Durée d'un cycle de bénévolat — passé à `DateInterval::createFromDateString` |
  | `CYCLE_TYPE` | `abcd` | enum `abcd`\|`*` | `abcd` : cycles alignés sur semaine ISO A/B/C/D ; sinon cycles flottants depuis `firstShiftDate` |
  | `DUE_DURATION_BY_CYCLE` | `180` | **minutes** | Temps de bénévolat dû par cycle |
  | `MIN_SHIFT_DURATION` | `90` | **minutes** | Durée min d'un créneau pour être comptabilisé |
  | `FORBID_SHIFT_OVERLAP_TIME` | `30` | **minutes** | Marge temporelle anti-chevauchement de créneaux |
  | `MAX_TIME_AT_END_OF_SHIFT` | `0` | **minutes** | Fenêtre de validation autorisée après la fin d'un créneau |
  | `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY` | `7` | **jours** | Délai de priorité aux anciens bénéficiaires sur un nouveau créneau — 🔴 **BUG** : casté `bool:` dans `services.yaml:147` (cf. CONFIG.1) |
  | `MAX_TIME_IN_ADVANCE_TO_BOOK_EXTRA_SHIFTS` | `'3 days'` | PHP nat. lang. | Délai max avant lequel on peut réserver un créneau extra |
  | `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS` | `-9` | **heures (négatif)** | Seuil de solde en dessous duquel un membre est "en retard" — valeur négative = dette acceptable ; 🟡 unité implicite, nom trompeur (voir ci-dessous) |

  **Adhésion / inscription**

  | Variable env | Valeur dist | Unité | Description |
  |---|---|---|---|
  | `REGISTRATION_DURATION` | `'1 year'` | PHP nat. lang. | Durée de validité d'une adhésion |
  | `MAXIMUM_NB_OF_BENEFICIARIES_IN_MEMBERSHIP` | `2` | entier | Nombre max de bénéficiaires par adhésion |
  | `TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS` | `null` | **jours** | Délai min (jours) pour libérer un créneau via l'épargne de temps ; `null` = pas de contrainte |

  **Fly & Fixed**

  | Variable env | Valeur dist | Description |
  |---|---|---|
  | `FLY_AND_FIXED_ENTITY_FLYING` | `Beneficiary` | Entité qui porte le statut "volant" : `Beneficiary` ou `Membership` |

  **Emails (6 boîtes + domaine)**

  | Paramètre Symfony | Variables env | Description |
  |---|---|---|
  | `emails.admin` | `EMAILS_ADMIN_ADDRESS` + `EMAILS_ADMIN_NAME` | Boîte administrateur |
  | `emails.contact` | `EMAILS_CONTACT_ADDRESS` + `EMAILS_CONTACT_NAME` | Boîte contact générale |
  | `emails.formation` | `EMAILS_FORMATION_ADDRESS` + `EMAILS_FORMATION_NAME` | Boîte formations |
  | `emails.member` | `EMAILS_MEMBER_ADDRESS` + `EMAILS_MEMBER_NAME` | Boîte adhérents |
  | `emails.noreply` | `EMAILS_NOREPLY_ADDRESS` + `EMAILS_NOREPLY_NAME` | Noreply (expéditeur transactionnel) |
  | `emails.shift` | `EMAILS_SHIFT_ADDRESS` + `EMAILS_SHIFT_NAME` | Boîte créneaux |
  | `emails.base_domain` | `EMAILS_BASE_DOMAIN` | Domaine racine pour génération d'adresses |
  | `send_email_copy_to_admin_for_booked_shift` | `SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT` | Copie admin pour chaque réservation de créneau — **défaut hardcodé `true`** dans `services.yaml:72` |

  **Affichage métier**

  | Variable env | Valeur dist | Description |
  |---|---|---|
  | `LOCAL_CURRENCY_NAME` | `"monnaie locale"` | Nom de la monnaie locale (affiché partout dans l'UI) |
  | `MAX_NB_OF_PAST_CYCLES_TO_DISPLAY` | `3` | Nb de cycles passés visibles dans les historiques |
  | `MAX_EVENT_PROXY_PER_MEMBER` | `1` | Nb max de procurations événement par membre |

  ### Findings

  #### 🔴 `CYCLE_DURATION` ignoré dans `MembershipService` — 4 valeurs hardcodées à `28`

  `MembershipService` reçoit `cycle_type` et `registration_duration` via `getParameter()` dans son constructeur, **mais pas `cycle_duration`**. Quatre emplacements utilisent `28` en dur :

  | Fichier:ligne | Code | Impact |
  |---|---|---|
  | `MembershipService.php:75` | `'+28 days'` dans `canRegister()` | Fenêtre de pré-inscription fixe à 28 j (coïncide avec cycle par défaut — non critique si CYCLE_TYPE=abcd) |
  | `MembershipService.php:146` | `floor($diff / 28)` dans `getStartOfCycle()` | Calcul du numéro de cycle courant — **cassé si CYCLE_DURATION ≠ '28 days'** |
  | `MembershipService.php:147` | `(28 * $currentCycleCount)` dans `getStartOfCycle()` | Idem |
  | `MembershipService.php:155–156` | `(28 * $cycleOffset)` dans `getStartOfCycle()` — avec **TODO développeur explicite** | Décalage entre cycles — **cassé si CYCLE_DURATION ≠ '28 days'** |
  | `MembershipService.php:170` | `"+27 days"` dans `getEndOfCycle()` | Fin de cycle = début + 27 j — **cassé si CYCLE_DURATION ≠ '28 days'** |
  | `MembershipService.php:181` | `"+28 days"` dans `getCycleNumber()` | Itération sur les cycles — **cassé si CYCLE_DURATION ≠ '28 days'** |

  **Périmètre** : `CYCLE_TYPE=abcd` n'est **pas** affecté (branche dédiée qui calcule depuis la semaine ISO). Les valeurs hardcodées impactent uniquement `cycle_type != "abcd"` (cycles flottants depuis `firstShiftDate`). En pratique Elefan utilise `abcd` et Scopeli probablement aussi — le bug est donc dormant, mais constitue une dette technique explicite (TODO dans le code) et un risque lors d'onboarding d'une nouvelle instance.

  **TODO** : injecter `cycle_duration` dans `MembershipService` et remplacer les 5 occurrences de `28` (lignes 146, 147, 156, 170, 181) et `27` (ligne 170) par le paramètre. À noter dans **SF-PREP.2** (migration injection constructeur).

  #### 🟡 Unités implicites non documentées dans `.env.dist`

  Quatre familles d'unités coexistent sans que `.env.dist` ne les documente :

  | Famille | Variables concernées | Unité attendue |
  |---|---|---|
  | PHP natural language | `CYCLE_DURATION`, `REGISTRATION_DURATION`, `MAX_TIME_IN_ADVANCE_TO_BOOK_EXTRA_SHIFTS` | Chaîne passée à `DateInterval::createFromDateString()` (ex: `'28 days'`, `'1 year'`) |
  | Minutes | `DUE_DURATION_BY_CYCLE`, `MIN_SHIFT_DURATION`, `FORBID_SHIFT_OVERLAP_TIME`, `MAX_TIME_AT_END_OF_SHIFT` | Entier en minutes |
  | Jours | `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY`, `TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS` | Entier en jours |
  | Heures (négatif) | `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS` | Entier en heures, **valeur négative** représentant une dette acceptable |

  `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS=-9` est comparé comme `SUM(timelog.time) < value * 60` (minutes dans la DB × 60 pour obtenir les secondes selon la requête DQL). La sémantique réelle est : **"un membre est 'en retard' si son solde de temps est inférieur à N heures"** (N négatif = dette). Le nom de la variable laisse entendre une durée temporelle après un créneau — la doc dans `.env.dist` est absente.

  **TODO** : ajouter des commentaires d'unité dans `.env.dist` pour chaque paramètre numérique (ex: `# minutes`, `# jours`, `# heures — négatif = seuil de dette`).

  #### 🔵 Valeur par défaut `send_email_copy_to_admin_for_booked_shift` hardcodée dans `services.yaml`

  `services.yaml:72` : `send_email_copy_to_admin_for_booked_shift_default: true`. Le défaut `true` est hardcodé dans le YAML, non dans `.env.dist`. Comportement cohérent (le `default:` de Symfony fonctionne), mais la valeur par défaut effective est invisible depuis `.env.dist`. Le commentaire dans le dist (`# SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT=false`) suggère `false` pour les nouvelles instances — en contradiction avec le défaut applicatif `true`.

---

## LOG — Observabilité

- [x] **LOG.1** — Configuration Monolog
  > Lire `config/packages/monolog.yaml` et variantes. Channels, handlers, niveaux, rotation → documentation finale.

  **Versions** : `monolog/monolog` 1.27.1, `symfony/monolog-bundle` v3.8.0.

  **Architecture par environnement**

  | Env | Pipeline de logging |
  |-----|---------------------|
  | `dev` / `test` | `stream` (debug, `!event`) + `stdout` (`LOGGING_STDOUT_LEVEL`) + `console` (`!event`, `!doctrine`, `!console`) + `server_log` (127.0.0.1:9911) |
  | `prod` | `fingers_crossed` (action\_level=info) → `grouped` → `[file` (warning), `mattermost` (`LOGGING_MATTERMOST_LEVEL`), `stdout` (`LOGGING_STDOUT_LEVEL`)] |

  **Composants custom (`src/Monolog/`)**

  - `MonologUserProcessor` — enrichit chaque log record avec l'ID utilisateur connecté + son nom d'affichage (`getDisplayNameWithMemberNumber()`). Taggé `monolog.processor` globalement (tous channels). Gère correctement le cas sans token (check L21 avant `getUser()`).
  - `ToggleableHandler` — wraps les handlers `stdout` et `mattermost` avec un flag enable/disable (`LOGGING_STDOUT_ENABLED`, `LOGGING_MATTERMOST_ENABLED`). Défini via `decorates:` Symfony DI. En prod, déclinaison supplémentaire pour `mattermost` dans `config/packages/prod/services.yaml`.

  **Paramètres d'env associés**

  | Variable | Usage | Env |
  |----------|-------|-----|
  | `LOGGING_STDOUT_LEVEL` | Niveau minimum du handler stdout | all |
  | `LOGGING_STDOUT_ENABLED` | Active/désactive stdout | all |
  | `LOGGING_MATTERMOST_ENABLED` | Active/désactive Mattermost | prod |
  | `LOGGING_MATTERMOST_URL` | Webhook Mattermost | prod |
  | `LOGGING_MATTERMOST_CHANNEL` | Channel Mattermost cible | prod |
  | `LOGGING_MATTERMOST_LEVEL` | Niveau minimum du handler Mattermost | prod |

  **Channels** : aucun channel custom applicatif déclaré. Tous les services injectent `LoggerInterface` → channel `app` par défaut. Seuls les channels built-in Symfony (`event`, `doctrine`, `console`) sont référencés pour les exclusions.

  **Findings**

  - 🟡 **Pas de rotation en prod** : le handler `file` est `type: stream` (pas `rotating_file`). Aucun logrotate externe trouvé. Le fichier `var/log/prod.log` croît indéfiniment. → TODO : passer à `rotating_file` avec `max_files: 30`, ou documenter la rotation infra.
  - 🟡 **`fingers_crossed` avec `action_level: info` atypique** : le pattern classique est `action_level: error` (buffer les logs debug pour les exposer quand une erreur survient). Ici, le buffer est flushé dès le premier log INFO — ce qui arrive très tôt dans chaque requête. Le handler `file` (level: warning) ignore ensuite les debug/info flushés ; les handlers `stdout`/`mattermost` ont leur propre seuil configurable. L'effet protective du `fingers_crossed` est quasi nul dans cette config. → TODO : passer à `action_level: error` ou supprimer le `fingers_crossed` au profit d'un `group` direct.
  - 🔵 **Déduplication Mattermost désactivée** : l'handler `deduplicated` (type: `deduplication`, time: 20s) est commenté dans `prod/monolog.yaml`. Sans déduplication, une erreur répétitive peut spammer le channel. À remettre si la charge d'alertes est un problème.
  - 🔵 **`MonologUserProcessor` taggé globalement** : s'applique à tous les channels y compris les commandes CLI et les queues. Impact performance négligeable (lookup `TokenStorage` par record), mais la dépendance sur `security.token_storage` génère un appel inutile hors contexte HTTP. Acceptable en l'état.
  - 🔵 **Aucun channel custom applicatif** : tous les services (shifts, paiements, auth, swipe) logguent dans `app`. Impossible de router/filtrer par domaine. Limitation actuelle acceptable ; à corriger en SF5 avec injection de channels nommés.
  - 🔵 **`server_log` handler (dev/test)** : port 9911 hardcodé. Requiert `bin/console server:log -vv` en écoute pour ne pas générer d'erreur de connexion silencieuse.

- [x] **LOG.2** — Ce qui est loggé
  > `grep -rn "logger->\|LoggerInterface" src/`. Événements métier critiques non loggés ? `catch` silencieux ? → TODO.

  **Classes loggant via LoggerInterface (7)**

  | Classe | Canal | Contenu |
  |--------|-------|---------|
  | `EventListener/EmailingEventListener` | `app` | 13× `info("Emailing Listener: on<Event>")` — label seul, pas de contexte |
  | `EventListener/TimeLogEventListener` | `app` | 6× `info("Time Log Listener: on<Event>")` — label seul |
  | `EventListener/CommissionEventListener` | `app` | 2× `info("Commission Listener: onLeave/onJoin")` — label seul |
  | `EventListener/MattermostEventListener` | `app` | 1× `info("Mattermost Listener: onShiftAlerts")` — label seul |
  | `EventListener/ShiftFreeLogEventListener` | `app` | 1× `info("Shift Free Log Listener: onShiftFreed")` — label seul |
  | `EventListener/PeriodPositionFreeLogEventListener` | `app` | 1× `info("PeriodPosition Free Log Listener: onPeriodPositionFreed")` — label seul |
  | `EventListener/CodeEventListener` | `app` | 1× `info("Code Listener: onCodeNew")` — label seul |
  | `Twig/Extension/AppExtension` | `app` | `error("QR Code/Barcode generation error: ".$e->getMessage())` — correct |
  | `Controller/SwipeCardController` | `app` | `error($exception->getMessage())` sur catch générique — message seul |
  | `Controller/CodeController` | `app` | 5× `info("CODE : <action>", ['username' => ...])` — contexte username |
  | `Command/UpdateHelloAssoPaymentsCommand` | `app` | `info("Fetching page N")` + count résultats — correct |
  | `Providers/Helloasso/HelloassoPaymentHandler` | `app` | `info("Payment already in db")`, `info("Processing payment #N for email")` — bon niveau de détail |

  **Pattern commun : "label seul" = logs inutiles**

  Les 25 appels des 7 event listeners (Emailing, TimeLog, Commission, Mattermost, ShiftFreeLog, PeriodPositionFreeLog, CodeEvent) se résument à `logger->info("XxxListener: onSomeEvent")`. Aucun ID d'entité (shift, member, beneficiary), aucun contexte métier. En prod, avec le filtre `warning` du handler `file` et le buffer `fingers_crossed(action_level: info)` flushé quasi instantanément (cf. LOG.1), ces lignes INFO n'atteignent même pas le fichier de log — elles sont émises dans le vide.

  **Couche service : aucun logger**

  Zéro injection de `LoggerInterface` dans `src/Service/`. Les services (`MembershipService`, `TimeLogService`, `SwipeCardHelper`, etc.) ne tracent rien — toute la logique métier non triviale (calcul de cycles, compteurs de temps, cotisations) est silencieuse.

  **Couche controller critique : aucun logger**

  | Controller | Actions sensibles non tracées |
  |------------|-------------------------------|
  | `UserController` | `addRole()` L238, `removeRole()` L205 — admin ROLE_ADMIN, ROLE_SUPER_ADMIN |
  | `MembershipController` | `setFrozen(true/false)` L546/575, `setWithdrawn()` L655/664, création membre L807 |
  | `ShiftController` | booking, freeing, validation manuelle — tracé uniquement via event listeners (labels seuls) |
  | `BeneficiaryController` | ré-activation de compte (`setWithdrawn(false)`) L143 |
  | `AdminController` | (pas de logger non plus) |

  **Catches silencieux ou problématiques (6)**

  1. 🔴 **`EmailingEventListener:256-258`** — `catch (\Exception $e) { die($e->getMessage()); }` — **`die()` en plein event listener**. Si la construction de l'email Helloasso `TOO_EARLY` lève une exception (Twig, SMTP, etc.), la requête meurt en renvoyant le message brut de l'exception en HTTP 200 vide, sans log Monolog, sans flash, sans stack trace structurée. Comportement identique à un crash silencieux côté monitoring.

  2. 🟡 **`BookingController:358-362`** — `catch (Exception $ex)` sans log ni flash : le parsing de la semaine/année du formulaire de filtre échoue silencieusement, fallback sur `$defaultFrom/$defaultTo`. L'utilisateur ne voit rien, les logs non plus.

  3. 🟡 **`DefaultController:174,188`** — deux catches sur les webhooks Helloasso entrants : `InvalidArgumentException` → HTTP 422, `ClientExceptionInterface` → HTTP 500 — aucun log. Les échecs de webhooks de paiement sont invisibles dans Monolog.

  4. 🟡 **`HelloassoController:83,101,125`** — 3 catches `ClientExceptionInterface` → `addFlash('error', ...)` seulement. Toute indisponibilité de l'API HelloAsso disparaît des logs.

  5. 🟡 **`MailController:167-169`** — `catch (TransportExceptionInterface)` : les adresses en échec sont collectées dans `$errored[]` puis affichées en flash, jamais loggées. Aucune trace structurée d'un envoi en masse partiellement raté.

  6. 🔵 **`HelloassoEventListener`** (pas de `catch`, mais pas de logger) — `linkPaymentToUser()` peut atteindre deux branches commentées (`//throw new LogicException(...)`) pour "user not found" et "user cannot register yet" : les cas d'anomalie sont convertis en sous-events (`TOO_EARLY`, `RE_REGISTRATION_SUCCESS`) mais aucun `logger->warning()` n'accompagne la décision. Si l'email Helloasso ne correspond à aucun utilisateur, seul un email est envoyé au payeur — aucune trace en log.

  **Commande `CloseMembershipCommand` : `$output->writeln()` ≠ Monolog**

  La fermeture massive de comptes (`setWithdrawn(true)`) est tracée via `$output->writeln()` uniquement — visible uniquement en mode interactif (cron sans capture de sortie = silencieux). Pas de logger injecté : zéro trace Monolog ni Mattermost.

  **Authentification OIDC/Keycloak : aucun log**

  `KeycloakAuthenticator` dispatche `BeneficiaryCreatedEvent` lors de la première connexion SSO, mais n'a pas de logger. La création de compte via SSO n'est pas tracée. `AuthenticationSuccessHandler` et `OidcLogoutHandler` n'ont pas non plus de logger : les connexions/déconnexions réussies sont invisibles.

  **Findings**

  - 🔴 **`die()` dans `EmailingEventListener`** (L256-258) : supprimer la clause `try/catch` ou la remplacer par un log `error` + re-throw ; `die()` n'a pas sa place dans un event listener.
  - 🟡 **Labels sans contexte** : les 25 logs "Listener: onEvent" sont du bruit. Les remplacer par des messages avec IDs d'entités (ex. `"Shift #%d booked by member #%d"`) ou les supprimer. Requiert SF5 pour injection propre de channels nommés.
  - 🟡 **Catches sans log sur les flux critiques** : `DefaultController` (webhooks Helloasso), `HelloassoController` (appels API), `MailController` (envois en masse) devraient logger au moins en `warning` avant de retourner une réponse HTTP d'erreur.
  - 🟡 **`CloseMembershipCommand`** : injecter `LoggerInterface` et logger chaque fermeture de compte (membre + date) en `info`, et le total en `notice`.
  - 🔵 **Couche service/controller sans logger** : à corriger en priorité lors du refactor SF5 — les actions sensibles (role, frozen, withdrawn) devraient être tracées avec actor + target + nouvelle valeur.
  - 🔵 **Auth OIDC non tracée** : loguer `info("OIDC login: user %s")` et `info("OIDC: new beneficiary created %s")` dans `KeycloakAuthenticator`.

- [x] **LOG.3** — Traçabilité des actions sensibles
  > Actions sensibles (changement rôle, suppression, validation paiement) tracées ? → TODO si manquant.

  **Résultat : aucune action sensible n'est tracée dans les logs.**

  #### Inventaire des actions sensibles et leur état de traçage

  | Action | Route / Fichier | Rôle requis | Log ? |
  |---|---|---|---|
  | Ajout d'un rôle | `user_add_role` — `UserController:222` | ROLE_ADMIN | ❌ aucun |
  | Retrait d'un rôle | `user_remove_role` — `UserController:189` | ROLE_ADMIN | ❌ aucun |
  | Suppression utilisateur | `user_delete` — `UserController:304` | ROLE_SUPER_ADMIN | ❌ aucun |
  | Suppression pré-adhésion | `pre_user_delete` — `UserController:368` | ROLE_USER_MANAGER | ❌ aucun |
  | Suppression bénéficiaire | `beneficiary_delete` — `BeneficiaryController:166` | voter `edit` | ❌ aucun |
  | Fermeture/réouverture compte | `member_withdrawn` — `MembershipController:639` | ROLE_USER_MANAGER | ❌ aucun |
  | Suppression membre | `member_delete` — `MembershipController:687` | ROLE_SUPER_ADMIN | ❌ aucun |
  | Nouvelle adhésion (manuel) | `member_new_registration` — `MembershipController:231` | voter `edit` | ❌ aucun |
  | Modification adhésion | `registration_edit` — `RegistrationsController:191` | ROLE_FINANCE_MANAGER | ❌ aucun |
  | Suppression adhésion | `registration_remove` — `RegistrationsController:212` | ROLE_SUPER_ADMIN | ❌ aucun |
  | Suppression paiement HA | `helloasso_payment_remove` — `HelloassoController:153` | ROLE_SUPER_ADMIN | ❌ aucun |
  | Édition paiement HA | `helloasso_payment_edit` — `HelloassoController:179` | ROLE_FINANCE_MANAGER | ❌ aucun |
  | Résolution orphelin HA | `helloasso_confirm_resolve_orphan` — `HelloassoController:264` | ROLE_USER | ❌ aucun |
  | Changement de mot de passe | `user_change_password` — `UserController:113` | IS_AUTHENTICATED_FULLY | ❌ aucun |
  | Changement d'email | `set_email` — `MembershipController:425` | non vérifié | ❌ aucun |
  | Traitement paiement HA (auto) | `HelloassoPaymentHandler::savePayments` | — | ⚠️ INFO uniquement |
  | Import paiements HA (commande) | `UpdateHelloAssoPaymentsCommand` | — | ⚠️ INFO uniquement |
  | Création code d'accès | `code_new` — `CodeController:191` | — | ⚠️ INFO uniquement |

  Les 3 cas marqués ⚠️ INFO sont silencieux en production (handler `file` filtre à `warning`, cf. LOG.1) — ils n'atteignent pas le fichier de log.

  #### Exception partielle — `Membership::withdrawn`
  La fermeture d'un compte est partiellement tracée **en base** via les champs `withdrawn_date` et `withdrawn_by_id` de l'entité `Membership` (`src/Entity/Membership.php:48-56`). C'est la seule action sensible qui laisse une trace persistante non-log. Limitations :
  - ne couvre que la fermeture, pas la réouverture (les deux champs sont remis à `null` lors du `setWithdrawn(false)`, `Membership.php:347-349`)
  - ne stocke que l'état le plus récent (pas d'historique des fermetures successives)
  - le rôle de l'admin ayant effectué l'opération est enregistré (`withdrawnBy`) mais sans timestamp de réouverture

  #### Conséquences
  - **Zéro audit trail** pour les changements de rôles (ROLE_ADMIN, ROLE_SUPER_ADMIN) : un ROLE_SUPER_ADMIN peut élever ou révoquer n'importe quel privilège sans trace.
  - **Zéro audit trail** pour les suppressions d'entités (User, Membership, Beneficiary, Registration, HelloassoPayment) : une suppression est irréversible et non tracée.
  - **Zéro audit trail** pour les opérations financières manuelles (création, modification, suppression d'adhésions et de paiements) : impossible de reconstituer qui a enregistré ou modifié un paiement.
  - Le changement de mot de passe et d'email n'est pas loggué, ce qui empêche de détecter une compromission de compte.

  **→ TODO** : Ajouter des logs `warning` (ou `security` channel) sur toutes les actions sensibles du tableau ci-dessus avec contexte `[actor_id, target_id, action, old_value/new_value]`. Priorité : changements de rôle et suppressions d'entités (irréversibles). L'ajout d'un channel `security` dédié dans Monolog permettrait une rotation et un seuil indépendants du canal applicatif.

---

## DB — Santé du schéma

- [x] **DB.1** — Validation schéma vs entités
  > `docker compose exec -T php php bin/console doctrine:schema:validate`. Divergences → TODO.
  >
  > **Résultat** : Mapping **OK** — aucune erreur d'annotation. Database **NOT IN SYNC**.
  >
  > ---
  >
  > ### Volume et nature des divergences
  >
  > - **37 `ALTER TABLE`** sur 37 tables (Doctrine DBAL 2.13.9).
  > - **0 `CREATE TABLE` / `DROP TABLE`** : toutes les tables sont présentes, pas de table fantôme, pas de table manquante.
  >
  > **Tables affectées** : `access_token`, `address`, `anonymous_beneficiary`, `auth_code`, `beneficiary`, `client`, `closing_exception`, `code`, `commission`, `dynamic_content`, `email_template`, `event`, `formation`, `fos_user`, `helloasso_payment`, `job`, `membership`, `membership_shift_exemption`, `note`, `opening_hour`, `opening_hour_kind`, `period`, `period_position`, `period_position_free_log`, `process_update`, `proxy`, `refresh_token`, `registration`, `service`, `shift`, `shiftfreelog`, `social_network`, `swipe_card`, `swipe_card_log`, `task`, `time_log`, `view_abstract_registration`.
  >
  > ---
  >
  > ### Pattern unique — `DEFAULT NULL` manquant (36 cas / 🟡 Faible impact)
  >
  > Toutes les divergences sauf une suivent le même patron :
  > ```sql
  > ALTER TABLE foo CHANGE col_nullable col_nullable INT DEFAULT NULL;
  > ```
  > La colonne existe dans la DB, est nullable, mais **sans la clause `DEFAULT NULL` explicite** requise par le mapping Doctrine 2.13.9.
  >
  > **Cause racine** : La DB a été créée ou les migrations ont été générées avec une version antérieure de Doctrine DBAL qui n'émettait pas `DEFAULT NULL` sur les colonnes nullable. DBAL 2.13.9 compare les définitions colonne à colonne et détecte l'absence comme une divergence.
  >
  > **Impact fonctionnel : nul.** MariaDB traite une colonne `INT` nullable sans `DEFAULT NULL` explicite identiquement à une colonne avec `DEFAULT NULL` — le comportement à l'INSERT est identique dans les deux cas. Les 36 cas sont donc une divergence **déclarative** (métadonnées), pas comportementale.
  >
  > ---
  >
  > ### Cas particulier — `dynamic_content.type` (🟠 Impact potentiel)
  >
  > ```sql
  > ALTER TABLE dynamic_content CHANGE type type VARCHAR(64) DEFAULT 'general' NOT NULL;
  > ```
  >
  > L'annotation Doctrine déclare :
  > ```php
  > @ORM\Column(name="type", type="string", length=64, options={"default": "general"})
  > ```
  > La DB manque la clause `DEFAULT 'general'` sur cette colonne `NOT NULL`.
  >
  > **Impact fonctionnel** : si un INSERT SQL direct (hors Doctrine, ex. script de migration manuelle, fixture externe) omet la colonne `type`, MariaDB retournera une erreur (colonne NOT NULL sans valeur par défaut). Doctrine passe toujours la valeur explicitement — aucun bug runtime constaté à ce jour — mais le schéma DB ne reflète pas l'intention de l'annotation.
  >
  > ---
  >
  > ### Remédiation recommandée
  >
  > | Action | Commande | Priorité |
  > |--------|---------|---------|
  > | Générer la migration | `php bin/console doctrine:migrations:diff` | 🟡 Faible |
  > | Réviser le diff | Vérifier que seuls des `ALTER TABLE CHANGE … DEFAULT NULL` et `DEFAULT 'general'` apparaissent | — |
  > | Committer et appliquer | Conventional Commit `fix:` | 🟡 Faible |
  >
  > **Ne pas utiliser** `doctrine:schema:update --force` : dangereux en production (pas de transaction, pas de versionnement).
  >
  > Ce correctif est purement déclaratif — aucun risque de perte de données.
  >
  > ---
  >
  > ### Résumé DB.1
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🟡 Faible | 36 colonnes nullable sans `DEFAULT NULL` explicite (divergence déclarative, 0 impact runtime) | XS (1 migration) |
  > | 🟠 Moyen | `dynamic_content.type` — `DEFAULT 'general'` absent du schéma DB (colonne NOT NULL sans défaut en DB) | XS (inclus dans la même migration) |
  >
  > → **TODO SYN.2** — `doctrine:migrations:diff` + révision + merge. Effort XS.

- [x] **DB.2** — État des migrations
  > `docker compose exec -T php php bin/console doctrine:migrations:status`. Migrations en attente ou orphelines → TODO.
  >
  > ### Résumé DB.2
  >
  > **Commande** : `doctrine:migrations:status` + `doctrine:migrations:list` + lecture Makefile + lecture `dploy.sh`.
  >
  > **État constaté** :
  > - Table `migration_versions` **absente** de la base Docker : Doctrine considère les 99 migrations comme *non exécutées*.
  > - La base Docker a été créée via `doctrine:schema:create` (cible `db-reset` du Makefile), qui court-circuite complètement le système de migrations.
  > - Les 99 migrations couvrent 5 ans d'historique (2018-11 → 2023-12).
  > - Aucune migration orpheline (version en DB sans fichier sur disque) — car la table de suivi n'existe pas.
  >
  > **Flux de bootstrap actuel** :
  > ```
  > make setup-test
  >   └─► db-fixtures
  >         └─► db-reset
  >               ├─ doctrine:database:drop --force --if-exists
  >               ├─ doctrine:database:create
  >               └─ doctrine:schema:create          ← bypasse les migrations
  > ```
  > La cible `db-migrate` (`doctrine:migrations:migrate`) existe mais n'est jamais appelée par `setup-test` ni par aucune autre cible.
  >
  > **Production** : `dploy.sh` ne lance pas les migrations — le bloc est commenté depuis l'origine avec un `#todo` explicite (ligne 4 et 74 du script).
  >
  > **Risque** : si quelqu'un lançait `doctrine:migrations:migrate` sur la base courante, 98 des 99 migrations n'ont pas de garde `skipIf`/`IF NOT EXISTS` — elles échoueraient avec "table already exists". Seule `Version20181103153303_initial` contient un `$this->skipIf($schema->hasTable('fos_user'), …)`.
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🔴 Élevé | Système de migrations incohérent : schema:create en test, migrations commentées en prod — les migrations ne sont jamais appliquées nulle part | M |
  > | 🟠 Moyen | `db-migrate` est une cible isolée non appelée par `setup-test` ; `migration_versions` absente → état de suivi inexistant | XS |
  > | 🟠 Moyen | 98 migrations sans garde d'idempotence — `migrate` sur schéma existant → crash garanti | S |
  >
  > → **TODO SYN.3** — Après `schema:create` en test, synchroniser le tracking Doctrine : `doctrine:migrations:version --add --all --no-interaction`. À ajouter dans la cible `db-reset` du Makefile juste après `schema:create`. Effort XS.
  >
  > → **TODO SYN.4** — Rétablir les migrations en production : décommenter et compléter le bloc migration dans `dploy.sh` (ligne 74), en lançant `doctrine:migrations:version --add --all` une seule fois sur les instances existantes pour synchroniser l'état, puis `doctrine:migrations:migrate --no-interaction` à chaque déploiement. Effort S (coordination avec les deux instances, Elefan + Scopeli).

- [x] **DB.3** — Qualité des migrations
  > Migrations avec `down()` vides, opérations irréversibles sans warning → TODO.
  >
  > ### Résumé DB.3
  >
  > **Méthode** : grep systématique sur les 99 fichiers (`TRUNCATE`, `DROP COLUMN`, `DELETE FROM` en `down()`, `$this->container`, garde plateforme) + lecture ciblée des cas flaggés.
  >
  > **Findings** :
  >
  > **1. `TRUNCATE` en `up()` + `down()` vide** (`Version20190430204903_swipe_card`)
  > `up()` exécute `TRUNCATE swipe_card` (purge complète), `down()` est vide. C'est une purge de données ponctuelle déguisée en migration — irréversible par conception. Non bloquant (la table était supposément vide à l'époque), mais trompeur pour qui lirait le log de migration.
  >
  > **2. `ContainerAwareInterface` + `$this->container`** (2 migrations — bloquant SF5+)
  > ```
  > Version20190218130524_job_id_not_null.php    ← $container->get('doctrine.orm.entity_manager')->getConnection()
  > Version20190402014558_add_role_to_never_logged_user.php ← $em->getRepository(User::class)->...->flush()
  > ```
  > Ces migrations implémentent `ContainerAwareInterface`. Dans `doctrine/migrations` 3.x (requis par Symfony 5+), la propriété `$container` n'est plus injectée — ces migrations **crashent** à l'exécution. Bloquant pour SF-PREP.
  >
  > **3. `DROP COLUMN` en `down()` — perte de données sur rollback** (2 migrations)
  > ```
  > Version20200301100000.php         ← ALTER TABLE shift DROP COLUMN fixe
  > Version20210224215308.php         ← ALTER TABLE swipe_card_log DROP COLUMN counter
  > ```
  > Un rollback (`doctrine:migrations:migrate prev`) détruit irrémédiablement les données de ces colonnes.
  >
  > **4. `DELETE FROM` en `down()` — suppression de données métier** (4 migrations)
  > ```
  > Version20190111150938.php
  > Version20191021000000_home_dynamic_content.php
  > Version20230519151558_dynamic_content_pre_membership_email.php
  > Version20230520171433_dynamic_content_home_bottom.php
  > ```
  > Ces migrations seedent des `dynamic_content` en `up()` et font `DELETE FROM` en `down()`. Un rollback supprime les données seedées (et potentiellement les données saisies ensuite par les admins).
  >
  > **5. Migrations sans garde de plateforme MySQL** (6 migrations)
  > `Version20190214200309`, `Version20190402014558`, `Version20191218002203`, `Version20200708035603`, `Version20190218130524`, `Version20190324212024` — manquent du `$this->abortIf(platform !== 'mysql')`. Risque faible (la DB est MariaDB partout), mais incohérent avec les 93 autres.
  >
  > | Gravité | Finding | Effort |
  > |---------|---------|--------|
  > | 🔴 Élevé | 2 migrations `ContainerAwareInterface` — cassent avec doctrine/migrations 3.x requis par SF5+ | S |
  > | 🟠 Moyen | TRUNCATE en `up()` + `down()` vide (purge ponctuelle non documentée) | XS |
  > | 🟠 Moyen | `DROP COLUMN` en `down()` sur 2 migrations — rollback = perte de données colonne | XS (doc) |
  > | 🟠 Moyen | `DELETE FROM` en `down()` sur 4 migrations — rollback = suppression de données seedées | XS (doc) |
  > | 🟡 Faible | 6 migrations sans garde de plateforme MySQL (`abortIf`) | XS |
  >
  > → **TODO MIG.1** — Réécrire les 2 migrations `ContainerAwareInterface` en SQL natif pur (`$this->addSql()`), sans passer par l'EntityManager. Bloquant pour la migration SF5+. Effort S.
  >
  > → **TODO MIG.2** — Ajouter un commentaire d'en-tête dans `Version20190430204903_swipe_card` documentant la nature de la purge et l'impossibilité de rollback. Effort XS.
  >
  > → **TODO MIG.3** — Marquer les 6 migrations à `down()` destructif (`DROP COLUMN` / `DELETE FROM`) avec un commentaire explicite `// WARNING: down() is destructive — data cannot be recovered`. Effort XS.

---

## CI — Qualité pipeline

- [x] **CI.1** — Lire `.github/workflows/ci.yaml`
  > Structure, versions PHP/Node fixées ou flottantes, secrets scopés. Documenter ici.

  **Structure** : 6 jobs — `setup` (install + build assets + upload artifacts) suivi de 5 jobs parallèles :
  - `fast-tests` : tests unit + intégration sans DB
  - `phpstan` : analyse statique via `make lint`
  - `symfony-tests` : tests fonctionnels avec MariaDB
  - `cypress-tests` : E2E Cypress (main + shift + membership) avec MariaDB + Symfony server
  - `cypress-tests-oidc` : E2E Cypress OIDC avec MariaDB + Keycloak

  Pattern artifact pass-through : `vendor/` et `public/build/` générés dans `setup`, uploadés, téléchargés par chaque job.

  **Versions PHP/Node** :
  - PHP : matrice déclarée (`matrix.php-versions: ['7.4']`) mais factice — un seul élément. Les jobs enfants hardcodent `7.4` directement (ne lisent pas la matrice). Version fixe : bonne reproductibilité.
  - Node : `20.11.0` fixé dans `setup` et `cypress-tests*`. Cohérent.
  - **Gap** : la CI teste PHP 7.4, le container Docker tourne PHP 8.1, et les 350 tests passent sous 8.1. La CI ne valide pas le runtime de déploiement réel.

  **Épinglage des actions** :
  - `actions/checkout@v4`, `actions/setup-node@v4`, `actions/cache@v4`, `actions/upload-artifact@v4`, `actions/download-artifact@v4` : épinglés au major (v4), pas au SHA digest — standard acceptable mais exposé aux breaking changes silencieux.
  - **`shivammathur/setup-php@verbose`** : `@verbose` n'est PAS un tag sémantique — c'est un alias comportemental qui pointe vers une branche/ref flottante. Risque supply chain : une mise à jour de `@verbose` s'applique sans contrôle de version. → **TODO CI.A**

  **Keycloak** : image `quay.io/keycloak/keycloak:26.0` — tag sémantique, pas de digest SHA. Mise à jour d'image possible lors d'un rebuild.

  **Secrets** : aucun `${{ secrets.* }}` dans le workflow. Les credentials MariaDB CI (`MYSQL_ROOT_PASSWORD: secret`, `MYSQL_PASSWORD: password`) sont en clair — volontaire et acceptable pour des services éphémères CI.

  **Symfony CLI via `curl | bash`** : pattern `curl -sS https://get.symfony.com/cli/installer | bash` dans `cypress-tests` et `cypress-tests-oidc`. Risque MITM classique ; atténué par HTTPS mais non vérifiable par hash.

  **Déclencheurs** : `on: push` sans filtre de branche → la CI tourne sur tout push vers n'importe quelle branche + sur PR vers `master`/`staging`/`dev`. Intentionnel pour feedback rapide, mais consomme des minutes CI sur toutes les branches de travail.

  **Absence** : pas de job lint JS/CSS (ESLint, Stylelint) dans la CI. PHPStan couvre uniquement PHP.

  **Findings** :
  - `shivammathur/setup-php@verbose` non versionné → TODO CI.A (pin à une version sémantique)
  - Gap PHP 7.4 (CI) vs PHP 8.1 (prod/docker) → TODO CI.B (ajouter PHP 8.1 à la matrice ou remplacer 7.4)
  - Pas de lint JS/CSS en CI → TODO CI.C (optionnel, effort XS)

- [x] **CI.2** — Tests flaky et couverture E2E

  **Suite E2E** : 9 fichiers Cypress, 2 groupes CI (`cypress-tests` et `cypress-tests-oidc`), Cypress 13.6.4.  
  Scénarios principaux : login super-admin, freeze/unfreeze, réservation de créneau, réadhésion, login Keycloak.

  **Patterns flaky identifiés** :

  1. **`cy.wait(N)` hardcodés pour les animations Materialize** — `super_admin_can_freeze_unfreeze_user.cy.js` : 4×`cy.wait(500)` pour laisser les animations collapsible/modal se terminer (lignes 30, 36, 57, 63). Sur un runner CI lent, 500 ms peut être insuffisant ; sur un runner rapide, c'est du délai inutile. Pattern classique de flakiness liée aux animations CSS.

  2. **Skips silencieux déguisés en succès** — `member_can_book_shift.cy.js` (test "book a shift") : si aucun créneau réservable n'est trouvé, le test log `'No bookable shifts found — skipping'` et retourne sans `throw`. Le test est vert même quand le chemin principal n'a pas été testé. Même problème dans `member_can_register.cy.js` : le test "admin can re-register" a trois branches (`if hasForm / else if hasTooEarly / else`) dont aucune ne provoque d'échec — le résultat dépend entièrement de l'état des fixtures aléatoires. Ces tests ne *garantissent* rien sur le comportement métier.

  3. **`Cypress.on('uncaught:exception', () => false)` global dans tous les fichiers** — écrase toutes les erreurs JS non attrapées. Masque les régressions front-end réelles et contribue à la couleur verte des tests indépendamment de l'état de l'application.

  4. **Mutation de base de données sans nettoyage** — `member_can_register.cy.js` est marqué `// MODIFIES DATABASE`. Pas d'`afterEach` ni de reset. Si le job CI retry ou si les tests sont relancés sur le même runner, l'état DB est sale et les fixtures aléatoires aggravent l'imprévisibilité (le membre 1 peut avoir ou non une adhésion valide).

  5. **Logique conditionnelle dans `loginKeycloak`** (`keycloak_reusables.cytools.js`) — le `cy.location().then()` avec re-click conditionnel sur `#kc-login` est timing-dépendant : si Keycloak répond assez vite pour avoir déjà redirigé, le `if` ne s'exécute pas ; sinon, il clique à nouveau. Comportement non déterministe selon la latence Keycloak.

  6. **Pas de configuration `retries` dans `cypress.config.js`** — aucun `retries: { runMode: N }`. Un échec transitoire (timeout réseau, race condition d'animation) est fatal au job CI au lieu d'être rejoué. Absence de filet de sécurité pour les flakiness résiduelles.

  **Couverture E2E vs routes existantes** :

  Total routes application : **238** (hors `_profiler`, `_wdt`, `_preview_error`).  
  Routes touchées par Cypress (directement ou par redirect) : **≈12** (login, profile, member_show, freeze/unfreeze, new_registration, booking, shift_book, oauth_login/check, homepage).  
  **Taux de couverture E2E : ~5%.**

  Domaines fonctionnels sans aucun scénario Cypress :

  | Domaine | Routes concernées | Criticité |
  |---|---|---|
  | Gestion des bénéficiaires | `beneficiary_edit`, `set_main`, `detach`, `delete`, `confirm` | Haute |
  | Cycle de vie adhérent | `member_new`, `member_withdrawn`, `member_flying`, `member_delete` | Haute |
  | Gestion des créneaux (admin) | `shift_free`, `shift_free_admin`, `shift_validate_admin`, `shift_new`, `shift_delete` | Haute |
  | Adhésions (admin) | `registrations`, `registration_edit`, `registration_remove` | Haute |
  | Ambassadeur | `ambassador_noregistration_list`, `lateregistration_list`, `shifttimelog_list`, `phone_show`, `new_note` | Moyenne |
  | Événements & proxies | `event_index`, `event_detail`, `event_proxy_give`, `event_proxy_take` | Moyenne |
  | API OAuth | `api_user`, `api_nextcloud_user`, `api_gitlab_user`, `api_swipe_in` | Moyenne |
  | HelloAsso paiements | `helloasso_payments`, `helloasso_browser`, `helloasso_manual_paiement_add`, etc. (8 routes) | Moyenne |
  | Swipe / card reader | `swipe_in`, `card_reader_index`, `card_reader_check`, `swipe_qr`, `swipe_br` | Basse (hardware) |
  | Admin CRUD complet | périodes, formations, commissions, horaires, services, tâches, codes, emails dynamiques | Variable |
  | Réinitialisation mot de passe | `fos_user_resetting_*` (4 routes) | Haute |
  | Auto-inscription | `user_self_register`, `fos_user_registration_*` (5 routes) | Haute |

  **TODOs issus de CI.2** :

  - **TODO CI.D** — Fixer les skips silencieux : remplacer les branches `else` sans assertion par `cy.fail('No testable state found — check fixtures')` ou dédier des fixtures déterministes pour les scénarios clés (réadhésion, réservation).
  - **TODO CI.E** — Remplacer les `cy.wait(500)` par des assertions sur l'état visible (`should('be.visible')` / `should('not.be.visible')`) — zéro wait hardcodé, le retry Cypress gère le timing.
  - **TODO CI.F** — Cibler `Cypress.on('uncaught:exception')` sur les erreurs JS connues et tolérées (par exemple, filtre sur `err.message`) plutôt que tout supprimer globalement.
  - **TODO CI.G** — Ajouter `retries: { runMode: 1 }` dans `cypress.config.js` pour absorber les flakiness résiduelles en CI sans masquer les vrais bugs.
  - **TODO CI.H** (backlog) — Scénarios manquants prioritaires : création/modification bénéficiaire, auto-inscription membre, reset mot de passe, libération créneau (admin). Ces 4 domaines couvrent les chemins critiques absents.

- [x] **CI.3** — Préparer la CI pour la migration Symfony (analyse seulement)

  **Contexte** : la CI actuelle est mono-version PHP 7.4. Le Docker local tourne déjà sur PHP 8.1. La migration Symfony (SF5 → SF6) implique des changements coordonnés dans la CI et `composer.json`.

  **Inventaire des occurrences PHP 7.4 à migrer dans `ci.yaml`** :

  | Localisation | Valeur actuelle | Cible SF5.4 | Cible SF6.4 |
  |---|---|---|---|
  | `setup` job — `matrix.php-versions` | `['7.4']` | `['7.4', '8.1']` | `['8.1']` |
  | `fast-tests` job — `php-version` | `'7.4'` | `'8.1'` | `'8.1'` |
  | `phpstan` job — `php-version` | `'7.4'` | `'8.1'` | `'8.1'` |
  | `symfony-tests` job — `php-version` | `'7.4'` | `'8.1'` | `'8.1'` |
  | `cypress-tests` job — `php-version` | `'7.4'` | `'8.1'` | `'8.1'` |
  | `cypress-tests-oidc` job — `php-version` | `'7.4'` | `'8.1'` | `'8.1'` |
  | `composer.json` `config.platform.php` | `"7.4"` | `"8.1"` | `"8.1"` |

  Note : SF5.4 est techniquement compatible PHP 7.2.5+, mais PHP 7.4 est EOL depuis nov. 2022. Passer directement à PHP 8.1 dans la CI (sans étape intermédiaire PHP 7.4+SF5) est la stratégie recommandée, alignée avec le Docker local déjà en 8.1.

  **Gestion des dépréciations (`phpunit.xml.dist`)** :

  Actuellement : `SYMFONY_DEPRECATIONS_HELPER=disabled` — les avertissements de dépréciation sont silencieux. C'est bloquant pour la migration : les dépréciations SF4→SF5 et SF5→SF6 doivent être visibles pour guider le travail.

  Séquence recommandée en 3 phases :
  1. **Avant migration** : passer `disabled` → `weak` pour compter les dépréciations sans casser les tests.
  2. **Pendant migration** : `weak` → `max[self]=0&verbose` (zéro tolérance sur le code propre, verbose sur le reste).
  3. **Après migration** : `max[self]=0&max[direct]=0` (zéro tolérance totale).

  **PHPStan (`phpstan` job / `make lint`)** :

  - `make lint` exécute `cache:warmup --env=dev` puis `phpstan analyse src`. Si des bundles incompatibles (FOSUserBundle, FOSOAuthServerBundle) bloquent le warmup, le job échoue avant même d'analyser.
  - Solution : ajouter un job de warm-up séparé en amont, ou utiliser `--no-debug` et skip du warmup pendant la phase de migration.
  - Les extensions `phpstan/phpstan-symfony` et `phpstan/phpstan-doctrine` sont déjà à jour et compatibles SF5/SF6.
  - Après migration, le `containerXmlPath` (`var/cache/dev/srcApp_KernelDevDebugContainer.xml`) restera valide — aucun changement de config PHPStan prévu.

  **Symfony CLI (jobs Cypress)** :

  Install actuelle fragile : chemin codé en dur `/home/runner/.symfony5/bin/symfony` lié à la version de l'installeur. À remplacer par :
  ```yaml
  - uses: symfonycorp/setup-symfony-cli@v1
  ```
  Cette action officielle gère les mises à jour de chemin automatiquement.

  **MariaDB** :

  Actuellement : `mariadb:10.4` (EOL juin 2024) dans les 3 jobs avec services. À upgrader vers `mariadb:10.11` (LTS) ou `mariadb:11.4` (LTS) lors de la migration — même batch, même PR.

  **`symfony/flex`** :

  Actuellement `^1.3.1`. SF6 requiert Flex `^2.0`. La mise à jour de Flex modifie la gestion des recettes et des `post-install-cmd`. À tester isolément avant la montée SF6.

  **Composer version** :

  Actuellement `composer:2.2` (pinné sur une version mineure). SF6 + Flex 2.x fonctionnent sur Composer ≥2.2. Migrer vers `composer:v2` pour permettre les mises à jour mineures automatiques (faible priorité).

  **Stratégie de migration recommandée (ordre CI)** :

  ```
  Étape 1 — Préparer PHP 8.1 compat (avant de toucher Symfony)
    - Ajouter PHP 8.1 en 2e entrée de la matrix dans setup
    - Mettre à jour platform.php → "8.1"
    - Vérifier que les 350 tests passent sur PHP 8.1 + SF4.4

  Étape 2 — Surfacer les dépréciations
    - SYMFONY_DEPRECATIONS_HELPER: disabled → weak
    - Lancer la CI → inventorier les dépréciations dans les logs

  Étape 3 — Migrer vers SF5.4
    - Résoudre les blocants (doctrine/persistence ^2, symfony/flex ^2)
    - Mettre à jour les versions Symfony dans composer.json
    - Migrer la matrix → ['8.1'] (supprimer 7.4)
    - Corriger les dépréciations SF4→SF5

  Étape 4 — Migrer vers SF6.4
    - Corriger les dépréciations SF5→SF6 (annotations → attributs, etc.)
    - Mettre à jour MariaDB → 10.11 ou 11.4
    - Remplacer Symfony CLI install par symfonycorp/setup-symfony-cli@v1

  Étape 5 — Durcir
    - SYMFONY_DEPRECATIONS_HELPER → max[self]=0&max[direct]=0
    - Retirer les baselines PHPStan si possible
  ```

  **TODOs issus de CI.3** :

  - **TODO CI.I** — Remplacer l'install Symfony CLI manuelle dans `cypress-tests` et `cypress-tests-oidc` par `uses: symfonycorp/setup-symfony-cli@v1` (éliminer le chemin `.symfony5` codé en dur).
  - **TODO CI.J** — Phase "dépréciations surfacées" : changer `SYMFONY_DEPRECATIONS_HELPER=disabled` → `weak` dans `phpunit.xml.dist` et intégrer la sortie dans les logs CI comme indicateur de progression migration.
  - **TODO CI.K** — Lors de la migration SF : mettre à jour `mariadb:10.4` → `mariadb:10.11` dans les 3 jobs de services (symfony-tests, cypress-tests, cypress-tests-oidc).
  - **TODO CI.L** — Ajouter PHP 8.1 comme 2e entrée dans la matrix avant de migrer Symfony, pour valider la compatibilité PHP 8.1 + SF4.4 sans risque.
  - **TODO CI.M** — Lors de la migration SF6 : passer `composer:2.2` → `composer:v2` dans le `setup` job et mettre à jour `composer.json` `config.platform.php` de `"7.4"` → `"8.1"`.

---

## RT — Runtime feature tracking (recommandation)

> **Objectif** : pouvoir savoir quelles routes sont utilisées par quelle instance, pour guider les décisions de dead code et de migration. **Cet item produit une recommandation d'implémentation dans la TODO**, pas une implémentation.

- [x] **RT.1** — Identifier le mécanisme d'identification d'instance
  > Hostname ? Variable d'env `APP_INSTANCE` ? Confirmer en lisant `config/` et `.env.dist`.

  **Résultat**

  Méthode : lecture de `config/services.yaml`, `config/packages/framework.yaml`, grep exhaustif de `APP_INSTANCE`, `hostname`, `gethostname`, `getenv` dans `src/` ; croisement avec findings CONFIG.2.

  ### Conclusion : aucun mécanisme d'identification d'instance au runtime

  Il n'existe **aucune variable `APP_INSTANCE`** ni aucune logique conditionnelle dans le code PHP qui distingue Elefan de Scopeli à l'exécution. Les deux instances sont deux déploiements indépendants du même code source, différenciés uniquement par leur fichier `.env`.

  ### Mécanisme implicite : hostname via `ROUTER_REQUEST_CONTEXT_HOST`

  La variable `ROUTER_REQUEST_CONTEXT_HOST` (ex. `membres.lelefan.org` vs `membres.scopeli.coop`) est le seul marqueur d'identité d'instance dans la config :

  - `config/services.yaml:149` — `router.request_context.host: '%env(ROUTER_REQUEST_CONTEXT_HOST)%'` : configure le host pour la génération d'URLs hors requête (commandes CLI, emails).
  - `config/packages/framework.yaml:15` — `cookie_domain: "%env(ROUTER_REQUEST_CONTEXT_HOST)%"` : scope les cookies de session au domaine de l'instance.

  Ce hostname n'est **jamais lu par du code PHP applicatif** (`src/`) pour bifurquer un comportement : il sert uniquement à Symfony pour la génération d'URLs et les cookies.

  ### Variables de branding (display uniquement, pas d'identité logique)

  `PROJECT_NAME` (`config/services.yaml:141`) et `SITE_NAME` (L151) sont utilisées pour l'affichage (emails, titres), pas pour décider d'un comportement. `PROJECT_URL` / `PROJECT_URL_DISPLAY` idem.

  ### Confirmation CONFIG.2

  La section CONFIG.2 avait déjà établi : *"Il n'existe aucune variable `APP_INSTANCE` ou mécanisme d'identification de déploiement au runtime"* et *"Conséquence directe pour RT.1 : il faudra créer une variable (`APP_INSTANCE=elefan|scopeli`) pour alimenter le tracking de routes recommandé en RT.2."* RT.1 confirme ce diagnostic par lecture directe des fichiers config.

  ### Implication pour RT.2

  Pour implémenter le tracking de routes par instance, une variable dédiée devra être créée : `APP_INSTANCE=elefan` / `APP_INSTANCE=scopeli` dans le `.env` de chaque déploiement. Le `ROUTER_REQUEST_CONTEXT_HOST` pourrait servir de source alternative (dérivation du nom d'instance depuis le hostname), mais une variable explicite est plus robuste et découplée des décisions d'hébergement.

- [x] **RT.2** — Rédiger la recommandation d'implémentation
  > Décrire dans la TODO : EventSubscriber sur `kernel.terminate`, upsert en base sur `(route_name, instance)`, page admin de rapport. Inclure le schéma de la table proposée. C'est une spec technique, pas du code.

  **Résultat**

  Spec technique du tracking de routes par instance. **Cet item ne produit pas de code — uniquement une spécification à intégrer dans SYN.2 (TODO priorisée).**

  ---

  ### Spec : Route Usage Tracker (tracking runtime multi-instance)

  **Objectif** : savoir quelles routes sont appelées sur chaque instance (Elefan, Scopeli) pour guider les décisions de dead code et de migration Symfony.

  #### Prérequis : variable `APP_INSTANCE`

  Ajouter dans le `.env` de chaque déploiement :
  ```
  APP_INSTANCE=elefan   # ou scopeli
  ```
  Déclarer dans `config/services.yaml` :
  ```yaml
  app_instance: '%env(APP_INSTANCE)%'
  ```
  Ajouter dans `.env.dist` avec commentaire explicatif.

  #### Schéma de la table proposée

  Table : `route_usage`

  | Colonne | Type | Contrainte | Description |
  |---|---|---|---|
  | `id` | `integer` | PK, AUTO_INCREMENT | — |
  | `route_name` | `varchar(255)` | NOT NULL | Nom Symfony de la route (ex. `admin_shift_index`) |
  | `instance` | `varchar(50)` | NOT NULL | Valeur de `APP_INSTANCE` au moment de l'appel |
  | `last_seen_at` | `datetime` | NOT NULL | Horodatage du dernier appel observé |
  | `hit_count` | `integer` | NOT NULL, DEFAULT 1 | Nombre de hits depuis le premier enregistrement |

  Index unique : `(route_name, instance)` — clé naturelle pour le upsert.

  Migration Doctrine à créer : `Version{YYYYMMDDHHMMSS}_route_usage_tracking.php`, suivant la convention des migrations existantes dans `src/Migrations/`.

  #### Entité Doctrine

  `src/Entity/RouteUsage.php` — annotations `@ORM\Table(name="route_usage")` + `@ORM\UniqueConstraint(columns={"route_name", "instance"})`. Champs : `$id`, `$routeName`, `$instance`, `$lastSeenAt`, `$hitCount`.

  Repository `src/Repository/RouteUsageRepository.php` avec méthode `upsert(string $routeName, string $instance): void` — `INSERT ... ON DUPLICATE KEY UPDATE hit_count = hit_count + 1, last_seen_at = NOW()` (via `EntityManager::getConnection()->executeStatement()`).

  #### EventSubscriber `kernel.terminate`

  `src/EventListener/RouteUsageSubscriber.php` — implémente `EventSubscriberInterface` (pattern cohérent avec `BeneficiaryInitializationSubscriber.php` existant).

  `kernel.terminate` est déclenché **après** que la réponse est envoyée au client : zéro impact sur la latence perçue.

  Logique `onKernelTerminate(TerminateEvent $event)` :
  1. Récupérer la route depuis `$event->getRequest()->attributes->get('_route')`.
  2. Ignorer si `null` (requêtes sans route Symfony, ex. assets).
  3. Ignorer les routes internes Symfony (`_profiler`, `_wdt`, `_error`).
  4. Appeler `RouteUsageRepository::upsert($routeName, $this->appInstance)`.

  Injection : `$appInstance` via `_defaults.bind` dans `services.yaml` (`$appInstance: '%app_instance%'`).

  Enregistrement dans `services.yaml` :
  ```yaml
  App\EventListener\RouteUsageSubscriber:
    tags:
      - { name: kernel.event_listener, event: kernel.terminate, method: onKernelTerminate }
  ```

  #### Page admin de rapport

  `src/Controller/AdminRouteUsageController.php` — `@Route("admin/route-usage")`, `@Security("is_granted('ROLE_ADMIN')")`.

  Action `index` : requête agrégée `SELECT route_name, instance, last_seen_at, hit_count FROM route_usage ORDER BY last_seen_at DESC`. Rendu dans `templates/admin/route_usage/index.html.twig`.

  Affichage : tableau avec colonnes `Route`, `Instance`, `Dernier appel`, `Nb hits`. Filtre par instance (dropdown). Bouton d'export CSV optionnel.

  Lien dans le menu admin (à ajouter dans le template de navigation admin existant).

  #### Estimation d'effort

  | Composant | Effort |
  |---|---|
  | Variable `APP_INSTANCE` + config | S (< 1h) |
  | Migration + entité + repository | S (2h) |
  | EventSubscriber | S (1h) |
  | Page admin + template | M (4h) |
  | **Total** | **M (< 1 jour)** |

  #### Risques

  - **Performance** : un upsert par requête HTTP. Acceptable pour un trafic coopératif (< 1000 req/h). Si le trafic augmente, mettre en cache en mémoire (APCu, Redis) et flusher périodiquement.
  - **Données en test** : ne pas activer en `test` env (ajouter une guard `if ($this->kernel->getEnvironment() === 'test') return;`).
  - **Migration SF5** : `kernel.terminate` et `EventSubscriberInterface` sont stables jusqu'à SF6+ — aucun impact de migration.

  **TODO SYN.2** : classer cet item 🟡 Mineur / M.

---

## SF-PREP — Préparation migration Symfony (analyse uniquement)

> **Objectif** : identifier les bloquants et estimer l'effort. La migration elle-même n'est pas dans le scope de cet audit.
> 🔀 **Modèle : Opus pour SF-PREP.2.** Rappeler `/model opus` avant SF-PREP.2, `/model sonnet` après.

- [x] **SF-PREP.1** — Identifier les bloquants techniques
  > `docker compose exec -T php composer require symfony/symfony:5.4.* --dry-run 2>&1`. Lister tous les conflits. Les noter ici.

  **Résultat**

  Le dry-run Composer (`--dry-run` puis `-W`) identifie 2 conflits directs et arrête tôt. L'analyse complète des dépendances directes dans `composer.json` révèle **4 bloquants hard** et **4 bloquants de configuration**.

  ---

  ### Bloquants durs (conflits Composer qui empêchent l'installation)

  | # | Package | Version actuelle | Contrainte root | Problème SF5.4 |
  |---|---|---|---|---|
  | B1 | `doctrine/persistence` | 1.3.8 | `^1.0` | SF5.4 exige `^2.0` — conflit direct confirmé par Composer |
  | B2 | `friendsofsymfony/user-bundle` | v2.2.4 | `^2.1` | Requiert `symfony ^4.4` uniquement ; v3.x disponible (v3.4.0) mais à valider |
  | B3 | `friendsofsymfony/oauth-server-bundle` | 1.6.2 | `^1.6` | Requiert `symfony ~2.8\|~3.0\|^4.0` ; seul `2.0.0-alpha.0` existe pour SF5 (instable) ; bundle effectivement abandonné |
  | B4 | `ornicar/gravatar-bundle` | 1.3.0 | `^1.3` | Requiert `symfony ~4.0\|~3.0\|~2.3` ; bundle abandonné (dernière release 2019), aucune version SF5 |

  **Cascade doctrine (déclenchée par B1) :**
  - `doctrine/orm: ^2.7` (v2.7.5) → doit monter à `^2.9+` (doctrine/persistence ^2.0 est requis par orm ≥2.9)
  - `doctrine/doctrine-bundle: ^2.3` (v2.3.2) → upgrade recommandé (v2.13.3 dispo) pour supporter persistence ^2

  ---

  ### Bloquants de configuration (pas de conflit Composer mais migration impossible sans correction)

  | # | Élément | Valeur actuelle | Valeur SF5 |
  |---|---|---|---|
  | C1 | `config.platform.php` | `"7.4"` | `"8.1"` — masque les incompatibilités réelles (ex. `shipmonk/dead-code-detector` requiert PHP ^8.1) |
  | C2 | `extra.symfony.require` | `"4.4.*"` | `"5.4.*"` — Flex l'utilise pour filtrer les recettes |
  | C3 | `symfony/flex` | v1.22.0 | `^2.x` — Flex 1.x gère les recettes SF4, Flex 2.x pour SF5+ |
  | C4 | `conflict: twig/twig: ">=3.0"` | présent | À supprimer — ajouté pour FOSUserBundle 2.x qui ne supporte pas Twig 3 ; SF5.4 supporte Twig 2 et 3 |

  ---

  ### Packages **compatibles SF5 sans changement de contrainte**

  | Package | Version | Compat SF5 |
  |---|---|---|
  | `liip/imagine-bundle` | 2.15.0 | ✓ supporte `^5.3` |
  | `vich/uploader-bundle` | 1.23.1 | ✓ supporte `^4.4 \|\| ^5.0` |
  | `knpuniversity/oauth2-client-bundle` | v2.17.0 | ✓ supporte `^5.0` |
  | `symfony/webpack-encore-bundle` | v1.17.2 | ✓ supporte `^4.4 \|\| ^5.0` |
  | `sensio/framework-extra-bundle` | v6.2.10 | ✓ supporte `^4.4\|^5.0\|^6.0` (abandonné mais OK techniquement) |
  | `guzzlehttp/guzzle` | 7.10.0 | ✓ |
  | `michelf/php-markdown` | 1.9.1 | ✓ (indépendant de Symfony) |
  | `beberlei/doctrineextensions` | v1.3.0 | ✓ (v1.5.0 dispo, upgrade recommandé) |

  ---

  ### Résumé exécutif

  **4 bloquants hard à résoudre avant toute tentative d'upgrade** :
  - B1 (`doctrine/persistence`) : fix de contrainte simple, cascade sur `doctrine/orm`
  - B2 (`friendsofsymfony/user-bundle`) : v3.x à valider ou remplacement par security native SF5 → **SF-PREP.2**
  - B3 (`friendsofsymfony/oauth-server-bundle`) : remplacement obligatoire (aucune version stable SF5) → **SF-PREP.2**
  - B4 (`ornicar/gravatar-bundle`) : remplacement trivial (inline ~5 lignes, pas de bundle nécessaire)

  **4 corrections de configuration** (C1–C4) : mécaniques, pas de risque fonctionnel.

- [x] **SF-PREP.2** — Évaluer l'effort de remplacement des bundles bloquants
  > Pour FOSUserBundle et FOSOAuthServerBundle : lire leur usage réel dans `src/` et `config/`. Estimer l'effort de migration vers les alternatives natives. Résultat → TODO avec estimations S/M/L/XL.

  **Résultat**

  Analyse de l'usage réel des deux bundles bloquants (B2 et B3 de SF-PREP.1) et estimation de l'effort de remplacement. Échelle d'effort : **S** (< 0.5 j) · **M** (0.5–2 j) · **L** (2–5 j) · **XL** (> 5 j, risque/coordination externe).

  ---

  ### Décomposition fonctionnelle préalable — deux préoccupations OAuth à NE PAS confondre

  Le code mêle deux rôles OAuth opposés ; seul le premier est bloquant.

  | Rôle | Brique | Ce que ça fait | Statut migration |
  |---|---|---|---|
  | **Serveur OAuth2** (fournisseur d'identité) | `friendsofsymfony/oauth-server-bundle` (B3) | gestion-compte **délivre** des tokens à des apps tierces (Nextcloud, GitLab… via `/oauth/v2/token`, `/oauth/v2/auth`, `/api/oauth/*`) | **Bloquant — à remplacer** |
  | **Client OAuth2 / OIDC** (consommateur) | `knpuniversity/oauth2-client-bundle` (v2.17, compat SF5 ✓) + `App\Security\KeycloakAuthenticator` + `App\Providers\*OauthAuthenticator*` | gestion-compte **consomme** un IdP externe (Keycloak) quand `oidc_enable=true`, et des API tierces (Helloasso, Igloohome) | **Non bloquant** — déjà sur bundle maintenu, hors scope |

  > ⚠️ **Toggle d'instance déterminant** : `OidcFirewallListener` (`src/EventListener/OidcFirewallListener.php`) **désactive** les routes FOSUser (login, /profile/edit, /resetting/request, /member/new…) quand `oidc_enable=true` et redirige `/login → /oauth/login`. Une instance sous Keycloak (OIDC) n'utilise donc PAS l'UI d'auth de FOSUserBundle — mais elle utilise toujours **le modèle de stockage User** (table `fos_user`) et **le serveur FOSOAuth** pour ses apps aval. À confirmer par instance (Elefan / Scopeli) avant de prioriser : si une instance est 100 % OIDC, le risque UX de la migration FOSUser y est moindre, mais le travail entité/serveur reste identique.

  ---

  ### B2 — FOSUserBundle → Security natif Symfony

  **Cible** : abandon total du bundle (déprécié, non supporté SF6+). Remplacement par Security natif + `symfonycasts/reset-password-bundle` + `symfonycasts/verify-email-bundle` (chemin documenté, `make:auth` / `make:registration-form`). La v3.x du bundle existe mais ne fait que repousser le problème → **non recommandée**.

  **Points d'accroche réels relevés :**

  | Zone | Fichier(s) | Détail | Effort |
  |---|---|---|---|
  | Entité | `src/Entity/User.php` | `extends FOS\UserBundle\Model\User`. La classe de base fournit `username/email/password/salt/enabled/roles/lastLogin/confirmationToken/passwordRequestedAt` + canonical fields. Migration = matérialiser ces champs en colonnes réelles + implémenter `UserInterface` + `PasswordAuthenticatedUserInterface` + réimplémenter `addRole/hasRole/removeRole/isEnabled/getRoles`. Garder `@ORM\Table(name="fos_user")` pour **éviter une migration DB**. | **M** |
  | Méthodes héritées consommées | tout `src/` + `templates/` | `hasRole` ×35, `setEnabled` ×13, `addRole` ×8, `removeRole` ×6, `isEnabled` ×6, `setPlainPassword` ×5, `getRoles` ×4, `setLastLogin` ×3, `getConfirmationToken` ×2. Toutes à reporter sur l'entité/trait (pas de changement d'appelant si signatures conservées). | inclus ci-dessus |
  | Config security | `config/packages/security.yaml` | `encoders: FOS\UserBundle\Model\UserInterface: bcrypt` → `password_hashers`. Provider `fos_user.user_provider.username_email` → provider Doctrine custom (login par **username OU email**, à réimplémenter). `form_login` provider/check_path. Le `KeycloakAuthenticator` (guard) est déjà natif. | **M** |
  | Routes auth | `config/routes.yaml` (`@FOSUserBundle/.../all.xml`) | Import à supprimer ; recréer controllers + routes login/logout/registration/resetting/profile/change_password. **Conserver les noms de routes** `fos_user_*` (réf. en dur ci-dessous) OU faire un find/replace global. | **L** |
  | Routes référencées en dur | `UserController:292`, `MembershipController:624,909`, `DefaultController:54`, `SwipeCardController:65`, `MailerService:85,113` | `fos_user_profile_show`, `fos_user_registration_check_email`, `fos_user_security_login`, `fos_user_registration_confirm`, `fos_user_resetting_reset`. | inclus |
  | Mailer | `src/Service/MailerService.php` | `implements FOS\UserBundle\Mailer\MailerInterface` ; `sendConfirmationEmailMessage` / `sendResettingEmailMessage` basées sur `getConfirmationToken`. Découpler de l'interface FOS, déléguer les tokens à reset-password/verify-email-bundle. | **S** |
  | Events | `UserController:133`, `MembershipController:337,788,900`, `SetFirstPasswordListener` | `FOSUserEvents::USER_PASSWORD_CHANGED` (×1 dispatch + listener `onPasswordChanged`) ; `FOSUserEvents::REGISTRATION_SUCCESS` (×3 dispatches, déclenche `SetFirstPasswordListener`). Remplacer par events applicatifs maison. | **M** |
  | Forms | `RegistrationType` (déjà `AbstractType` autonome), `UserType`/`UserWithBeneficiaryType` (custom, **n'étendent PAS** les form types FOS) | Faible impact : retirer le câblage `fos_user.yaml` (`registration.form.type`, `profile.form.type`), garder les classes. | **S** |
  | Templates | `templates/bundles/FOSUserBundle/` | **14 templates** override (layout, Profile, Registration, Resetting ×6, Security/login, ChangePassword) à rebrancher sur les nouveaux controllers/chemins. | **M** |
  | Config bundle | `config/packages/fos_user.yaml`, `config/bundles.php` | Supprimer ; reporter `group_class: App\Entity\Formation` (les "groupes" FOS = Formations, voir `User::getGroups()`) et `from_email`. | **S** |

  > Note : `OidcFirewallListener` importe `FOS\UserBundle\Event\GetResponseUserEvent` mais ne s'en sert pas (méthode typée `RequestEvent`) → import mort à supprimer au passage.

  **Effort agrégé B2 : `L` (≈ 4–5 j).** Chemin balisé (nombreux guides, `make:*`), faible risque externe, mais volume réel (entité + 14 templates + serveur de routes auth + flux reset/confirm). **Rector n'aide pas** ici (logique, pas annotations).

  ---

  ### B3 — FOSOAuthServerBundle → ⚠️ pas de chemin SF5 propre

  **Constat de l'état de l'art (WebSearch, juin 2026)** : le successeur officiel coordonné avec la core team est **`league/oauth2-server-bundle`** (ex-`trikoder/oauth2-bundle`) — mais il **exige Symfony ≥ 6.4**. Il n'existe **aucune cible stable pour le palier SF5.4** :
  - rester sur FOSOAuth `2.0.0-alpha.0` (instable, bundle abandonné) → **non viable** ;
  - intégration custom directe sur la lib agnostique `league/oauth2-server` pour SF5.4 → **travail sur mesure conséquent** ;
  - **sauter le palier** : reporter la migration du serveur OAuth jusqu'à l'arrivée sur SF6.4 (séquencer SF5.4 d'abord en gardant temporairement FOSOAuth si l'install le permet, ou viser directement 6.4 pour ce bloc).

  > 🔧 **Décision d'architecture à prendre AVANT de coder** (règle 9) : ce bloc conditionne la trajectoire de migration globale. Option recommandée à valider avec l'utilisateur : **migrer SF4.4→5.4 sur tout le reste, traiter le serveur OAuth comme un chantier dédié calé sur SF6.4** plutôt que d'écrire une intégration `league/oauth2-server` jetable pour SF5.4.

  **Points d'accroche réels relevés :**

  | Zone | Fichier(s) | Détail | Effort |
  |---|---|---|---|
  | Entités | `src/Entity/{Client,AccessToken,AuthCode,RefreshToken}.php` | 4 entités `extends FOS\OAuthServerBundle\Entity\*`. À re-modéliser sur le schéma `league` (modèle différent : pas d'entités Doctrine côté league par défaut, repositories à implémenter). | **L** |
  | Config | `config/packages/fos_oauth_server.yaml`, `config/bundles.php`, `config/routes.yaml` | `db_driver`, 4 classes, form authorize, `user_provider` ; routes `token.xml` + `authorize.xml`. À réécrire intégralement. | **M** |
  | Firewall | `config/packages/security.yaml` | `fos_oauth: true` sur firewalls `main` + `api` ; firewalls `oauth_token`/`oauth_authorize`. À remplacer par l'authenticator de token du nouveau bundle. | **M** |
  | Admin clients | `src/Controller/ClientController.php` | CRUD via `fos_oauth_server.client_manager.default` (`createClient`/`updateClient`). Réécrire contre le nouveau gestionnaire de clients. | **M** |
  | Form client | `src/Form/ClientType.php` | Grant types via constantes `OAuth2::*` (lib `friendsofsymfony/oauth2-php`). Remapper sur les grants du nouveau lib. | **S** |
  | Listener autorisation | `src/EventListener/OAuthEventListener.php` | `OAuthEvent` pre/post-authorization (lie User↔Client, `isAuthorizedClient`/`addClient`). À réimplémenter sur le cycle d'événements du nouveau bundle. | **M** |
  | Lien User↔Client | `src/Entity/User.php` | `ManyToMany Client`, `isAuthorizedClient/getClients/addClient`, import `ClientInterface`. À conserver/adapter. | **S** |
  | Consommateurs aval | `src/Controller/ApiController.php` (`ROLE_OAUTH_LOGIN`) | Endpoints `/api/oauth/user`, `/api/oauth/nextcloud_user`, `/api/v4/user` (GitLab) → **SSO réel d'apps tierces**. Grants utilisés : `auth_code` (SSO Nextcloud/GitLab) + `client_credentials`/`user_credentials` (API). Compat tokens/endpoints à préserver, sinon re-enrôler les clients sur **chaque instance**. | **coordination** |

  **Effort agrégé B3 : `XL` (> 5 j + coordination externe).** Pas de remplacement drop-in pour SF5.4 ; re-modélisation complète des 4 entités + serveur de routes + admin + listener, **plus** la coordination SSO avec les apps aval (Nextcloud, GitLab) sur les deux instances. C'est le **chemin critique** de toute la migration SF.

  ---

  ### B4 (rappel SF-PREP.1) — ornicar/gravatar-bundle → inline

  Déjà utilisé inline dans `ApiController:111` (`new GravatarHelper(new GravatarApi())`). Remplacement trivial par calcul d'URL Gravatar maison (~5 lignes : `md5(strtolower(trim($email)))`). **Effort : `S`.**

  ---

  ### TODO consolidée (estimations S/M/L/XL)

  | # | Tâche | Effort | Dépendances / Risque |
  |---|---|---|---|
  | T1 | Confirmer par instance (Elefan/Scopeli) la valeur de `oidc_enable` et l'inventaire des apps aval consommant le serveur OAuth | **S** | Prérequis de priorisation |
  | T2 | Entité `User` : matérialiser les champs BaseUser en colonnes, implémenter `UserInterface`/`PasswordAuthenticatedUserInterface`, garder `fos_user` comme nom de table | **M** | — |
  | T3 | `security.yaml` natif : `password_hashers`, provider username-or-email custom, `form_login` | **M** | T2 |
  | T4 | Controllers + routes auth (login/logout/registration/resetting/profile/change-password) + reset-password & verify-email bundles ; rebrancher 14 templates ; découpler `MailerService` | **L** | T2, T3 |
  | T5 | Remplacer events `FOSUserEvents::*` (REGISTRATION_SUCCESS ×3, USER_PASSWORD_CHANGED) par events maison ; nettoyer import mort dans `OidcFirewallListener` | **M** | T4 |
  | T6 | **Décision archi** serveur OAuth : intégration `league/oauth2-server` custom pour SF5.4 **vs** report sur SF6.4 (recommandé) | **S** (décision) | Bloque T7 |
  | T7 | Remplacer le serveur OAuth (4 entités, routes token/authorize, firewall, admin ClientController, OAuthEventListener) | **XL** | T6 + coordination SSO aval |
  | T8 | Gravatar inline (retirer `ornicar/gravatar-bundle`) | **S** | — |

  **Synthèse effort migration des bloquants : B2 = `L`, B3 = `XL` (chemin critique, pas de cible SF5.4), B4 = `S`.** Recommandation : traiter B2+B4 dans le palier SF5.4, isoler B3 en chantier dédié séquencé sur SF6.4.

- [x] **SF-PREP.3** — Inventaire des annotations à migrer

  **Résultat du scan** (`rector/rector` 2.3.4 déjà en dépendance dev) :

  | Annotation | Volume | Fichiers | Rector | Note |
  |---|---|---|---|---|
  | `@ORM\Column` | 218 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\JoinColumn` | 72 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\ManyToOne` | 67 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\Id` | 39 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\Entity` | 39 | 40 entities | ✅ `doctrine: true` | 33/39 avec `repositoryClass` |
  | `@ORM\Table` | 38 | 38 entities | ✅ `doctrine: true` | 1 avec `uniqueConstraints` nested (Membership) |
  | `@ORM\GeneratedValue` | 38 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\PrePersist` | 36 | entities | ✅ `doctrine: true` | — |
  | `@ORM\HasLifecycleCallbacks` | 30 | entities | ✅ `doctrine: true` | — |
  | `@ORM\OneToMany` | 29 | entities | ✅ `doctrine: true` | — |
  | `@ORM\ManyToMany` | 10 | entities | ✅ `doctrine: true` | — |
  | `@ORM\OneToOne` | 8 | entities | ✅ `doctrine: true` | — |
  | `@ORM\PreUpdate` | 6 | entities | ✅ `doctrine: true` | — |
  | `@ORM\JoinTable` | 5 | entities | ✅ `doctrine: true` | — |
  | `@ORM\AttributeOverrides/Override` | 6 | 3 entities (AuthCode, AccessToken, RefreshToken) | ✅ `doctrine: true` | nested |
  | `@ORM\UniqueConstraint` | 1 | Membership | ✅ `doctrine: true` | nested in @ORM\Table |
  | **Total @ORM** | **641** | **40 entities** | **✅ 100% Rector** | |
  | `@Route` (Symfony) | 257 | 41 controllers | ✅ `symfony: true` | — |
  | `@Route` (Sensio legacy) | — | 2 controllers (`AdminShiftFreeLogController`, `AdminPeriodPositionFreeLogController`) | ✅ `sensiolabs: true` | — |
  | `@Security("is_granted(...)")` | 159 | 38 controllers | ✅ `sensiolabs: true` + `SecurityAttributeToIsGrantedAttributeRector` | 2-pass: @Security→#[Security]→#[IsGranted] |
  | `@Security("is_granted('IS_AUTHENTICATED_REMEMBERED', user)")` | 1 | DefaultController:139 | ✅ couvert par regex `IS_GRANTED_AND_SUBJECT_REGEX` | sujet variable `user` |
  | `@Security("has_role(...)")` | 1 | HelloassoController:93 | ⚠️ **MANUEL** | `has_role()` dépréciée SF3 → remplacer par `is_granted()` avant Rector |
  | `@Assert\*` | 47 | entities | ✅ `symfony: true` (SF52_VALIDATOR_ATTRIBUTES) | NotBlank×19, DateTime/Date×10, Valid/NotNull/IsTrue×12, autres×6 |
  | `@Method` (Sensio) | **0** | 2 imports inutilisés (`AdminShiftFreeLogController`, `AdminPeriodPositionFreeLogController`) | — | Import mort, jamais utilisé comme annotation |
  | `@ParamConverter` | **0** | — | — | Absent du codebase |
  | `@Template` | **0** | — | — | Absent du codebase |
  | `@IsGranted` (Sensio) | **0** | — | — | Projet utilise `@Security` exclusivement |

  **Total : ~1 107 annotations → ~1 106 automatisables par Rector, 1 pré-traitement manuel.**

  **Rector config à ajouter dans `rector.php` :**
  ```php
  ->withAttributesSets(symfony: true, doctrine: true, sensiolabs: true)
  ```
  Puis second pass avec `SecurityAttributeToIsGrantedAttributeRector` (SF6.2 set) pour `#[Security("is_granted(...)")]` → `#[IsGranted(...)]`.

  **Pré-requis manuel (1 point) :**
  - `HelloassoController.php:93` — `@Security("has_role('ROLE_FINANCE_MANAGER')")` : remplacer `has_role()` par `is_granted()` avant de lancer Rector.

  **Cleanup annexe (hors migration fonctionnelle) :**
  - Supprimer les imports morts `use Sensio\...\Configuration\Method` dans les 2 controllers listés ci-dessus.

---

## SPEC — Spécifications fonctionnelles
> 🔀 **Modèle : Opus** pour toute cette section. Rappeler à l'utilisateur : `/model opus` avant SPEC.1, `/model sonnet` après SPEC.10.

> **C'est le livrable central de l'audit.** Les specs doivent être **lisibles et utiles pour un humain d'abord** — langage clair, structure logique, exemples concrets tirés du code. Le format markdown structuré et la terminologie cohérente les rendent également exploitables par un LLM. Chaque spec suit ce template :
> ```
> ### [Domaine]
> **Acteurs** : rôles concernés
> **Instances** : Elefan / Scopeli / toutes (à préciser si connu)
> **Flux principal** : étapes
> **Règles métier** : contraintes identifiées dans le code
> **Données** : entités impliquées, champs clés
> **Cas limites** : comportements edge-case détectés
> **Routes** : liste des routes associées
> **Tests existants** : ce qui est couvert
> **Gaps** : non testé / non documenté / ambigu
> ```

- [x] **SPEC.1** — Cartographier les domaines fonctionnels
  > `docker compose exec -T php php bin/console debug:router --format=txt`. 43 controllers, **~235 routes** au total dont **~205 routes applicatives** (hors profiler/wdt, liip_imagine, et le redirect `root`). Regroupement ci-dessous en **10 domaines fonctionnels** + 2 catégories transverses + 1 catégorie infra exclue.
  >
  > ⚠️ **Note Helloasso/Igloohome** : `debug:router` émet 2 warnings (`HelloassoClient`/`IgloohomeClient` __construct : argument null) car cette instance ne configure pas les variables `HELLOASSO_*`/`IGLOOHOME_*`. La table de routes est néanmoins complète et correcte — ces warnings concernent l'enregistrement des **commandes**, pas des routes. Confirme la dégradation gracieuse documentée en AP.9.
  >
  > ---
  >
  > ### Vue d'ensemble — domaines vs plan SPEC
  >
  > | # | Domaine fonctionnel | Controllers principaux | Routes (~) | Couvert par |
  > |---|---------------------|------------------------|:----------:|-------------|
  > | A | **Adhérents / Bénéficiaires** | `MembershipController`, `BeneficiaryController`, `NoteController` + pre-users de `UserController` | 30 | **SPEC.2** |
  > | B | **Créneaux / Planning** | `ShiftController`, `BookingController`, `PeriodController`, `CardReaderController`, `TimeLogController`, `AdminPeriod*`, `AdminShift*`, `AdminMembershipShiftExemption*` | 45 | **SPEC.3** |
  > | C | **Authentification & Autorisation** | FOSUserBundle, `OAuthController` (Keycloak), `SwipeCardController`, `UserController` (rôles, install_admin) | 35 | **SPEC.4** |
  > | D | **Cotisations & Paiements** | `HelloassoController`, `RegistrationsController` | 13 | **SPEC.5** |
  > | E | **Administration & Configuration** | `AdminController`, `Code`, `Commission`, `Service`, `Task`, `Job`, `Formation`, `DynamicContent`, `EmailTemplate`, `SocialNetwork`, `ClosingException`, `OpeningHour`, `ProcessUpdate`, `Client` | 50 | **SPEC.6** |
  > | F | **Notifications & Emails** | `MailController`, `EmailTemplateController`, `shift_contact_form` | 5 (+ event-driven) | **SPEC.7** |
  > | G | **API & Intégrations externes** | `ApiController`, FOSOAuthServer, OIDC, webhook Helloasso, Igloohome (CLI) | 9 | **SPEC.8** |
  > | **H** | **🆕 Gouvernance / Assemblées générales (Events & Procurations)** | `EventController`, `AdminEventController`, `AdminEventKindController` | 16 | **⚠️ NON PRÉVU** — voir gap ci-dessous |
  > | I | **Pages publiques & Widgets embarqués** | `DefaultController`, `WidgetController` + actions `*_widget` | 9 | transverse (à répartir) |
  > | J | **Contrôle d'accès physique (Codes & Badges)** | `CodeController`, `SwipeCardController`, `CardReaderController`, Igloohome | 18 | transverse SPEC.3/4/6/8 |
  >
  > ---
  >
  > ### Détail par domaine
  >
  > **A — Adhérents / Bénéficiaires** (→ SPEC.2)
  > Gestion du cycle de vie d'un membre et de ses bénéficiaires, notes internes, onboarding.
  > - Membre : `member_show`, `member_new`, `member_join`, `member_edit_firewall`, `member_delete`, `member_flying`, `member_freeze`, `member_unfreeze`, `member_freeze_change`, `member_withdrawn`, `member_add_beneficiary`, `member_new_beneficiary`, `user_office_tools`, `admin_emails_csv`
  > - Onboarding / activation : `find_member_number`, `find_me`, `confirm`, `set_email` (⚠️ SEC.2/SEC.3 — account takeover)
  > - Bénéficiaire : `beneficiary_edit`, `beneficiary_set_main`, `beneficiary_detach`, `beneficiary_delete`
  > - Notes : `note_reply`, `note_edit`, `note_delete`
  > - Pré-inscrits (anonymous beneficiaries) : `pre_user_index`, `pre_user_recall`, `pre_user_delete`, `user_quick_new`, `user_self_register`
  > - **Chevauchements** : `member_new_registration` (→ aussi SPEC.5 cotisation), `member_join` (fusion d'adhésions — logique métier AP.1 finding 2b).
  >
  > **B — Créneaux / Planning** (→ SPEC.3)
  > Cœur métier : réservation/libération de créneaux, périodes, validation de présence.
  > - Réservation membre : `booking`, `booking_by_day`, `bucket_show`, `bucket_show_for_beneficiary`, `shift_book`, `shift_free`, `shift_accept_reserved`, `shift_reject_reserved`, `timelog_new`
  > - Réservation admin : `booking_admin`, `admin_bucket_show`, `bucket_edit`, `bucket_lock_unlock`, `bucket_delete`, `shift_new`, `shift_book_admin`, `shift_free_admin`, `shift_validate_admin`, `shift_delete`, `member_timelog_delete`
  > - Validation présence : `card_reader_index`, `card_reader_check` (+ `root` → redirect `/cardReader`)
  > - Admin périodes : `admin_period_*` (index/new/edit/delete/copy), `admin_periodposition_*` (new/delete/book/free), `admin_shifts_generation`, `admin_periodpositionfreelog_index`
  > - Admin exemptions/logs : `admin_shiftexemption_*`, `admin_shiftfreelog_index`, `admin_membershipshiftexemption_*`
  > - Suivi ambassadeur : `ambassador_shifttimelog_list`, `ambassador_beneficiary_fixe_without_periodposition_list`
  > - Vue publique : `period_index`, `shift_widget`, `schedule`
  > - **Chevauchements** : `shift_contact_form` (→ SPEC.7), `card_reader_check`/badges (→ domaine J).
  >
  > **C — Authentification & Autorisation** (→ SPEC.4)
  > - FOS login/logout : `fos_user_security_login`, `fos_user_security_check`, `fos_user_security_logout`
  > - FOS registration : `fos_user_registration_register`, `_check_email`, `_confirm`, `_confirmed`
  > - FOS resetting : `fos_user_resetting_request`, `_send_email`, `_check_email`, `_reset`
  > - FOS profil/mdp : `fos_user_profile_show`, `fos_user_profile_edit`, `fos_user_change_password`, `user_change_password`
  > - OIDC Keycloak : `oauth_login`, `oauth_logout`, `oauth_check` (instance-specific Scopeli)
  > - Badges (auth passwordless) : `swipe_in`, `swipe_show`, `swipe_qr`, `swipe_br`, `activate_swipe`, `enable_swipe`, `disable_swipe`, `delete_swipe`
  > - Gestion comptes/rôles (admin) : `user_index`, `non_member_users_list`, `admin_users_list`, `roles_list`, `user_install_admin`, `user_add_role`, `user_remove_role`, `user_delete`, `user_client_remove`, `user_import_csv`
  > - **Chevauchements** : badges (→ domaine J), gestion comptes (→ SPEC.6 admin).
  >
  > **D — Cotisations & Paiements** (→ SPEC.5)
  > - Helloasso : `helloasso_notify` (webhook), `helloasso_payments`, `helloasso_browser`, `helloasso_campaign_details`, `helloasso_manual_paiement_add`, `helloasso_payment_remove`, `helloasso_payment_edit`, `helloasso_resolve_orphan`, `helloasso_confirm_resolve_orphan`, `helloasso_orphan_exit_and_back`
  > - Adhésions/cotisations : `registrations`, `registration_edit`, `registration_remove`, `member_new_registration` (cross SPEC.2)
  > - **Note instance** : Helloasso = instance-specific (Elefan ; Scopeli à confirmer CONFIG.2).
  >
  > **E — Administration & Configuration** (→ SPEC.6)
  > CRUD des entités de configuration coopérative.
  > - Dashboard : `admin`
  > - Codes d'accès : `codes_list`, `code_edit`, `code_generate`, `code_toggle`, `code_change_done`, `code_delete` (cross domaine J)
  > - Commissions : `admin_commissions`, `commission_new`, `commission_edit`, `commission_add_beneficiary`, `commission_remove_beneficiary`, `commission_delete`
  > - Services/Tâches : `service_*`, `service_navlist`, `tasks_list`, `task_*`, `job_*`
  > - Formations : `formation_*`
  > - Contenu dynamique / templates : `dynamic_content_list`, `dynamic_content_edit`, `email_template_*` (cross SPEC.7)
  > - Horaires/fermetures : `admin_openinghour_*`, `admin_openinghour_kind_*`, `admin_closingexception_*`
  > - Réseaux sociaux : `admin_socialnetwork_*`
  > - Notes de version : `process_update_list`, `process_update_count_unread`, `process_update_new`, `process_update_edit`, `process_update_delete`
  > - Clients OAuth : `client_list`, `client_new`, `client_edit`, `client_delete` (cross SPEC.8)
  >
  > **F — Notifications & Emails** (→ SPEC.7)
  > Essentiellement **piloté par événements** (cf. AP.7 — `EmailingEventListener`, 13 types d'emails). Routes directes :
  > - Mailing admin : `mail_edit`, `mail_edit_one_beneficiary`, `mail_bucketshift`, `mail_send`
  > - Templates : `email_template_*` (cross SPEC.6)
  > - Contact créneau : `shift_contact_form` (cross SPEC.3)
  >
  > **G — API & Intégrations externes** (→ SPEC.8)
  > - API interne : `api_swipe_in`, `api_user`, `api_nextcloud_user` (`/api/oauth/nextcloud_user`), `api_gitlab_user` (`/api/v4/user`)
  > - Serveur OAuth (SSO sortant) : `fos_oauth_server_token`, `fos_oauth_server_authorize`
  > - OIDC entrant (Keycloak) : `oauth_login/logout/check` (cross SPEC.4)
  > - Webhook Helloasso : `helloasso_notify` (cross SPEC.5)
  > - Igloohome (serrures) : pas de route — piloté par `UpdateIgloohomeCodeCommand` (cross domaine J)
  >
  > ---
  >
  > ### ⚠️ Gaps du plan SPEC — domaines non couverts par SPEC.2-8
  >
  > **Gap 1 — Gouvernance / Assemblées générales & Procurations (domaine H) — NON PRÉVU dans le plan.**
  > `EventController` expose un domaine fonctionnel **entièrement distinct** : la gestion d'événements associatifs (typiquement les AG) avec un système de **procurations (proxies)** :
  > - Public/membre : `event_index`, `event_detail`, `event_widget`, `event_proxy_give` (donner procuration), `event_proxy_take` (recevoir), `event_proxy_find_beneficiary`, `event_proxy_lite_delete`
  > - Admin : `admin_event_*`, `admin_event_kind_*`, `admin_proxies_list`, `admin_event_proxies_list`, `admin_event_proxy_edit/delete`, `admin_event_signatures`
  >
  > Ce domaine (votes/quorum/émargement en AG) ne se réduit ni à SPEC.2 (membres) ni à SPEC.6 (admin CRUD). **Recommandation** : ajouter un **SPEC.5bis ou SPEC.11 — Gouvernance & Assemblées générales** au plan, OU le traiter explicitement comme sous-section de SPEC.6 avec un avertissement de complétude. À trancher avec l'utilisateur avant SPEC.6 (cf. question ci-dessous).
  >
  > **Gap 2 — Contrôle d'accès physique (domaine J) transverse.**
  > Les **codes d'accès** (`CodeController` — codes de porte rotatifs), les **badges** (`SwipeCardController` + chiffrement Vigenère SEC.1.7), le **lecteur de badge** (`CardReaderController`) et l'intégration **Igloohome** (serrures connectées) forment une chaîne fonctionnelle cohérente « accès physique au local » qui traverse SPEC.3 (validation créneau), SPEC.4 (auth badge), SPEC.6 (gestion codes) et SPEC.8 (Igloohome). **Recommandation** : traiter comme une **sous-section transverse dédiée** dans SPEC.4 (auth) avec cross-refs, plutôt que de l'éparpiller. Forte composante sécurité (SEC.1.7, SEC.2.2).
  >
  > **Gap 3 — Widgets embarqués (domaine I) transverse.**
  > 5 actions `*_widget` (`event_widget`, `shift_widget`, `closingexception_widget`, `openinghour_widget`, `widget`) génèrent des fragments HTML embarquables sur des sites externes. Préoccupation de **présentation transverse**, pas un domaine métier — à documenter par domaine concerné + une note transverse dans SPEC.6 (générateurs de widgets admin : `*_widget_generator`).
  >
  > ---
  >
  > ### Catégorie infra — EXCLUE des specs
  >
  > Routes techniques sans valeur fonctionnelle métier : `_preview_error`, `_wdt`, `_profiler_*` (11 routes, dev only), `liip_imagine_filter` + `liip_imagine_filter_runtime` (cache d'images, infra), `root` (redirect `/cardReader` → `/card_reader`).
  >
  > ---
  >
  > ### Conséquences pour SPEC.2-10
  >
  > 1. **Ordre de traitement** : A (SPEC.2) et B (SPEC.3) sont les plus gros et les plus interdépendants (membre ↔ créneau via `TimeLog`/cycle). Les traiter en premier.
  > 2. **Le domaine H (Gouvernance/AG)** → ✅ **tranché (session 52)** : spec dédiée **SPEC.11**.
  > 3. **Le domaine J (accès physique)** → ✅ **tranché (session 52)** : sous-section transverse de **SPEC.4**.
  > 4. Chaque spec devra porter les **annotations multi-instance** (SPEC.9) : Helloasso, OIDC/Keycloak, Igloohome, `use_fly_and_fixed` sont des features instance-specific déjà identifiées (CONFIG.2/CONFIG.3).

- [x] **SPEC.2** — Spec : Adhérents / Bénéficiaires
  > Sources lues : `MembershipController` (1242 l.), `BeneficiaryController` (275 l.), `NoteController` (146 l.), `UserController` (pre-users), `MembershipVoter`, `MembershipService`, `CloseMembershipCommand` ; entités `Membership`, `Beneficiary`, `AnonymousBeneficiary`, `Note`, `Registration` ; events `MemberCreatedEvent`, `BeneficiaryAddEvent`, `BeneficiaryCreatedEvent`, `AnonymousBeneficiaryCreatedEvent`, `AnonymousBeneficiaryRecallEvent`.
  > Croisé avec : D.5 (TODOs internes), AP.1 (fat controller), SEC.2.1 + SEC.3.4 (account takeover), DC.3/DC.4 (méthodes mortes).
  >
  > ---
  >
  > ## SPEC.2 — Adhérents / Bénéficiaires
  >
  > ### Vocabulaire essentiel (lever l'ambiguïté Membership / Beneficiary / User)
  >
  > Le modèle de données distingue **trois concepts** souvent confondus dans l'UI (où l'on parle d'« adhérent ») :
  >
  > | Concept | Entité | Rôle |
  > |---------|--------|------|
  > | **Adhésion / compte adhérent** | `Membership` | Porte le **numéro d'adhérent** (`member_number`), l'historique de cotisations, le statut (gelé/volant/fermé), les créneaux. C'est l'unité de facturation et de cotisation. |
  > | **Bénéficiaire** | `Beneficiary` | Une **personne physique** rattachée à une adhésion (nom, prénom, téléphone, adresse). Une adhésion a 1 à N bénéficiaires ; l'un est le **bénéficiaire principal** (`mainBeneficiary`). |
  > | **Compte de connexion** | `User` (FOSUserBundle) | Identifiants (email/username, mot de passe, rôles). Relation **1-1** avec `Beneficiary`. Couvert en détail par SPEC.4 ; ici uniquement vu comme cible des flux d'onboarding. |
  > | **Pré-inscrit** | `AnonymousBeneficiary` | Personne en attente de finalisation d'adhésion : seul l'email est connu, pas encore de `User`/`Beneficiary`. Transformé en adhésion via lien d'invitation (code Vigenère). |
  >
  > Une `Membership` sans bénéficiaire n'a pas de sens fonctionnel ; `getMainBeneficiary()` retombe automatiquement sur le premier bénéficiaire de la collection si `mainBeneficiary` n'est pas explicitement positionné (`Membership.php:328`).
  >
  > **Acteurs** :
  > - **Anonyme** : onboarding public (find_me, find_member_number, confirm, set_email) + finalisation d'adhésion via lien d'invitation (member_new / member_add_beneficiary avec `code`).
  > - **ROLE_USER** (adhérent connecté) : voit/édite sa propre adhésion (via voter), demande un changement de gel (`freeze_change`), s'auto-réinscrit (`self_register`).
  > - **ROLE_USER_VIEWER** : consultation des fiches membres, post-its, liste des pré-inscrits.
  > - **ROLE_USER_MANAGER** : gel/dégel, fermeture/réouverture, statut volant, suppression de pré-inscrits.
  > - **ROLE_ADMIN** : fusion de comptes (`join`), tout ce qui précède.
  > - **ROLE_SUPER_ADMIN** : suppression d'adhésion, export CSV des emails.
  >
  > **Instances** :
  > - **Toutes** : cycle de vie membre, bénéficiaires, notes, onboarding.
  > - **⚠️ Différence majeure OIDC (Scopeli)** : si `oidc_enable=true`, le `MembershipVoter` **refuse tout `canEdit`/`canView` non-admin** (`MembershipVoter.php:78,138`). La gestion d'identité est déléguée à Keycloak ; l'édition de membre par l'adhérent lui-même est désactivée. Chez Elefan (`oidc_enable=false`), l'adhérent peut éditer sa propre adhésion.
  > - **Volant/Fixe** : le concept `flying` n'a de sens que si `use_fly_and_fixed=true` (cross CONFIG.3, SPEC.3).
  > - **Cycle de cotisation** : `registration_every_civil_year` (cotisation calée sur l'année civile) vs `registration_duration` (durée glissante, ex. `1 year`) — détermine `getExpire()` (`MembershipService.php:84`).
  > - **Plafond bénéficiaires** : `maximum_nb_of_beneficiaries_in_membership` (cross D.5 finding 6 : valeurs `[1,2]` hardcodées dans le form de recherche).
  >
  > ---
  >
  > ### Sous-domaine 1 — Cycle de vie de l'adhésion (Membership)
  >
  > **Flux principal — création d'une adhésion** (`member_new`, `MembershipController::newAction`) :
  > 1. Deux points d'entrée : (a) **admin** authentifié avec droit `create` (voter → `PlaceIP::isLocationOk()`, restriction IP du local) ; (b) **anonyme** via lien d'invitation portant `?code=` (email chiffré Vigenère décodé → `AnonymousBeneficiary`).
  > 2. Calcul du `member_number` : `findOneBy([], ['member_number'=>'DESC'])` + 1 — **dans le controller, non atomique** (AP.1 finding 2a).
  > 3. Création `Membership` + `Beneficiary` principal + `User` + `Registration` initiale (montant/mode repris du pré-inscrit si Helloasso : montant `--`).
  > 4. Dispatch `FOSUserEvents::REGISTRATION_SUCCESS` (création du compte, mail d'activation) puis `MemberCreatedEvent`.
  > 5. Si le pré-inscrit déclarait des co-bénéficiaires (`beneficiaries_emails`), création d'un `AnonymousBeneficiary` par email avec `joinTo` = bénéficiaire principal + dispatch `AnonymousBeneficiaryCreatedEvent` (mail d'invitation à chacun). Le pré-inscrit source est supprimé.
  >
  > **Flux — consultation** (`member_show`) : action « fourre-tout » de 131 lignes construisant **18 formulaires** (AP.1 finding 5). Accès via voter `view`, ou via **token temporaire** dans l'URL pour les non-admins (voir Règles métier).
  >
  > **Règles métier** :
  > - `member_number <= 0` → redirection homepage (numéros réservés/techniques masqués) (`MembershipController.php:85`).
  > - **Statuts mutuellement documentés** : `withdrawn` (fermé), `frozen` (gelé), `frozenChange` (changement de gel demandé en fin de cycle), `flying` (volant). `setWithdrawn(false)` réinitialise `withdrawnDate` + `withdrawnBy` (`Membership.php:344`).
  > - **Gel/dégel** : `freeze`/`unfreeze` (manager) positionnent `frozen` et remettent `frozenChange=false`. `freeze_change` (adhérent sur sa propre adhésion, voter) bascule `frozenChange` — appliqué en fin de cycle. Après un `freeze_change` sur sa propre adhésion, redirection vers `fos_user_profile_show`, sinon vers la fiche membre.
  > - **Fermeture/réouverture** (`withdrawn`) : `withdrawn=true` exige `close`, `withdrawn=false` exige `open`. Trace `withdrawnDate` + `withdrawnBy` (utilisateur courant) à la fermeture manuelle.
  > - **Clôture automatique** (`app:member:close <delay>`, `CloseMembershipCommand`) : ferme les adhésions dont la cotisation n'est pas renouvelée après `delay`. Positionne `withdrawn=true`, `withdrawnDate=now`, `frozen=false`. **⚠️ `withdrawnBy` reste null** (`CloseMembershipCommand.php:62`, TODO D.5 finding 1) → impossible de distinguer clôture cron vs clôture manuelle.
  > - **Réinscription** (`member_new_registration`) : montant prix libre > 0 obligatoire ; un adhérent **ne peut pas enregistrer/modifier sa propre (ré)adhésion** ; la nouvelle date doit être postérieure à l'expiration courante. `getExpire()` dépend de `registration_every_civil_year` vs `registration_duration` (multi-instance).
  > - **Expiration / à jour** : `isUptodate()` = `getExpire() > today` ; `canRegister()` = expire dans < 28 jours (`MembershipService.php:72`, **28 hardcodé** — cross D.5 finding 3).
  > - **Suppression** (`member_delete`, SUPER_ADMIN) : cascade ORM sur registrations, beneficiaries, notes, proxies, timeLogs, exemptions (`Membership.php:80-126`).
  >
  > **Flux — fusion de comptes** (`member_join`, ADMIN) : déplace tous les bénéficiaires de `fromMember` vers `destMember`, puis supprime `fromMember`. Garde-fous : comptes distincts, et somme des bénéficiaires ≤ plafond. **⚠️ Aucune transaction** (`flush()` en deux temps après `setMainBeneficiary(null)`) — AP.1 finding 2b ; risque d'état incohérent si le 2e flush échoue.
  >
  > ---
  >
  > ### Sous-domaine 2 — Bénéficiaires (Beneficiary)
  >
  > **Flux principal** :
  > - **Ajout** (`member_new_beneficiary`, voter `BENEFICIARY_ADD` → `isLocationOk`) : valide d'abord `BeneficiaryCanHost` sur le principal ; si KO → invite à refaire une adhésion. Plafond `maximum_nb_of_beneficiaries_in_membership` vérifié (avec un **`<=` discutable** : la comparaison `count() <= max` autorise potentiellement max+1 — à confirmer). Dispatch `BeneficiaryAddEvent`.
  > - **Ajout depuis pré-inscrit** (`member_add_beneficiary`, anonyme via `code`) : le pré-inscrit doit avoir un `joinTo` ; rattache le nouveau bénéficiaire à l'adhésion cible et supprime le pré-inscrit. Redirige vers `fos_user_registration_check_email`.
  > - **Édition** (`beneficiary_edit`, voter `edit` sur l'adhésion).
  > - **Bénéficiaire principal** (`beneficiary_set_main`) : repositionne `mainBeneficiary`.
  > - **Détachement** (`beneficiary_detach`) : retire le bénéficiaire de l'adhésion et lui crée **sa propre nouvelle adhésion** (nouveau `member_number`). Refus sur le bénéficiaire principal.
  > - **Suppression** (`beneficiary_delete`, voter `edit` ; admin ou token temporaire).
  >
  > **Règles métier** :
  > - `Beneficiary::isMain()` = identité avec `membership.mainBeneficiary` (`Beneficiary.php:390`).
  > - `Beneficiary::isNew()` = `shifts.count() <= 3` (**seuil hardcodé**, D.5 finding 10 ; utilisé par `CodeVoter` pour brider l'accès badge des débutants — cross SPEC.4).
  > - Relation `Beneficiary 1-1 User` **non nullable** (`user_id NOT NULL`, `Beneficiary.php:71`) : un bénéficiaire a toujours un compte de connexion (créé via FOSUserBundle).
  > - Champs `openid` / `openid_member_number` : rattachement OIDC (instance Scopeli, cross SPEC.4).
  >
  > ---
  >
  > ### Sous-domaine 3 — Notes internes (Note)
  >
  > Deux usages d'une même entité `Note` :
  > - **Note de membre** : `subject` = `Membership`. Affichée sur la fiche membre. Création via `ambassador_new_note` (AmbassadorController, hors périmètre strict). Édition/réponse/suppression ici.
  > - **Post-it** (`subject = null`) : mémo libre de l'office, listé dans `user_office_tools`. Déduplication à la création (même auteur + même texte + subject null → refus).
  >
  > **Routes** (⚠️ **double préfixe** `note` : la classe est `@Route("note")` et les méthodes `@Route("/note/{id}/...")` → chemins réels `/note/note/{id}/...`) :
  > - `note_reply` (POST, ROLE_USER_VIEWER) : crée une note enfant, hérite du `subject` et reprend l'arborescence (`parent`).
  > - `note_edit` (GET|POST, voter `edit`).
  > - `note_delete` (DELETE, voter `delete`).
  >
  > **Données** : `Note(text, author→User, subject→Membership nullable, parent→Note nullable, children, createdAt)` — auto-référence pour les fils de réponses.
  >
  > ---
  >
  > ### Sous-domaine 4 — Onboarding & pré-inscrits (AnonymousBeneficiary)
  >
  > **Flux principal — activation de compte (public)** :
  > 1. `find_me` (`activeUserAccountAction`) : l'adhérent saisit son numéro → rend `confirm.html.twig` avec son bénéficiaire principal.
  > 2. `find_member_number` : recherche par prénom (`findActiveFromFirstname`) → liste de bénéficiaires candidats, lien vers `confirm`.
  > 3. `confirm` (POST, public, `id` = Beneficiary) : page de confirmation affichant nom + email (masqué si temporaire).
  > 4. `set_email` (POST, **id = Beneficiary**) : si l'email courant est « temporaire » (`MailerService::isTemporaryEmail`) et le nouvel email valide → remplace l'email du `User`. Permet à l'adhérent pré-inscrit de fixer son vrai email.
  >
  > **Flux — pré-inscription par l'office** :
  > - `user_quick_new` (ROLE_USER_VIEWER) : crée un `AnonymousBeneficiary` (email + co-bénéficiaires + montant/mode) → dispatch `AnonymousBeneficiaryCreatedEvent` (mail d'invitation avec lien `code`).
  > - `pre_user_index` : liste des pré-inscrits (tri `createdAt DESC`).
  > - `pre_user_recall` (ROLE_USER_VIEWER) : renvoie l'invitation, set `recallDate`. ⚠️ Redirige vers `referer` brut (open-redirect mineur potentiel).
  > - `pre_user_delete` (**GET**, ROLE_USER_MANAGER) : ⚠️ mutation via verbe GET (pas de CSRF, idempotence trompeuse).
  > - `user_self_register` (ROLE_USER) : page de ré-adhésion proposée si `canRegister()`.
  >
  > **Données** : `AnonymousBeneficiary(email unique, beneficiaries_emails CSV, amount, mode, joinTo→Beneficiary nullable, registrar→User, createdAt, recallDate)`. Le lien `joinTo` distingue « nouvelle adhésion » (null) de « ajout à une adhésion existante » (non-null) — bifurcation gérée dans `newAction` (redirige vers `member_add_beneficiary`).
  >
  > ---
  >
  > ### Mécanisme transverse — token temporaire d'accès membre (sécurité)
  >
  > Pour permettre à un adhérent **non-admin** d'accéder à sa fiche `member_show` et aux actions liées sans `ROLE_USER_MANAGER`, l'app génère un **token temporaire** passé en query param :
  > ```
  > Membership::getTmpToken($key) = md5(id . member_number . $key . date('d'))
  > // $key = session('token_key') . user.username   (Membership.php:164)
  > ```
  > - Vérifié dans `MembershipVoter::canEdit` (`MembershipVoter.php:152-156`).
  > - **Rotation quotidienne** (`date('d')` = jour du mois → en réalité rotation mensuelle cyclique, pas vraiment 24 h glissantes : un token est rejouable le même jour de chaque mois).
  > - Couplé à `session('token_key')` (régénéré à chaque passage par `member_edit_firewall`).
  > - **Note sécurité** : MD5 + composantes prévisibles (id, member_number séquentiels) ; la robustesse repose sur `token_key` (uniqid de session). À durcir (cross SPEC.4 / SEC.1).
  >
  > ---
  >
  > ### Données — récapitulatif des entités du domaine
  >
  > | Entité | Champs clés | Relations |
  > |--------|-------------|-----------|
  > | `Membership` | `member_number` (bigint), `withdrawn`+`withdrawnDate`+`withdrawnBy`, `frozen`, `frozen_change`, `flying`, `firstShiftDate`, `createdAt` | 1-N `Registration`, 1-N `Beneficiary`, 1-1 `mainBeneficiary`, 1-N `Note`, 1-N `TimeLog`, 1-N `Proxy`(giver), 1-N `MembershipShiftExemption` |
  > | `Beneficiary` | `lastname`, `firstname`, `phone`, `flying`, `openid`, `openid_member_number`, `createdAt` | 1-1 `User` (NOT NULL), 1-1 `Address`, N-1 `Membership`, 1-N `Shift`, N-N `Commission`/`Task`/`Formation`, 1-N `SwipeCard`, 1-N `Proxy`(owner) |
  > | `AnonymousBeneficiary` | `email` (unique), `beneficiaries_emails`, `amount`, `mode`, `createdAt`, `recallDate` | 1-1 `joinTo`→`Beneficiary`, N-1 `registrar`→`User` |
  > | `Note` | `text`, `createdAt` | N-1 `author`→`User`, N-1 `subject`→`Membership`(nullable), self-ref `parent`/`children` |
  > | `Registration` | `date`, `amount`, `mode` (const TYPE_CASH/CHECK/LOCAL/CREDIT_CARD/HELLOASSO/DEFAULT) | N-1 `Membership`, N-1 `registrar`→`User`, 1-1 `HelloassoPayment` |
  >
  > ---
  >
  > ### Routes — inventaire complet (~30)
  >
  > | Route | Méthode / chemin | Contrôle d'accès |
  > |-------|------------------|------------------|
  > | `member_show` | GET `/member/{member_number}/show` | voter `view` (ou token tmp) |
  > | `member_new` | GET\|POST `/member/new` | voter `create` (IP) **ou** anonyme via `code` |
  > | `member_new_registration` | GET\|POST `/member/{member_number}/newRegistration` | voter `edit` |
  > | `member_new_beneficiary` | GET\|POST `/member/{member_number}/newBeneficiary` | voter `beneficiary_add` (IP) |
  > | `member_add_beneficiary` | GET\|POST `/member/add_beneficiary` | **anonyme** via `code` |
  > | `member_edit_firewall` | GET\|POST `/member/edit` | ROLE_USER_VIEWER |
  > | `member_flying` | POST `/member/{id}/flying` | ROLE_USER_MANAGER + voter `flying` |
  > | `member_freeze` | POST `/member/{id}/freeze` | voter `freeze` |
  > | `member_unfreeze` | POST `/member/{id}/unfreeze` | voter `freeze` |
  > | `member_freeze_change` | POST `/member/{id}/freeze_change` | voter `freeze_change` |
  > | `member_withdrawn` | POST `/member/{id}/withdrawn` | ROLE_USER_MANAGER + voter `close`/`open` |
  > | `member_delete` | DELETE `/member/{id}` | ROLE_SUPER_ADMIN |
  > | `member_join` | GET\|POST `/member/join` | ROLE_ADMIN |
  > | `set_email` | POST `/member/{id}/set_email` (id=Beneficiary) | 🔴 **AUCUN** (SEC.2.1 + SEC.3.4) |
  > | `find_me` | GET\|POST `/member/find_me` | **public** |
  > | `user_office_tools` | GET\|POST `/member/office_tools` | ROLE_USER_VIEWER |
  > | `admin_emails_csv` | GET `/member/emails_csv` | ROLE_SUPER_ADMIN |
  > | `beneficiary_edit` | GET\|POST `/beneficiary/{id}/edit` | voter `edit` |
  > | `beneficiary_set_main` | GET `/beneficiary/{id}/set_main` | voter `edit` |
  > | `beneficiary_detach` | POST `/beneficiary/{id}/detach` | voter `edit` |
  > | `beneficiary_delete` | DELETE `/beneficiary/{id}` | voter `edit` (admin ou token) |
  > | `find_member_number` | GET\|POST `/beneficiary/find_member_number` | **public** |
  > | `confirm` | POST `/beneficiary/{id}/confirm` (id=Beneficiary) | **public** |
  > | `note_reply` | POST `/note/note/{id}/reply` | ROLE_USER_VIEWER |
  > | `note_edit` | GET\|POST `/note/note/{id}/edit` | voter `edit` |
  > | `note_delete` | DELETE `/note/note/{id}` | voter `delete` |
  > | `user_quick_new` | GET\|POST `/user/quick_new` | ROLE_USER_VIEWER |
  > | `pre_user_index` | GET `/user/pre_users` | ROLE_USER_VIEWER |
  > | `pre_user_recall` | GET `/user/pre_users/{id}/recall` | ROLE_USER_VIEWER |
  > | `pre_user_delete` | **GET** `/user/pre_users/{id}/delete` | ROLE_USER_MANAGER |
  > | `user_self_register` | GET `/user/self_register` | ROLE_USER |
  >
  > **Chevauchements** : `member_new_registration` (cotisation → SPEC.5) ; intégration Helloasso dans `newAction`/`Registration::TYPE_HELLOASSO` (→ SPEC.5) ; `member_new`/`member_add_beneficiary` déclenchent l'auth FOSUserBundle (→ SPEC.4) ; tous les events de ce domaine alimentent les emails (→ SPEC.7).
  >
  > ---
  >
  > ### Tests existants
  >
  > **`tests/Functional/Controller/MembershipControllerTest.php`** (15 tests) — couverture **surface HTTP** uniquement :
  > - `find_me` : 200, présence du champ, numéro inexistant.
  > - `office_tools` / `emails_csv` : matrice anonyme(302) / admin(200) / user(403).
  > - Routes admin GET 200 (`member_show`, `member_edit_firewall`, `member_join`).
  > - Routes redirigeant (`newRegistration`, `newBeneficiary`).
  > - `member_new` 200 admin ; `add_beneficiary` sans code → refus.
  > - Méthodes HTTP : 405 sur GET pour les routes POST-only (flying/freeze/unfreeze/freeze_change/withdrawn) et DELETE-only (`member_delete`).
  >
  > **`tests/Unit/Service/BeneficiaryServiceTest.php`** (16 tests) : statut/icône/displayName/warning, `getCycleShiftDurationSum`, interaction fly_and_fixed. **N'est pas dans le périmètre controller mais couvre la logique d'affichage bénéficiaire.**
  >
  > **`SmokeTest`** : `find_me`, `office_tools`, `emails_csv` (codes de retour).
  >
  > ---
  >
  > ### Gaps
  >
  > **Sécurité (cross SEC)** :
  > - 🔴 `set_email` : aucune auth + aucun CSRF → **account takeover** (SEC.2.1 + SEC.3.4). Le plus grave du domaine.
  > - 🟡 `pre_user_delete` et `beneficiary_set_main` : mutations via **GET** (pas de CSRF, préchargeables).
  > - 🟡 Flow d'énumération (`find_member_number` → `confirm` → `find_me`) : recherche publique d'adhérents par prénom (SEC.2 finding 6, recommandation rate-limit).
  > - 🟡 `pre_user_recall` : redirection vers `referer` non validé.
  >
  > **Cohérence / dette (cross AP.1, D.5)** :
  > - 🟠 `member_number` non atomique (race condition) — dupliqué dans `newAction` ET `beneficiary_detach`.
  > - 🟠 `joinAction` : fusion sans transaction.
  > - 🟠 `withdrawnBy` jamais renseigné par le cron de clôture (traçabilité perdue).
  > - 🟡 `showAction` : 18 formulaires dans une action ; pattern `// FIXME $member->getMainBeneficiary()->getUser()` triplé (Membership/Beneficiary/Note `redirectToShow`) — null-safety si pas de bénéficiaire principal (D.5 finding 9).
  > - 🟡 `BeneficiaryController::getErrorMessages()` : méthode privée morte (DC.4 B.2).
  > - 🟡 Plafond bénéficiaires : comparaison `count() <= max` dans `newBeneficiary` (off-by-one possible) à confirmer vs `count() >= max` dans `joinAction`.
  >
  > **Non testé** :
  > - Aucun test ne **soumet** réellement les formulaires de cycle de vie (freeze/unfreeze/withdrawn/flying) ni ne vérifie les transitions d'état et la traçabilité (`withdrawnBy`, `withdrawnDate`).
  > - `member_join` (fusion), `beneficiary_detach` (création d'adhésion dérivée), `member_new` avec `code` (parcours pré-inscrit) : aucun test fonctionnel du comportement métier.
  > - `MembershipService::getExpire/isUptodate/canRegister` non testés alors qu'ils portent la logique multi-instance (`registration_every_civil_year`).
  > - `CloseMembershipCommand` (clôture cron) non testé.
  >
  > **Ambigu / à documenter** :
  > - Comportement complet en mode `oidc_enable=true` (Scopeli) : quelles actions restent accessibles ? À confirmer en CONFIG.2 + SPEC.4.
  > - Double préfixe de route `note` (`/note/note/...`) : intentionnel ? Probable vestige de refactor.
  > - Sémantique exacte de `frozenChange` appliqué « en fin de cycle » : par quel mécanisme (commande cron ?) — à relier à SPEC.3 (cycles).

- [x] **SPEC.3** — Spec : Créneaux (Shifts)
  > Sources lues : `ShiftController` (828 l.), `BookingController` (718 l.), `PeriodController` (61 l.), `CardReaderController` (124 l.), `TimeLogController` (102 l.), `AdminPeriodController` (545 l.), `AdminMembershipShiftExemptionController` (230 l.), `AdminShiftExemptionController` (127 l.), `AdminShiftFreeLogController` (148 l.), `AdminPeriodPositionFreeLogController` (118 l.) ; `ShiftService` (596 l.), `MembershipService` (239 l.) ; entités `Shift` (663 l.), `Period` (515 l.), `PeriodPosition` (400 l.), `TimeLog` (321 l.), `MembershipShiftExemption` (313 l.) ; commandes `ShiftGenerateCommand`, `CycleStartCommand`, `CycleHalfCommand`, `FreeReservedShiftsCommand`, `FixShiftMissingPositionCommand` ; tests `ShiftTest`, `ShiftBucketTest`, `ShiftServiceUnitTest`, `ShiftServiceTest`.
  > Croisé avec : AP.1 (firstShiftDate dupliqué, createShift*Form ×5, generateShifts via web), D.5 (shift_cycle TODO, use_fly_and_fixed copy), DC.3 (faux positifs ShiftService), SEC.2.2 + SEC.3.3 (card_reader/check), PERF (N+1 buckets).
  >
  > ---
  >
  > ## SPEC.3 — Créneaux (Shifts)
  >
  > ### Vocabulaire essentiel (lever les ambiguïtés du domaine)
  >
  > | Terme | Entité / objet | Rôle |
  > |-------|----------------|------|
  > | **Créneau** | `Shift` | Occurrence temporelle d'une tâche bénévole (date+heure précise). Peut être libre, réservé, pré-réservé, validé. |
  > | **Slot / Créneau type** | `Period` | Modèle récurrent hebdomadaire (jour de semaine, horaire, job). Sert à générer les `Shift` futurs. |
  > | **Poste** | `PeriodPosition` | Un poste au sein d'un `Period` : un `Period` → N `PeriodPosition` → N bénévoles simultanés possibles. Porte optionnellement un `weekCycle` (A/B/C/D). |
  > | **Bucket** | `ShiftBucket` (non persisté) | Groupe de créneaux partageant le même horaire (start+end) et le même `Job`. Abstractions UI calculées à la volée. |
  > | **Shifter** | `Beneficiary` | Le bénéficiaire qui réalise le créneau. |
  > | **Booker** | `User` | L'utilisateur qui a enregistré la réservation (peut différer du shifter). |
  > | **TimeLog** | `TimeLog` | Journal d'entrées de temps : validation de créneau, début de cycle, ajustements manuels, épargne. |
  > | **Cycle** | (calculé) | Période de bénévolat de 28 jours (hardcodé). Deux modes : `abcd` (synchrone, toute la coop) et `firstShiftDate` (propre à chaque membre). |
  > | **Exemption** | `MembershipShiftExemption` | Plage de dates où un membre est dispensé de bénévolat (maladie, congé…). |
  > | **Créneau fixe / Créneau volant** | `Shift.fixe` | Si `use_fly_and_fixed=true` : "fixe" = même bénévole cycle après cycle via `PeriodPosition.shifter` ; "volant" = à réserver librement chaque cycle. |
  >
  > ---
  >
  > **Acteurs** :
  > - **Anonyme** : widget shift public, schedule.
  > - **ROLE_USER** (adhérent connecté) : réservation de créneau, annulation (avec règles), acceptation/rejet pré-réservation, contact co-bénévoles, vue planning (`period_index`).
  > - **ROLE_SHIFT_MANAGER** : gestion admin créneaux et périodes (réservation, libération, validation de présence, création, génération, exemptions, logs de libération).
  > - **ROLE_ADMIN** : suppression de buckets, périodes, positions, génération de créneaux, copie de périodes.
  > - **ROLE_SUPER_ADMIN** : suppression de `TimeLog`.
  > - **Système / Lecteur de badge** : validation de présence automatique via badge NFC (`card_reader_check`).
  >
  > **Instances** :
  > - **Toutes** : réservation membre, validation, exemptions, TimeLog.
  > - **`use_fly_and_fixed=true`** (Elefan probable) : créneaux fixes/volants, `PeriodPosition.shifter`, interdiction d'annuler les créneaux fixes (`fly_and_fixed_allow_fixed_shift_free`), vue semaine type enrichie.
  > - **`cycle_type=abcd`** (Elefan probable) : semaines ABCD synchronisées sur le calendrier ISO, positions filtrées par `weekCycle`.
  > - **`use_time_log_saving=true`** (instance-specific) : compteur épargne, règles d'annulation avec délai min et vérification de solde.
  > - **`reserve_new_shift_to_prior_shifter=true`** (instance-specific) : pré-réservation du nouveau créneau pour l'ancien bénévole du cycle précédent.
  > - **`newUserStartAsBeginner=true`** (instance-specific) : blocage des nouveaux bénévoles sur les buckets vides.
  >
  > ---
  >
  > ### Sous-domaine 1 — Génération des créneaux (Period → Shift)
  >
  > **Flux principal — Configuration des semaines types** :
  > 1. L'admin crée un `Period` (`admin_period_new`) : jour de semaine (0=Lundi…6=Dimanche), horaire start/end, `Job` (type de poste).
  > 2. Il ajoute des `PeriodPosition` (`admin_periodposition_new`) : qualification (`Formation` optionnelle), `weekCycle` (A/B/C/D si `cycle_type=abcd`, null sinon), nombre d'exemplaires.
  > 3. En mode `use_fly_and_fixed` : il assigne un bénéficiaire fixe à la position (`admin_periodposition_book`).
  > 4. Il peut copier un jour sur un autre (`admin_period_copy`). **⚠️ La copie ne transfère pas les shifters** (clone PHP → shifter=null), TODO dans le code.
  >
  > **Flux principal — Génération des créneaux futurs** (`app:shift:generate <date> [--to <date>]`) :
  > 1. Pour chaque date dans la plage, vérifie d'abord les `ClosingException` → si fermeture exceptionnelle, aucun créneau généré.
  > 2. Récupère les `Period` correspondant au `dayOfWeek` de la date.
  > 3. Pour chaque `Period`, pour chaque `PeriodPosition` :
  >    - En mode `abcd` : ignore les positions dont le `weekCycle` ne correspond pas à la semaine ISO courante (modulo 4 → A/B/C/D).
  >    - Vérifie l'idempotence : `findBy(start+end+job+position)` → ne crée pas si existe déjà.
  >    - Si `use_fly_and_fixed` et `position.shifter != null` et membre non exempté → créneau fixe (shifter pré-rempli, `fixe=true`).
  >    - Si `reserve_new_shift_to_prior_shifter` et créneau du cycle précédent (J-28) avait un shifter → `lastShifter` = ancien shifter (pré-réservé).
  >    - Sinon → créneau libre.
  > 4. Dispatch `ShiftReservedEvent` pour chaque créneau pré-réservé (email au bénévole pour confirmation).
  >
  > **⚠️ Antipattern AP.1 — Commande depuis le web** : `admin_shifts_generation` instancie `Symfony\Bundle\FrameworkBundle\Console\Application` en mémoire et exécute `app:shift:generate` via `Application::run()`. Output ANSI capturé dans un flash message — risque de timeout HTTP pour des plages longues. Pattern à remplacer par un bus de messages ou une route API avec streaming.
  >
  > ---
  >
  > ### Sous-domaine 2 — Réservation de créneau (membre)
  >
  > **Flux principal** :
  > 1. L'adhérent accède à `/booking` → si adhésion expirée ou gelée → redirection homepage.
  > 2. Si plusieurs bénéficiaires dans l'adhésion → page de sélection bénéficiaire.
  > 3. Vue calendrier : créneaux futurs groupés en `ShiftBucket` (même horaire + job), rendus sur une grille 6h-22h.
  > 4. Clic sur un bucket → modal → l'adhérent choisit un créneau disponible.
  > 5. POST `shift_book` : `beneficiaryId` + `typeService` (fixe=1/volant=0 si `use_fly_and_fixed`).
  > 6. `ShiftService::isShiftBookable()` vérifie toutes les règles (voir Règles métier).
  > 7. Si OK : `shift.shifter = beneficiary`, `shift.booker = currentUser`, `shift.bookedTime = now`. Si `firstShiftDate` null → mis à jour.
  > 8. Dispatch `ShiftBookedEvent` → emails (SPEC.7).
  >
  > **⚠️ firstShiftDate dupliqué (AP.1)** : la mise à jour de `firstShiftDate` est copiée-collée dans `bookShiftAction` (l.170-175) **et** `bookShiftAdminAction` (l.226-232). Logique identique, à factoriser.
  >
  > **Réservation admin** (`shift_book_admin`, `ROLE_SHIFT_MANAGER`) :
  > - Peut réserver pour n'importe quel membre.
  > - Vérifications supplémentaires : qualification (formation), exemption, règle `forbid_own_shift_book_admin` (ne peut pas réserver son propre créneau, sauf ROLE_ADMIN).
  > - Le choix fixe/volant est présent si `use_fly_and_fixed`.
  >
  > **Pré-réservation** (`shift_accept_reserved`, `shift_reject_reserved`) :
  > - Quand `reserve_new_shift_to_prior_shifter=true`, le créneau généré a un `lastShifter` (ancien bénévole) et l'email `ShiftReservedEvent` lui propose d'accepter ou refuser.
  > - `shift_accept_reserved` : GET `/shift/{id}/accept` → confirme la réservation (`shifter = lastShifter`, `lastShifter = null`).
  > - `shift_reject_reserved` : GET `/shift/{id}/reject` → libère (`lastShifter = null`). Le créneau devient libre.
  > - **⚠️ Mutations via GET** (pas de CSRF, pas de protection anti-rejeu).
  > - L'accès est contrôlé par le voter `accept`/`reject` sur `Shift` (à documenter en SPEC.4).
  >
  > ---
  >
  > ### Sous-domaine 3 — Annulation de créneau
  >
  > **Flux membre** (`shift_free`, POST, ROLE_USER, voter `ShiftVoter::FREE`) :
  > 1. Vérifie `canFreeShift(currentUser.beneficiary, shift)` — règles voir Règles métier.
  > 2. Store `beneficiary`, `fixe`, `reason` avant libération.
  > 3. `shift.free()` : efface shifter, booker, bookedTime, fixe.
  > 4. Dispatch `ShiftFreedEvent` → emails, `ShiftFreeLog` créé par listener.
  > 5. En mode `use_time_log_saving` : si un `TimeLog::TYPE_SAVING` existe sur ce shift/membre → message info compteur épargne décrémenté.
  >
  > **Flux admin** (`shift_free_admin`, ROLE_SHIFT_MANAGER) :
  > - Règle `forbid_own_shift_free_admin`.
  > - Si le créneau était validé (`wasCarriedOut=1`) → appelle d'abord `invalidateShiftParticipation()` avant de libérer → dispatch `ShiftInvalidatedEvent` puis `ShiftFreedEvent`.
  >
  > **Libération des pré-réservés** (`app:shift:free <date>`) :
  > - Libère les `lastShifter` non confirmés à la date donnée (les bénévoles n'ayant pas répondu à l'email de pré-réservation).
  > - **⚠️ Coordination cron non documentée** : doit être lancé `reserve_new_shift_to_prior_shifter_delay` jours après `app:shift:generate`. Ce délai est un paramètre app mais l'orchestration cron n'est pas spécifiée.
  >
  > ---
  >
  > ### Sous-domaine 4 — Validation de présence
  >
  > **Via lecteur de badge** (`card_reader_index`, `card_reader_check`) :
  > 1. `card_reader_index` : voter `card_reader` (mécanisme à documenter en SPEC.4) ; liste les créneaux en cours et à venir du jour.
  > 2. `card_reader_check` (POST) : reçoit `swipe_code` (EAN13), vérifie l'intégrité EAN13, décode le badge, trouve le bénéficiaire.
  > 3. Trouve les créneaux en cours (+10 min de tolérance) pour ce bénéficiaire → valide (`wasCarriedOut=true`) ceux qui ne l'étaient pas.
  > 4. Dispatch `ShiftValidatedEvent` → `TimeLog::TYPE_SHIFT_VALIDATED` via listener.
  > 5. Affiche le compteur de temps restant du cycle courant.
  > 6. Si `swipeCardLogging=true` → dispatch `SwipeCardEvent` (logs de passage).
  >
  > **🔴 card_reader_check sans authentification ni CSRF (SEC.2.2 + SEC.3.3)** : la route POST `/card_reader/check` n'a **aucun contrôle d'accès** (@Route sans @Security, pas de `denyAccessUnlessGranted`). N'importe qui connaissant un code EAN13 valide + un code de badge actif peut valider des créneaux ou lire les compteurs de temps d'un membre. La tolérance de +10 min élargit encore la fenêtre d'abus. Seul garde-fou : la vérification EAN13 et la validité du badge en base.
  >
  > **Via interface admin** (`shift_validate_admin`, POST, ROLE_SHIFT_MANAGER, voter `ShiftVoter::VALIDATE`) :
  > - Bascule `wasCarriedOut` entre true et false (validation ↔ invalidation).
  > - Règle `forbid_own_shift_validate_admin`.
  > - Dispatch `ShiftValidatedEvent` ou `ShiftInvalidatedEvent`.
  >
  > ---
  >
  > ### Sous-domaine 5 — Mécanisme de cycle
  >
  > **Deux modes selon `cycle_type`** :
  >
  > | Mode | Déclencheur | `getStartOfCycle()` |
  > |------|-------------|---------------------|
  > | `abcd` | ISO week number | Remonte au dernier "lundi de semaine A" (week % 4 == 1). Tous les membres au même rythme. |
  > | (défaut / firstShiftDate) | `membership.firstShiftDate` | Start = firstShiftDate + N×28j (N = nombre de cycles écoulés). Cycle propre à chaque membre. |
  >
  > **Durée toujours 28 jours** : `getEndOfCycle` = start + 27 jours, `lastCycleDate` dans `ShiftGenerateCommand` = J-28. **Hardcodé partout** avec un TODO "should use cycle_duration instead of hardcoded 28" (`MembershipService.php:155`, `ShiftGenerateCommand:168`). Aucun paramètre `cycle_duration` exposé.
  >
  > **Application du frozenChange** (gap résolu de SPEC.2) :
  > - La commande `app:user:cycle_start` identifie les membres dont un nouveau cycle commence (`Membership::findWithNewCycleStarting(date, cycle_type)`).
  > - Elle dispatche `MemberCycleEndEvent` → le listener `MemberCycleEndEventListener` applique `frozenChange` : si `frozenChange=true`, le membre passe en `frozen=true` pour le nouveau cycle (ou le dégage selon la direction). C'est le seul mécanisme d'application automatique du gel demandé.
  >
  > **Alerte mi-cycle** (`app:user:cycle_half`) : à mi-cycle, dispatch `MemberCycleHalfEvent` avec les créneaux déjà réservés → emails d'alerte aux membres qui n'ont pas encore tout réservé.
  >
  > **⚠️ shift_cycle — confusion d'unité (DC.3/AP.1)** dans `ShiftService::isShiftBookable` (l.257) :
  > ```php
  > // TODO refactor code to remove shift_cycle
  > $shift_cycle = $this->membershipService->getCycleNumber($member, $shift->getStart());
  > return $this->canBookDuration($beneficiary, $shift->getDuration(), $shift_cycle) or $this->canBookExtraShift($beneficiary, $shift);
  > ```
  > `getCycleNumber` retourne le numéro de cycle **relatif** (peut valoir -1, 0, 1, 2…) mais `canBookDuration` attend `$cycle = 0` pour cycle courant, `1` pour cycle suivant. Les valeurs négatives ou >1 produisent des calculs incorrects → faux positifs DC.3 potentiels.
  >
  > ---
  >
  > ### Sous-domaine 6 — Exemptions de créneau
  >
  > **Deux entités** :
  > - `ShiftExemption` : catalogue des motifs (maladie, congé parental…). CRUD admin via `admin_shiftexemption_*`.
  > - `MembershipShiftExemption` : instance d'exemption pour un membre sur une plage de dates. Unique par (membership, start) et (membership, end).
  >
  > **Flux** :
  > 1. Admin crée une exemption (`admin_membershipshiftexemption_new`) → vérifie que le membre n'a pas de créneaux planifiés sur la période (blocage si oui).
  > 2. L'exemption est vérifiée avant toute réservation (`Membership::isCurrentlyExemptedFromShifts(date)`).
  > 3. La génération des créneaux fixes respecte aussi l'exemption (`ShiftGenerateCommand:127`).
  > 4. Suppression bloquée si le début est passé (sauf ROLE_SUPER_ADMIN).
  >
  > ---
  >
  > ### Sous-domaine 7 — TimeLog (compteur de temps)
  >
  > Le `TimeLog` est le **journal comptable** du bénévolat. Chaque entrée a un `type`, un `time` (en minutes, smallint), et est rattachée à un `Membership` (et optionnellement à un `Shift`).
  >
  > | Type (constante) | Valeur | Déclencheur |
  > |------------------|--------|-------------|
  > | `TYPE_SHIFT_VALIDATED` | 1 | Validation de présence (badge ou admin) |
  > | `TYPE_SHIFT_INVALIDATED` | 10 | Invalidation admin |
  > | `TYPE_SHIFT_FREED_SAVING` | 21 | Annulation avec compteur épargne |
  > | `TYPE_CYCLE_END` | 2 | Début de cycle (crédité/débité) |
  > | `TYPE_CYCLE_END_FROZEN` | 3 | Début de cycle (compte gelé) |
  > | `TYPE_CYCLE_END_EXPIRED_REGISTRATION` | 4 | Début de cycle (adhésion expirée) |
  > | `TYPE_CYCLE_END_EXEMPTED` | 6 | Début de cycle (exempté) |
  > | `TYPE_CYCLE_END_SAVING` | 7 | Début de cycle (compteur épargne) |
  > | `TYPE_REGULATE_OPTIONAL_SHIFTS` | 5 | Régulation bénévolat facultatif |
  > | `TYPE_SAVING` | 20 | Compteur épargne +/- |
  > | `TYPE_CUSTOM` | 0 | Ajout manuel admin (`timelog_new`) |
  >
  > Le champ `requestRoute` trace la route à l'origine de l'entrée (audit trail technique).
  >
  > **Règles de protection** :
  > - Création manuelle : ROLE_SHIFT_MANAGER, garde `forbid_own_timelog_new_admin`.
  > - Suppression : ROLE_SUPER_ADMIN uniquement.
  > - Les TimeLog sont cascadés à la suppression de la `Membership` (ON DELETE CASCADE).
  >
  > ---
  >
  > ### Sous-domaine 8 — Verrouillage de bucket (locked)
  >
  > Un admin ROLE_SHIFT_MANAGER peut verrouiller/déverrouiller un bucket (`bucket_lock_unlock`) via `ShiftVoter::LOCK`. Le verrou s'applique à **tous les créneaux du bucket** (même horaire+job). Un créneau verrouillé (`locked=true`) n'est plus réservable.
  >
  > ---
  >
  > ### Données — récapitulatif des entités
  >
  > | Entité | Champs clés | Relations |
  > |--------|-------------|-----------|
  > | `Shift` | `start`(datetime), `end`(datetime), `wasCarriedOut`(bool), `locked`(bool), `fixe`(bool), `bookedTime`, `createdAt` | N-1 `job`→Job(EAGER), N-1 `shifter`→Beneficiary, N-1 `booker`→User, N-1 `lastShifter`→Beneficiary, N-1 `position`→PeriodPosition(onDelete=SET NULL), N-1 `formation`→Formation(onDelete=SET NULL), N-1 `createdBy`→User, 1-N `timeLogs`, 1-N `freeLogs`→ShiftFreeLog |
  > | `Period` | `dayOfWeek`(smallint 0-6 Lun-Dim), `start`(time), `end`(time), `createdAt`, `updatedAt` | N-1 `job`→Job(EAGER), 1-N `positions`→PeriodPosition(cascade=persist+remove), N-1 `createdBy`, N-1 `updatedBy`→User |
  > | `PeriodPosition` | `weekCycle`(string 1, nullable: A/B/C/D), `bookedTime`, `createdAt`, `updatedAt` | N-1 `period`→Period, N-1 `shifter`→Beneficiary(nullable), N-1 `booker`→User, N-1 `formation`→Formation(onDelete=CASCADE), 1-N `shifts`, 1-N `freeLogs`→PeriodPositionFreeLog |
  > | `TimeLog` | `time`(smallint, minutes), `type`(smallint), `description`(nullable), `requestRoute`(nullable), `createdAt` | N-1 `membership`→Membership(onDelete=CASCADE), N-1 `shift`→Shift(nullable, onDelete=SET NULL), N-1 `createdBy`→User |
  > | `MembershipShiftExemption` | `start`(date), `end`(date), `description`, `createdAt` | N-1 `membership`→Membership(onDelete=CASCADE), N-1 `shiftExemption`→ShiftExemption, N-1 `createdBy`→User |
  > | `ShiftBucket` | (non persisté, calculé) | Collection de `Shift` partageant start+end+job |
  >
  > ---
  >
  > ### Règles métier — `isShiftBookable` (ShiftService:207)
  >
  > Contrôles appliqués dans l'ordre :
  > 1. Créneau passé, verrouillé ou déjà réservé → **refus**
  > 2. `lastShifter` ≠ beneficiary → **refus** (réservé pour quelqu'un d'autre)
  > 3. Formation requise non obtenue → **refus**
  > 4. `newUserStartAsBeginner` + bucket vide (`isShiftEmpty`) → **refus** (débutant ne peut pas être le premier)
  > 5. Chevauchement horaire (`forbidShiftOverlapTime` minutes de marge) → **refus**
  > 6. Membre exempté à la date du créneau → **refus**
  > 7. Membre retiré (`withdrawn`) → **refus**
  > 8. `firstShiftDate > shift.start` → **refus** (créneau antérieur à la date d'entrée)
  > 9. Membre gelé → **refus** si créneau ≤ fin de cycle courant ; si créneau > fin de cycle courant et `!frozenChange` → **refus** également
  > 10. Quota cycle : `canBookDuration(beneficiary, shift.duration, shift_cycle) OR canBookExtraShift(...)` → si refus des deux → **refus**
  >
  > ### Règles métier — `canFreeShift` (ShiftService:267)
  >
  > 1. Créneau sans shifter → **refus**
  > 2. Shifter ≠ beneficiary → **refus**
  > 3. (non admin uniquement) Créneau passé ou en cours → **refus**
  > 4. (non admin) `use_fly_and_fixed` + créneau fixe + `!fly_and_fixed_allow_fixed_shift_free` → **refus**
  > 5. (non admin) `use_time_log_saving` + délai mini non respecté → **refus**
  > 6. (non admin) `use_time_log_saving` + solde épargne insuffisant → **refus**
  >
  > ---
  >
  > ### Routes — inventaire complet (~45)
  >
  > | Route | Méthode / chemin | Contrôle d'accès |
  > |-------|------------------|------------------|
  > | `booking` | GET\|POST `/booking/` | ROLE_USER |
  > | `booking_by_day` | GET\|POST `/booking/day/{day}/{beneficiary}/{cycle}` | ROLE_USER (si beneficiary fourni) |
  > | `bucket_show` | GET `/booking/bucket/{id}/show` | public (anonyme possible) |
  > | `bucket_show_for_beneficiary` | GET `/booking/bucket/{id}/show/for/{beneficiary}/cycle/{cycle}` | ROLE_USER |
  > | `shift_book` | POST `/shift/{id}/book` | ROLE_USER |
  > | `shift_free` | POST `/shift/{id}/free` | ROLE_USER + voter FREE |
  > | `shift_accept_reserved` | **GET** `/shift/{id}/accept` | voter `accept` |
  > | `shift_reject_reserved` | **GET** `/shift/{id}/reject` | voter `reject` |
  > | `shift_contact_form` | GET\|POST `/shift/{id}/contact_form` | **public** (⚠️ pas de @Security) |
  > | `shift_widget` | GET `/shift/widget` | **public** |
  > | `period_index` | GET\|POST `/period/` | ROLE_USER |
  > | `booking_admin` | GET\|POST `/booking/admin` | ROLE_SHIFT_MANAGER |
  > | `admin_bucket_show` | GET `/booking/admin/bucket/{id}/show` | ROLE_SHIFT_MANAGER |
  > | `bucket_edit` | GET\|POST `/booking/bucket/{id}/edit` | ROLE_SHIFT_MANAGER |
  > | `bucket_lock_unlock` | POST `/booking/bucket/{id}/lock` | ROLE_SHIFT_MANAGER + voter LOCK |
  > | `bucket_delete` | DELETE `/booking/bucket/{id}` | ROLE_ADMIN |
  > | `shift_new` | GET\|POST `/shift/new` | ROLE_SHIFT_MANAGER |
  > | `shift_book_admin` | GET\|POST `/shift/{id}/book_admin` | ROLE_SHIFT_MANAGER |
  > | `shift_free_admin` | POST `/shift/{id}/free_admin` | ROLE_SHIFT_MANAGER + voter FREE |
  > | `shift_validate_admin` | POST `/shift/{id}/validate_admin` | ROLE_SHIFT_MANAGER + voter VALIDATE |
  > | `shift_delete` | DELETE `/shift/{id}` | ROLE_ADMIN |
  > | `admin_period_index` | GET\|POST `/admin/period/` | ROLE_SHIFT_MANAGER |
  > | `admin_period_new` | GET\|POST `/admin/period/new` | ROLE_SHIFT_MANAGER |
  > | `admin_period_edit` | GET\|POST `/admin/period/{id}/edit` | ROLE_SHIFT_MANAGER |
  > | `admin_period_delete` | DELETE `/admin/period/{id}` | ROLE_ADMIN |
  > | `admin_period_copy` | GET\|POST `/admin/period/copy` | ROLE_ADMIN |
  > | `admin_periodposition_new` | POST `/admin/period/{id}/position/add` | ROLE_SHIFT_MANAGER |
  > | `admin_periodposition_delete` | DELETE `/admin/period/{id}/position/{position}` | ROLE_ADMIN |
  > | `admin_periodposition_book` | POST `/admin/period/{id}/position/{position}/book` | ROLE_SHIFT_MANAGER |
  > | `admin_periodposition_free` | POST `/admin/period/{id}/position/{position}/free` | ROLE_SHIFT_MANAGER |
  > | `admin_shifts_generation` | GET\|POST `/admin/period/generateShifts/` | ROLE_ADMIN |
  > | `admin_shiftexemption_index/new/edit/delete` | — | ROLE_SHIFT_MANAGER |
  > | `admin_membershipshiftexemption_index/new/edit/delete` | — | ROLE_USER_MANAGER |
  > | `admin_shiftfreelog_index` | GET `/admin/shiftfreelog/` | ROLE_SHIFT_MANAGER |
  > | `admin_periodpositionfreelog_index` | GET `/admin/periodpositionfreelog/` | ROLE_SHIFT_MANAGER |
  > | `timelog_new` | GET\|POST `/time_log/{id}/new` | ROLE_SHIFT_MANAGER (garde own) |
  > | `member_timelog_delete` | DELETE `/time_log/{id}/timelog_delete/{timelog_id}` | ROLE_SUPER_ADMIN |
  > | `card_reader_index` | GET `/card_reader/` | voter `card_reader` |
  > | `card_reader_check` | POST `/card_reader/check` | 🔴 **AUCUN** (SEC.2.2 + SEC.3.3) |
  > | `ambassador_shifttimelog_list` | — | AmbassadorController (ROLE_USER_VIEWER) |
  > | `ambassador_beneficiary_fixe_without_periodposition_list` | — | AmbassadorController (ROLE_USER_VIEWER) |
  >
  > **Chevauchements** : `shift_contact_form` (→ SPEC.7) ; `card_reader_*` / badges / SwipeCard / codes d'accès physique (→ SPEC.4 domaine J transverse) ; génération intégrant `ClosingException`/`OpeningHour` (→ SPEC.6).
  >
  > ---
  >
  > ### Événements dispatché (cross SPEC.7)
  >
  > | Événement | Déclencheur | Listener attendu |
  > |-----------|-------------|-----------------|
  > | `ShiftBookedEvent` | Réservation membre ou admin | Email confirmation |
  > | `ShiftFreedEvent` | Annulation (membre ou admin) | Email annulation, ShiftFreeLog |
  > | `ShiftValidatedEvent` | Validation badge ou admin | TimeLog TYPE_SHIFT_VALIDATED |
  > | `ShiftInvalidatedEvent` | Invalidation admin | TimeLog TYPE_SHIFT_INVALIDATED |
  > | `ShiftDeletedEvent` | Suppression créneau ou bucket | Email si réservé |
  > | `ShiftReservedEvent` | Pré-réservation (generate) | Email au lastShifter |
  > | `ShiftReminderEvent` | `app:shift:reminder` (cron) | Email rappel |
  > | `ShiftAlertsEvent` | `app:shift:send_alerts` (cron) | Email/Mattermost alertes créneaux vides |
  > | `PeriodPositionFreedEvent` | Libération position fixe | Email au bénévole |
  > | `MemberCycleEndEvent` | `app:user:cycle_start` | Gel/dégel, TimeLog cycle |
  > | `MemberCycleHalfEvent` | `app:user:cycle_half` | Email alerte mi-cycle |
  >
  > ---
  >
  > ### Tests existants
  >
  > **`tests/Unit/Entity/ShiftTest.php`** (553 lignes) — très complet : `getDuration`, `getIsPast/Current/Future/Upcoming`, `isFixe`, `isBefore`, `isFirstByShifter`, `getTmpToken`, formats d'affichage de dates. Bonne couverture de l'entité.
  >
  > **`tests/Unit/Entity/ShiftBucketTest.php`** (439 lignes) — `addShift`, `canBookInterval`, `filterByFormations`, `compareShifts`, `getShiftWithMinId`. Couverture complète de l'entité virtuelle.
  >
  > **`tests/Unit/Service/ShiftServiceUnitTest.php`** (512 lignes) — `isBeginner`, `canBookDuration`, `canBookOnCycle`, `canBookSomething`, `canFreeShift`, `isShiftBookable`. Tests unitaires avec mocks extensifs. Couvre les principales règles métier.
  >
  > **`tests/Integration/Service/ShiftServiceTest.php`** (339 lignes) — tests d'intégration avec DB. Couvre les cas de cycle, exemption, gel.
  >
  > **`tests/Unit/Service/PeriodServiceTest.php`** — `getWeekCycleArray` uniquement.
  >
  > **SmokeTest** : `booking`, `booking_admin` (codes retour uniquement).
  >
  > ---
  >
  > ### Gaps
  >
  > **Sécurité (cross SEC)** :
  > - 🔴 `card_reader_check` : aucune authentification, aucun CSRF → validation de présence possible par n'importe qui avec un badge EAN13 valide. Lecture des compteurs de temps membres.
  > - 🟡 `shift_contact_form` : aucune annotation @Security → public de facto. Un anonyme peut appeler la route avec n'importe quel `{id}` de shift et envoyer un email aux co-bénévoles.
  > - 🟡 `shift_accept_reserved` / `shift_reject_reserved` : mutations via **GET** (pas de CSRF, rejouables).
  >
  > **Code / dette (cross AP.1, D.5)** :
  > - 🟠 Duplication `createShift*Form` (5 méthodes) entre `ShiftController` et `BookingController` — TODO dans les commentaires.
  > - 🟠 `firstShiftDate` mis à jour à deux endroits identiques (`bookShiftAction` + `bookShiftAdminAction`).
  > - 🟠 `admin_shifts_generation` lance `app:shift:generate` via `Application::run()` depuis un controller HTTP — risque timeout, output ANSI dans flash message.
  > - 🟠 `shift_cycle` dans `isShiftBookable` : confusion d'unité (`getCycleNumber` retourne relatif, `canBookDuration` attend 0/1). TODO inline.
  > - 🟠 Durée de cycle 28 jours hardcodée dans 3 endroits (TODO inline dans `MembershipService`).
  > - 🟡 `admin_period_copy` ne copie pas les shifters des positions (TODO inline).
  > - 🟡 `FixShiftMissingPositionCommand` : ne fonctionne pas pour `cycle_type=abcd` (exit code 1 explicite, TODO weekCycle).
  > - 🟡 `app:shift:free` (libération pré-réservés) : coordination cron non documentée avec `reserve_new_shift_to_prior_shifter_delay`.
  >
  > **Non testé** :
  > - `app:shift:generate`, `app:user:cycle_start`, `app:user:cycle_half`, `app:shift:free` : aucun test de commande.
  > - `card_reader_check` : aucun test fonctionnel.
  > - Génération en mode `reserve_new_shift_to_prior_shifter` et flow pré-réservation complet.
  > - Validation/invalidation de présence (admin + badge) : parcours métier non couvert.
  > - Mode `use_time_log_saving` : `canFreeShift` avec épargne ; création/décrémentation du compteur.
  > - Mode `fly_and_fixed` : génération de créneaux fixes, interdiction d'annulation.
  > - `admin_period_copy` : comportement des shifters sur clone.
  > - Exemptions : test de la règle de blocage à la création.
  >
  > **Ambigu / à confirmer** :
  > - Voter `card_reader` (qui y a accès ? ROLE_SHIFT_MANAGER ? Rôle dédié ?) — à documenter en SPEC.4.
  > - Voter `accept`/`reject` sur `Shift` — règles exactes (qui peut accepter/rejeter une pré-réservation pour qui ?).
  > - Voter `ShiftVoter::LOCK` — qui peut verrouiller ? (ROLE_ADMIN ? ROLE_SHIFT_MANAGER ?) — à confirmer dans `ShiftVoter`.
  > - `schedule` : route listée en SPEC.1 domaine B — non trouvée dans les controllers lus (probable `AmbassadorController` ou `DefaultController`).
  > - `ambassador_shifttimelog_list` et `ambassador_beneficiary_fixe_without_periodposition_list` : routes ambassadeur partiellement dans SPEC.3 — à documenter complètement en SPEC.6 (administration) ou transverse.
  > - `ShiftExemption` (catalogue) vs `MembershipShiftExemption` (instance) : double CRUD (`admin_shiftexemption_*` via `AdminShiftExemptionController` + `admin_membershipshiftexemption_*`) — relation et droits à clarifier.

- [x] **SPEC.4** — Spec : Authentification & Autorisation
  > Sources lues : `security.yaml`, `AuthenticationSuccessHandler`, `KeycloakAuthenticator`, `OAuthController`, `SwipeCardController`, `UserController`, `AdminController` (routes auth), `CardReaderController`, `CodeController`, `UpdateIgloohomeCodeCommand`, `VerifyCodeChangeCommand` ; `SwipeCardVoter`, `CodeVoter`, `ShiftVoter`, `UserVoter`, `MembershipVoter` ; entités `User`, `SwipeCard`, `Code` ; `SwipeCardHelper` (Vigenère) ; `SwipeCardEventListener`.
  > Croisé avec : DC.2 (AuthenticationSuccessHandler null), SEC.1.7 (Vigenère), SEC.2.2 + SEC.3.3 (card_reader/check sans auth → SPEC.3), AP.7 (CodeEventListener corps commenté), SPEC.2 (token temporaire md5 / oidc gap), SPEC.3 (voter accept/reject/lock/card_reader).
  >
  > ---
  >
  > ## SPEC.4 — Authentification & Autorisation (+ Domaine J : Accès physique)
  >
  > ### Vocabulaire essentiel
  >
  > | Terme | Entité / concept | Rôle |
  > |-------|-----------------|------|
  > | **User** | `User` (FOSUserBundle `BaseUser`) | Identifiants (email, username, mot de passe bcrypt, rôles). Toujours associé 1-1 à un `Beneficiary` pour les adhérents. |
  > | **Rôle** | string ROLE_* stocké en JSON dans `fos_user.roles` | Attribution de droits. Géré via hiérarchie Symfony. |
  > | **Badge / SwipeCard** | `SwipeCard` | Carte NFC physique associée à un `Beneficiary`. Porte un `code` (EAN13 sur 12 chiffres) stocké en clair. Peut être activé/désactivé. Sert à l'auth passwordless et à la validation de présence. |
  > | **Code de porte** | `Code` | Code numérique d'accès au local (typiquement 4 chiffres), géré en rotation. Ouvert/fermé. Associé à un `registrar` (User). |
  > | **Voter** | classe Symfony `Voter` | Décide l'accès à un objet métier pour un attribut donné (ex. `view`, `edit`). Plusieurs voters peuvent opérer sur le même attribut, le `decisionManager` les agrège (strategy : `affirmative` par défaut). |
  > | **Vigenère** | `App\Helper\SwipeCard` | Algorithme de chiffrement symétrique basé sur XOR+base64, clé `SWIPE_CARD_SECRET`. Utilisé pour obfusquer les codes de badge dans les URLs et les tokens de notifications email. |
  > | **OIDC / Keycloak** | `KeycloakAuthenticator` (Guard SF4) | Auth OpenID Connect entrante depuis Keycloak (instance Scopeli uniquement). Crée ou met à jour le `User`/`Beneficiary`/`Membership` à chaque connexion. |
  > | **OAuth sortant** | FOSOAuthServerBundle + `ClientRegistry` | Expose un serveur OAuth 2.0 (SSO sortant, ex. vers Nextcloud/GitLab). Distinct de l'OIDC entrant. |
  > | **PlaceIP** | `App\Helper\PlaceIP` | Vérifie que la requête provient de l'IP locale du local coopératif (configurable). Garde-fou pour les actions physiques (appairage de badge, création de membre, génération de code). |
  >
  > ---
  >
  > ### Hiérarchie des rôles (`security.yaml:4-13`)
  >
  > ```
  > ROLE_USER                    ← base : adhérent connecté
  >   └─ ROLE_ADMIN_PANEL        ← accès au panneau /admin/
  >        ├─ ROLE_SHIFT_MANAGER  ← gestion des créneaux
  >        ├─ ROLE_USER_VIEWER    ← lecture fiches membres
  >        │    └─ ROLE_USER_MANAGER  ← mutations membres (gel, fermeture)
  >        ├─ ROLE_FINANCE_MANAGER ← cotisations/paiements
  >        └─ ROLE_PROCESS_MANAGER ← notes de version
  >
  > ROLE_ADMIN = [ROLE_USER_MANAGER, ROLE_FINANCE_MANAGER,
  >               ROLE_SHIFT_MANAGER, ROLE_PROCESS_MANAGER]
  > ROLE_SUPER_ADMIN ⊇ ROLE_ADMIN
  > ROLE_OAUTH_LOGIN ⊇ ROLE_USER   ← clients OAuth API
  > ```
  >
  > Implication : `ROLE_USER_MANAGER` implique `ROLE_USER_VIEWER` implique `ROLE_ADMIN_PANEL` implique `ROLE_USER`. L'`ROLE_ADMIN` absorbe tous les rôles "manager" sauf le chemin ROLE_ADMIN_PANEL direct (qui n'est pas parent de ROLE_SHIFT_MANAGER en soi — ROLE_SHIFT_MANAGER est enfant de ROLE_ADMIN_PANEL).
  >
  > **Mécanisme switch_user** (`security.yaml:28-30`) : ROLE_ADMIN peut usurper l'identité d'un autre utilisateur via le paramètre `_login_as`. Aucune route dédiée — paramètre GET/POST dans n'importe quelle requête. L'impersonification crée un token avec `ROLE_PREVIOUS_ADMIN`.
  >
  > ---
  >
  > **Acteurs** :
  > - **Anonyme** : accès public (login, reset password, registration FOS, `swipe_in`, `shift_accept/reject_reserved` via token URL, `card_reader_index`/`card_reader_check` depuis l'IP locale, `code_change_done` via token Vigenère).
  > - **ROLE_USER** (adhérent) : profil FOS, changement de mot de passe, appairage/activation/désactivation de badge (propre), liste des codes de porte si non-débutant avec créneau actif.
  > - **ROLE_USER_VIEWER** : lecture fiches membres, listes admin (users, rôles, pré-inscrits), `swipe_show`.
  > - **ROLE_USER_MANAGER** : désactivation de badges (admin override), mutations membres.
  > - **ROLE_ADMIN** : ajout/retrait de rôles (sauf ROLE_ADMIN), toggle codes (open/close), suppression de badges.
  > - **ROLE_SUPER_ADMIN** : import CSV utilisateurs, suppression User, ajout ROLE_ADMIN à un user, suppression de codes, `user_install_admin` (second admin).
  > - **Système** : `UpdateIgloohomeCodeCommand`, `VerifyCodeChangeCommand` (tâches cron).
  > - **Keycloak (Scopeli)** : IdP externe ; le `KeycloakAuthenticator` réconcilie les identités à chaque callback.
  >
  > **Instances** :
  > - **Toutes** : authentification FOS (login/logout/reset/profil), gestion des rôles, badges SwipeCard, lecteur de badge.
  > - **Scopeli uniquement** (`oidc_enable=true`) : flux OIDC Keycloak (`oauth_login/logout/check`) ; `MembershipVoter::canEdit` retourne toujours `false` (identité déléguée à Keycloak) ; `UserVoter` retourne `false` pour tous les utilisateurs authentifiés (le lecteur de badge fonctionne en mode anonyme depuis l'IP locale).
  > - **Elefan probable** (`code_generation_enabled=true`) : génération de codes de porte rotatifs par les membres (CodeVoter::GENERATE).
  > - **Igloohome** : serrures connectées, instance-specific (paramètres `IGLOOHOME_*`).
  >
  > ---
  >
  > ### Sous-domaine 1 — Authentification FOSUserBundle (form-based)
  >
  > **Flux principal — Login** :
  > 1. GET `/login` → formulaire (username/email + password + CSRF token).
  > 2. POST `/login_check` → FOSUserBundle vérifie les credentials (bcrypt), crée la session Symfony.
  > 3. Symfony appelle `AuthenticationSuccessHandler::onAuthenticationSuccess()` (via `security.interactive_login`).
  > 4. **⚠️ Bug DC.2** : si la requête ne contient pas de `target_path`, la méthode retourne `null` implicitement au lieu d'une `Response`. Elle viole `AuthenticationSuccessHandlerInterface` (qui exige `Response`). En pratique, le handler est enregistré comme listener sur `security.interactive_login` via `onSecurityInteractiveLogin` qui appelle `onAuthenticationSuccess` mais **ignore sa valeur de retour** — le vrai handler de succès est le composant Symfony, pas cette classe. La redirection par défaut de FOSUserBundle prend le relais. Le bug est masqué mais peut causer des erreurs de type dans un contexte strict.
  > 5. Après connexion, si `oidc_enable=true`, le firewall Keycloak (`KeycloakAuthenticator`) est le seul Guard actif — mais les deux firewalls cohabitent dans `security.yaml`.
  >
  > **Flux — Reset password** : flow standard FOSUserBundle (request → email → check_email → reset). Pas de personnalisation notable.
  >
  > **Flux — Registration** : FOS expose les routes `register`, `check_email`, `confirm`, `confirmed`. L'app n'en fait pas usage direct (l'onboarding passe par `member_new` + lien d'invitation). Ces routes **restent exposées et actives** même si l'auto-inscription n'est pas voulue.
  >
  > **Changement de mot de passe** :
  > - `fos_user_change_password` : flow standard FOS.
  > - `user_change_password` (`UserController`) : form custom avec `IS_AUTHENTICATED_FULLY` (vérifié inline, pas par @Security). Valide la correspondance des deux mots de passe. Dispatch `FOSUserEvents::USER_PASSWORD_CHANGED`.
  >
  > **Bootstrap admin** (`user_install_admin`) :
  > - Route sans @Security. Logique inline : si aucun `ROLE_SUPER_ADMIN` n'existe → crée l'admin depuis `emails.admin`/`super_admin.initial_password`/`super_admin.username` (paramètres injectés). Si déjà présent → nécessite `ROLE_ADMIN` pour créer un admin supplémentaire.
  > - **⚠️ Risque bootstrap** : avant la première installation, n'importe qui peut accéder à cette route et créer le super-admin. À sécuriser par réseau ou par accès direct au serveur.
  >
  > ---
  >
  > ### Sous-domaine 2 — Authentification OIDC Keycloak (Scopeli)
  >
  > **Flux principal** (`KeycloakAuthenticator`, Guard SF4) :
  > 1. L'utilisateur clique « Se connecter via Keycloak » → `oauth_login` → redirect vers Keycloak (`KnpU\OAuth2ClientBundle`).
  > 2. Keycloak callback → `oauth_check` → `KeycloakAuthenticator::supports()` (route === `oauth_check`) → `getCredentials()` → `fetchAccessToken`.
  > 3. `getUser()` : récupère le `KeycloakResourceOwner` (claims JWT Keycloak).
  > 4. **Réconciliation** : cherche d'abord par `openid` (Beneficiary) ; si absent, par email (User) ; si absent, crée un nouveau Beneficiary+Membership+Registration.
  > 5. **Mise à jour systématique** : à chaque connexion, `updateBeneficiary()` sync prénom/nom/téléphone/adresse/email et **remplace entièrement les rôles et formations** depuis les claims Keycloak (`oidc_roles_claim`, `oidc_roles_map`, `oidc_formations_claim`, `oidc_formations_map`, `oidc_commissions_claim`, `oidc_commissions_map`).
  > 6. **Co-membership** (`updateCoMembership`) : si le claim `co_member_number` est présent → rattache le bénéficiaire à l'adhésion existante et supprime l'ancienne. Logique complexe avec plusieurs `flush()` imbriqués sans transaction globale.
  > 7. Succès → redirect vers `homepage` (`KeycloakAuthenticator::onAuthenticationSuccess`).
  > 8. `oauth_logout` → si `oidc_enable=true`, construit l'URL de logout Keycloak avec `redirect_uri` absolu.
  >
  > **Mapping des attributs** : via `oidc_user_attributes_map` (paramètre JSON) — clés pointées par notation pointée (ex. `attributes.member_number`). Si `firstname`, `lastname`, `member_number`, `email` sont absents du token → exception lancée, connexion échouée.
  >
  > **Conséquences de l'OIDC sur les autres domaines** :
  > - `MembershipVoter::canEdit()` : retourne toujours `false` si `oidc_enable=true` → l'adhérent ne peut pas éditer sa propre adhésion.
  > - `UserVoter` : retourne `false` pour tout utilisateur authentifié si `oidc_enable=true` → card_reader accessible uniquement depuis l'IP locale sans login.
  > - `MembershipVoter::canView()` délègue à `canEdit()` → même avec `ROLE_USER_VIEWER`, si `oidc_enable=true` et l'utilisateur n'est pas SUPER_ADMIN/ADMIN/USER_MANAGER, il n'a pas accès (⚠️ gap SPEC.2 — accès des viewers en mode OIDC à clarifier).
  >
  > **Gaps OIDC** :
  > - `member_number` OIDC (`openid_member_number`) vs `member_number` DB : le setter `setMemberNumber()` sur la `Membership` (l.106 `KeycloakAuthenticator`) écrase le numéro à chaque connexion avec la valeur Keycloak — risque de drift si Keycloak et DB désynchronisés.
  > - `createMembership()` (l.241) : `member_number = rand(10000,100000)` si aucun `openid_member_number` → collision possible avec les numéros séquentiels de la DB.
  > - `updateCoMembership()` : plusieurs `flush()` imbriqués sans transaction → état incohérent possible si l'une des opérations échoue (orphelinage de `Membership` ou de `Beneficiary`).
  >
  > ---
  >
  > ### Sous-domaine 3 — Auth passwordless par badge (SwipeCard)
  >
  > **Flux principal — Connexion par QR/badge** (`swipe_in`, route publique GET `/sw/in/{code}`) :
  > 1. L'URL est générée via `swipe_qr` (QR code PNG) ou `swipe_br` (barcode PNG) : le `{code}` est le code de la `SwipeCard` **chiffré en Vigenère** (base64(XOR(code, key))).
  > 2. `swipeInAction` décode via `vigenereDecode($code)` → code brut de la carte.
  > 3. Cherche la `SwipeCard` active (code + `enable=true`) via `findLastEnable`.
  > 4. Si trouvée : crée directement un `UsernamePasswordToken` avec le User et ses rôles → injecte dans `security.token_storage` → dispatch `security.interactive_login`.
  > 5. Redirect vers homepage. Flash d'erreur si carte introuvable ou inactive.
  >
  > **⚠️ Sécurité SEC.1.7** : le code brut est transmis chiffré en Vigenère (XOR + base64, clé fixe `SWIPE_CARD_SECRET`). L'algorithme ne fournit ni intégrité ni fraîcheur — un code Vigenère intercepté est rejouable indéfiniment. Si `SWIPE_CARD_SECRET` fuite, tous les badges du système sont compromis (pas de changement de code possible sans changer la clé et réinitialiser les QR).
  >
  > **Gestion des badges (SwipeCardController, routes `/sw/...`)** :
  >
  > | Action | Route | Accès |
  > |--------|-------|-------|
  > | Appairage (nouveau badge) | `activate_swipe` POST | ROLE_USER + voter `PAIR` |
  > | Réactivation | `enable_swipe` POST | ROLE_USER + voter `ENABLE` |
  > | Désactivation | `disable_swipe` POST | ROLE_USER + voter `DISABLE` |
  > | Suppression | `delete_swipe` POST | ROLE_ADMIN + voter `DELETE` |
  > | Affichage admin | `swipe_show` GET `/{id}/show` | ROLE_USER_MANAGER |
  > | QR code PNG | `swipe_qr` GET `/{code}/qr.png` | ⚠️ **AUCUN** (public) |
  > | Barcode PNG | `swipe_br` GET `/{code}/br.png` | ⚠️ **AUCUN** (public) |
  >
  > **⚠️ swipe_qr / swipe_br sans auth** : n'importe qui connaissant un code Vigenère valide (lisible dans les emails d'invitation ou dans les URLs de login) peut télécharger le QR ou barcode de n'importe quel badge. Ces images permettent l'usurpation d'accès physique (validation de présence via `card_reader_check`) et la connexion passwordless via `swipe_in`.
  >
  > **SwipeCardVoter** — règles détaillées :
  > - `PAIR` : ROLE_SUPER_ADMIN/ADMIN/USER_MANAGER toujours OK ; sinon : le bénéficiaire n'a aucun badge **ou** aucun badge actif. Une carte désactivée ne bloque pas le rattachement d'une nouvelle.
  > - `ENABLE` / `DISABLE` : ROLE_SUPER_ADMIN/ADMIN/USER_MANAGER toujours OK ; sinon : propriétaire de la carte (`card.beneficiary.user === currentUser`).
  > - `DELETE` : ROLE_SUPER_ADMIN/ADMIN/USER_MANAGER uniquement. `@Security("is_granted('ROLE_ADMIN')")` double-gate sur le controller.
  >
  > **Logging des passages** (`SwipeCardEventListener`) : si `swipe_card_logging=true`, chaque passage badge dispatche `SwipeCardEvent::SWIPE_CARD_SCANNED` → `SwipeCardLog(date, counter, swipeCard)` en base. Si `swipe_card_logging_anonymous=true`, le log est créé sans lien vers la `SwipeCard` (anonymisation).
  >
  > ---
  >
  > ### Sous-domaine 4 — Gestion des comptes et des rôles
  >
  > **Routes admin** (dans `AdminController` sous `/admin/`) :
  >
  > | Route | Chemin | Accès | Description |
  > |-------|--------|-------|-------------|
  > | `user_index` | GET\|POST `/admin/users` | ROLE_USER_MANAGER | Liste paginée + filtres + export CSV + envoi mail groupé |
  > | `non_member_users_list` | GET `/admin/non_member_users` | ROLE_ADMIN | Users sans `Beneficiary` lié |
  > | `admin_users_list` | GET `/admin/admin_users` | ROLE_ADMIN | Users avec ROLE_ADMIN (avec form de suppression) |
  > | `roles_list` | GET `/admin/roles` | ROLE_ADMIN | Tableau des rôles avec comptages |
  > | `user_import_csv` | GET\|POST `/admin/importcsv` | ROLE_SUPER_ADMIN | Import batch via `app:import:users` (via `Application::run()` — même antipattern qu'`admin_shifts_generation`) |
  >
  > **Routes dans `UserController`** :
  >
  > | Route | Accès | Règle métier |
  > |-------|-------|-------------|
  > | `user_add_role` GET | ROLE_ADMIN | ROLE_ADMIN seul ne peut pas s'attribuer ROLE_ADMIN → exige ROLE_SUPER_ADMIN |
  > | `user_remove_role` GET\|POST | ROLE_ADMIN | Même restriction pour retrait ROLE_ADMIN |
  > | `user_delete` DELETE | ROLE_SUPER_ADMIN | Supprime le User (cascade ORM) |
  > | `user_client_remove` GET\|POST | IS_AUTHENTICATED_FULLY + (ROLE_ADMIN OU soi-même) | Retire un client OAuth du compte |
  > | `user_install_admin` GET\|POST | Aucune (logique inline) | Bootstrap ou ajout admin |
  > | `user_change_password` GET\|POST | IS_AUTHENTICATED_FULLY (inline) | Change le mot de passe de l'utilisateur courant |
  >
  > **⚠️ user_add_role / user_remove_role via GET** : mutations d'état (ajout/retrait de rôle) via verbe GET — pas de protection CSRF. Un lien forgé peut ajouter ou retirer un rôle.
  >
  > ---
  >
  > ### Voter architecture — synthèse
  >
  > | Voter | Sujet | Attributs | Accès minimal |
  > |-------|-------|-----------|--------------|
  > | `UserVoter` | `User` (ou null pour `card_reader`) | VIEW, EDIT, CLOSE, FREEZE, FREEZE_CHANGE, CREATE, ANNOTATE, ACCESS_TOOLS, CARD_READER | Voir détail ci-dessous |
  > | `MembershipVoter` | `Membership` | VIEW, EDIT, BOOK, OPEN, CLOSE, FREEZE, FREEZE_CHANGE, FLYING, CREATE, ANNOTATE, ACCESS_TOOLS, BENEFICIARY_ADD, ROLE_ADD, ROLE_REMOVE | Voir SPEC.2 |
  > | `ShiftVoter` | `Shift` | BOOK, FREE, REJECT, ACCEPT, LOCK, VALIDATE | Voir SPEC.3 |
  > | `SwipeCardVoter` | `SwipeCard` | PAIR, ENABLE, DISABLE, DELETE | Voir sous-domaine 3 |
  > | `CodeVoter` | `Code` | VIEW, GENERATE, EDIT, OPEN, CLOSE, DELETE | Voir sous-domaine J.1 |
  > | `NoteVoter` | `Note` | EDIT, DELETE | ROLE_USER_VIEWER ou auteur |
  > | `DynamicContentVoter` | `DynamicContent` | EDIT | ROLE_ADMIN |
  > | `EmailTemplateVoter` | `EmailTemplate` | EDIT | ROLE_ADMIN |
  > | `ProcessUpdateVoter` | `ProcessUpdate` | EDIT, DELETE, NEW | ROLE_PROCESS_MANAGER |
  > | `TaskVoter` | `Task` | — | (hors périmètre SPEC.4) |
  >
  > **UserVoter — logique CARD_READER** : attribut spécial sans sujet `User` requis.
  > - Utilisateur non authentifié : accès si `PlaceIP::isLocationOk()` (IP locale uniquement).
  > - Utilisateur authentifié + `oidc_enable=true` : **toujours refusé** (y compris ADMIN).
  > - Utilisateur authentifié + `oidc_enable=false` : SUPER_ADMIN → toujours OK ; ADMIN → OK ; USER_MANAGER → OK ; sinon switch case CARD_READER → `return true` (tous les utilisateurs connectés).
  > - **⚠️ Implication** : en mode OIDC (Scopeli), seul l'accès depuis l'IP locale *sans session* permet d'utiliser le lecteur de badge. Toute session authentifiée est bloquée.
  >
  > **ShiftVoter — ACCEPT/REJECT sans login** (croisé SPEC.3) :
  > - Si l'utilisateur n'est pas connecté, le voter ne refuse pas directement pour ces deux attributs (`user = null`).
  > - Vérifie via `canReject/canAccept` : si connecté → `user.beneficiary === shift.lastShifter` ; si non connecté → `request.token == shift.getTmpToken(lastShifter.id)`.
  > - `Shift::getTmpToken(id)` : `md5(shift.id . id . shift.start.timestamp())` — MD5, pas de clé secrète, composantes prévisibles. Moins robuste que le token temporaire de `Membership`.
  >
  > ---
  >
  > ### Sous-domaine J — Contrôle d'accès physique (transverse)
  >
  > #### J.1 — Codes de porte rotatifs (`CodeController`, `CodeVoter`)
  >
  > **Concept** : un code de porte est un entier (typiquement 4 chiffres) stocké en clair dans `Code.value`. Plusieurs codes peuvent coexister, certains `closed=true` (archives), d'autres `closed=false` (actifs). Le principe de rotation : un membre génère un nouveau code, l'affiche sur le boîtier physique, puis ferme les anciens (`code_change_done`).
  >
  > **Flux — Consultation** (`codes_list`) :
  > 1. ROLE_USER requis (gateway @Security).
  > 2. ROLE_ADMIN : voit les 100 derniers codes (ouverts + fermés).
  > 3. Non-admin : voit les 10 codes ouverts + 3 fermés récents.
  > 4. `denyAccessUnlessGranted('view', $codes[0])` : CodeVoter::VIEW → accès si (a) `code.registrar === user` OU (b) non-débutant (`!isBeginner(beneficiary)`) ET créneau actif dans la fenêtre [-120min, +60min] (`isBeneficiaryHasShifts`).
  > 5. **⚠️** : si aucun code ouvert n'existe (`!count($codes)`), redirect homepage sans voter — accès toujours refusé si table vide. Risque de lock-out opérationnel.
  >
  > **Flux — Génération** (`code_generate`, `CODE_GENERATION_ENABLED=true`) :
  > 1. Vérifie d'abord que l'utilisateur peut voir les anciens codes (même règle que `codes_list`).
  > 2. Si l'utilisateur a déjà un code ouvert → affiche le code existant (pas de doublon).
  > 3. Sinon : génère `rand(0, 9999)` (4 chiffres), crée `Code`, dispatch `CodeNewEvent`.
  > 4. **⚠️ CodeEventListener::onCodeNew() corps commenté (AP.7)** : `CodeNewEvent` est dispatchée mais le listener n'exécute rien (corps commenté). Probablement un email d'alerte était prévu.
  > 5. **⚠️ CodeVoter::GENERATE** : exige en plus que l'utilisateur ne soit pas le `registrar` du code ouvert le plus récent ET que `PlaceIP::isLocationOk()` → le membre doit être physiquement au local pour générer un code.
  >
  > **Flux — Confirmation de changement** (`code_change_done`, route sans @Security) :
  > - **Deux chemins** : (a) utilisateur connecté → session normale ; (b) utilisateur non connecté → token Vigenère dans l'URL (`token=vigenereEncode(username + ',code:' + id)`).
  > - En mode (b), le controller **impersonnifie temporairement** l'utilisateur via `setToken(UsernamePasswordToken)`, effectue les fermetures de codes, puis restaure le token précédent.
  > - Ferme les codes des autres membres qui étaient visibles par cet utilisateur (selon `CodeVoter::VIEW`).
  > - **⚠️** : le token Vigenère dans l'URL (envoyé par email par `VerifyCodeChangeCommand`) n'a pas de date d'expiration et est rejouable indéfiniment.
  >
  > **Flux — Vérification de changement** (`VerifyCodeChangeCommand`, `app:code:verify_change`) :
  > - Si plus d'un code ouvert existe ET le plus récent a été créé dans les `last_run` heures → impersonifie ce registrar (via `TokenStorage::setToken`) pour évaluer `CodeVoter::VIEW` sur les anciens codes.
  > - **⚠️** : impersonification en CLI sans Request complète → `PlaceIP::isLocationOk()` échoue (IP nulle en CLI → la vérification `$checkIps=false` ou `in_array(null, $ips)` détermine le résultat). Comportement dépendant de la valeur de `enable_place_local_ip_address_check`.
  >
  > **CodeVoter — logique OPEN/CLOSE/DELETE** :
  > ```
  > VIEW / CLOSE  : ROLE_ADMIN OU canView()
  > GENERATE      : code_generation_enabled ET (ROLE_ADMIN OU (canView ET IP locale ET pas mon propre code du jour))
  > OPEN / EDIT   : ROLE_ADMIN; sinon fall-through vers DELETE
  > DELETE        : ROLE_SUPER_ADMIN; sinon canDelete() → toujours false
  > ```
  > **⚠️ Fall-through OPEN→DELETE** : un non-ROLE_ADMIN qui tente `open` (code fermé → toggle) passe par la branche DELETE → refus. Asymétrie non documentée : fermer un code est permis à un viewer actif ; rouvrir un code est réservé à ROLE_ADMIN.
  >
  > **Intégration Igloohome** (`UpdateIgloohomeCodeCommand`, `app:code:update_igloohome`) :
  > - S'authentifie auprès de l'API Igloohome (`IgloohomeClient`) pour créer un code PIN avec une plage de validité (start/end ISO 8601).
  > - Enregistre le code dans la table `code` (registrar = `super_admin.username`) et ferme les anciens codes ouverts.
  > - En cas d'échec API : envoie un email d'alerte aux `alert_recipients` et retourne exit code 1.
  > - **Instance-specific** : activé uniquement si les variables `IGLOOHOME_*` sont configurées (Elefan probable).
  > - **⚠️** : le code Igloohome est stocké **en clair** dans `Code.value` (comme les autres codes). Pas de chiffrement différencié.
  >
  > #### J.2 — Badges NFC / SwipeCard (auth physique)
  >
  > Voir **Sous-domaine 3** ci-dessus pour l'appairage, l'activation/désactivation et le login via badge.
  >
  > **Entité `SwipeCard`** : `code` (string 50, unique, EAN12 sur 12 chiffres — le 13e est le checksum, stripped à l'appairage) ; `enable` (bool nullable) ; `disabled_at` (datetime nullable) ; `number` (integer, ordre d'appairage) ; `beneficiary` (N-1) ; `logs` (1-N `SwipeCardLog`).
  > Note : `getEnable()` retourne `false` si `disabled_at` est non-null, indépendamment de `enable`. Double condition sécurisante mais `setEnable(false)` set les deux (cohérent).
  >
  > #### J.3 — Lecteur de badge (`CardReaderController`)
  >
  > Couvert en détail en **SPEC.3 Sous-domaine 4**. Synthèse du point de vue autorisation :
  > - `card_reader_index` : voter `card_reader` (UserVoter) — voir règles ci-dessus.
  > - `card_reader_check` (POST) : **aucune auth, aucun CSRF** (SEC.2.2 + SEC.3.3) — toute personne avec un EAN13 valide peut valider des créneaux.
  >
  > #### J.4 — Serrures Igloohome (CLI uniquement)
  >
  > Voir **J.1** ci-dessus. Aucune route HTTP — uniquement via `app:code:update_igloohome`. Typiquement orchestré par un cron.
  >
  > ---
  >
  > ### Données — récapitulatif des entités
  >
  > | Entité | Champs clés | Relations |
  > |--------|-------------|-----------|
  > | `User` | `email`(unique), `username`(unique), `password`(bcrypt), `enabled`(bool), `last_login`(datetime), `roles`(JSON array) | 1-1 `beneficiary`→Beneficiary(EAGER, nullable), N-N `clients`→Client, 1-N `annotations`→Note, 1-N `processUpdates` |
  > | `SwipeCard` | `code`(string 50, unique, EAN12), `enable`(bool nullable), `disabled_at`(datetime nullable), `number`(int), `createdAt` | N-1 `beneficiary`→Beneficiary, 1-N `logs`→SwipeCardLog |
  > | `SwipeCardLog` | `date`(datetime), `counter`(int, minutes restants du cycle) | N-1 `swipeCard`→SwipeCard(nullable si anonyme) |
  > | `Code` | `value`(string 255, nullable), `closed`(bool), `createdAt` | N-1 `registrar`→User(onDelete=SET NULL) |
  >
  > ---
  >
  > ### Routes — inventaire complet (~35 + domaine J ~18)
  >
  > **Domaine C — Auth & Autorisation**
  >
  > | Route | Méthode / chemin | Contrôle d'accès |
  > |-------|------------------|------------------|
  > | `fos_user_security_login` | GET `/login` | public |
  > | `fos_user_security_check` | POST `/login_check` | public (form submit) |
  > | `fos_user_security_logout` | GET `/logout` | IS_AUTHENTICATED |
  > | `fos_user_registration_register` | GET\|POST `/register/` | IS_AUTHENTICATED_ANONYMOUSLY |
  > | `fos_user_registration_check_email` | GET `/register/check-email` | IS_AUTHENTICATED_ANONYMOUSLY |
  > | `fos_user_registration_confirm` | GET `/register/confirm/{token}` | IS_AUTHENTICATED_ANONYMOUSLY |
  > | `fos_user_registration_confirmed` | GET `/register/confirmed` | IS_AUTHENTICATED |
  > | `fos_user_resetting_request` | GET `/resetting/request` | IS_AUTHENTICATED_ANONYMOUSLY |
  > | `fos_user_resetting_send_email` | POST `/resetting/send-email` | IS_AUTHENTICATED_ANONYMOUSLY |
  > | `fos_user_resetting_check_email` | GET `/resetting/check-email` | IS_AUTHENTICATED_ANONYMOUSLY |
  > | `fos_user_resetting_reset` | GET\|POST `/resetting/reset/{token}` | IS_AUTHENTICATED_ANONYMOUSLY |
  > | `fos_user_profile_show` | GET `/profile/` | IS_AUTHENTICATED_FULLY |
  > | `fos_user_profile_edit` | GET\|POST `/profile/edit` | IS_AUTHENTICATED_FULLY |
  > | `fos_user_change_password` | GET\|POST `/profile/change-password` | IS_AUTHENTICATED_FULLY |
  > | `oauth_login` | GET `/oauth/login` | public (redirect Keycloak) |
  > | `oauth_logout` | GET `/oauth/logout` | public (redirect Keycloak logout si oidc_enable) |
  > | `oauth_check` | GET `/oauth/callback` | Guard `KeycloakAuthenticator` |
  > | `swipe_in` | GET `/sw/in/{code}` | **public** (auth passwordless) |
  > | `activate_swipe` | POST `/sw/activate` | ROLE_USER + voter PAIR |
  > | `enable_swipe` | POST `/sw/enable` | ROLE_USER + voter ENABLE |
  > | `disable_swipe` | POST `/sw/disable` | ROLE_USER + voter DISABLE |
  > | `delete_swipe` | POST `/sw/delete` | ROLE_ADMIN + voter DELETE |
  > | `swipe_show` | GET `/sw/{id}/show` | ROLE_USER_MANAGER |
  > | `swipe_qr` | GET `/sw/{code}/qr.png` | ⚠️ **AUCUN** |
  > | `swipe_br` | GET `/sw/{code}/br.png` | ⚠️ **AUCUN** |
  > | `user_index` | GET\|POST `/admin/users` | ROLE_USER_MANAGER |
  > | `non_member_users_list` | GET `/admin/non_member_users` | ROLE_ADMIN |
  > | `admin_users_list` | GET `/admin/admin_users` | ROLE_ADMIN |
  > | `roles_list` | GET `/admin/roles` | ROLE_ADMIN |
  > | `user_import_csv` | GET\|POST `/admin/importcsv` | ROLE_SUPER_ADMIN |
  > | `user_add_role` | **GET** `/user/{id}/addRole/{role}` | ROLE_ADMIN (+ rule : SUPER_ADMIN for ROLE_ADMIN) |
  > | `user_remove_role` | GET\|POST `/user/{id}/removeRole/{role}` | ROLE_ADMIN (+ rule) |
  > | `user_delete` | DELETE `/user/{id}` | ROLE_SUPER_ADMIN |
  > | `user_install_admin` | GET\|POST `/user/install_admin` | ⚠️ **AUCUN** (logique inline) |
  > | `user_change_password` | GET\|POST `/user/change_password` | IS_AUTHENTICATED_FULLY (inline) |
  > | `user_client_remove` | GET\|POST `/user/{username}/remove_client/{client_id}` | IS_AUTHENTICATED_FULLY + (ROLE_ADMIN OU soi-même) inline |
  >
  > **Domaine J — Accès physique**
  >
  > | Route | Méthode / chemin | Contrôle d'accès |
  > |-------|------------------|------------------|
  > | `codes_list` | GET `/codes/` | ROLE_USER + CodeVoter::VIEW |
  > | `code_edit` | GET\|POST `/codes/new` | ROLE_USER + (admin ou ...) |
  > | `code_generate` | GET\|POST `/codes/generate` | ROLE_USER + CodeVoter::GENERATE (+ IP locale) |
  > | `code_toggle` | GET\|POST `/codes/{id}/toggle` | ROLE_USER + CodeVoter::OPEN ou CLOSE |
  > | `code_change_done` | GET `/codes/close_all` | ⚠️ **AUCUN** (token Vigenère URL ou session) |
  > | `code_delete` | DELETE `/codes/{id}` | CodeVoter::DELETE (SUPER_ADMIN uniquement) |
  > | `card_reader_index` | GET `/card_reader/` | UserVoter::CARD_READER (voir règles) |
  > | `card_reader_check` | POST `/card_reader/check` | ⚠️ **AUCUN** (SEC.2.2) |
  > | CLI `app:code:update_igloohome` | — | système (cron) |
  > | CLI `app:code:verify_change` | — | système (cron) |
  >
  > ---
  >
  > ### Événements dispatché
  >
  > | Événement | Déclencheur | Listener |
  > |-----------|-------------|---------|
  > | `security.interactive_login` | `swipe_in` (badge), login form | `AuthenticationSuccessHandler::onSecurityInteractiveLogin` |
  > | `FOSUserEvents::USER_PASSWORD_CHANGED` | `user_change_password` | FOSUserBundle (email de confirmation) |
  > | `FOSUserEvents::REGISTRATION_SUCCESS` | `member_new` → création User | FOSUserBundle (email d'activation) |
  > | `BeneficiaryCreatedEvent` | `KeycloakAuthenticator::updateBeneficiary` (nouveau) | `EmailingEventListener` |
  > | `CodeNewEvent` | `code_generate` | `CodeEventListener::onCodeNew` (**corps commenté**) |
  > | `SwipeCardEvent::SWIPE_CARD_SCANNED` | `card_reader_check` (si logging) | `SwipeCardEventListener` → `SwipeCardLog` |
  >
  > ---
  >
  > ### Tests existants
  >
  > **`SmokeTest`** : couvre les flux login (form render, login valide, login invalide, routes protégées → redirect /login), `card_reader_index` (200 si connecté), routes admin (200 admin). Aucun test sur le flow badge, les voters, le flow OIDC, ou la gestion des rôles.
  >
  > **`AdminControllerTest`** : uniquement `user_import_csv` via `app:import:users` (commande CLI, pas la route HTTP). Teste l'import CSV de 50 users avec et sans commissions.
  >
  > **Pas de tests** pour : `swipe_in`, `activate_swipe/enable_swipe/disable_swipe`, voters (`SwipeCardVoter`, `CodeVoter`, `UserVoter`, `ShiftVoter::ACCEPT/REJECT`), flow OIDC Keycloak, `user_add_role/remove_role`, `code_generate/code_change_done`, `card_reader_check`.
  >
  > ---
  >
  > ### Gaps
  >
  > **Sécurité** :
  > - 🔴 `card_reader_check` : aucune auth, aucun CSRF — déjà documenté SPEC.3, cross-ref SEC.2.2.
  > - 🟠 `swipe_qr` / `swipe_br` : aucune @Security → QR et barcode de badge téléchargeables par quiconque connaissant le code Vigenère. Le code Vigenère figure dans les emails (lien `swipe_in`) et dans les URLs cliquées : risque d'exfiltration via logs d'accès, referers ou screenshots. Permet usurpation de badge + connexion passwordless.
  > - 🟠 Chiffrement Vigenère (SEC.1.7) : XOR+base64, clé fixe sans rotation — ni intégrité, ni fraîcheur. Touche : login par badge, confirmation de changement de code, tokens accept/reject de pré-réservation (SPEC.3). Remplacement recommandé par HMAC signé + expiration (ex. JWT court).
  > - 🟡 `code_change_done` : aucune @Security, auth via token Vigenère URL sans expiration → rejouable indéfiniment ; impersonification temporaire en session sans revocation.
  > - 🟡 `user_install_admin` : accessible sans auth avant le premier setup → risque si la route est atteinte avant l'installation (pas de protection réseau documentée).
  > - 🟡 `AuthenticationSuccessHandler::onAuthenticationSuccess` (DC.2) : retourne `null` implicitement → viole l'interface. Bug masqué par la façon dont le listener est enregistré, mais peut causer des erreurs de type si Symfony strict-types evolue.
  > - 🟡 `user_add_role` via GET : mutation CSRF-less — un lien peut ajouter un rôle sans confirmation.
  > - 🟡 `ShiftVoter::ACCEPT/REJECT` token : `md5(shift.id . lastShifter.id . shift.start.timestamp())` — MD5 sans secret, composantes prévisibles (id séquentiels, timestamp dans le futur connu). Moins robuste que le token de Membership qui utilise `uniqid` de session.
  > - 🟡 `VerifyCodeChangeCommand` : impersonification CLI de l'utilisateur pour évaluer `CodeVoter::VIEW` — le contexte PlaceIP peut être incorrect en CLI selon la valeur de `enable_place_local_ip_address_check`.
  > - 🟡 `updateCoMembership` (KeycloakAuthenticator) : plusieurs `flush()` imbriqués sans transaction → état incohérent possible sur Scopeli.
  >
  > **Code / dette** :
  > - 🟠 `CodeVoter::isLocationOk()` (l.151) : méthode privée qui duplique exactement `PlaceIP::isLocationOk()` — commentaire `//DUPLICATED` présent. Refactorer pour utiliser `PlaceIP` directement.
  > - 🟠 `CodeVoter::OPEN` fall-through vers `DELETE` : asymétrie non documentée ouvrir/fermer code. Un non-admin peut fermer mais pas ouvrir un code.
  > - 🟠 `KeycloakAuthenticator::createMembership()` : `member_number = rand(10000,100000)` si pas d'`openid_member_number` → collision possible avec les numéros séquentiels.
  > - 🟡 `CodeVoter::canView()` : fenêtres temporelles hardcodées `PT2H` (−2h) et `PT1H` (+1h) avec `TODO put in conf`.
  > - 🟡 `code_generate` : code aléatoire `rand(0, 9999)` → peut produire `0000` (all-zero), pas de vérification de collision.
  >
  > **Non testé** :
  > - Tout le flux OIDC (Keycloak) : `KeycloakAuthenticator::getUser()`, mapping attributs, co-membership, sync rôles/formations/commissions.
  > - `swipe_in` (login par badge) et tous les `SwipeCardVoter` attributs.
  > - `CodeVoter` : VIEW/GENERATE avec débutant, avec créneau actif, avec IP locale, fall-through OPEN→DELETE.
  > - `UserVoter::CARD_READER` en mode OIDC vs non-OIDC.
  > - `user_add_role` / `user_remove_role` : restrictions ROLE_ADMIN vs SUPER_ADMIN.
  > - `code_change_done` en mode non-connecté (token Vigenère).
  >
  > **Ambigu / à clarifier** :
  > - `fos_user_registration_*` : ces routes sont-elles utilisées ou désactivées dans la configuration de chaque instance ? Si l'auto-inscription est non voulue, elles devraient être désactivées.
  > - `ROLE_OAUTH_LOGIN` : rôle présent dans la hiérarchie mais aucun code ne semble l'attribuer explicitement — semble être automatiquement accordé par FOSOAuthServerBundle aux clients OAuth. À confirmer en SPEC.8.
  > - Comportement complet en mode `oidc_enable=true` côté Scopeli pour les vues membres : quels attributs MembershipVoter permettent encore l'accès (SUPER_ADMIN/ADMIN court-circuitent le check oidc mais USER_MANAGER est bloqué) ? À confirmer.
  > - `swipe_card_logging_anonymous` : les logs anonymes ne pointent pas vers une `SwipeCard` — comment relier les passages à un membre a posteriori ?

- [x] **SPEC.5** — Spec : Cotisations & Paiements
  > Sources : `HelloassoController`, `RegistrationsController`, `HelloassoClient`, `HelloassoPaymentHandler`, `HelloassoEventListener`, `EmailingEventListener` (handlers helloasso), entités `HelloassoPayment`/`Registration`/`AbstractRegistration`, events `HelloassoEvent`, `HelloassoNotificationRequest`, `UpdateHelloAssoPaymentsCommand`, `HelloassoPaymentCommand`, `RegistrationType`, `MembershipService`.
  >
  > #### Vocabulaire
  > - **Registration** : enregistrement d'une adhésion d'un `Beneficiary` — mode de paiement + montant + date. Peut être manuelle (CASH/CHECK/LOCAL) ou automatisée via Helloasso.
  > - **HelloassoPayment** : paiement reçu de la plateforme Helloasso, persisté localement avant liaison à une `Registration`.
  > - **Orphan payment** : `HelloassoPayment` sans `Registration` associée (email du payeur inconnu dans la base, ou bénéficiaire introuvable).
  > - **canRegister** : critère métier autorisant une nouvelle adhésion — vrai si l'adhésion courante expire dans moins de 28 jours.
  > - **TOO_EARLY** : état où un paiement Helloasso arrive mais où `canRegister` = false (adhésion encore trop loin de l'expiration).
  > - **Prolongement automatique** : si l'adhésion précédente n'est pas encore expirée au moment du paiement, la nouvelle date d'adhésion est fixée au lendemain de l'expiration (le membre ne « perd » pas de durée).
  >
  > #### Acteurs
  > - **Membre** : initie le paiement sur Helloasso ; reçoit un email de confirmation ou un email « qui es-tu ? » (orphelin).
  > - **`ROLE_FINANCE_MANAGER`** : consulte/browse les paiements Helloasso, importe manuellement, édite les orphelins.
  > - **`ROLE_SUPER_ADMIN`** : supprime un `HelloassoPayment` (si non lié) ou une `Registration`.
  > - **Helloasso** : plateforme tierce — envoie un webhook POST sur `/helloassoNotify`, expose une API OAuth2 (Client Credentials).
  > - **Cron / CLI** : `app:member:update_payments` pour rattraper les paiements manqués ; `app:helloasso:payment` pour re-dispatcher un événement ou lister les orphelins.
  >
  > #### Instances
  > - **Elefan** : Helloasso activé. Variables `HELLOASSO_*` définies (campaign slug, organization slug, client_id/secret, API URLs).
  > - **Scopeli** : vraisemblablement Helloasso désactivé. Toutes les variables Helloasso ont `default::` dans `services.yaml` → valeur vide si non définies. Si désactivé : webhook, browser, commands et flux automatique hors service. À confirmer.
  >
  > #### Flux principal — Webhook (chemin nominal)
  >
  > ```
  > Membre paie sur Helloasso
  >        ↓
  > Helloasso POST /helloassoNotify  [PUBLIC — aucune auth requise]
  >   JSON: { eventType: "Payment", data: { id, state: "Authorized", ... } }
  >        ↓
  > HelloassoNotificationRequest::createFromRequest() → parse JSON
  >        ↓
  > isPaymentValidated() → true si eventType == "Payment" && state == "Authorized"
  >        ↓ [sinon: 200 OK sans traitement]
  > HelloassoClient::getPayment(paymentId)
  >   → re-fetch depuis API Helloasso (OAuth Bearer; org-scoped)
  >        ↓
  > HelloassoPaymentHandler::savePayments([payment])
  >   → findOneBy(['paymentId' => payment.id]) → skip si déjà en DB
  >   → createFromPayementObject() : amount / 100 (centimes → €), date, email, status
  >   → persist + flush
  >   → dispatch HelloassoEvent::PAYMENT_AFTER_SAVE
  >        ↓
  > HelloassoEventListener::onPaymentAfterSave()
  >   → findOneBy(['email' => strtolower(payment.email)])
  >   ┌── Trouvé → linkPaymentToUser()
  >   └── Non trouvé → email « qui es-tu ? » avec lien Vigenère encodé → ORPHAN flow
  > ```
  >
  > #### Flux — linkPaymentToUser (succès ou TOO_EARLY)
  >
  > ```
  > linkPaymentToUser(User, HelloassoPayment)
  >   → user.getBeneficiary() ?
  >     └── Non → throw LogicException 'user without beneficiary'  ← non rattrapée → 500
  >   → membership_service.canRegister(membership) ?
  >     ├── Non (expiration > 28j) → dispatch TOO_EARLY
  >     │     → EmailingEventListener::onHelloassoTooEarly()
  >     │           → renderView('emails/too_early_registration.html.twig') dans try/catch
  >     │           → die($e->getMessage()) si exception  ← BUG CRITIQUE (AP.7)
  >     │           → mailer.send()
  >     └── Oui → crée Registration (TYPE_HELLOASSO / mode=6)
  >           → date = expire+1j si encore valide, sinon payment.date
  >           → registration.createdAt = payment.date
  >           → si membership.withdrawn → setWithdrawn(false)  [réactivation auto]
  >           → persist + flush
  >           → dispatch RE_REGISTRATION_SUCCESS
  >                 → EmailingEventListener::onHelloassoRegistrationSuccess()
  >                       → email « Re-adhésion » si registrations.count > 1
  >                       → email « Première adhésion » si count == 1
  > ```
  >
  > #### Flux — Résolution d'un orphelin (self-service)
  >
  > ```
  > Email envoyé au payeur → lien /helloasso/payment/{id}/resolve_orphan/{vigenere_code}
  >   (code = Vigenère(email du payeur))
  >        ↓
  > resolveOrphan() [GET, ROLE_USER]
  >   → vigenere_decode(code) == payment.email ?
  >     ├── Non → flash error, redirect homepage
  >     └── Oui, payment déjà lié → flash error, redirect homepage
  >         Oui, orphelin → affiche vue de confirmation
  >        ↓
  > confirmOrphan() [GET, ROLE_USER]  ← GET mutant, pas de CSRF
  >   → vigenere_decode(code) == payment.email ? → dispatch ORPHAN_SOLVE(payment, currentUser)
  >   → HelloassoEventListener::onOrphanSolve() → linkPaymentToUser(user, payment)
  >   [NB: ne vérifie PAS si payment.registration != null avant dispatch]
  > ```
  >
  > #### Flux — Import manuel (FINANCE_MANAGER)
  >
  > ```
  > GET /helloasso/browser → helloassoClient.getForms() → liste campagnes
  > GET /helloasso/browser/{formType}/{slug} → getFormPayments() + getFormDetails()
  > POST /helloasso/manualPaimentAdd/{paymentId} → getPayment() → savePayments([payment])
  > ```
  >
  > #### Flux — Commande CLI rattrapage
  >
  > ```
  > app:member:update_payments --delay='1 month'
  >   → getFormPayments('Membership', helloasso_campaign_slug, {from, page})
  >   → savePayments(results.data)
  >   → si page < totalPages → processPage(slug, from, page+1)  [récursif]
  > ```
  >
  > #### Flux — Enregistrement manuel (hors Helloasso)
  >
  > ```
  > GET/POST /{member_number}/newRegistration [MembershipVoter::EDIT]
  >   → RegistrationType form
  >   → modes (non-SUPER_ADMIN) : CASH(1), CHECK(2), LOCAL(3), HELLOASSO(6)
  >   → modes (SUPER_ADMIN) : CASH(1), CHECK(2), LOCAL(3) [TYPE_CREDIT_CARD commenté]
  >   → validations : montant > 0, date > expiration courante, pas d'auto-enregistrement
  >   → aucun événement dispatché (pas d'email de confirmation)
  >
  > GET /registrations/ [FINANCE_MANAGER]
  >   → AbstractRegistration via vue SQL + SQL natif pour totaux par mode
  >   → filtrage date via query params 'from'/'to' (format Y-m-d)
  > ```
  >
  > #### Règles métier
  > 1. `canRegister(membership)` : `getExpire() < now + 28 days`. Fenêtre de 28 jours non configurable (hardcodée).
  > 2. `getExpire()` — deux modes selon le paramètre `registration_every_civil_year` :
  >    - **Annuel civil** : expire le 31 décembre de l'année de la dernière adhésion.
  >    - **Durée fixe** : `lastRegistration.date + registration_duration - 1 day`.
  > 3. **Prolongement** : si l'adhésion précédente est encore valide à la date du paiement, la nouvelle date est `expire + 1 jour` (pas de perte de durée).
  > 4. **Déduplication** : contrainte `UNIQUE` sur `helloasso_payment.payment_id` → un paiement Helloasso ne peut être enregistré qu'une fois.
  > 5. **Montants** : API Helloasso v5 → centimes ; `createFromPayementObject` divise par 100. Les anciens mappings `fromActionObj`/`fromPaymentObj` (API v3) laissent les montants en euros (dead code).
  > 6. **Suppression** : `HelloassoPayment` supprimable uniquement si `registration == null` (sinon flash error). `Registration` supprimable sauf si c'est la dernière du membership.
  > 7. **Réactivation** : si `membership.withdrawn == true`, il est remis à `false` automatiquement sur un paiement Helloasso validé.
  > 8. **Auto-enregistrement interdit** : le registrar ne peut pas être le membre lui-même pour `member_new_registration`.
  >
  > #### Données
  >
  > | Entité | Table | Champs clés |
  > |--------|-------|-------------|
  > | `HelloassoPayment` | `helloasso_payment` | `payment_id` UNIQUE, `amount` float, `email`, `status`, `campaign_id`, `registration_id` FK nullable (SET NULL on delete) |
  > | `Registration` | `registration` | `amount` **string**, `mode` int, `membership_id`, `registrar_id` (SET NULL), `created_at` |
  > | `AbstractRegistration` | `view_abstract_registration` | Vue SQL read-only ; `type` : 1=TYPE_MEMBER, 2=TYPE_ANONYMOUS |
  >
  > Modes de paiement (`Registration.mode`) :
  >
  > | Constante | Valeur | Origine | Exposé en formulaire |
  > |-----------|--------|---------|---------------------|
  > | TYPE_CASH | 1 | Manuel | Oui |
  > | TYPE_CHECK | 2 | Manuel | Oui |
  > | TYPE_LOCAL | 3 | Manuel (monnaie locale) | Oui |
  > | TYPE_CREDIT_CARD | 4 | Défini, commenté | Non (commenté dans form) |
  > | TYPE_DEFAULT | 5 | Historique ? | Non (jamais assigné) |
  > | TYPE_HELLOASSO | 6 | Webhook / CLI | Oui (non-SUPER_ADMIN seulement) |
  >
  > Variables d'env Helloasso (toutes `default::` → optionnelles) :
  > - `HELLOASSO_REGISTRATION_CAMPAIGN_URL` : URL publique de la campagne (rendue en Twig)
  > - `HELLOASSO_CAMPAIGN_SLUG` : slug utilisé par `UpdateHelloAssoPaymentsCommand`
  > - `HELLOASSO_CLIENT_ID`, `HELLOASSO_CLIENT_SECRET` : OAuth2 Client Credentials
  > - `HELLOASSO_ORGANIZATION_SLUG` : slug organisation pour les appels API
  > - `HELLOASSO_API_BASE_URL`, `HELLOASSO_API_AUTH_URL` : endpoints API
  >
  > #### Cas limites
  > 1. **User sans beneficiary** : `linkPaymentToUser` throw `LogicException('user without beneficiary')` non rattrapée → HTTP 500 lors du traitement webhook.
  > 2. **`confirmOrphan` sur paiement déjà lié** : contrairement à `resolveOrphan` et `editPaymentAction`, `confirmOrphan` ne vérifie pas `payment.getRegistration()` avant de dispatcher `ORPHAN_SOLVE` → double-liaison potentielle.
  > 3. **TOO_EARLY sans retry** : le paiement reste orphelin (sans Registration). Aucun mécanisme automatique de retry. Résolution manuelle via admin uniquement.
  > 4. **Email dupliqué** : `findOneBy(['email' => ...])` retourne le premier résultat Doctrine (non déterministe si plusieurs `User` ont le même email).
  > 5. **Webhook re-livré** : dédupliqué par `paymentId` → idempotent. Correct.
  > 6. **Paiement non Authorized** : `isPaymentValidated()` = false → 200 OK sans traitement. Correct.
  > 7. **Récursion `processPage`** : `UpdateHelloAssoPaymentsCommand` est récursive (appels imbriqués par page). Risque de stack overflow PHP sur de grands historiques.
  > 8. **Amount type mismatch** : `Registration.amount` est une `string` en base ; `HelloassoPayment.amount` est un `float`. Le float est casté implicitement en string par MySQL au persist. Fragile selon la locale PHP (séparateur décimal).
  >
  > #### Routes (13 + 1 cross-domaine)
  >
  > | Nom | Méthode | URL | Accès | Contrôleur |
  > |-----|---------|-----|-------|-----------|
  > | `helloasso_notify` | POST | `/helloassoNotify` | **PUBLIC** (aucune auth) | `DefaultController` |
  > | `helloasso_payments` | GET | `/helloasso/payments` | ROLE_FINANCE_MANAGER | `HelloassoController` |
  > | `helloasso_browser` | GET | `/helloasso/browser` | ROLE_FINANCE_MANAGER | `HelloassoController` |
  > | `helloasso_campaign_details` | GET | `/helloasso/browser/{formType}/{slug}` | ROLE_FINANCE_MANAGER | `HelloassoController` |
  > | `helloasso_manual_paiement_add` | POST | `/helloasso/manualPaimentAdd/{paymentId}` | ROLE_FINANCE_MANAGER | `HelloassoController` |
  > | `helloasso_payment_remove` | DELETE | `/helloasso/payments/{id}` | ROLE_SUPER_ADMIN | `HelloassoController` |
  > | `helloasso_payment_edit` | GET/POST | `/helloasso/payment/{id}/edit` | ROLE_FINANCE_MANAGER | `HelloassoController` |
  > | `helloasso_resolve_orphan` | GET | `/helloasso/payment/{id}/resolve_orphan/{code}` | ROLE_USER | `HelloassoController` |
  > | `helloasso_confirm_resolve_orphan` | GET | `/helloasso/payment/{id}/confirm_resolve_orphan/{code}` | ROLE_USER | `HelloassoController` |
  > | `helloasso_orphan_exit_and_back` | GET | `/helloasso/payment/{id}/orphan_exit_and_back/{code}` | ROLE_USER | `HelloassoController` |
  > | `registrations` | GET/POST | `/registrations/` | ROLE_FINANCE_MANAGER | `RegistrationsController` |
  > | `registration_edit` | GET/POST | `/registrations/{id}/edit` | ROLE_FINANCE_MANAGER | `RegistrationsController` |
  > | `registration_remove` | DELETE | `/registrations/{id}/remove` | ROLE_SUPER_ADMIN | `RegistrationsController` |
  > | `member_new_registration` | GET/POST | `/{member_number}/newRegistration` | MembershipVoter::EDIT | `MembershipController` (cross SPEC.2) |
  >
  > #### Événements
  >
  > | Événement | Constante | Déclencheur | Listener |
  > |-----------|-----------|-------------|---------|
  > | `helloasso.payment_after_save` | `PAYMENT_AFTER_SAVE` | Après persist d'un paiement (webhook ou CLI) | `HelloassoEventListener::onPaymentAfterSave` → link ou email orphelin |
  > | `helloasso.orphan_solve` | `ORPHAN_SOLVE` | Admin ou self-service user résout l'orphelin | `HelloassoEventListener::onOrphanSolve` → linkPaymentToUser |
  > | `helloasso.registration_success` | `RE_REGISTRATION_SUCCESS` | Après création d'une Registration Helloasso | `EmailingEventListener::onHelloassoRegistrationSuccess` → email confirmation |
  > | `helloasso.too_early` | `TOO_EARLY` | canRegister() = false | `EmailingEventListener::onHelloassoTooEarly` → email (BUG: die) |
  >
  > #### Tests existants
  > - **Zéro test** sur le domaine Helloasso : aucun fichier de test ne couvre `HelloassoController`, `HelloassoEventListener`, `HelloassoPaymentHandler`, `HelloassoClient`, `HelloassoNotificationRequest`.
  > - **`SmokeTest`** : GET `/registrations/` → 200 OK (présence de route uniquement, sans données).
  > - **`MembershipServiceTest`** : teste `canRegister` et `getExpire` (couverture partielle de la fenêtre de réenregistrement).
  > - **Aucun test** sur `UpdateHelloAssoPaymentsCommand`, `HelloassoPaymentCommand`, `RegistrationType`.
  >
  > #### Gaps / Findings
  >
  > **Sécurité** :
  > - 🔴 **Webhook `/helloassoNotify` non authentifié** : accessible sans authentification (firewall `main` avec `anonymous: true`, aucune règle `access_control`). Le commentaire dans le code reconnaît que la vérification de signature Helloasso n'est pas implémentée (réservée aux partenaires). La mitigation actuelle — re-fetch du paiement depuis l'API Helloasso après réception — empêche l'injection de données forgées, mais : (a) n'importe qui peut déclencher le re-traitement d'un `paymentId` connu, (b) n'importe qui peut spammer l'endpoint et épuiser le rate-limit API Helloasso.
  > - 🟠 **`confirmOrphan` est une route GET qui mute l'état** sans protection CSRF. Le token Vigenère offre une validation faible (algorithme symétrique public, sans secret serveur) — quiconque obtient l'URL peut confirmer à la place du payeur.
  > - 🟡 **Vigenère** : même schéma que `code_change` (SPEC.4). Clé symétrique, algorithme réversible sans secret, ne constitue pas une signature cryptographique.
  >
  > **Bugs** :
  > - 🔴 **`die($e->getMessage())`** dans `EmailingEventListener::onHelloassoTooEarly()` (l.257) : tue le processus PHP sur toute exception pendant le rendu du template `too_early_registration.html.twig`. Confirmé AP.7. Expose le message d'exception dans la réponse HTTP. À remplacer par log + propagation correcte.
  > - 🟠 **`linkPaymentToUser` : `LogicException('user without beneficiary')`** non rattrapée → HTTP 500 lors du traitement webhook. L'exception n'est pas loggée avant propagation.
  > - 🟠 **`confirmOrphan` ne vérifie pas `payment.getRegistration()`** avant dispatch → double-liaison potentielle si l'orphelin a été résolu entre-temps par l'admin ou un autre chemin.
  > - 🟡 **`processPage` récursive** : `UpdateHelloAssoPaymentsCommand` s'appelle récursivement par page. Risque de stack overflow PHP sur grand historique ; pattern itératif préférable.
  >
  > **Code / dette** :
  > - 🟠 **Dead code : `fromActionObj()` et `fromPaymentObj()`** dans `HelloassoPayment` : deux anciens mappings (API Helloasso v3) abandonnés au profit de `createFromPayementObject()` (v5). À supprimer après vérification dans les migrations.
  > - 🟠 **`TYPE_CREDIT_CARD` (4) et `TYPE_DEFAULT` (5)** : définis dans les constantes `Registration`, jamais assignés en production (formulaire commenté, aucun dispatch système). Dead code probable — à confirmer avant suppression.
  > - 🟡 **`Registration.amount` stockée en `string`** : incohérence de type avec `HelloassoPayment.amount` (`float`). La conversion implicite float→string dépend de la locale PHP.
  > - 🟡 **`UpdateHelloAssoPaymentsCommand` : `formType` hardcodé à `'Membership'`** et une seule `helloasso_campaign_slug`. Ne supporte pas plusieurs campagnes. Pas de mode `--dry-run`.
  > - 🟡 **`canRegister` fenêtre de 28 jours hardcodée** : `new \DateTime('+28 days')` non exposé en configuration.
  >
  > **Non testé** :
  > - Flux webhook complet : parsing, dédup, `linkPaymentToUser`, TOO_EARLY, orphan.
  > - `HelloassoClient` : authentification OAuth, `getForms`, `getFormPayments`, `getPayment`.
  > - `HelloassoPaymentHandler` : idempotence de `savePayments`, dispatch d'événement.
  > - `UpdateHelloAssoPaymentsCommand` : pagination récursive, gestion d'erreur API.
  > - `registrations` : SQL natif (totaux agrégés par mode), filtrage date, pagination.
  > - `helloasso_resolve_orphan` / `helloasso_confirm_resolve_orphan` : validation Vigenère, cas orphelin déjà résolu.
  >
  > **Ambigu / à clarifier** :
  > - `view_abstract_registration` : quelle requête SQL sous-jacente ? Quelles tables agrège-t-elle ? (`AbstractRegistration.TYPE_ANONYMOUS` = 2 — qui crée ces enregistrements anonymes ? Probablement les achats en caisse sans adhésion — à croiser avec le domaine Shift/Caisse).
  > - Scopeli : Helloasso activé ou non ? Si oui, même campagne ou slug différent ?
  > - `member_new_registration` dispatche-t-il un événement `member_new_registration` ? Non vu dans `MembershipController::newRegistration` — l'event du même nom semble dispatché ailleurs (cross SPEC.2 à confirmer).

- [ ] **SPEC.6** — Spec : Administration & Configuration
  > Croiser : controllers admin, commands d'administration, paramètres métier (CONFIG.3).

- [ ] **SPEC.7** — Spec : Notifications & Emails
  > Croiser : `EmailingEventListener`, templates Twig d'emails, events déclencheurs.

- [ ] **SPEC.8** — Spec : API & Intégrations externes
  > Croiser : OAuth exposé, Helloasso, Igloohome, Keycloak. `src/Providers/`.

- [ ] **SPEC.9** — Annotations d'usage par instance
  > Pour chaque spec : annoter "utilisé chez Elefan/Scopeli" si identifiable via CONFIG.2 ou RT.

- [ ] **SPEC.10** — Glossaire métier
  > Termes du domaine (Shift, Period, Beneficiary, Swipe, Commission, Service…) — définition, entité associée.

- [ ] **SPEC.11** — Spec : Gouvernance & Assemblées générales
  > **✅ Décision (session 52)** : domaine H (gap SPEC.1) traité dans une spec dédiée plutôt que dilué dans SPEC.6.
  > Croiser : `EventController`, `AdminEventController`, `AdminEventKindController`, entités `Event`/`EventKind`/`Proxy`, event `EventProxyCreatedEvent`, PERF cas #2 (proxy list N+1). Couvre : événements associatifs (AG), procurations (give/take/find), émargement/signatures, widgets event. ~16 routes.

---

## SYN — Synthèse et livrables finaux
> 🔀 **Modèle : Opus.** Rappeler `/model opus` avant SYN.1, `/model sonnet` après SYN.4.

- [ ] **SYN.1** — Documentation mise à jour
  > Consolider les findings de D.1-5, CONFIG.1-3, LOG.1. Produire un `DOCUMENTATION.md` (gitignored pour l'instant) : architecture, setup, variables d'env, mécanisme multi-instance, observabilité.

- [ ] **SYN.2** — TODO priorisée
  > Consolider tous les findings notés "→ TODO" dans chaque section. Classer par :
  > - 🔴 Critique (sécurité, correctifs bloquants)
  > - 🟠 Important (dead code confirmé, antipatterns significatifs, gaps de tests majeurs)
  > - 🟡 Mineur (cosmétique, améliorations non urgentes)
  > Puis par effort : S (< 2h) / M (1 jour) / L (> 1 jour) / XL (sprint).
  > Inclure les recommandations RT.2 et SF-PREP dans la section "chantiers futurs".

- [ ] **SYN.3** — Vérifier la cohérence des specs
  > Relire les SPEC.1-10. Terminologie cohérente ? Cross-références entre domaines ? Gaps évidents ? Compléter si nécessaire.

- [ ] **SYN.4** — PR de l'audit
  > Committer les fichiers de documentation produits (DOCUMENTATION.md, specs). Ouvrir la PR avec un résumé des findings principaux et le lien vers la TODO.

---

## EXTRA — Pistes découvertes en cours d'audit

> Nouvelles pistes identifiées pendant le traitement d'un item. Format : `- [ ] **[item-origine]** — description`

<!-- Les findings s'ajoutent ici au fil des sessions -->

- [ ] **[D.5]** — `BeneficiaryController::getErrorMessages()` (ligne 187) : méthode private jamais appelée de l'extérieur (seule la récursion interne subsiste). Rector ne l'a pas détectée en DC.1 (récursion self-référente). À vérifier manuellement en DC.3 comme candidat dead code.

- [ ] **[DEP.3]** — `custom_animation.less` non importée dans `app.js` (absente des `require()` en bas des imports LESS) : les animations CSS ne sont pas bundlées par webpack. En parallèle, `templates/period/index.html.twig:13` référence `{{ asset('bundles/app/css/custom_animation.css') }}` — URL de l'ère pré-webpack, invalide avec Encore (qui output dans `public/build/`). À documenter dans **D.3** addendum et **SYN.2**.

- [ ] **[DEP.3]** — `canvas-gauges` CDN HS : vérifier si `display_gauge` est activé chez Elefan et/ou Scopeli (variable de config ou flag d'instance). Si désactivé partout, la feature peut être retirée plutôt que corrigée. À confirmer en **CONFIG.2**.

- [ ] **[SPEC.5]** — `fromActionObj()` et `fromPaymentObj()` dans `HelloassoPayment` : dead code (anciens mappings API Helloasso v3). Seul `createFromPayementObject()` (v5) est utilisé. À confirmer via `git log -S fromActionObj` + vérification migrations avant suppression. Candidat **DC.3**.

- [ ] **[SPEC.5]** — `Registration::TYPE_CREDIT_CARD` (4) et `TYPE_DEFAULT` (5) : définis dans les constantes mais jamais assignés par le code actuel. Le formulaire `RegistrationType` commente `TYPE_CREDIT_CARD`. À vérifier dans les données de production (SELECT DISTINCT mode FROM registration) pour confirmer si ces valeurs existent en base avant suppression des constantes. Candidat **DC.3**.

- [ ] **[SPEC.5]** — `confirmOrphan()` (`HelloassoController` l.264) : route GET mutante sans vérification de `payment.getRegistration()` avant dispatch d'`ORPHAN_SOLVE`. Contrairement à `resolveOrphan` (l.245) et `editPaymentAction` (l.185), ce cas peut créer une double-liaison si l'orphelin a déjà été résolu. Bug à corriger dans **SYN.2**.

- [ ] **[SPEC.5]** — `view_abstract_registration` : `AbstractRegistration` est mappée sur une vue SQL (read-only). La migration créant cette vue est à identifier pour comprendre ce qu'elle agrège (Registration + type anonyme ?). Utile pour **SYN.1** (documentation) et pour comprendre `AbstractRegistration::TYPE_ANONYMOUS` (2).

- [ ] **[DC.2]** — `AuthenticationSuccessHandler::onAuthenticationSuccess()` viole `AuthenticationSuccessHandlerInterface` : quand `$target` est absent, la méthode retourne `null` implicitement alors que l'interface exige un `Response`. Le `return;` supprimé par Rector masque davantage ce chemin. Bug à classer dans **SYN.2** (bugs, effort XS).

- [ ] **[AP.7]** — `CodeEventListener::onCodeNew()` (L30-35) : corps entièrement commenté (seul `$this->logger->info(...)` subsiste). Le listener est enregistré dans le container mais sans logique — candidat suppression ou TODO d'implémentation. Confirmer en DC.3.

- [ ] **[AP.7]** — `EmailingEventListener::onHelloassoTooEarly()` L257 : `die($e->getMessage())` tue le process PHP sur exception email. Bug critique — peut produire une page blanche en production lors d'un paiement Helloasso. Corriger en priorité (TODO AP.7.1). Cross-ref SYN.2.

- [ ] **[TC.5]** — Aucune commande n'a d'option `--dry-run`, y compris les opérations irréversibles (`app:anonymize`, `app:member:close`, `app:shift:generate`). Recommandation UX opérationnelle : ajouter `--dry-run` aux commandes destructives (output de ce qui serait fait sans modifier la base). À porter dans **SYN.2** (ergonomie CLI, effort S par commande).

- [ ] **[PERF.1, PERF.2]** — Volumétries de prod requises pour valider la sévérité des findings PERF. Les comptages de lignes utilisés pendant l'audit (beneficiary: 56, proxy: 0, closing_exception: 0, shift: 51, membership: 56, time_log: 0) sont issus de la base de test — ils ne reflètent pas la production Elefan ni Scopeli. Avant de prioriser les correctifs PERF, refaire l'analyse avec une dump prod anonymisée (à fournir après anonymisation côté utilisateur). Les sévérités 🔴/🟡 sont des estimations raisonnées mais non confirmées sur données réelles.

- [ ] **[CI.1] TODO CI.A** — `shivammathur/setup-php@verbose` dans `.github/workflows/ci.yaml` : `@verbose` est un alias comportemental flottant, pas un tag sémantique. Épingler à une version (`@v2` ou mieux un SHA digest) pour éliminer le risque supply chain. Effort XS.

- [ ] **[CI.1] TODO CI.B** — Gap PHP 7.4 (CI) vs PHP 8.1 (container Docker / prod) : la CI valide du code PHP 7.4 alors que le runtime de déploiement est PHP 8.1. Ajouter PHP 8.1 à la matrice CI (ou remplacer 7.4 par 8.1 si 7.4 n'est plus ciblé). Effort XS (ajout d'entrée matrice + vérification des extensions requises). Cross-ref SF-PREP.

- [ ] **[CI.1] TODO CI.C** — Pas de lint JS/CSS dans la CI (pas d'ESLint, pas de Stylelint). PHPStan couvre PHP uniquement. Si du code JS/CSS évolue, les erreurs de syntaxe ou de style ne sont pas détectées automatiquement. Optionnel — effort XS si les outils sont déjà présents dans `package.json`.

- [ ] **[SPEC.3]** — `shift_accept_reserved` (GET `/shift/{id}/accept`) et `shift_reject_reserved` (GET `/shift/{id}/reject`) : mutations d'état via verbe GET — pas de protection CSRF, rejouables par un lien dans un email (le flow d'invitation les utilise exactement ainsi). Risque limité (le voter `accept`/`reject` vérifie l'identité), mais contraire aux bonnes pratiques REST. À porter dans **SYN.2** (sécurité, effort XS → POST + token CSRF).

- [ ] **[SPEC.3]** — `shift_contact_form` (GET|POST `/shift/{id}/contact_form`) : aucune annotation `@Security` → route publique de facto. N'importe qui peut envoyer un email aux co-bénévoles d'un créneau en connaissant un `id` de `Shift`. Ajouter au moins `ROLE_USER`. À porter dans **SYN.2** (sécurité, effort XS).

- [ ] **[SPEC.3]** — `FreeReservedShiftsCommand` (`app:shift:free <date>`) : doit être exécuté `reserve_new_shift_to_prior_shifter_delay` jours après `ShiftGenerateCommand`. Cette coordination n'est documentée ni dans le README ni dans les commandes elles-mêmes. Un mauvais ordonnancement cron laisse des pré-réservations en suspens indéfiniment. À documenter dans **SYN.1** + **SYN.2** (ergonomie CLI/ops).

- [ ] **[SPEC.3]** — `FixShiftMissingPositionCommand` (`app:shift:fix_missing_position`) retourne exit code 1 explicitement pour `cycle_type=abcd` avec message d'erreur. Les instances avec `cycle_type=abcd` n'ont donc pas de commande de réparation des `Shift.position=null` issus de la migration `Version20211223205749`. À tracer dans **SYN.2** (dette technique, effort M pour implémenter le filtre weekCycle manquant).

- [ ] **[SPEC.4]** — `swipe_qr` (GET `/sw/{code}/qr.png`) et `swipe_br` (GET `/sw/{code}/br.png`) : aucune annotation `@Security` → images téléchargeables sans authentification par quiconque connaissant le code Vigenère (présent dans les URLs des emails `swipe_in`). Permettent l'usurpation de badge (connexion passwordless + validation de présence). Ajouter `@Security("is_granted('ROLE_USER_MANAGER')")` ou restreindre au propriétaire du badge. Porter dans **SYN.2** (sécurité, effort XS).

- [ ] **[SPEC.4]** — `code_change_done` (GET `/codes/close_all`) : aucune `@Security` ; authentification temporaire via token Vigenère URL (`username,code:id`) sans date d'expiration → rejouable indéfiniment. Le controller impersonifie l'utilisateur (`setToken`/`setToken(previousToken)`) pour fermer des codes tiers. Ajouter expiration au token ou exiger une session active. Porter dans **SYN.2** (sécurité, effort S).

- [ ] **[SPEC.4]** — `user_install_admin` (GET\|POST `/user/install_admin`) : aucune `@Security` ; accessible sans authentification avant le premier setup (aucun SUPER_ADMIN en base). Risque de prise de contrôle si la route est atteinte par un tiers avant l'installation. Documenter la procédure de sécurisation réseau ou ajouter un guard d'environnement. Porter dans **SYN.2** (sécurité, effort XS).

- [ ] **[SPEC.4]** — `user_add_role` (GET `/user/{id}/addRole/{role}`) : mutation via verbe GET sans protection CSRF — un lien forgé peut ajouter un rôle sans confirmation. Remplacer par POST + formulaire CSRF. Porter dans **SYN.2** (sécurité, effort XS).

- [ ] **[SPEC.4]** — `CodeVoter::isLocationOk()` (l.151) : méthode privée qui duplique `PlaceIP::isLocationOk()`, avec commentaire `//\App\Security\UserVoter::isLocationOk DUPLICATED`. Refactorer pour injecter `PlaceIP` directement dans `CodeVoter` (comme dans `MembershipVoter`). Porter dans **SYN.2** (dette, effort XS).

- [ ] **[SPEC.4]** — `CodeVoter` : fall-through de `case self::OPEN:` vers `case self::DELETE:` (PHP switch sans `break`) — un non-ROLE_ADMIN qui tente d'ouvrir un code fermé (`toggle`) passe par la branche DELETE (→ SUPER_ADMIN ou `canDelete()` = false). Asymétrie ouvrir/fermer non documentée. À clarifier si intentionnel et documenter, ou corriger avec un `break` et une règle explicite. Porter dans **SYN.2** (dette/bug, effort XS).

- [ ] **[SPEC.4]** — `code_generate` : le code de porte est `rand(0, 9999)` (4 chiffres, peut produire `0000`), sans vérification de collision avec les codes ouverts existants. Si deux membres génèrent en parallèle, ils peuvent obtenir le même code. Porter dans **SYN.2** (robustesse, effort S).
