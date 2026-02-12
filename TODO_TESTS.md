# TODO — Amélioration des tests

> **Branche** : `improve-tests`  
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

## Étape 1 — Nettoyage et correction des tests existants

### Commit 1.1 : `fix(tests): rename Functionnal → Functional (typo)`
- [ ] Renommer le dossier `tests/Functionnal/` en `tests/Functional/`
- [ ] Mettre à jour le namespace dans `DatabasePrimer.php` : `App\Tests\Functional`
- [ ] Mettre à jour le namespace dans `AdminControllerTest.php` : `App\Tests\Functional\Controller`
- [ ] Mettre à jour le `use` dans `AdminControllerTest.php`

### Commit 1.2 : `refactor(tests): remove debug echo statements from AdminControllerTest`
- [ ] Supprimer les 4 lignes `echo "\n\033[32m ... \033[0m\n";` dans `AdminControllerTest.php`
  (PHPUnit affiche déjà les noms des tests)

### Commit 1.3 : `refactor(tests): fix PSR naming convention in AdminControllerTest`
- [ ] Renommer la variable `$Beneficiaries` en `$beneficiaries` (2 occurrences)

### Commit 1.4 : `refactor(tests): replace deprecated setMethods() with onlyMethods() in ShiftServiceTest`
- [ ] Remplacer `setMethods([...])` par `onlyMethods([...])` (3 occurrences)
- [ ] Remplacer `setMethodsExcept([...])` par l'alternative compatible PHPUnit 9+

### Commit 1.5 : `refactor(tests): use data providers to reduce duplication in AdminControllerTest`
- [ ] Créer un `@dataProvider csvImportProvider` pour factoriser les 2 tests d'import CSV sur base vide
- [ ] Créer un `@dataProvider csvImportWithCommissionsProvider` pour factoriser les 2 tests avec commissions
- [ ] Résultat attendu : passer de 4 méthodes quasi identiques à 2 méthodes paramétrées

### Commit 1.6 : `fix(tests): replace deprecated App:Entity syntax with Entity::class in tests`
- [ ] Remplacer `'App:User'` par `User::class` dans `AdminControllerTest.php`
- [ ] Remplacer `'App:Beneficiary'` par `Beneficiary::class`
- [ ] Remplacer `'App:Membership'` par `Membership::class`
- [ ] Remplacer `'App:Shift'` par `Shift::class` dans `ShiftServiceTest.php`
- [ ] Ajouter les `use` correspondants

---

## Étape 2 — Tests unitaires des services métier

### Commit 2.1 : `test(MembershipService): add unit tests for core membership logic`
- [ ] Créer `tests/Unit/Service/MembershipServiceTest.php`
- [ ] Tester `getRemainder()` : cas cotisation à jour, en retard, pas de dernière registration
- [ ] Tester `canRegister()` : cas autorisé, cas refusé
- [ ] Tester `getExpire()` : cas civil year vs durée fixe
- [ ] Tester `isUptodate()` : cas à jour, expiré
- [ ] Tester `getStartOfCycle()` / `getEndOfCycle()` : avec différents `cycle_type` (abcd)
- [ ] Tester `hasWarningStatus()` : cas warning, pas de warning

### Commit 2.2 : `test(BeneficiaryService): add unit tests for beneficiary logic`
- [ ] Créer `tests/Unit/Service/BeneficiaryServiceTest.php`
- [ ] Tester `getCycleShiftDurationSum()` : avec shifts, sans shifts, multi-cycle
- [ ] Tester `getDisplayNameWithMemberNumberAndStatusIcon()` : différents statuts
- [ ] Tester `hasWarningStatus()` : bénéficiaire en alerte, normal
- [ ] Tester `getStatusIcon()` : les différents cas d'icônes

### Commit 2.3 : `test(ShiftService): add missing unit tests for untested methods`
- [ ] Ajouter dans `ShiftServiceTest.php` (ou nouveau fichier `tests/Unit/Service/ShiftServiceTest.php`) :
- [ ] Tester `remainingToBook()` : cas basique, cas rien à réserver
- [ ] Tester `canBookExtraShift()` : extra shifts autorisés, non autorisés, délai respecté / non
- [ ] Tester `canBookSomething()` : cas flying, cas normal
- [ ] Tester `canBookShift()` : overlap interdit, shift dans le passé
- [ ] Tester `canBookDuration()` : durée suffisante, insuffisante
- [ ] Tester `canFreeShift()` : depuis admin, depuis user, time_log_saving activé
- [ ] Tester `isShiftEmpty()` : shift vide, shift avec shifters
- [ ] Tester `getBookableShiftsCount()` / `isBucketBookable()`

### Commit 2.4 : `test(TimeLogService): add unit tests for time log operations`
- [ ] Créer `tests/Unit/Service/TimeLogServiceTest.php`
- [ ] Tester `initTimeLog()` : création correcte du TimeLog, calcul du temps
- [ ] Tester `initShiftValidatedTimeLog()` : durée correcte, membership associée
- [ ] Tester `initShiftInvalidatedTimeLog()` : temps négatif correctement calculé
- [ ] Tester `initCycleBeginningTimeLog()` : reset correct du compteur
- [ ] Tester `initSavingTimeLog()` : saving avec et sans shift
- [ ] Tester `initCustomTimeLog()` : avec et sans date, avec et sans description

### Commit 2.5 : `test(PeriodService): add unit tests for period logic`
- [ ] Créer `tests/Unit/Service/PeriodServiceTest.php`
- [ ] Tester `getDaysOfWeekArray()` : retour correct des jours
- [ ] Tester `getWeekCycleArray()` : retour correct selon config
- [ ] Tester `hasWarningStatus()` : période avec alerte, sans alerte

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

### Commit 5.2 : `test(infra): add phpunit test suites separation`
- [ ] Modifier `phpunit.xml.dist` pour séparer les suites de tests :
  - `unit` → `tests/Unit`
  - `integration` → `tests/Integration`
  - `functional` → `tests/Functional`
- [ ] Permettre de lancer `./vendor/bin/phpunit --testsuite=unit` (rapide, sans BDD)

### Commit 5.3 : `ci: add GitHub Actions workflow for running tests`
- [ ] Créer `.github/workflows/tests.yml`
- [ ] Configurer PHP + extensions nécessaires
- [ ] Lancer `composer install`
- [ ] Lancer `./vendor/bin/phpunit --testsuite=unit` (sans BDD, toujours exécutable)
- [ ] Optionnel : lancer aussi les tests d'intégration avec une base SQLite

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
| Fichiers de test PHP | 2 | ~20 |
| Méthodes de test | ~15 | ~100+ |
| Services testés | 1/14 | 5/14 |
| Entités testées | 0/42 | 4/42 (les plus critiques) |
| Contrôleurs testés | 1/43 | Smoke test global + 1-2 détaillés |
| Specs Cypress | 3 | 5 |
