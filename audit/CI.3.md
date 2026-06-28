# CI.3 — Préparer la CI pour la migration Symfony (analyse seulement)

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

