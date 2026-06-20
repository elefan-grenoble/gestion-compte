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

Outil de gestion coopérative (créneaux de travail, adhérents, cotisations) utilisé par **plusieurs instances indépendantes** : Elefan (Grenoble), Scopely (Nantes), et d'autres coopératives. Chaque instance déploie sa propre version. Toutes les features ne sont pas utilisées partout.

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
  > Liste de 14 chemins d'URL hardcodés (L43–62) pour le contrôle d'accès OIDC. Aucune référence aux noms de routes Symfony — si une route est renommée, la protection ne suit pas. Concerne une fonctionnalité instance-specific (Scopely utilise OIDC, Elefan non). PHPDoc erroné ligne 28–30 (`@param PeriodPositionFreedEvent`) — copie de l'autre listener.
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
  > Sans paramètre `{beneficiary}`, la route est accessible anonymement et rend les créneaux du jour (noms des bénéficiaires inclus si `display_name_shifters=true`). Croiser avec **CONFIG.2** pour savoir si `display_name_shifters` est activé chez Elefan/Scopely.
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
  > **Recommandation TODO** : documenter dans le README/guide d'installation que `APP_SECRET` doit être régénéré à chaque déploiement (`openssl rand -hex 32`). Alerter les instances existantes (Elefan, Scopely) de vérifier leur valeur deployée. L'historique ne peut pas être réécrit sans coordination.
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
  > | `OAuthController` | ~40 | Flow OAuth OIDC (`/oauth/login`, `/oauth/callback`, `/oauth/logout`) — instance-specific (Scopely), difficile à tester sans stub Keycloak |
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
  > Comment Elefan et Scopely configurent-ils leur instance différemment ? Paramètres Symfony, table de config en base, feature flags ? `grep -rn "getParameter\|ParameterBagInterface" src/`. Résultat → documentation finale + specs (SPEC.9).

  **Résultat**

  Méthode : lecture de `config/services.yaml`, `config/packages/twig.yaml`, `.env.dist` ; grep exhaustif `getParameter` (84 appels) et `ParameterBagInterface` dans `src/`.

  ### Architecture de configuration : 3 couches, tout par variables d'environnement

  Il n'existe **aucune table de configuration en base** (pas d'entité `Parameter`/`Config`/`Setting` dans `src/Entity/`), **aucun feature flag framework** (Flipper, Unleash, etc.), et **aucune variable `APP_INSTANCE`** ou mécanisme d'identification de déploiement au runtime. Elefan et Scopely sont deux déploiements indépendants du même code, différenciés uniquement par leur fichier `.env` (ou équivalent CI/CD).

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
  | **Identité coopérative** | `SITE_NAME`, `PROJECT_NAME`, `MAIN_COLOR`, `LOCAL_CURRENCY_NAME` | Branding Elefan vs Scopely |
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
  Conséquence directe pour RT.1 : il faudra créer une variable (`APP_INSTANCE=elefan|scopely`) pour alimenter le tracking de routes recommandé en RT.2.

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

  **Périmètre** : `CYCLE_TYPE=abcd` n'est **pas** affecté (branche dédiée qui calcule depuis la semaine ISO). Les valeurs hardcodées impactent uniquement `cycle_type != "abcd"` (cycles flottants depuis `firstShiftDate`). En pratique Elefan utilise `abcd` et Scopely probablement aussi — le bug est donc dormant, mais constitue une dette technique explicite (TODO dans le code) et un risque lors d'onboarding d'une nouvelle instance.

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
  > → **TODO SYN.4** — Rétablir les migrations en production : décommenter et compléter le bloc migration dans `dploy.sh` (ligne 74), en lançant `doctrine:migrations:version --add --all` une seule fois sur les instances existantes pour synchroniser l'état, puis `doctrine:migrations:migrate --no-interaction` à chaque déploiement. Effort S (coordination avec les deux instances, Elefan + Scopely).

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

- [ ] **RT.1** — Identifier le mécanisme d'identification d'instance
  > Hostname ? Variable d'env `APP_INSTANCE` ? Confirmer en lisant `config/` et `.env.dist`.

