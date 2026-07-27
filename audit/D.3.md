# D.3 — Documentation d'installation

- [x] **D.3** — Documentation d'installation

Lire intégralement `doc/install.local.md`, `doc/install.serveur.md`, `doc/install.tests.linux.md` (et tout autre fichier dans `doc/`). Suivre mentalement chaque étape : cohérente avec l'état réel (Docker, Makefile, MariaDB 12+, Keycloak, variables d'env) ? Lister tous les écarts, étapes manquantes, commandes obsolètes.

**Fichiers lus** : `install.local.md`, `install.serveur.md`, `install.tests.linux.md`, `dev.md`, `start.md`, `maj.md`, `maj-v2.0.md`.

---

### `doc/install.local.md`

**1. Port PhpMyAdmin incorrect**
Ligne 84 : `http://localhost:8080` pour PhpMyAdmin. Dans le dist file (`docker-compose.symfony_server.yml.dist`), PhpMyAdmin est mappé sur **8081:80**, pas 8080. Le port 8080 est occupé par Keycloak (8080:8080). Le lien est donc cassé.

**2. Message de démarrage PHP erroné**
Ligne 36 : « La ligne `PHP 7.4.27 Development Server (http://0.0.0.0:8000) started` indique que le déploiement est fonctionnel ». C'est faux : le container utilise `symfony serve --allow-all-ip` (Symfony CLI), dont le message de démarrage est différent. Le numéro de version `7.4.27` est de plus hardcodé.

**3. Étapes de setup redondantes avec le Makefile**
Le guide décrit `docker compose build`, `docker compose up`, puis `make setup-test` en raccourci (ligne 90). En réalité, `make setup-test` fait tout (build, up, composer install, schéma, fixtures, cache). Les étapes manuelles induisent en erreur : elles n'incluent pas `composer install`, la création du schéma, ni les stubs Encore. La doc devrait présenter `make setup-test` comme le workflow **principal**, pas comme un raccourci.

**4. `DEV_MODE_ENABLED` non documentée**
Ligne 38 : mention de la variable `DEV_MODE_ENABLED` sans explication sur sa valeur, son effet, ni son emplacement (`.env`). À documenter dans CONFIG.1.

**5. Volume Docker — l'écart est géré silencieusement par le Makefile**
Le dist file contient un bind mount `./mysql:...`. Le Makefile le remplace par un volume nommé `db_data` via `sed`. Ce comportement n'est pas documenté dans `install.local.md` : l'utilisateur qui copie manuellement (`cp ... compose.yaml`) obtiendra un fichier différent de celui produit par `make`.

**6. Section Nix — cohérente**
`flake.nix` existe, la section est valide.

---

### `doc/install.serveur.md`

**1. Contrainte PHP sous-évaluée**
Ligne 5 : « PHP (version 7.2 et supérieure) ». La contrainte réelle dans `composer.json` est `"php": "7.4"` — minima = 7.4, pas 7.2.

**2. `assetic:dump` — commande inexistante dans ce projet**
Ligne 42 : `php bin/console assetic:dump`. Assetic est abandonné depuis Symfony 4. Ce projet utilise **Webpack Encore** (`webpack.config.js`, `encore-build` dans le Makefile). La commande à utiliser pour compiler les assets est `npm ci && npm run build` (ou `make encore-build`). La commande `assetic:dump` n'existe pas et renverra une erreur.

**3. Typo dans le nom de commande Doctrine**
Ligne 38 : `php bin/console doctrine:migration:migrate`. Le nom correct est **`doctrine:migrations:migrate`** (avec un `s`).

**4. `server:start` supprimé en Symfony 4**
Lignes 48-55 : `php bin/console server:start`. Cette commande n'existe plus en Symfony 4.x. Le serveur de développement est maintenant lancé via **`symfony serve`** (Symfony CLI). Pour la production, le guide recommande correctement nginx/Apache, mais la commande de dépannage est invalide.

