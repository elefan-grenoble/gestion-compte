# DEP.3 — Dépendances JS

- [x] **DEP.3** — Dépendances JS

Lire `package.json`. Packages inutilisés ou vulnérables.

**Findings :**

---

### Production dependencies

**1. `canvas-gauges` ^2.1.5-radial — PHANTOM + CDN CASSÉ (🔴)**

Le package npm est installé (2.1.7 en lock) mais **jamais importé** dans `assets/js/`. Il est chargé exclusivement via une balise `<script>` CDN externe dans `templates/layout.html.twig:80` :
```html
<script src="https://cdn.rawgit.com/Mikhus/canvas-gauges/..."></script>
```
**rawgit.com a fermé en octobre 2019** — l'URL ne répond plus. La jauge radiale est rendue dans `templates/booking/home_dashboard.html.twig:9` (`{% if display_gauge %}`). La feature "jauge de remplissage" du dashboard est **cassée en production** pour toutes les instances.

Fix : remplacer le tag CDN par `require('canvas-gauges')` dans `app.js` (package npm déjà installé).

**2. `jquery` ^3.4.1** — Utilisé ✅ (importé dans `app.js`). Lock résout à 3.7.1.

**3. `material-icons-css` ^1.0.1 — INUTILISÉ (🟡)**

Package installé mais **jamais importé** dans les JS ou LESS. Les icônes Material Design sont servies via des polices locales dans `assets/fonts/iconfont/`, déclarées dans `assets/less/material-icons.less` (importé via `custom.less`). Le package npm est un doublon inutile. Supprimable de `package.json`.

**4. `materialize-css` ^1.0.0** — Utilisé ✅ (importé dans `app.js`).

**5. `simplemde` ^1.11.2** — Utilisé. Vulnérable/abandonné, déjà documenté en DEP.1.

---

### Dev dependencies

**6. `@babel/plugin-proposal-class-properties` ^7.18.6 — DÉPRÉCIÉ (🟡)**

Utilisé dans `webpack.config.js:55`. Marqué comme deprecated dans le package-lock : _"This proposal has been merged to the ECMAScript standard […] Please use @babel/plugin-transform-class-properties instead."_ Remplacement direct : `@babel/plugin-transform-class-properties`.

**7. `@hotwired/stimulus` ^3.0.0 — INUTILISÉ (🟡)**

`.enableStimulusBridge()` est **commenté** dans `webpack.config.js`. Aucun import Stimulus dans les fichiers JS, aucun `assets/bootstrap.js` ni `assets/controllers.json`. Supprimable.

**8. `@symfony/stimulus-bridge` ^3.0.0 — INUTILISÉ (🟡)** — même raison que ci-dessus.

**9. `@symfony/webpack-encore` ^1.7.0** — Utilisé ✅. Lock résout à 1.8.2 — version ancienne (encore actuel ~4.x), fonctionnelle.

**10. `core-js` ^3.0.0** — Utilisé implicitement ✅ (via Babel `useBuiltIns: 'usage'` + `corejs: 3`).

**11. `cypress` ^13.6.4** — Utilisé ✅ (tests E2E).

**12. `cypress-dotenv` ^2.0.0 — INUTILISÉ (🟡)**

Non référencé dans `cypress.config.js` ni dans les fichiers `cypress/support/`. Supprimable.

**13. `file-loader` ^6.2.0** — Utilisé ✅ (webpack-encore v1.x l'utilise pour `copyFiles()`).

**14. `less` ^4.2.0 + `less-loader` ^11.1.3** — Utilisés ✅ (`.enableLessLoader()` + imports LESS dans `app.js`).

**15. `regenerator-runtime` ^0.13.2 — PROBABLEMENT INUTILISÉ (🟡)**

Non importé dans `assets/js/`. Babel avec `@babel/preset-env` + `useBuiltIns: 'usage'` + `corejs: 3` gère automatiquement les polyfills async/generator. Supprimable si le build passe sans.

**16. `webpack-notifier` ^1.8.0** — Utilisé ✅ (`.enableBuildNotifications()`).

---

### Fichiers parasites committés

**17. `assets/js/jquery-3.6.js`** — Copie locale complète de jQuery 3.6.1 (10 909 lignes). Non importée dans `app.js` ni dans aucun template. Stale artifact, supprimable.

**18. `assets/less/card.css`, `custom.css`, `custom_animation.css`** — CSS pré-compilés committés aux côtés des sources LESS. Artefacts de l'ère pré-webpack. Non importés par webpack (qui compile les `.less` directement). Supprimables. Voir EXTRA : `custom_animation.less` non bundlée + lien cassé dans `period/index.html.twig`.

---

### Synthèse

| Priorité | Finding | Action |
|----------|---------|--------|
| 🔴 Feature cassée | `canvas-gauges` CDN rawgit.com HS | Remplacer CDN par import npm dans `app.js` |
| 🟡 Nettoyage | `material-icons-css` inutilisé | Supprimer de `package.json` |
| 🟡 Nettoyage | `@hotwired/stimulus` + `@symfony/stimulus-bridge` inutilisés | Supprimer de `package.json` |
| 🟡 Nettoyage | `cypress-dotenv` inutilisé | Supprimer de `package.json` |
| 🟡 Nettoyage | `regenerator-runtime` probablement inutilisé | Supprimer + vérifier le build |
| 🟡 Dépréciation | `@babel/plugin-proposal-class-properties` | Remplacer par `plugin-transform-class-properties` |
| 🟡 Artefacts | `jquery-3.6.js` + CSS pré-compilés dans `less/` | Supprimer du dépôt |

→ Les items 🔴 et 🟡 nettoyage alimenteront **SYN.2** (TODO priorisée), catégorie JS/Frontend.

---

