# CI.1 — Lire `.github/workflows/ci.yaml`

- [x] **CI.1** — Lire `.github/workflows/ci.yaml`

Structure, versions PHP/Node fixées ou flottantes, secrets scopés. Documenter ici.

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

