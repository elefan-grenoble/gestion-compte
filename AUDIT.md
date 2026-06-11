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

- [ ] **AP.3** — Requêtes hors Repository
  > `grep -rn "createQuery\|createNativeQuery\|getConnection\|createQueryBuilder" src/` hors `src/Repository/`. SQL/DQL dans controllers ou services → TODO.

- [ ] **AP.4** — Container injecté comme service locator
  > `grep -rn "ContainerInterface\|DependencyInjection\\\\Container" src/` hors `Kernel.php` → TODO.

- [ ] **AP.5** — Services avec état mutable
  > Services singleton qui ont des propriétés écrites après construction (risque entre requêtes) → TODO.

- [ ] **AP.6** — Couplage Request → Service
  > `grep -rn "Request \$request" src/Service/` — services qui dépendent de HTTP directement → TODO.

- [ ] **AP.7** — Event listeners surchargés
  > Lire `src/EventListener/`. Listeners > 50 lignes de logique métier → TODO.

- [ ] **AP.8** — Commandes sans délégation service
  > Lire `src/Command/`. Commandes > 30 lignes dans `execute()` sans déléguer → TODO.

- [ ] **AP.9** — Providers externes (src/Providers/)
  > Lire les 7 fichiers. Interface + implémentation correctement séparées ? Couplage fort ? → TODO.

---

## SEC — Sécurité (analyse uniquement)
> 🔀 **Modèle : Opus.** Rappeler à l'utilisateur : `/model opus` avant SEC.1, `/model sonnet` après SEC.7.

- [ ] **SEC.1** — Configuration sécurité Symfony
  > Lire `config/packages/security.yaml`. Firewalls, access_control, voters. Gaps → TODO.

- [ ] **SEC.2** — Autorisation dans les controllers
  > `grep -rn "denyAccessUnlessGranted\|IsGranted\|isGranted" src/Controller/`. Actions sans vérification → TODO.

- [ ] **SEC.3** — CSRF
  > `grep -rn "csrf_protection.*false\|'csrf'.*false" src/`. Formulaires non protégés → TODO.

- [ ] **SEC.4** — Requêtes non paramétrées
  > `grep -rn "\"SELECT\|'SELECT\|createNativeQuery" src/Repository/`. Concaténation de variables dans du SQL → TODO critique.

- [ ] **SEC.5** — Upload fichiers
  > Config VichUploader : validation MIME, extension, taille max ? → TODO si manquant.

- [ ] **SEC.6** — Twig `|raw`
  > `grep -rn "|raw" templates/`. Inventaire et justification pour chaque usage.

- [ ] **SEC.7** — Secrets hardcodés
  > `grep -rn "password\s*[=:]\s*['\"][^$'\"]" src/ config/` (hors .env.example et commentaires) → TODO critique si trouvé.

---

## TC — Couverture de tests (analyse uniquement)

- [ ] **TC.1** — Rapport de couverture
  > `docker compose exec -T php composer test-coverage 2>&1`. % global et par namespace. Résultat → TODO (zones non couvertes).

- [ ] **TC.2** — Controllers sans test fonctionnel
  > Croiser `ls src/Controller/` avec `ls tests/Functional/`. Lister les controllers sans couverture → TODO.

- [ ] **TC.3** — Services sans test unitaire
  > Croiser `src/Service/` avec `tests/Unit/`. Lister les gaps → TODO.

- [ ] **TC.4** — Qualité des tests existants
  > Lire les tests. Assertions vagues, mocks abusifs, fixtures mal isolées → TODO.

- [ ] **TC.5** — Commandes non testées
  > 25 commandes dans `src/Command/` — combien ont des tests ? → TODO.

---

## PERF — Performance (analyse uniquement)

- [ ] **PERF.1** — N+1 queries potentielles
  > `grep -rn "findAll()\|findBy(\[\])" src/` puis vérifier si le résultat est itéré avec des appels supplémentaires → TODO.

- [ ] **PERF.2** — Collections non paginées
  > `grep -rn "findAll()" src/Repository/` utilisé dans des controllers qui rendent des listes → TODO.

- [ ] **PERF.3** — Cache applicatif
  > `grep -rn "CacheItemPoolInterface\|cache\.app\|TagAwareCacheInterface" src/`. Calculs lourds sans cache → TODO.

---

## CONFIG — Configuration multi-instance

- [ ] **CONFIG.1** — Variables d'environnement
  > Lire `.env.dist`. Toutes les variables documentées ? `grep -rn "getenv\|\$_ENV\|->get('" src/ config/` pour trouver celles utilisées dans le code mais absentes du `.dist`. Résultat → documentation finale.

- [ ] **CONFIG.2** — Mécanisme de personnalisation par instance
  > Comment Elefan et Scopely configurent-ils leur instance différemment ? Paramètres Symfony, table de config en base, feature flags ? `grep -rn "getParameter\|ParameterBagInterface" src/`. Résultat → documentation finale + specs (SPEC.9).

- [ ] **CONFIG.3** — Paramètres métier configurables
  > Comportements paramétrables (durée créneaux, règles adhésion, seuils, emails) — documentés ? Résultat → documentation finale.

---

## LOG — Observabilité

- [ ] **LOG.1** — Configuration Monolog
  > Lire `config/packages/monolog.yaml` et variantes. Channels, handlers, niveaux, rotation → documentation finale.

- [ ] **LOG.2** — Ce qui est loggé
  > `grep -rn "logger->\|LoggerInterface" src/`. Événements métier critiques non loggés ? `catch` silencieux ? → TODO.

- [ ] **LOG.3** — Traçabilité des actions sensibles
  > Actions sensibles (changement rôle, suppression, validation paiement) tracées ? → TODO si manquant.

---

## DB — Santé du schéma

- [ ] **DB.1** — Validation schéma vs entités
  > `docker compose exec -T php php bin/console doctrine:schema:validate`. Divergences → TODO.

- [ ] **DB.2** — État des migrations
  > `docker compose exec -T php php bin/console doctrine:migrations:status`. Migrations en attente ou orphelines → TODO.

- [ ] **DB.3** — Qualité des migrations
  > Migrations avec `down()` vides, opérations irréversibles sans warning → TODO.

---

## CI — Qualité pipeline

- [ ] **CI.1** — Lire `.github/workflows/ci.yaml`
  > Structure, versions PHP/Node fixées ou flottantes, secrets scopés. Documenter ici.

- [ ] **CI.2** — Tests flaky et couverture E2E
  > Skips, retries, scénarios Cypress manquants vs routes existantes → TODO.

- [ ] **CI.3** — Préparer la CI pour la migration Symfony (analyse seulement)
  > Ce qu'il faudra changer dans la CI pour tester SF5 puis SF6 → TODO migration.

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
