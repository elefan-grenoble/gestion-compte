# TC.3 — Services sans test unitaire

- [x] **TC.3** — Services sans test unitaire

Croiser `src/Service/` avec `tests/Unit/`. Lister les gaps → TODO.

**Méthode** : inventaire exhaustif de `src/Service/` (15 fichiers) croisé avec `tests/Unit/Service/` (4 fichiers) et `tests/Integration/Service/` (1 fichier). Lecture du contenu de chaque service non couvert pour évaluer testabilité et valeur métier.

---

### Vue d'ensemble

| Catégorie | Services | % |
|-----------|----------|---|
| Tests unitaires existants | 5 | 33 % |
| Sans aucun test unitaire | 10 | 67 % |
| **Total** | **15** | **100 %** |

**Services couverts** :
- `BeneficiaryService` → `tests/Unit/Service/BeneficiaryServiceTest.php`
- `MembershipService` → `tests/Unit/Service/MembershipServiceTest.php`
- `PeriodService` → `tests/Unit/Service/PeriodServiceTest.php`
- `ShiftService` → `tests/Unit/Service/ShiftServiceUnitTest.php` + `tests/Integration/Service/ShiftServiceTest.php`
- `TimeLogService` → `tests/Unit/Service/TimeLogServiceTest.php`

---

### Groupe A — Testables, valeur métier haute (🔴 priorité)

#### `MailerService` (144L) — logique pure extractible

| Méthode | Testabilité | Valeur |
|---------|------------|--------|
| `isTemporaryEmail(string $email): bool` | **Pure logic**, aucun mock requis. Regex sur `$baseDomain`. | Critique — détermine si un email est temporaire ; bug silencieux non détectable sans test |
| `getAllowedEmails(): array` | **Pure transformation** du tableau de config `$sendableEmails`. Aucun mock requis. | Moyen — format d'affichage dans le select "from" des mails |
| `sendConfirmationEmailMessage()` / `sendResettingEmailMessage()` | Dépend de `$mailer`, `$router`, `$templating`, `$entity_manager`. Test d'intégration seulement. | — |

→ Tests unitaires faisables **sans aucun mock** pour `isTemporaryEmail` et `getAllowedEmails` : instancier `MailerService` avec les 3 paramètres scalaires + des dummies pour les dépendances lourdes inutilisées par ces deux méthodes.

#### `ShiftFreeLogService` (50L) — pattern identique à `TimeLogService`

| Méthode | Testabilité | Valeur |
|---------|------------|--------|
| `generateShiftString(Shift $shift): string` | Pure string : `$shift->getJob()->getName() . ' - ' . $shift->getDisplayDateSeperateTime()`. Mock `Shift` + `Job`. | Moyen — string stockée dans `ShiftFreeLog::shiftString`, visible dans les logs d'audit |
| `initShiftFreeLog(Shift, Beneficiary, bool, ?string): ShiftFreeLog` | Suit exactement le pattern `TimeLogService::initTimeLog()`. Mock `TokenStorage`, `RequestStack`. | Haut — construit le log d'audit de libération de créneau ; couvrir le chemin user auth vs anonyme |

→ Pattern déjà éprouvé dans `TimeLogServiceTest`. Effort d'écriture minimal.

#### `PeriodPositionFreeLogService` (49L) — miroir de `ShiftFreeLogService`

| Méthode | Testabilité | Valeur |
|---------|------------|--------|
| `generatePeriodPositionString(PeriodPosition): string` | Délègue à `(string) $periodPosition` (`__toString`). Mock `PeriodPosition`. | Faible — wrapper trivial |
| `initPeriodPositionFreeLog(PeriodPosition, Beneficiary, ?DateTime): PeriodPositionFreeLog` | Pattern identique à `initShiftFreeLog`. | Haut — log d'audit de libération de position |

→ Copier-adapter `ShiftFreeLogServiceTest` : effort < 30 min.

---

### Groupe B — Testables, valeur moyenne (🟡)