**5. Rewrite nginx obsolète — mauvais point d'entrée**
Lignes 69-74 : la règle nginx pointe vers `/app.php` (point d'entrée Symfony 3.x — `web/app.php`). Depuis Symfony 4, le point d'entrée est `public/index.php`. La règle correcte est :
```
rewrite ^/sw/(.*)/(qr|br)\.png$ /index.php/sw/$1/$2.png last;
```

**6. `composer install` sans flags de production**
Ligne 34 : `composer install` sans `--no-dev --optimize-autoloader`. En production, cela installe les dépendances de développement (PHPStan, Rector, fixtures, etc.), ce qui est incorrect.

**7. Pas de mention des assets ni des variables d'env**
Aucune instruction pour : copier `.env.dist` → `.env`, configurer les variables, ni compiler les assets JS. La section Installation est incomplète pour un déploiement réel.

**8. Crontab — `--env=prod` absent**
Les commandes cron ne spécifient pas `--env=prod`. Symfony defaulte sur `dev` si `APP_ENV` n'est pas défini, ce qui charge les services de debug, désactive le cache opcode, etc. Un serveur sans variable `APP_ENV=prod` dans son environnement shell cron lancera les commandes en mode dev.

---

### `doc/dev.md`

**1. Chemin de log incorrect**
Ligne 55 : `tail -100 var/logs/dev.log`. Symfony 4.x utilise `var/log/` (sans `s`). Le fichier correct est `var/log/dev.log`.

**2. Commandes Docker obsolètes**
Lignes 42-47 :
```
docker exec -i php php bin/console --env=test doctrine:database:create
docker exec -i php php bin/console --env=test doctrine:schema:create
docker exec -i php php ./vendor/bin/phpunit
```
Ces commandes utilisent `docker exec` (syntaxe ancienne, sans `compose`) et sont entièrement remplacées par les targets Makefile (`make db-reset`, `make test`). Elles ne sont plus à jour et ne correspondent pas au workflow documenté dans `install.tests.linux.md`.

**3. Branche principale — vérifier si `master` ou `main`**
Ligne 5 : « La branche principale est `master` ». À confirmer (la norme GitHub récente est `main`). Cette incohérence peut dérouter les contributeurs.

**4. PRs recommandées en français**
Ligne 9 : « en préférant le Français ». Contredit la pratique réelle (et `CLAUDE.md`) qui exige l'anglais pour tous les artefacts git.

---

### `doc/start.md`

**1. Credentials super admin incohérents**
Ligne 5 : `babar/password`. `install.local.md` (ligne 59) indique `admin/password`. Les deux docs doivent s'accorder. À vérifier avec les fixtures (qui définit l'admin créé par `doctrine:fixtures:load`).

**2. Lien FOSUserBundle mort**
Ligne 17 : `http://symfony.com/doc/2.0/bundles/FOSUserBundle/command_line_tools.html`. Lien vers la doc Symfony 2.0 qui n'est plus maintenue ni accessible.

---

### `doc/maj.md`

**1. `assetic:dump` — même problème que `install.serveur.md`**
Ligne 50 : `php bin/console assetic:dump`. Commande inexistante dans ce projet depuis la migration vers Webpack Encore. À remplacer par `npm ci && npm run build`.

**2. Opérations absentes**
La procédure de mise à jour ne mentionne pas :
- La recompilation des assets JS (`npm ci && npm run build`)
- La purge de l'Opcache / redémarrage PHP-FPM après remplacement des fichiers PHP
- La vérification de la version PHP si un upgrade est requis

---

### `doc/maj-v2.0.md`

**1. Diff nginx inversé — BUG CRITIQUE**
Lignes 71-82 : le diff de configuration nginx est **dans le mauvais sens**. Il montre le passage de SF4 vers SF3 au lieu de SF3 vers SF4 :
```diff
-    root   /elefan/public/;    ← retire le dossier SF4
+    root   /elefan/web/;       ← remet le dossier SF3
```
Pour une migration 1.47 (SF3) → 2.0 (SF4), le diff correct est :
```diff
-    root   /elefan/web/;
+    root   /elefan/public/;
    location / {
-        index  app.php;
+        index  index.php;
-        try_files $uri /app.php$is_args$args;
+        try_files $uri /index.php$is_args$args;
```
En l'état, un admin qui suit ce guide à la lettre dégrade sa config nginx plutôt que de la mettre à jour.

---

### `doc/install.tests.linux.md`

C'est le fichier le mieux maintenu. Deux points mineurs :

**1. Package `docker-compose` v1 dans les prérequis**
Ligne 14 : `sudo apt install -y docker.io docker-compose make`. `docker-compose` est le binaire standalone v1, déprecié. Ubuntu 22.04+ et Debian 12+ proposent `docker-compose-v2`. Le Makefile supporte les deux (autodetect), mais mieux vaut guider vers la version actuelle.

**2. Keycloak non démarré par `make up`**
`make test-e2e-oidc` nécessite Keycloak, mais `make up` ne démarre que `database`, `php`, `mailcatcher`. Aucune instruction pour démarrer Keycloak localement pour les tests OIDC.

---

**Résumé des gravités :**
| Gravité | Finding |
|---------|---------|
| 🔴 Critique | `maj-v2.0.md` — diff nginx inversé (migration casse la prod) |
| 🟠 Important | `install.serveur.md` — `assetic:dump` inexistant ; `server:start` inexistant ; PHP >= 7.2 (réel : 7.4) ; rewrite nginx `/app.php` → `/index.php` ; `composer install` sans `--no-dev` |
| 🟠 Important | `install.local.md` — port PhpMyAdmin 8080 vs 8081 réel ; message de démarrage PHP faux |
| 🟠 Important | `dev.md` — `var/logs/` faux (→ `var/log/`) ; commandes Docker sans `compose` obsolètes |
| 🟡 Mineur | `maj.md` — `assetic:dump` + opérations manquantes post-update |
| 🟡 Mineur | `start.md` — credentials admin incohérents ; lien mort FOSUserBundle |
| 🟡 Mineur | `install.tests.linux.md` — `docker-compose` v1 dans prérequis ; Keycloak OIDC non documenté |

→ Toutes ces corrections alimenteront **SYN.1** (documentation mise à jour).

