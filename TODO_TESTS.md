# TODO — Amélioration des tests

> **Branche** : `chriskaya/improve-tests-sf4`  
> **Règle** : aucune modification du code fonctionnel (`src/`, `templates/`, etc.)  
> **Principe** : chaque étape = un commit atomique, clair et compréhensible.

---

## État des lieux (vérifié)

### Environnement
- **PHP local** : 7.4.33 (identique au Dockerfile)
- **PHPUnit** : 9.6.16 ✅ installé et fonctionnel
- **BDD locale** : MySQL via WAMP ✅

### Résultat des tests — 16/16 ✅
- ✅ Suite `integration` : 12/12 (`ShiftServiceTest`)
- ✅ Suite `functional` : 4/4 (`AdminControllerTest`)

---

## Étape 0 — Prérequis : rendre les tests exécutables ✅ DONE

### Commit 0.1 : `fix(test): add missing stdout monolog handler for test env`
- [x] Ajout du handler `stdout` dans `config/packages/test/monolog.yaml`
  (le kernel Symfony plantait en env test car `services.yaml` décore `monolog.handler.stdout`)

### Commit 0.2 : `fix(test): add phpunit test suites separation`
- [x] Séparation des test suites dans `phpunit.xml.dist` :
  - `unit` → `tests/Unit/`
  - `integration` → `tests/Integration/`
  - `functional` → `tests/Functionnal/`
- [x] Création du dossier `tests/Unit/`

### Commit 0.3 : `fix(entity): make openid fields nullable to match migration`
- [x] Ajout de `nullable=true` sur `openid` et `openid_member_number` dans `Beneficiary.php`
  (les annotations ORM étaient NOT NULL alors que la migration SQL les crée nullables ;
  git-blame : commit `f4eaf753` ajoutait les champs sans `nullable=true`)
- [x] Adaptation des signatures PHP : `?string` pour getters/setters

---

## Étape 1 — Nettoyage et correction des tests existants ✅ DONE

### Commit 1.1 : `fix(tests): rename Functionnal → Functional (typo)`
- [x] Renommer le dossier `tests/Functionnal/` en `tests/Functional/`
- [x] Mettre à jour le namespace dans `DatabasePrimer.php` : `App\Tests\Functional`
- [x] Mettre à jour le namespace dans `AdminControllerTest.php` : `App\Tests\Functional\Controller`
- [x] Mettre à jour le `use` dans `AdminControllerTest.php`

### Commit 1.2+1.3+1.6 : `refactor(tests): clean up AdminControllerTest`
- [x] Supprimer les 4 lignes `echo "\n\033[32m ... \033[0m\n";` dans `AdminControllerTest.php`
- [x] Renommer la variable `$Beneficiaries` en `$beneficiaries` (2 occurrences)
- [x] Remplacer `'App:User'` par `User::class`, `'App:Beneficiary'` par `Beneficiary::class`, `'App:Membership'` par `Membership::class`
- [x] Ajouter les `use` correspondants

### Commit 1.4 : `refactor(tests): replace deprecated PHPUnit mock methods in ShiftServiceTest`
- [x] Remplacer `setMethods([...])` par `onlyMethods([...])` (3 occurrences)
- [x] Remplacer `setMethodsExcept([...])` par `onlyMethods([])` (compatible PHPUnit 9+)
- [x] Utiliser `addMethods()` pour les méthodes custom du repository (`findShiftsForBeneficiary`)
- [x] Note : `'App:Shift'` conservé dans le mock car le code source (`ShiftService`, `BeneficiaryService`) l'utilise encore

### Commit 1.5 : `refactor(tests): use data providers in AdminControllerTest`
- [x] Créer un `@dataProvider csvDelimiterProvider` unique pour les 2 variantes (comma/semicolon)
- [x] Factoriser 4 méthodes quasi identiques en 2 méthodes paramétrées
- [x] Remplacer `strpos()` + `assertTrue()` par `assertStringContainsString()`
- [x] Correction du typo : `CommissionFiled` → `CommissionFilled`

---

