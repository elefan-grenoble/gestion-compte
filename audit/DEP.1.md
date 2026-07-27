# DEP.1 — Audit sécurité

- [x] **DEP.1** — Audit sécurité

Outil : `symfony security:check` (dans container) pour PHP — `composer audit` indisponible en Composer 2.2 LTS.
JS : `npm audit` sur le host.

---

### PHP — 30 CVEs dans 14 packages (`symfony security:check`)

#### 🔴 Critique — auth bypass, injection, exécution de code

| Package | Version | CVE | Description |
|---------|---------|-----|-------------|
| `symfony/security-http` | v4.4.50 | CVE-2026-45063 | Usurpation d'identité via regex DN non ancrée dans `X509Authenticator` |
| `symfony/security-http` | v4.4.50 | CVE-2026-48489 | Bypass du firewall via sous-requête `failure_forward` → accès non authentifié aux routes protégées par `access_control` |
| `symfony/cache` | v4.4.48 | CVE-2026-45073 | SQL injection dans `PdoAdapter::doClear()` via `$prefix` non échappé |
| `symfony/http-foundation` | v4.4.49 | CVE-2025-64500 | Parsing incorrect de `PATH_INFO` → contournement partiel d'autorisation |
| `symfony/mailer` | v4.4.49 | CVE-2026-45068 | Argument injection dans `SendmailTransport` via destinataire avec tiret |
| `symfony/mime` | v4.4.47 | CVE-2026-45067 | Injection d'en-têtes email / commande SMTP via CRLF dans `Address` |
| `symfony/mime` | v4.4.47 | CVE-2026-45070 | Injection d'en-têtes via caractères non-token dans les noms de paramètre |
| `twig/twig` | v2.16.1 | CVE-2026-46633 | Injection de code PHP via nom de template contrôlé dans `{% use %}` |
| `twig/twig` | v2.16.1 | CVE-2026-46628 | Le filtre `spaceless` marque sa sortie comme sûre implicitement → XSS potentiel |

**Note sandbox Twig** : les CVE CVE-2024-51754/55, CVE-2026-24425, CVE-2026-46627/35/36/38, CVE-2026-47732, CVE-2026-48805/06/07/08 **ne s'appliquent pas** — la sandbox Twig n'est pas activée dans ce projet.

#### 🟠 Important — redirections, DoS, désérialisation

| Package | Version | CVE | Description |
|---------|---------|-----|-------------|
| `symfony/http-foundation` | v4.4.49 | CVE-2024-50345 | Open redirect via URLs normalisées par le navigateur |
| `symfony/routing` | v4.4.44 | CVE-2026-45065 | `UrlGenerator` bypass regex non ancrée → injection d'URL hors-site |
| `symfony/routing` | v4.4.44 | CVE-2026-48784 | Encodage des segments `.` saute 1 sur 2 → URL s'effondre sous normalisation RFC 3986 |
| `symfony/dom-crawler` | v4.4.45 | CVE-2026-45071 | XXE dans `DomCrawler::addXmlContent()` si `validateOnParse = true` (opt-in) |
| `symfony/monolog-bridge` | v4.4.43 | CVE-2026-45077 | Désérialisation PHP non authentifiée dans le listener `server:log` (nécessite port exposé) |
| `symfony/yaml` | v4.4.45 | CVE-2026-45133 | Stack exhaustion parser YAML via blocs imbriqués non bornés |
| `symfony/yaml` | v4.4.45 | CVE-2026-45304 | Allocation mémoire exponentielle via alias récursifs ("Billion Laughs") |
| `symfony/yaml` | v4.4.45 | CVE-2026-45305 | ReDoS via backtracking catastrophique dans `Parser::cleanup()` |

#### 🟡 Mineur — périmètre limité

