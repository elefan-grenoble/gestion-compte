# SEC.7 — Secrets hardcodés

- [x] **SEC.7** — Secrets hardcodés

`grep -rn "password\s*[=:]\s*['\"][^$'\"]" src/ config/` (hors .env.example et commentaires) → TODO critique si trouvé.

**Périmètre** : `src/` (PHP), `config/` (YAML), fichiers `.env*` committé (`.env.dist`, `.env.test`, `.env.oidc.test`, `.envrc`). Scan gitleaks (historique git + working tree). Recherche manuelle de patterns `password`, `secret`, `api_key`, `token`, `private_key`, PEM.

---

<a id="SEC.7-F1"></a>
### F1 — `APP_SECRET` réel dans l'historique git public 🟡

**Localisation** : commit `a408661e` (2020-03-29, "Symfony 4 migration"), fichier `.env.dist`, ligne 21.
**Valeur commitée** : `APP_SECRET=4814f742d29ec73fd902ad2a0d360b76` (hex 32 chars, entropie élevée).
**Correctif existant** : commit `c30e1f36` (2023-12-20) remplace la valeur par le placeholder `ThisTokenIsNotSoSecretChangeIt`. L'état actuel (HEAD) est sain.
**Risque résiduel** : le secret reste accessible dans l'historique git public (`git show a408661e:.env.dist`). Toute instance qui a cloné entre 2020 et 2023 sans régénérer `APP_SECRET` utilise cette valeur publique. L'`APP_SECRET` Symfony sert à signer les CSRF tokens, les URLs signées, les cookies "remember-me" et les sessions.
**Recommandation TODO** : documenter dans le README/guide d'installation que `APP_SECRET` doit être régénéré à chaque déploiement (`openssl rand -hex 32`). Alerter les instances existantes (Elefan, Scopeli) de vérifier leur valeur deployée. L'historique ne peut pas être réécrit sans coordination.

<a id="SEC.7-F2"></a>
### F2 — Credentials de test faibles dans les fichiers CI committé 🔵

**Fichiers** : `.env.test`, `.env.oidc.test` (tous deux committé, utilisés par GitHub Actions).
**Valeurs concernées** :
- `DATABASE_URL="mysql://root:secret@..."` — mot de passe DB `secret`
- `SUPER_ADMIN_INITIAL_PASSWORD=password` — mot de passe super-admin `password`
- `APP_SECRET='$ecretf0rt3st'` — placeholder lisible, non ambigu

**Contexte** : ces credentials sont intentionnellement faibles pour l'environnement CI (Docker isolé, réseau interne). Ils ne représentent pas de risque direct.
**Risque résiduel** : si un développeur copie un fichier `.env.test` en base pour un déploiement staging accessible réseau, les credentials triviaux ouvrent un vecteur d'accès.
**Recommandation TODO** : ajouter un commentaire en tête des fichiers `# CI ONLY — do not use in staging/prod`.

<a id="SEC.7-F3"></a>
### F3 — `.env.dist` : `SUPER_ADMIN_INITIAL_PASSWORD=password` dans le template 🟡

**Localisation** : `.env.dist` (template HEAD), valeur `SUPER_ADMIN_INITIAL_PASSWORD=password`.
**Risque** : un développeur qui copie `.env.dist` → `.env` sans changer ce paramètre expose le compte super-admin avec le mot de passe `password` dès que l'app est joignable sur le réseau. Le mot de passe est défini par `UserAdmin::setInitialSuperAdmin()` qui l'utilise à l'initialisation.
**Recommandation TODO** : remplacer la valeur template par `<change-me>` ou `SUPER_ADMIN_INITIAL_PASSWORD=changeme_immediately` avec un commentaire explicite. Ajouter une validation au setup qui refuse `password` ou `changeme` en `APP_ENV=prod`.

<a id="SEC.7-F4"></a>
### F4 — Absence de `.gitleaks.toml` (faux positifs CI) 🔵

**Constat** : `gitleaks detect --no-git` remonte 1 482 faux positifs, tous dans `var/phpstan-dead-code/cache/` (clés internes PHPStan de type `variableKey => v2-<hex>-7.4`). Aucun vrai secret dans ce répertoire.
**Impact** : un job CI qui lancerait `gitleaks detect --no-git` (working tree) échouerait sur ces faux positifs. Le scan historique (`gitleaks detect`, sans `--no-git`) ne fait remonter que F1.
**Recommandation TODO** : créer `.gitleaks.toml` avec une règle d'exclusion de `var/` (non committé, mais utile en CI). Exemple :
```toml
[allowlist]
paths = ["var/"]
```

---

### Posture globale — SEC.7

| Statut | Finding | Fichiers |
|--------|---------|---------|
| 🟡 Moyen | `APP_SECRET` hex réel dans l'historique git (2020–2023) | commit `a408661e`, `.env.dist` ligne 21 |
| 🟡 Moyen | `SUPER_ADMIN_INITIAL_PASSWORD=password` dans le template dev | `.env.dist` |
| 🔵 Info | Credentials faibles dans les fichiers CI (intentionnel, isolé) | `.env.test`, `.env.oidc.test` |
| 🔵 Info | Absence de `.gitleaks.toml` → faux positifs CI | — |
| ✅ OK | Aucun secret hardcodé dans `src/` (PHP) | — |
| ✅ OK | Aucun secret hardcodé dans `config/` (YAML) | — |
| ✅ OK | `.env` (live credentials) gitignored | `.gitignore:56` |
| ✅ OK | Template HEAD utilise des placeholders clairs | `.env.dist` HEAD |
| ✅ OK | Tous les services utilisent `%env(...)%` | `config/services.yaml` |

→ `/model sonnet` peut être repris dès TC.1.

---