## Étape 2 — Tests unitaires des services métier ✅ DONE

### Commit 2.1 : `test(MembershipService): add unit tests for core membership logic`
- [x] Créer `tests/Unit/Service/MembershipServiceTest.php`
- [x] Tester `getRemainder()`, `canRegister()`, `getExpire()`, `isUptodate()`
- [x] Tester `getStartOfCycle()` / `getEndOfCycle()` / `getCycleNumber()` : ABCD + non-ABCD, offsets
- [x] Tester `hasWarningStatus()` : withdrawn, frozen, flying, not up-to-date, all ok
- [x] 27 tests, 32 assertions

### Commit 2.2 : `test(BeneficiaryService): add unit tests for beneficiary logic`
- [x] Créer `tests/Unit/Service/BeneficiaryServiceTest.php`
- [x] Tester `getCycleShiftDurationSum()` : avec shifts, sans shifts
- [x] Tester `getDisplayNameWithMemberNumberAndStatusIcon()` : format, avec warning
- [x] Tester `hasWarningStatus()` : delegation, flying beneficiary, flying entity mismatch
- [x] Tester `getStatusIcon()` : withdrawn, frozen, flying, exempted, registration missing, multiple, none
- [x] 15 tests, 25 assertions

### Commit 2.3 : `test(ShiftService): add unit tests for untested methods`
- [x] Créer `tests/Unit/Service/ShiftServiceUnitTest.php`
- [x] Tester `remainingToBook()`, `canBookExtraShift()`, `canBookSomething()`, `canBookShift()`, `canBookDuration()`
- [x] Tester `canFreeShift()` : no shifter, different shifter, admin, past shift, fixed shift, time log saving
- [x] Tester `isBeginner()`, `shiftTimeByCycle()`, `getMinimalShiftDuration()`
- [x] 26 tests, 29 assertions

### Commit 2.4 : `test(TimeLogService): add unit tests for time log operations`
- [x] Créer `tests/Unit/Service/TimeLogServiceTest.php`
- [x] Tester `initTimeLog()`, `initShiftValidatedTimeLog()`, `initShiftInvalidatedTimeLog()`
- [x] Tester `initCycleBeginningTimeLog()`, `initCurrentCycleBeginningTimeLog()`
- [x] Tester `initSavingTimeLog()`, `initCustomTimeLog()`, `initRegulateOptionalShiftsTimeLog()`
- [x] Tester `initShiftFreedSavingTimeLog()`, `initCycleEndSavingTimeLog()`
- [x] 17 tests, 36 assertions

### Commit 2.5 : `test(PeriodService): add unit tests for period logic`
- [x] Créer `tests/Unit/Service/PeriodServiceTest.php`
- [x] Tester `getDaysOfWeekArray()`, `getWeekCycleArray()`
- [x] Tester `hasWarningStatus()` : disabled, frozen, withdrawn, flying, empty, weekCycle filter
- [x] Découverte du bug #4 (précédence `and`/`or` dans `hasWarningStatus`)
- [x] 11 tests, 16 assertions

---

## Étape 3 — Tests unitaires des entités (logique métier)

### Commit 3.1 : `test(Entity/Membership): add unit tests for membership entity`
- [ ] Créer `tests/Unit/Entity/MembershipTest.php`
- [ ] Tester les getters/setters essentiels
- [ ] Tester `getMainBeneficiary()` / `setMainBeneficiary()`
- [ ] Tester `getFrozen()` / `setFrozen()` / `getFrozenChange()`
- [ ] Tester les méthodes de calcul si présentes dans l'entité

### Commit 3.2 : `test(Entity/Shift): add unit tests for shift entity`
- [ ] Créer `tests/Unit/Entity/ShiftTest.php`
- [ ] Tester `getIsPast()` : shift passé, futur, en cours
- [ ] Tester `getDuration()` : calcul correct entre start et end
- [ ] Tester `getIntervalCode()`
- [ ] Tester les relations (shifter, formation, etc.)