#### `OpeningHourService` (52L) — logique métier avec dépendance EM

`isOpen(\DateTime $date)` orchestre deux appels EM + un `array_filter` sur les plages horaires. `isClosed()` est l'inverse.

Testable avec mocks du repository `OpeningHour` et `ClosingException`. Trois chemins distincts à couvrir :
1. Aucun `OpeningHour` pour ce jour → `false`
2. `OpeningHour` présent mais hors plage horaire → `false`
3. `OpeningHour` dans plage + pas de `ClosingException` → `true`
4. `OpeningHour` dans plage + `ClosingException` active → `false`

→ Valeur réelle : `isOpen()` est appelé dans les vues et controllers pour conditionner l'affichage et l'accès. Un bug sur les edge-cases horaires (exactement à l'heure d'ouverture/fermeture) serait invisible sans test.

#### `FixtureGroupConsoleService` (27L) — helper de fixtures

`getGroups()` retourne `$this->input->getOption('group')` ou `[]`. Trivial à tester, mais c'est un helper de commande de fixtures (infrastructure de test elle-même). **Valeur quasi nulle** — skip justifié.

#### `OpeningHourKindService` (24L) — délégation EM pure

`hasEnabled()` → `count($em->getRepository(OpeningHourKind::class)->findEnabled()) > 0`. Une ligne effective. Testable avec mock EM, mais valeur marginale : si le repository retourne [] ou [kind], le test ne teste que le mock, pas la logique métier.

---

### Groupe C — Non unitairement testables / valeur faible (🔵 skip ou intégration)

| Service | Raison | Alternative |
|---------|--------|-------------|
| `EventService` (47L) | 2 méthodes = 100% query builder delegation vers `ProxyRepository`. Aucune logique pure. | Test d'intégration avec vraie DB |
| `PeriodFormHelper` (87L) | Couplé `FormBuilder` + `EntityType` + `JobRepository`. `createFilterForm()` = `getFilterForm()` + setData. Aucune logique extractible. | Couvert indirectement par les tests fonctionnels des controllers qui l'utilisent |
| `Picture/BasePathPicture` (31L) | Délégation pure à `UploaderHelper::asset()` puis `CacheManager::getBrowserPath()`. La méthode `getPicturePath` n'a aucune logique propre. | N/A — le mock ne teste que les mocks |
| `SearchUserFormHelper` (749L) | God class avec `ContainerInterface` injection. Construit des form builders et des query builders Doctrine. Pas de logique pure isolable. Déjà couverte à ~48% via les smoke tests (TC.1). | Refactoring préalable requis (extraire la logique pure) avant tout test unitaire utile |

---

### Résumé et priorisation

| Priorité | Action | Service | Effort estimé |
|----------|--------|---------|--------------|
| 🔴 Immédiat | Tests unitaires `isTemporaryEmail` + `getAllowedEmails` | `MailerService` | XS — aucun mock |
| 🔴 Immédiat | Tests `generateShiftString` + `initShiftFreeLog` | `ShiftFreeLogService` | S — pattern TC.1 |
| 🔴 Immédiat | Tests `initPeriodPositionFreeLog` | `PeriodPositionFreeLogService` | XS — copier-adapter |
| 🟡 Moyen terme | Tests `isOpen()` avec 4 chemins | `OpeningHourService` | S — 3 mocks |
| 🔵 Skip | Délégation pure, valeur nulle en unitaire | `EventService`, `OpeningHourKindService`, `PeriodFormHelper`, `BasePathPicture`, `FixtureGroupConsoleService` | — |
| 🔵 Post-refactoring | God class, refactoring requis | `SearchUserFormHelper` | — |

→ **TODO SYN.2** — ajouter en catégorie Tests : `MailerService::isTemporaryEmail`, `ShiftFreeLogService`, `PeriodPositionFreeLogService` en priorité 🔴 immédiat ; `OpeningHourService::isOpen` en 🟡.

