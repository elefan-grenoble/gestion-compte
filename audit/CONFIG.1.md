# CONFIG.1 — Variables d'environnement

- [x] **CONFIG.1** — Variables d'environnement

Lire `.env.dist`. Toutes les variables documentées ? `grep -rn "getenv\|\$_ENV\|->get('" src/ config/` pour trouver celles utilisées dans le code mais absentes du `.dist`. Résultat → documentation finale.

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

