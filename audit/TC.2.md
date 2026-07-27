# TC.2 — Controllers sans test fonctionnel

- [x] **TC.2** — Controllers sans test fonctionnel

Croiser `ls src/Controller/` avec `ls tests/Functional/`. Lister les controllers sans couverture → TODO.

**Méthode** : inventaire `src/Controller/` (43 fichiers) croisé avec `tests/Functional/` (3 classes de test) et `debug:router` pour confirmer les routes couvertes par `SmokeTest.php`.

---

### Vue d'ensemble

| Catégorie | Controllers | % |
|-----------|------------|---|
| Classe de test dédiée | 2 | 4.7 % |
| SmokeTest uniquement (HTTP status, aucune logique métier) | 28 | 65.1 % |
| Couverture nulle (ni smoke, ni dédié) | 13 | 30.2 % |
| **Total** | **43** | **100 %** |

**Tests fonctionnels existants** :
- `AdminControllerTest.php` — couvre uniquement l'import CSV via commande Symfony (pas de route HTTP directe)
- `MembershipControllerTest.php` — couvre partiellement : `find_me`, `office_tools`, `emails_csv`, `member_show` (GET uniquement), restrictions de méthodes HTTP (405). Les actions avec mutation d'état (`freeze`, `withdraw`, `join`, `flying`) n'ont aucun test POST.
- `SmokeTest.php` — vérifie les codes de retour HTTP (200/302/403/405) pour les routes principales. Aucune assertion sur le contenu, le comportement métier, les formulaires ou les mutations d'état.

---

### Groupe A — Couverture nulle (0 % de lignes) — 13 controllers

Confirmé par TC.1 pour les items marqués *(TC.1)* ; les autres sont vérifiés par l'absence dans tous les providers de test et `debug:router`.

#### 🔴 Critique — findings sécurité actifs sans filet de test

| Controller | Lignes | Findings | Routes clés |
|-----------|--------|---------|-------------|
| `BeneficiaryController` | 115+ | SEC.2.1 (account takeover via `setEmailAction` sans auth) *(TC.1)* | GET/POST `/beneficiary/*` |
| `SwipeCardController` | 154+ | SEC.1.13 (auth badge par GET), SEC.3.5 (4 routes sans CSRF) *(TC.1)* | GET/POST `/swipe_card/*`, `/sw/*` |
| `UserController` | 159+ | SEC.2.5 (bootstrap admin sans auth) *(TC.1)* | GET/POST `/user/install_admin`, `/user/*` |
| `ShiftController` | 439+ | SEC.1.3 (`contact_form` sans auth), SEC.1.5 (`accept`/`reject` voter seul) — 4.8 % *(TC.1)* | POST `/shift/{id}/book`, `/shift/{id}/free`, `/shift/{id}/accept` |

#### 🟠 Important — logique métier ou intégration tierce sans test

| Controller | Lignes | Findings | Routes clés |
|-----------|--------|---------|-------------|
| `CommissionController` | ~80 | SEC.2.3 (fatal error anonyme + `$_POST` direct) | POST `/commissions/{id}/add_beneficiary`, `remove_beneficiary` |
| `AmbassadorController` | 185+ | 0 % *(TC.1)* | GET/POST `/ambassador/*` |
| `MailController` | 117+ | 0 % *(TC.1)* | GET/POST `/admin/mail/*` |
| `HelloassoController` | 116+ | 0 % *(TC.1)*, paiement Helloasso | GET/POST `/admin/helloasso/*`, `/helloassoNotify` |
| `TimeLogController` | ~60 | SEC.6 (flash `<>` debug artifact) | GET/POST `/time_log/*` |
| `NoteController` | ~60 | — | GET/POST/DELETE `/note/note/*` |
| `AdminMembershipShiftExemptionController` | 113+ | 0 % *(TC.1)* | GET/POST `/admin/membershipshiftexemption/*` |
| `AdminPeriodPositionFreeLogController` | ~60 | AP.6 (null guard manquant dans `PeriodPositionFreeLogService`) | GET/POST `/admin/period/positionfreelogs/` |

#### 🟡 Mineur

| Controller | Lignes | Note |
|-----------|--------|------|
| `ApiController` | ~80 | Endpoints OAuth API (`/api/oauth/user`, `/api/v4/user`), 0 % |
| `OAuthController` | ~40 | Flow OAuth OIDC (`/oauth/login`, `/oauth/callback`, `/oauth/logout`) — instance-specific (Scopeli), difficile à tester sans stub Keycloak |

---

### Groupe B — SmokeTest uniquement — 28 controllers

Ces controllers sont "verts" au sens HTTP mais n'ont aucun test de logique métier, de formulaire, ou de mutation d'état.

**Sous-groupe prioritaire (logique métier ou findings actifs) :**

| Controller | Couverture TC.1 | Priorité test |
|-----------|----------------|--------------|
| `BookingController` | 13.7 % | 🟠 — logique de réservation complexe, action `showBucketAction` publique (SEC.1.12) |
| `RegistrationsController` | smoke | 🟠 — SQL brut avec `$connection->prepare()` (AP.3.1) |
| `CardReaderController` | smoke | 🟠 — POST `/card_reader/check` (SEC.2.2) couvert en smoke GET uniquement |
| `CommissionController` | smoke | 🟠 — fatal error anonyme (SEC.2.3) non testée par le smoke |
| `CodeController` | ~0 % | 🟡 — 1 seul test (redirect), logique OIDC non testée |
| `AdminPeriodController` | smoke | 🟡 — génération de créneaux (AP.1.2f, AP.8.3), seulement GET `/admin/period/` en smoke |
| `EventController` | smoke | 🟡 — recherche bénéficiaire avec `expr()->literal()` (SEC.4.1) |

**Sous-groupe CRUD admin (faible priorité — logique simple, accès restreint `ROLE_ADMIN`) :**
`AdminClosingExceptionController`, `AdminEventController`, `AdminEventKindController`, `AdminOpeningHourController`, `AdminOpeningHourKindController`, `AdminShiftExemptionController`, `AdminShiftFreeLogController`, `ClientController`, `FormationController`, `JobController`, `SocialNetworkController`, `ServiceController`, `DynamicContentController`, `EmailTemplateController`, `TaskController`, `PeriodController`, `ProcessUpdateController`, `OpeningHourController`, `ClosingExceptionController`, `WidgetController`, `DefaultController`

---

### Résumé et priorisation

| Priorité | Action | Controllers | Effort |
|----------|--------|------------|--------|
| 🔴 Immédiat | Tests fonctionnels couvrant les failles SEC actives | `BeneficiaryController`, `SwipeCardController`, `UserController` | M |
| 🔴 Immédiat | Tests POST pour `ShiftController` (book, free, contact_form) | `ShiftController` | M |
| 🟠 Court terme | Tests d'autorisation pour les actions publiques identifiées | `CommissionController`, `CardReaderController` (POST check), `HelloassoController` | S–M |
| 🟠 Court terme | Tests logique métier | `AmbassadorController`, `MailController`, `TimeLogController`, `NoteController` | S chacun |
| 🟡 Moyen terme | Tests POST pour MembershipController (actions avec mutation) | `MembershipController` (compléter) | S |
| 🟡 Moyen terme | Tests logique métier smoke-only prioritaires | `BookingController`, `RegistrationsController` | M chacun |
| 🟢 Backlog | CRUD admin simples | 21 controllers smoke-only CRUD | L global |

→ **TODO SYN.2** — catégorie Tests : prioriser dans l'ordre du tableau ci-dessus. Les 🔴 sont des tests à écrire avant tout correctif des failles SEC correspondantes.