### Commit 3.3 : `test(Entity/ShiftBucket): add unit tests for shift bucket entity`
- [ ] Créer `tests/Unit/Entity/ShiftBucketTest.php`
- [ ] Tester `addShift()` / `removeShift()` / `getShifterCount()`
- [ ] Tester `getStart()` / `getEnd()` / `getDuration()`
- [ ] Tester `getJob()` / `getFormation()`

### Commit 3.4 : `test(Entity/Beneficiary): add unit tests for beneficiary entity`
- [ ] Créer `tests/Unit/Entity/BeneficiaryTest.php`
- [ ] Tester `getDisplayName()` / `__toString()`
- [ ] Tester `getMembership()` / `getMemberNumber()`
- [ ] Tester `getCommissions()` : ajout, suppression
- [ ] Tester `getShifts()` : collection de shifts

---

## Étape 4 — Tests fonctionnels des contrôleurs critiques

### Commit 4.1 : `test(functional): add smoke tests for all public routes`
- [ ] Créer `tests/Functional/Controller/SmokeTest.php`
- [ ] Tester que toutes les routes publiques répondent en 200 ou 302 (redirection login)
- [ ] Couvrir au minimum : `/`, `/login`, `/event/`, `/schedule`
- [ ] Utiliser un `@dataProvider` avec la liste des URLs

### Commit 4.2 : `test(functional): add authenticated route tests`
- [ ] Créer `tests/Functional/Controller/AuthenticatedSmokeTest.php`
- [ ] Créer un helper pour simuler un utilisateur connecté (loginAs)
- [ ] Tester les routes protégées en tant qu'admin : `/admin/`, `/admin/members/`
- [ ] Tester les routes protégées en tant qu'utilisateur : `/member/`, `/shift/`
- [ ] Vérifier les codes de retour (200, 403 pour accès interdit)

### Commit 4.3 : `test(functional): add MembershipController tests`
- [ ] Créer `tests/Functional/Controller/MembershipControllerTest.php`
- [ ] Tester l'affichage de la page membership
- [ ] Tester la soumission de formulaire de registration
- [ ] Tester les cas d'erreur (formulaire invalide)

---

## Étape 5 — Infrastructure et qualité des tests

### Commit 5.1 : `test(infra): add base TestCase classes with shared helpers`
- [ ] Créer `tests/Unit/UnitTestCase.php` : base pour les tests unitaires avec helpers de mocking partagés (container mock, em mock)
- [ ] Créer `tests/Functional/FunctionalTestCase.php` : renommer/étendre `DatabasePrimer` en ajoutant un helper `loginAs($username)`

### Commit 5.2 — DONE (étape 0.2) : séparation des test suites PHPUnit

