# TC.5 — Commandes non testées

- [x] **TC.5** — Commandes non testées

25 fichiers dans `src/Command/` : 1 classe abstraite (`CsvCommand`), 2 commandes indisponibles en dev (credentials API absents), 22 commandes enregistrées.

**Couverture actuelle : 1/24 commandes réelles testées (4,2 %)**

Seule `app:import:users` (`ImportUsersCommand`) est exercée, via `AdminControllerTest` (4 runs via `@dataProvider`).

### Inventaire complet

| Commande | Fichier | Lignes | DB écrits | Mail | Events | Tests |
|---|---|---|---|---|---|---|
| `app:import:users` | ImportUsersCommand | 252 | ✅ flush | — | ✅ dispatch | ✅ AdminControllerTest |
| `app:shift:generate` | ShiftGenerateCommand | 182 | ✅ persist+flush | — | ✅ dispatch | ❌ |
| `app:anonymize` | AnonymizeDataCommand | 198 | ✅ persist+flush | — | — | ❌ |
| `app:doc` | DoctorCommand | 142 | ✅ flush ×3 | — | — | ❌ |
| `app:user:mass_mail` | SendMassMailCommand | 152 | — | ✅ direct | — | ❌ |
| `app:user:cycle_start` | CycleStartCommand | 78 | — | — | ✅ dispatch | ❌ |
| `app:user:cycle_half` | CycleHalfCommand | 87 | — | — | ✅ dispatch | ❌ |
| `app:shift:send_alerts` | SendShiftAlertsCommand | 117 | — | — | ✅ dispatch | ❌ |
| `app:shift:send_late_shifters` | AmbassadorShiftTimeLogCommand | 101 | — | ✅ direct | — | ❌ |
| `app:shift:reminder` | ShiftReminderCommand | 74 | — | — | ✅ dispatch | ❌ |
| `app:member:close` | CloseMembershipCommand | 78 | ✅ persist+flush | — | — | ❌ |
| `app:shift:free` | FreeReservedShiftsCommand | 74 | ✅ persist+flush | — | — | ❌ |
| `app:helloasso:payment` | HelloassoPaymentCommand | 83 | — | — | ✅ dispatch | ❌ (API ext.) |
| `app:shift:fix_missing_position` | FixShiftMissingPositionCommand | 124 | ✅ DQL UPDATE | — | — | ❌ |
| `app:user:fix_time_log` | FixTimeLogCommand | 74 | ✅ flush | — | — | ❌ |
| `app:user:fix_beneficiary_addresses` | FixBeneficiariesWithoutAddressCommand | 64 | ✅ flush | — | — | ❌ |
| `app:shiftfreelog:init_shift_string_field` | InitShiftFreeLogShiftStringFieldCommand | 60 | ✅ flush | — | — | ❌ |
| `app:user:init_time_log` | InitTimeLogCommand | 82 | ✅ flush | — | — | ❌ |
| `app:user:init_first_shift_date` | InitUsersFirstShiftDateCommand | 56 | ✅ flush | — | — | ❌ |
| `app:beneficiary:randomise` | RandomSortMembersCommand | 114 | — | — | — | ❌ |
| `app:custom-purge` | CustomPurgerCommand | 43 | ✅ purge | — | — | ❌ (indirectement via DatabasePrimer) |
| `app:code:verify_change` | VerifyCodeChangeCommand | 131 | — | ✅ direct | — | ❌ |
| `app:member:update_payments` | UpdateHelloAssoPaymentsCommand | 77 | — | — | — | ❌ (API ext., non dispo dev) |
| `app:code:update_igloohome` | UpdateIgloohomeCodeCommand | 107 | ✅ flush | ✅ direct | — | ❌ (API ext., non dispo dev) |

### Commandes indisponibles en dev

`app:member:update_payments` et `app:code:update_igloohome` échouent à l'enregistrement Symfony en dev : `HelloassoClient::__construct()` et `IgloohomeClient::__construct()` reçoivent `null` pour les credentials. Ces commandes sont instance-spécifiques (Elefan utilise Helloasso, Igloohome est un système de codes d'accès physique).

### Patterns récurrents

- **Toutes les commandes sont des orchestrateurs** : elles appellent des repositories et des services, n'implémentent pas de logique métier propre. Cela réduit la valeur des tests unitaires purs — les tests fonctionnels (CommandTester + DB de test) sont le pattern approprié.
- **Aucune commande n'a de `--dry-run`**, même pour les opérations irréversibles (`app:anonymize`, `app:member:close`, `app:shift:generate`). C'est un manque d'ergonomie opérationnelle (voir EXTRA).
- `CycleStartCommand` a déjà un `--date` option avec le commentaire `//useful for tests` : intention de tester jamais concrétisée.
- `AnonymizeDataCommand` demande une confirmation interactive — nécessite `setInputStream()` pour être testée.
- `SendMassMailCommand` contient une logique de filtrage des destinataires (membres actifs, gelés, non-membres) qui mériterait des tests fonctionnels (vérification BCC recipients).

### Priorisation des quick wins

| Priorité | Commande | Rationale |
|---|---|---|
| 🔴 1 | `app:member:close` | Logique de date critique, état irréversible (`withdrawn=true`), aucune dépendance externe. Pattern : fixture membre expiré → run → assert withdrawn. |
| 🔴 2 | `app:user:cycle_start` | Core métier, `--date` déjà présent, pas de DB write directe (events). Pattern : fixture membres → run avec date fixe → assert events dispatched. |
| 🔴 3 | `app:shift:generate` | Commande la plus complexe (182L, ABCD cycle, pré-réservation). Core business. Pattern : fixtures Period + PeriodPosition → run date → assert Shift créés en base. |
| 🟡 4 | `app:shift:free` | Simple, DB writes directs, logique date-based. Pattern : fixture Shift réservé en passé → run → assert libéré. |
| 🟡 5 | `app:beneficiary:randomise` | Aucune dépendance externe, output-only. Le plus simple à tester. |
| 🔵 6 | `app:doc` | 3 options indépendantes, toutes testables avec fixtures. Valeur surtout régressive. |

---