- [ ] **RT.2** — Rédiger la recommandation d'implémentation
  > Décrire dans la TODO : EventSubscriber sur `kernel.terminate`, upsert en base sur `(route_name, instance)`, page admin de rapport. Inclure le schéma de la table proposée. C'est une spec technique, pas du code.

---

## SF-PREP — Préparation migration Symfony (analyse uniquement)

> **Objectif** : identifier les bloquants et estimer l'effort. La migration elle-même n'est pas dans le scope de cet audit.
> 🔀 **Modèle : Opus pour SF-PREP.2.** Rappeler `/model opus` avant SF-PREP.2, `/model sonnet` après.

- [ ] **SF-PREP.1** — Identifier les bloquants techniques
  > `docker compose exec -T php composer require symfony/symfony:5.4.* --dry-run 2>&1`. Lister tous les conflits. Les noter ici.

- [ ] **SF-PREP.2** — Évaluer l'effort de remplacement des bundles bloquants
  > Pour FOSUserBundle et FOSOAuthServerBundle : lire leur usage réel dans `src/` et `config/`. Estimer l'effort de migration vers les alternatives natives. Résultat → TODO avec estimations S/M/L/XL.

- [ ] **SF-PREP.3** — Inventaire des annotations à migrer
  > `grep -rn "@Route\|@IsGranted\|@ParamConverter\|@Template\|@Entity\|@Column\|@ORM" src/` — volume à migrer vers attributs PHP 8. Rector couvre automatiquement `@Route` et `@IsGranted`. `@ParamConverter` → migration manuelle vers `#[MapEntity]`. Résultat → TODO.

---

## SPEC — Spécifications fonctionnelles
> 🔀 **Modèle : Opus** pour toute cette section. Rappeler à l'utilisateur : `/model opus` avant SPEC.1, `/model sonnet` après SPEC.10.

> **C'est le livrable central de l'audit.** Les specs doivent être **lisibles et utiles pour un humain d'abord** — langage clair, structure logique, exemples concrets tirés du code. Le format markdown structuré et la terminologie cohérente les rendent également exploitables par un LLM. Chaque spec suit ce template :
> ```
> ### [Domaine]
> **Acteurs** : rôles concernés
> **Instances** : Elefan / Scopely / toutes (à préciser si connu)
> **Flux principal** : étapes
> **Règles métier** : contraintes identifiées dans le code
> **Données** : entités impliquées, champs clés
> **Cas limites** : comportements edge-case détectés
> **Routes** : liste des routes associées
> **Tests existants** : ce qui est couvert
> **Gaps** : non testé / non documenté / ambigu
> ```

- [ ] **SPEC.1** — Cartographier les domaines fonctionnels
  > `docker compose exec -T php php bin/console debug:router --format=txt 2>&1`. Regrouper les routes en domaines cohérents. Produire la liste ici avant d'écrire les specs.

- [ ] **SPEC.2** — Spec : Adhérents / Bénéficiaires
  > Croiser : `BeneficiaryController`, `MemberController`, entités `Beneficiary`/`Member`, events `BeneficiaryAddEvent`/`MemberCreatedEvent`.

- [ ] **SPEC.3** — Spec : Créneaux (Shifts)
  > Croiser : `ShiftController`, `ShiftService`, entités `Shift`/`Period`/`PeriodPosition`, events shift-related, commands shift-related.

- [ ] **SPEC.4** — Spec : Authentification & Autorisation
  > Croiser : `security.yaml`, `AuthenticationSuccessHandler`, `KeycloakController`, voters, `SwipeCard`.

- [ ] **SPEC.5** — Spec : Cotisations & Paiements
  > Croiser : `HelloassoController`, `HelloassoClient`, events `HelloassoEvent`, entités paiement.

- [ ] **SPEC.6** — Spec : Administration & Configuration
  > Croiser : controllers admin, commands d'administration, paramètres métier (CONFIG.3).

- [ ] **SPEC.7** — Spec : Notifications & Emails
  > Croiser : `EmailingEventListener`, templates Twig d'emails, events déclencheurs.

