# D.4 — CHANGELOG.md

- [x] **D.4** — CHANGELOG.md

Géré par release-please ? Dernières entrées cohérentes avec git log ? Juste une vérification rapide.

**Findings :**

**1. release-please configuré et opérationnel**
Manifest `{".": "1.47.2"}` cohérent avec le dernier tag git. Config v4, `release-type: php`, sections françaises personnalisées (`feat` → Nouveautés, `fix` → Corrections, etc.). Aucun type Conventional Commit n'est masqué (`hidden: false` sur tous) — y compris `chore`, `ci`, `build` — ce qui donne des sections "Technique" très verbeuses.

**2. 4 versions manquantes dans le CHANGELOG**
Les tags git v1.45.8, v1.45.9, v1.46.0, v1.47.0 existent mais n'ont pas d'entrée CHANGELOG. Ces versions couvrent la période 2023-2025 entre l'adoption de release-please (PR #1049, commit 58d5b0aa) et les premières releases entièrement gérées par l'outil. Les notes de release de cette période sont sur GitHub (onglet Releases) mais absentes du fichier CHANGELOG.md.

**3. Trois formats coexistent dans le fichier**
| Période | Format | Exemple |
|---------|--------|---------|
| 1.47.1+ | release-please (liens comparatifs, sections par type, sans "v" dans le titre) | `## [1.47.2](...)` |
| v1.45.0–v1.45.7 | GitHub Release Notes (liste PR avec `@auteur`) | `## [v1.45.7](...)` |
| v1.44.x et avant | Date-first, sans lien comparatif | `## 2023-06-28 (v1.44.7)` |
Cette hétérogénéité est cosmétique et sans impact fonctionnel.

**4. Doublons dans l'entrée 1.47.2**
Plusieurs items de la section 1.47.1 sont répétés dans 1.47.2 (ex: "ajout d'une Github Action pour assign automatiquement l'auteur", commit 2f64030, ligne 13 et ligne 32). Anomalie connue de release-please quand des PRs mergées avant la release sont incluses dans le prochain cycle de calcul.

**5. `extra-files` référence un fichier inexistant — BUG SILENCIEUX**
`release-please-config.json` déclare `"extra-files": ["app/config/config.yml"]` pour y écrire la version à chaque release. Ce fichier est issu de la structure Symfony 3.x et **n'existe plus dans ce projet Symfony 4**. Conséquence : release-please ne met à jour aucun fichier de version dans le code — l'update est silencieusement ignoré. Le champ devrait être supprimé ou remplacé par le fichier réel qui porte la version (si un tel fichier existe).

→ Le finding 5 (extra-files obsolète) alimentera **SYN.2** (TODO priorisée), catégorie CI/Technique.