| Package | Version | CVE | Description | Limite |
|---------|---------|-----|-------------|--------|
| `symfony/validator` | v4.4.48 | CVE-2024-50343 | Réponse incorrecte quand l'input se termine par `\n` | Comportement edge-case |
| `symfony/polyfill-intl-idn` | v1.33.0 | CVE-2026-46644 | Labels `xn--` avec payload ASCII acceptés comme équivalents | IDN peu utilisé |
| `symfony/process` | v4.4.44 | CVE-2024-51736 | Hijack d'exécution via `Process` | **Windows uniquement** |
| `phpunit/phpunit` | 9.6.32 | CVE-2026-24765 | Désérialisation non sûre dans PHPT code coverage | **Dev uniquement** |

**Contexte** : toutes ces CVEs ont une correction disponible dans les versions Symfony 4.4.x maintenues (pas de saut de version majeure requis). La contrainte bloquante est que ce projet est verrouillé à Symfony 4.4 — un `composer update symfony/*` est possible sans rompre la compatibilité SF4.

---

### JS — 47 vulnérabilités (`npm audit`)

#### 🔴 Critique — production, sans correctif

| Package | CVE/Advisory | Description | Correctif |
|---------|-------------|-------------|-----------|
| `simplemde` `*` | GHSA-wg85-p6j7-gp3w | XSS dans le rendu markdown — **aucun fix disponible**, projet abandonné | Remplacement requis (ex: EasyMDE, fork activement maintenu) |

**Contexte** : `simplemde` est une dépendance de **production** (champ `dependencies` dans `package.json`), incluse dans le bundle final. Elle est utilisée comme éditeur markdown dans les formulaires (`assets/js/app.js`, `templates/form/fields.html.twig`). Le projet est archivé sur GitHub depuis 2017 — aucune mise à jour de sécurité à attendre.

#### 🟠 Important — build/dev toolchain (hors bundle production)

Les 46 autres vulnérabilités (dont 2 critiques `form-data` + `@babel/plugin-transform-modules-systemjs`) sont dans la chaîne de build :
- `@symfony/webpack-encore`, `webpack`, `webpack-dev-server`, `webpack-dev-middleware` : **serveur de dev uniquement**, non inclus dans le bundle de production
- `cypress` et ses transitives (`@cypress/request`, `form-data`, `uuid`) : **tests E2E uniquement**
- `@babel/*`, `lodash`, `serialize-javascript`, `terser-webpack-plugin` : **transpileur/minifieur**, non exposés en runtime

**Exceptions notables dans la toolchain :**
| Package | Sévérité | Advisory | Impact pratique |
|---------|---------|---------|----------------|
| `form-data` < 2.5.4 | Critique | GHSA-fjxv-7rqg-78g4 | Aléatoire non cryptographique pour boundary multipart — via `@cypress/request` (Cypress dev) |
| `@babel/plugin-transform-modules-systemjs` | Haute | GHSA-fv7c-fp4j-7gwp | Code arbitraire si input malveillant au transpileur — risque CI si le code source est non maîtrisé |
| `webpack-dev-middleware` | Haute | GHSA-wr3j-pwj9-hqq6 | Path traversal — **dev serveur uniquement** |
| `lodash` | Haute | GHSA-xxjr-mmjv-4gpg | Prototype pollution — dans la chaîne webpack (dev) |

**`npm audit fix`** : 43 vulnérabilités corrigeables sans breaking change. 4 restantes nécessitent `--force` (upgrade `@symfony/webpack-encore` 1.x → 6.0, breaking change).

---

### Synthèse et priorisation

| Priorité | Action | Effort |
|----------|--------|--------|
| 🔴 Immédiat | `composer update symfony/*` : patch toutes les CVEs SF4.4 sans rupture | S |
| 🔴 Court terme | Remplacer `simplemde` par EasyMDE (fork maintenu, API compatible) | M |
| 🟠 Build | `npm audit fix` sur la toolchain (43 fixes sans breaking change) | S |
| 🟡 Optionnel | Upgrade `@symfony/webpack-encore` 1.x → 6.0 (breaking, évaluer au cas par cas) | L |

→ Toutes les actions de priorité 🔴 alimenteront **SYN.2** (TODO priorisée), catégorie Sécurité.