- [ ] **SPEC.8** — Spec : API & Intégrations externes
  > Croiser : OAuth exposé, Helloasso, Igloohome, Keycloak. `src/Providers/`.

- [ ] **SPEC.9** — Annotations d'usage par instance
  > Pour chaque spec : annoter "utilisé chez Elefan/Scopely" si identifiable via CONFIG.2 ou RT.

- [ ] **SPEC.10** — Glossaire métier
  > Termes du domaine (Shift, Period, Beneficiary, Swipe, Commission, Service…) — définition, entité associée.

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

- [ ] **[DEP.3]** — `canvas-gauges` CDN HS : vérifier si `display_gauge` est activé chez Elefan et/ou Scopely (variable de config ou flag d'instance). Si désactivé partout, la feature peut être retirée plutôt que corrigée. À confirmer en **CONFIG.2**.

- [ ] **[DC.2]** — `AuthenticationSuccessHandler::onAuthenticationSuccess()` viole `AuthenticationSuccessHandlerInterface` : quand `$target` est absent, la méthode retourne `null` implicitement alors que l'interface exige un `Response`. Le `return;` supprimé par Rector masque davantage ce chemin. Bug à classer dans **SYN.2** (bugs, effort XS).

- [ ] **[AP.7]** — `CodeEventListener::onCodeNew()` (L30-35) : corps entièrement commenté (seul `$this->logger->info(...)` subsiste). Le listener est enregistré dans le container mais sans logique — candidat suppression ou TODO d'implémentation. Confirmer en DC.3.

- [ ] **[AP.7]** — `EmailingEventListener::onHelloassoTooEarly()` L257 : `die($e->getMessage())` tue le process PHP sur exception email. Bug critique — peut produire une page blanche en production lors d'un paiement Helloasso. Corriger en priorité (TODO AP.7.1). Cross-ref SYN.2.

- [ ] **[TC.5]** — Aucune commande n'a d'option `--dry-run`, y compris les opérations irréversibles (`app:anonymize`, `app:member:close`, `app:shift:generate`). Recommandation UX opérationnelle : ajouter `--dry-run` aux commandes destructives (output de ce qui serait fait sans modifier la base). À porter dans **SYN.2** (ergonomie CLI, effort S par commande).

- [ ] **[PERF.1, PERF.2]** — Volumétries de prod requises pour valider la sévérité des findings PERF. Les comptages de lignes utilisés pendant l'audit (beneficiary: 56, proxy: 0, closing_exception: 0, shift: 51, membership: 56, time_log: 0) sont issus de la base de test — ils ne reflètent pas la production Elefan ni Scopely. Avant de prioriser les correctifs PERF, refaire l'analyse avec une dump prod anonymisée (à fournir après anonymisation côté utilisateur). Les sévérités 🔴/🟡 sont des estimations raisonnées mais non confirmées sur données réelles.

- [ ] **[CI.1] TODO CI.A** — `shivammathur/setup-php@verbose` dans `.github/workflows/ci.yaml` : `@verbose` est un alias comportemental flottant, pas un tag sémantique. Épingler à une version (`@v2` ou mieux un SHA digest) pour éliminer le risque supply chain. Effort XS.

- [ ] **[CI.1] TODO CI.B** — Gap PHP 7.4 (CI) vs PHP 8.1 (container Docker / prod) : la CI valide du code PHP 7.4 alors que le runtime de déploiement est PHP 8.1. Ajouter PHP 8.1 à la matrice CI (ou remplacer 7.4 par 8.1 si 7.4 n'est plus ciblé). Effort XS (ajout d'entrée matrice + vérification des extensions requises). Cross-ref SF-PREP.

- [ ] **[CI.1] TODO CI.C** — Pas de lint JS/CSS dans la CI (pas d'ESLint, pas de Stylelint). PHPStan couvre PHP uniquement. Si du code JS/CSS évolue, les erreurs de syntaxe ou de style ne sont pas détectées automatiquement. Optionnel — effort XS si les outils sont déjà présents dans `package.json`.
