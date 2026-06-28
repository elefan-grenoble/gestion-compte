# LOG.1 — Configuration Monolog

- [x] **LOG.1** — Configuration Monolog

Lire `config/packages/monolog.yaml` et variantes. Channels, handlers, niveaux, rotation → documentation finale.

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

