# DC.3 — Méthodes publiques mortes (si P0.3 = upgrade validé)

- [x] **DC.3** — Méthodes publiques mortes (si P0.3 = upgrade validé)

**Outil** : `shipmonk/dead-code-detector` 1.2.0, `phpstan-dead-code.neon` (providers Symfony + Doctrine + Twig activés, `paths: src/`, `ignoreErrors` sur Controller + EventListener + Form + Security).

**Limitation critique — annotations PHP 7 non supportées :**
Le `SymfonyUsageProvider` ne détecte que les attributs PHP 8 natifs (`#[Route]`, `#[AsEventListener]`), pas les annotations docblock (`@Route`, tags `kernel.event_listener` en YAML). Conséquence : toutes les actions de controller et méthodes d'event listener sont "mortes" dans le graphe interne, créant une cascade de faux positifs sur leurs callees. Le `TwigUsageProvider` ne détecte pas les appels de services via variable Twig (`shift_service.method()`), uniquement les fonctions/filtres d'extensions Twig. **DC.3 devra être refait post-migration PHP 8 avec attributs natifs.**

**Upgrade PHP 8.1 du container :** effectué. 350 tests passent. Seuls nouveaux warnings : `strlen(null)` dans `FOSUserBundle\PasswordUpdater` (dépréciations PHP 8.1, non bloquantes — seront fatales en PHP 9, confirme DEP.2). Modification `netcat` → `netcat-openbsd` dans le Dockerfile (Debian Bookworm).

---

### Findings haute confiance (grep confirmé — non appelés depuis PHP ni Twig)

| Méthode | Gravité | Notes |
|---------|---------|-------|
| `Helper\Html2Pdf::create` | 🟠 Mort | Déjà confirmé DC.2. Classe entière inutilisée. |
| `Helper\Html2Pdf::generatePdf` | 🟠 Mort | Idem. |
| `Helper\SwipeCard::generateCode` | 🟡 Mort | Déjà confirmé DC.1. Variable locale inutile. |
| `BeneficiaryRepository::findFromAutoComplete` | 🟡 Probablement mort | Grep vide dans src/ + templates/. Les controllers utilisent `getDoctrine()->getRepository()` directement. |
| `CommissionRepository::findByString` | 🟡 Probablement mort | Aucun appelant trouvé. |
| `EventRepository::findAllDisplayedHome` | 🟡 Probablement mort | Aucun appelant trouvé. |
| `PeriodPositionRepository::findByBeneficiary` | 🟡 Probablement mort | Aucun appelant trouvé. |
| `ShiftRepository::findFirst` | 🟡 Probablement mort | Aucun appelant trouvé. |
| `ShiftRepository::findReservedBefore` | 🟡 Probablement mort | Aucun appelant trouvé. |
| `BeneficiaryService::getAutocompleteBeneficiaries` | 🟡 Probablement mort | Aucun appelant trouvé dans src/ ni templates/. |
| `MembershipService::getAutocompleteMemberships` | 🟡 Probablement mort | Aucun appelant trouvé. |
| `OpeningHourService::isClosed` | 🟡 Probablement mort | Templates utilisent `isOpen`, pas `isClosed`. Aucun appelant PHP. |
| `FixtureGroupConsoleService::setInput` | 🟡 Probablement mort | Aucun appelant trouvé. |

### Faux positifs identifiés (cascade depuis les controllers)

Les méthodes suivantes sont flagguées mais **réellement utilisées** — elles sont appelées depuis `isShiftBookable()` (lui-même appelé depuis ShiftController + ShiftVoter, mais "mort" dans le graphe interne à cause des false positives des controllers) :
- `ShiftService::canBookShift`, `::isShiftEmpty`, `::canBookDuration`, `::canBookExtraShift`

Les méthodes suivantes sont appelées **depuis des templates Twig via variable de service** (`shift_service.method()`) — non détectées par le TwigUsageProvider :
- `ShiftService::getBeneficiaryShiftCount`, `::getBeneficiaryShiftFreedCount`, `::remainingToBook`, `::shiftTimeByCycle`, `::getMinimalShiftDuration`
- `MembershipService::getShiftFreeLogs`, `::getPeriodPositionFreeLogs`
- `OpeningHourKindService::hasEnabled`, `PeriodService::getDaysOfWeekArray`, `BeneficiaryService::hasWarningStatus`

Les méthodes d'entités (`PeriodPositionFreeLog::*`, `ShiftFreeLog::*`, `ShiftAlert::*`) sont utilisées via notation Twig pointée (`entity.property`) — même limitation TwigUsageProvider.

Les méthodes `ShiftService::canBookExtraShiftBucket`, `::getBeneficiariesWhoCanBook`, `::getBeneficiariesWhoCanBookForCycle`, `::getFirstBookable`, `::getShiftsForBeneficiary`, `::removeEmptyShift` nécessitent une vérification manuelle approfondie. Probables faux positifs liés à la cascade, mais non confirmés.

→ Les findings haute confiance alimenteront **DC.4** (consolidation TODO).