### Commit 5.3 : `ci: improve GitHub Actions workflow`
Améliorations du fichier `.github/workflows/ci.yaml` existant :
- [x] Ajouter `push:` au trigger pour exécuter la CI à chaque push, quelle que soit la branche
- [ ] Fixer `${{ matrix.php-versions }}` dans les jobs `phpStan`, `symfony-tests`, `cypress-tests`
  (la variable est vide car la matrice n'est déclarée que dans le job `setup` ; hardcoder `'7.4'`)
- [ ] Ajouter un job `fast-tests` sans BDD pour les suites `unit` + `integration`
  (rapide, pas besoin de MariaDB ni d'attente de connexion)
- [ ] Uploader les screenshots/vidéos Cypress en cas d'échec
  (`actions/upload-artifact` sur `cypress/screenshots/` et `cypress/videos/`)
- [ ] Cacher `node_modules` / le binaire Cypress pour accélérer le job `cypress-tests`
- [ ] Renommer le step "Run unit and functional tests" pour refléter les suites réelles

### Commit 5.4 : `test(infra): add code coverage configuration`
- [ ] Vérifier que la section `<coverage>` dans `phpunit.xml.dist` est correctement configurée
- [ ] Ajouter un script `composer test-coverage` dans `composer.json`
- [ ] Documenter le seuil de couverture minimum visé dans ce fichier TODO

---

## Étape 6 — Amélioration des tests Cypress (E2E)

### Commit 6.1 : `test(cypress): add custom commands in support/commands.js`
- [ ] Déplacer la logique de `login_reusables.cytools.js` dans `cypress/support/commands.js` en tant que `Cypress.Commands.add('login', ...)`
- [ ] Adapter les tests existants pour utiliser `cy.login()` au lieu de l'import
- [ ] Garder le login Keycloak séparé : `Cypress.Commands.add('loginKeycloak', ...)`

### Commit 6.2 : `test(cypress): add E2E tests for member shift booking flow`
- [ ] Créer `cypress/e2e/shift/member_can_book_shift.cy.js`
- [ ] Tester le parcours : login → voir les créneaux → réserver un créneau → confirmation
- [ ] Vérifier que le créneau apparaît dans "mes créneaux"

### Commit 6.3 : `test(cypress): add E2E tests for membership registration flow`
- [ ] Créer `cypress/e2e/membership/member_can_register.cy.js`
- [ ] Tester : login → accéder à la page d'adhésion → formulaire → confirmation

---

## Résumé des priorités

| Priorité | Étapes | Impact |
|----------|--------|--------|
| 🔴 Haute | Étape 1 (nettoyage) | Qualité du code de test existant |
| 🔴 Haute | Étape 2 (services) | Couverture du cœur métier |
| 🔴 Haute | Étape 5.1-5.2 (infra) | Exécutabilité et organisation |
| 🟡 Moyenne | Étape 3 (entités) | Couverture des modèles |
| 🟡 Moyenne | Étape 4 (contrôleurs) | Détection des régressions HTTP |
| 🟡 Moyenne | Étape 5.3-5.4 (CI) | Automatisation |
| 🟢 Basse | Étape 6 (Cypress) | Couverture E2E |

---

## Métriques cibles

| Métrique | Actuel | Cible après TODO |
|----------|--------|-----------------|
| Fichiers de test PHP | 8 | ~20 |
| Méthodes de test | ~112 | ~150+ |
| Services testés | 5/14 | 5/14 |
| Entités testées | 0/42 | 4/42 (les plus critiques) |
| Contrôleurs testés | 1/43 | Smoke test global + 1-2 détaillés |
| Specs Cypress | 3 | 5 |

---

## Annexe — Problèmes détectés dans le code fonctionnel

> Liste des anomalies rencontrées dans `src/` pendant le travail sur les tests.
> Ces problèmes ne sont **pas corrigés** ici (sauf mention contraire) pour respecter
> la règle « ne pas toucher au code fonctionnel ».

| # | Fichier(s) | Problème | Sévérité | Corrigé ? |
|---|-----------|----------|----------|-----------|
| 1 | `src/Entity/Beneficiary.php` | `openid` et `openid_member_number` : annotation ORM `nullable=true` manquante alors que la migration SQL (`f4eaf753`) crée des colonnes nullables → `doctrine:schema:create` échoue en test | 🔴 Bloquant | ✅ commit `5bd1c5cf` (exception à la règle, bug avéré) |
| 2 | `src/Service/ShiftService.php`, `src/Service/BeneficiaryService.php`, `src/Service/MembershipService.php`, `src/Command/*.php`, `src/EventListener/*.php` | Utilisation systématique de la syntaxe Doctrine dépréciée `'App:Entity'` au lieu de `Entity::class` dans les appels `getRepository()` (~20+ occurrences) | 🟡 Moyenne | ❌ |
| 3 | `src/Service/MembershipService.php:13`, `src/Service/BeneficiaryService.php:13`, `src/Service/ShiftService.php:15` | `use phpDocumentor\Reflection\Types\Array_;` — import inutilisé (3 fichiers) | 🟢 Faible | ❌ |
| 4 | `src/Service/PeriodService.php:53` | Bug de précédence d'opérateurs : `$shifterIsFlying = (... and ...) or (... and ...)` — `or` a une précédence plus basse que `=`, donc la branche `Membership` de flying n'est jamais assignée à `$shifterIsFlying`. Devrait utiliser `||` et `&&` au lieu de `or` et `and`. | 🔴 Bug | ❌ |
