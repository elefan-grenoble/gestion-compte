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
- [x] Créer `tests/Unit/Entity/MembershipTest.php`
- [x] Tester constructeur, member number, getDisplayMemberNumber, __toString
- [x] Tester beneficiaries: add/remove, setMainBeneficiary (also adds), fallback to first, getBeneficiariesWithMainInFirstPosition, getMemberNumberWithBeneficiaryListString
- [x] Tester withdrawn: setWithdrawn(false) clears date and by
- [x] Tester frozen/frozenChange/flying getters/setters
- [x] Tester registrations: add/remove, getLastRegistration, hasValidRegistrationBefore
- [x] Tester timeLogs: getShiftTimeLogs (excludes saving), getSavingTimeLogs, getShiftTimeCount (with before filter), getSavingTimeCount
- [x] Tester notes/proxies (reflection pour init collections non initialisées dans le constructeur)
- [x] Tester getTmpToken (deterministic, different keys), shift exemptions (isCurrentlyExemptedFromShifts, getCurrentMembershipShiftExemptions)
- [x] 37 tests, 63 assertions

### Commit 3.2 : `test(Entity/Shift): add unit tests for shift entity`
- [x] Créer `tests/Unit/Entity/ShiftTest.php`
- [x] Tester constructor defaults, start/end setters, getDuration (3h et 90min), getIntervalCode
- [x] Tester temporal: getIsPast, getIsCurrent, getIsPastOrCurrent, getIsFuture, getIsUpcoming, isBefore
- [x] Tester booking: booker, bookedTime, shifter, free() clears all
- [x] Tester validate/invalidateShiftParticipation, formation/job, locked/fixe, lastShifter
- [x] Tester isFirstByShifter (no shifter, true, false), getTmpToken, display methods, createdAt
- [x] 41 tests, 48 assertions

### Commit 3.3 : `test(Entity/Beneficiary): add unit tests for beneficiary entity`
- [x] Créer `tests/Unit/Entity/BeneficiaryTest.php`
- [x] Tester constructor, firstname (ucfirst+strtolower), lastname (strtoupper)
- [x] Tester display names: getDisplayName, getDisplayNameWithMemberNumber, getPublicDisplayName, __toString
- [x] Tester getMemberNumber (delegates to membership, null without), isMain (true/false)
- [x] Tester isNew (threshold <= 3), flying, commissions (owned filter), formations, shifts
- [x] Tester swipeCards (reflection init, getEnabledSwipeCards filter), user/email delegation, phone, openId
- [x] 32 tests, 49 assertions

### Commit 3.4 : `test(Entity/ShiftBucket): add unit tests for shift bucket entity`
- [x] Créer `tests/Unit/Entity/ShiftBucketTest.php`
- [x] Tester constructor, addShift/addShifts (skips non-Shift), getFirst
- [x] Tester delegation: getJob, getStart, getEnd, getDuration, getIntervalCode
- [x] Tester getShifterCount (0, partial, all), removeEmptyShift (multiple vs single)
- [x] Tester getSortedShifts (null when empty, collection when not)
- [x] Tester canBookInterval (no booking, already booked, already reserved, different interval)
- [x] Tester statics: compareShifts, shiftIntersectFormations, filterByFormations, createShiftFilterCallback
- [x] 31 tests, 46 assertions

---

## Étape 4 — Tests fonctionnels des contrôleurs critiques

### Commit 4.1 : `test(functional): add smoke tests for all public routes` ✅ DONE
- [x] Créer `tests/Functional/Controller/SmokeTest.php`
- [x] Tester que toutes les routes publiques répondent en 200 ou 302 (redirection login)
- [x] Couvrir au minimum : `/`, `/login`, `/event/`, `/schedule`
- [x] Utiliser un `@dataProvider` avec la liste des URLs

### Commit 4.2 : `test(functional): add authenticated route tests` ✅ DONE
- [x] Créer `tests/Functional/Controller/AuthenticatedSmokeTest.php` (intégré dans `SmokeTest.php`)
- [x] Créer un helper pour simuler un utilisateur connecté (loginAs)
- [x] Tester les routes protégées en tant qu'admin : `/admin/`, `/admin/members/`
- [x] Tester les routes protégées en tant qu'utilisateur : `/member/`, `/shift/`
- [x] Vérifier les codes de retour (200, 403 pour accès interdit)

### Commit 4.3 : `test(functional): add MembershipController tests`
- [x] Créer `tests/Functional/Controller/MembershipControllerTest.php`
- [x] Tester l'affichage de la page membership (`/member/find_me`, `/member/office_tools`, `/member/emails_csv`)
- [x] Tester la soumission de formulaire (`/member/find_me` avec numéro invalide)
- [x] Tester les cas d'erreur (accès anonyme, accès regular user → 403)
- [x] Documenter les 13 routes bloquées par `new Session()` (annexe #7) via `markTestSkipped`
- [x] Ajouter les routes testables au SmokeTest (`find_me`, `office_tools`, `emails_csv`)

---

## Étape 5 — Infrastructure et qualité des tests

### Commit 5.1 : `test(infra): add base TestCase classes with shared helpers` ✅ DONE
- [x] Créer `tests/Functional/FunctionalTestCase.php` : étend `DatabasePrimer` avec un helper `loginAs($username)`
- [x] Migrer `SmokeTest`, `MembershipControllerTest` et `AdminControllerTest` vers `FunctionalTestCase`
- [x] Supprimer les `loginAs()` dupliqués dans chaque fichier de test

### Commit 5.2 — DONE (étape 0.2) : séparation des test suites PHPUnit

### Commit 5.3 : `ci: improve GitHub Actions workflow` ✅ DONE
Améliorations du fichier `.github/workflows/ci.yaml` existant :
- [x] Ajouter `push:` au trigger pour exécuter la CI à chaque push, quelle que soit la branche
- [x] Fixer `php-version: '7.4'` en dur dans les jobs `phpStan`, `symfony-tests`, `cypress-tests`
- [x] Ajouter un job `fast-tests` sans BDD pour les suites `unit` + `integration`
- [x] Supprimer le service MariaDB inutile du job `phpStan`
- [x] Restreindre `symfony-tests` à la suite `functional` uniquement (besoin DB)
- [x] Uploader les screenshots Cypress en cas d'échec (retention: 7 jours)
- [x] Renommer les steps pour plus de clarté
- [x] Cacher `node_modules` / le binaire Cypress pour accélérer le job `cypress-tests`

### Commit 5.4 : `test(infra): add code coverage configuration` ✅ DONE
- [x] Vérifier que la section `<coverage>` dans `phpunit.xml.dist` est correctement configurée
- [x] Exclure `src/Migrations`, `src/DataFixtures`, `src/Kernel.php` de la couverture
- [x] Ajouter les scripts `composer test`, `test-unit`, `test-functional`, `test-coverage` dans `composer.json`
- [x] Seuil de couverture : pas de minimum imposé pour l'instant (xdebug non installé en CI)

---

## Étape 6 — Amélioration des tests Cypress (E2E)

### Commit 6.1 : `test(cypress): stabilize freeze/unfreeze test` ✅ DONE
- [x] Filtrer les membres déjà gelés avec `.not('.frozen')` pour éviter les faux positifs
- [x] Cibler `.collapsible-header` directement pour ouvrir le collapsible Materialize
- [x] Ajouter `.should('be.visible')` avant chaque clic sur bouton modal (attente animation)
- [x] Remplacer `cy.contains('gelé')` fragile par des assertions structurelles sur le header et le badge
- [x] Supprimer le regex lookbehind `(?<!dé)gelé` non fiable

### Commit 6.2 : `test(cypress): add custom commands in support/commands.js` ✅ DONE
- [x] Créer `cy.login(username, password)` dans `cypress/support/commands.js`
- [x] Créer `cy.loginKeycloak(username, password)` (utilise `Cypress.env('KEYCLOAK_URL')` automatiquement)
- [x] Adapter les 3 tests existants pour utiliser `cy.login()` / `cy.loginKeycloak()` au lieu des imports

### Commit 6.3 : `test(cypress): add E2E tests for member shift booking flow` ✅ DONE
- [x] Créer `cypress/e2e/shift/member_can_book_shift.cy.js`
- [x] Tester le parcours : login → page booking → trouver un créneau libre → réserver → confirmation
- [x] Vérifier la redirection vers la page d'accueil avec message de succès
- [x] Ajouter le script npm `cy:test:shift` et l'intégrer dans le job CI `cypress-tests`

### Commit 6.4 : `test(cypress): add E2E tests for membership registration flow` ✅ DONE
- [x] Créer `cypress/e2e/membership/member_can_register.cy.js`
- [x] Tester : login → accéder à la page d'adhésion → formulaire → confirmation

---

## Résumé des priorités

| Priorité | Étapes | Impact |
|----------|--------|--------|
| 🔴 Haute | Étape 1 (nettoyage) | Qualité du code de test existant |
| 🔴 Haute | Étape 2 (services) | Couverture du cœur métier |
| 🔴 Haute | Étape 5.1-5.2 (infra) | Exécutabilité et organisation |
| 🟡 Moyenne | Étape 3 (entités) | Couverture des modèles |
| 🟡 Moyenne | Étape 4 (contrôleurs) | Détection des régressions HTTP |
| 🟡 Moyenne | Étape 5.3 (CI) ✅ / 5.4 (coverage) | Automatisation |
| 🟢 Basse | Étape 6 (Cypress) | Couverture E2E |

---

## Métriques cibles

| Métrique | Initial | Actuel | Cible après TODO |
|----------|---------|--------|------------------|
| Fichiers de test PHP | 2 | 13 | ~20 |
| Tests PHPUnit (exécutés) | 16 | 108 (dont 13 skipped) | ~120+ |
| Services testés | 1/14 | 5/14 | 5/14 |
| Entités testées | 0/42 | 4/42 | 4/42 (les plus critiques) |
| Contrôleurs testés | 1/43 | Smoke (70 routes) + MembershipController | Smoke + 1-2 détaillés |
| Specs Cypress | 3 | 5 | 5 |
| Jobs CI | 3 | 4 (+ fast-tests) | 4 |

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
| 5 | `templates/layoutlight.html.twig:9` | `{% include "_partial" %}` inclut un répertoire au lieu d'un fichier. Devrait être `{% include "_partial/style_config.html.twig" %}` comme dans `layout.html.twig`. Provoque un 500 sur toutes les pages utilisant `layoutlight` (widgets). | 🔴 Bug | ✅ corrigé (exception à la règle, bug avéré) |
| 6 | `templates/openinghour/_partial/widget.html.twig:8` | `{% if kind_title %}` accède à `openingHourKind.name` sans vérifier que `openingHourKind` n'est pas `null`. Le contrôleur passe `null` quand aucun `opening_hour_kind_id` n'est fourni → 500. Devrait être `{% if kind_title and openingHourKind %}`. | 🔴 Bug | ✅ corrigé (exception à la règle, bug avéré) |
| 7 | `src/Controller/*.php` (quasi tous) | Utilise `new Session()` (~120 occurrences) au lieu de `$request->getSession()`. Cela instancie un `NativeSessionStorage` qui contourne la configuration `session.storage.mock_file` de l'env test → 500 en test ("headers already sent"). Les routes GET qui passent par du code appelant `new Session()` (ex: `/` authentifié, `/codes/`) sont en erreur. **Les smoke tests de ces routes sont désactivés en attendant la correction.** | 🟡 Moyenne | ❌ |
| 8 | `src/Controller/MembershipController.php:454` | `findUserHelpAction` fait un `render('default/find_user_number.html.twig')` mais le template n'existe pas → 500 systématique sur `/member/help_find_user`. | 🔴 Bug | ❌ |
| 9 | `src/Controller/MembershipController.php:463` | `findUserAction` contient `die($request->getName())` — code de debug/mort qui retourne une réponse vide → route `/member/find_user` inutilisable. | 🔴 Bug | ❌ |
