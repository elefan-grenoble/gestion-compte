# SPEC.3 — Spec : Créneaux (Shifts)

- [x] **SPEC.3** — Spec : Créneaux (Shifts)

Sources lues : `ShiftController` (828 l.), `BookingController` (718 l.), `PeriodController` (61 l.), `CardReaderController` (124 l.), `TimeLogController` (102 l.), `AdminPeriodController` (545 l.), `AdminMembershipShiftExemptionController` (230 l.), `AdminShiftExemptionController` (127 l.), `AdminShiftFreeLogController` (148 l.), `AdminPeriodPositionFreeLogController` (118 l.) ; `ShiftService` (596 l.), `MembershipService` (239 l.) ; entités `Shift` (663 l.), `Period` (515 l.), `PeriodPosition` (400 l.), `TimeLog` (321 l.), `MembershipShiftExemption` (313 l.) ; commandes `ShiftGenerateCommand`, `CycleStartCommand`, `CycleHalfCommand`, `FreeReservedShiftsCommand`, `FixShiftMissingPositionCommand` ; tests `ShiftTest`, `ShiftBucketTest`, `ShiftServiceUnitTest`, `ShiftServiceTest`.
Croisé avec : AP.1 (firstShiftDate dupliqué, createShift*Form ×5, generateShifts via web), D.5 (shift_cycle TODO, use_fly_and_fixed copy), DC.3 (faux positifs ShiftService), SEC.2.2 + SEC.3.3 (card_reader/check), PERF (N+1 buckets).

---

## SPEC.3 — Créneaux (Shifts)

### Vocabulaire essentiel (lever les ambiguïtés du domaine)

| Terme | Entité / objet | Rôle |
|-------|----------------|------|
| **Créneau** | `Shift` | Occurrence temporelle d'une tâche bénévole (date+heure précise). Peut être libre, réservé, pré-réservé, validé. |
| **Slot / Créneau type** | `Period` | Modèle récurrent hebdomadaire (jour de semaine, horaire, job). Sert à générer les `Shift` futurs. |
| **Poste** | `PeriodPosition` | Un poste au sein d'un `Period` : un `Period` → N `PeriodPosition` → N bénévoles simultanés possibles. Porte optionnellement un `weekCycle` (A/B/C/D). |
| **Bucket** | `ShiftBucket` (non persisté) | Groupe de créneaux partageant le même horaire (start+end) et le même `Job`. Abstractions UI calculées à la volée. |
| **Shifter** | `Beneficiary` | Le bénéficiaire qui réalise le créneau. |
| **Booker** | `User` | L'utilisateur qui a enregistré la réservation (peut différer du shifter). |
| **TimeLog** | `TimeLog` | Journal d'entrées de temps : validation de créneau, début de cycle, ajustements manuels, épargne. |
| **Cycle** | (calculé) | Période de bénévolat de 28 jours (hardcodé). Deux modes : `abcd` (synchrone, toute la coop) et `firstShiftDate` (propre à chaque membre). |
| **Exemption** | `MembershipShiftExemption` | Plage de dates où un membre est dispensé de bénévolat (maladie, congé…). |
| **Créneau fixe / Créneau volant** | `Shift.fixe` | Si `use_fly_and_fixed=true` : "fixe" = même bénévole cycle après cycle via `PeriodPosition.shifter` ; "volant" = à réserver librement chaque cycle. |

---

**Acteurs** :
- **Anonyme** : widget shift public, schedule.
- **ROLE_USER** (adhérent connecté) : réservation de créneau, annulation (avec règles), acceptation/rejet pré-réservation, contact co-bénévoles, vue planning (`period_index`).
- **ROLE_SHIFT_MANAGER** : gestion admin créneaux et périodes (réservation, libération, validation de présence, création, génération, exemptions, logs de libération).
- **ROLE_ADMIN** : suppression de buckets, périodes, positions, génération de créneaux, copie de périodes.
- **ROLE_SUPER_ADMIN** : suppression de `TimeLog`.
- **Système / Lecteur de badge** : validation de présence automatique via badge NFC (`card_reader_check`).

**Instances** :
- **Toutes** : réservation membre, validation, exemptions, TimeLog.
- **`use_fly_and_fixed=true`** (Elefan probable) : créneaux fixes/volants, `PeriodPosition.shifter`, interdiction d'annuler les créneaux fixes (`fly_and_fixed_allow_fixed_shift_free`), vue semaine type enrichie.
- **`cycle_type=abcd`** (Elefan probable) : semaines ABCD synchronisées sur le calendrier ISO, positions filtrées par `weekCycle`.
- **`use_time_log_saving=true`** (instance-specific) : compteur épargne, règles d'annulation avec délai min et vérification de solde.
- **`reserve_new_shift_to_prior_shifter=true`** (instance-specific) : pré-réservation du nouveau créneau pour l'ancien bénévole du cycle précédent.
- **`newUserStartAsBeginner=true`** (instance-specific) : blocage des nouveaux bénévoles sur les buckets vides.

---

### Sous-domaine 1 — Génération des créneaux (Period → Shift)

**Flux principal — Configuration des semaines types** :
1. L'admin crée un `Period` (`admin_period_new`) : jour de semaine (0=Lundi…6=Dimanche), horaire start/end, `Job` (type de poste).
2. Il ajoute des `PeriodPosition` (`admin_periodposition_new`) : qualification (`Formation` optionnelle), `weekCycle` (A/B/C/D si `cycle_type=abcd`, null sinon), nombre d'exemplaires.
3. En mode `use_fly_and_fixed` : il assigne un bénéficiaire fixe à la position (`admin_periodposition_book`).
4. Il peut copier un jour sur un autre (`admin_period_copy`). **⚠️ La copie ne transfère pas les shifters** (clone PHP → shifter=null), TODO dans le code.

**Flux principal — Génération des créneaux futurs** (`app:shift:generate <date> [--to <date>]`) :
1. Pour chaque date dans la plage, vérifie d'abord les `ClosingException` → si fermeture exceptionnelle, aucun créneau généré.
2. Récupère les `Period` correspondant au `dayOfWeek` de la date.
3. Pour chaque `Period`, pour chaque `PeriodPosition` :
   - En mode `abcd` : ignore les positions dont le `weekCycle` ne correspond pas à la semaine ISO courante (modulo 4 → A/B/C/D).
   - Vérifie l'idempotence : `findBy(start+end+job+position)` → ne crée pas si existe déjà.
   - Si `use_fly_and_fixed` et `position.shifter != null` et membre non exempté → créneau fixe (shifter pré-rempli, `fixe=true`).
   - Si `reserve_new_shift_to_prior_shifter` et créneau du cycle précédent (J-28) avait un shifter → `lastShifter` = ancien shifter (pré-réservé).
   - Sinon → créneau libre.
4. Dispatch `ShiftReservedEvent` pour chaque créneau pré-réservé (email au bénévole pour confirmation).

**⚠️ Antipattern AP.1 — Commande depuis le web** : `admin_shifts_generation` instancie `Symfony\Bundle\FrameworkBundle\Console\Application` en mémoire et exécute `app:shift:generate` via `Application::run()`. Output ANSI capturé dans un flash message — risque de timeout HTTP pour des plages longues. Pattern à remplacer par un bus de messages ou une route API avec streaming.

---

### Sous-domaine 2 — Réservation de créneau (membre)

**Flux principal** :
1. L'adhérent accède à `/booking` → si adhésion expirée ou gelée → redirection homepage.
2. Si plusieurs bénéficiaires dans l'adhésion → page de sélection bénéficiaire.
3. Vue calendrier : créneaux futurs groupés en `ShiftBucket` (même horaire + job), rendus sur une grille 6h-22h.
4. Clic sur un bucket → modal → l'adhérent choisit un créneau disponible.
5. POST `shift_book` : `beneficiaryId` + `typeService` (fixe=1/volant=0 si `use_fly_and_fixed`).
6. `ShiftService::isShiftBookable()` vérifie toutes les règles (voir Règles métier).
7. Si OK : `shift.shifter = beneficiary`, `shift.booker = currentUser`, `shift.bookedTime = now`. Si `firstShiftDate` null → mis à jour.
8. Dispatch `ShiftBookedEvent` → emails (SPEC.7).

**⚠️ firstShiftDate dupliqué (AP.1)** : la mise à jour de `firstShiftDate` est copiée-collée dans `bookShiftAction` (l.170-175) **et** `bookShiftAdminAction` (l.226-232). Logique identique, à factoriser.

**Réservation admin** (`shift_book_admin`, `ROLE_SHIFT_MANAGER`) :
- Peut réserver pour n'importe quel membre.
- Vérifications supplémentaires : qualification (formation), exemption, règle `forbid_own_shift_book_admin` (ne peut pas réserver son propre créneau, sauf ROLE_ADMIN).
- Le choix fixe/volant est présent si `use_fly_and_fixed`.

**Pré-réservation** (`shift_accept_reserved`, `shift_reject_reserved`) :
- Quand `reserve_new_shift_to_prior_shifter=true`, le créneau généré a un `lastShifter` (ancien bénévole) et l'email `ShiftReservedEvent` lui propose d'accepter ou refuser.
- `shift_accept_reserved` : GET `/shift/{id}/accept` → confirme la réservation (`shifter = lastShifter`, `lastShifter = null`).
- `shift_reject_reserved` : GET `/shift/{id}/reject` → libère (`lastShifter = null`). Le créneau devient libre.
- **⚠️ Mutations via GET** (pas de CSRF, pas de protection anti-rejeu).
- L'accès est contrôlé par le voter `accept`/`reject` sur `Shift` (à documenter en SPEC.4).

---

### Sous-domaine 3 — Annulation de créneau

**Flux membre** (`shift_free`, POST, ROLE_USER, voter `ShiftVoter::FREE`) :
1. Vérifie `canFreeShift(currentUser.beneficiary, shift)` — règles voir Règles métier.
2. Store `beneficiary`, `fixe`, `reason` avant libération.
3. `shift.free()` : efface shifter, booker, bookedTime, fixe.
4. Dispatch `ShiftFreedEvent` → emails, `ShiftFreeLog` créé par listener.
5. En mode `use_time_log_saving` : si un `TimeLog::TYPE_SAVING` existe sur ce shift/membre → message info compteur épargne décrémenté.

**Flux admin** (`shift_free_admin`, ROLE_SHIFT_MANAGER) :
- Règle `forbid_own_shift_free_admin`.
- Si le créneau était validé (`wasCarriedOut=1`) → appelle d'abord `invalidateShiftParticipation()` avant de libérer → dispatch `ShiftInvalidatedEvent` puis `ShiftFreedEvent`.

**Libération des pré-réservés** (`app:shift:free <date>`) :
- Libère les `lastShifter` non confirmés à la date donnée (les bénévoles n'ayant pas répondu à l'email de pré-réservation).
- **⚠️ Coordination cron non documentée** : doit être lancé `reserve_new_shift_to_prior_shifter_delay` jours après `app:shift:generate`. Ce délai est un paramètre app mais l'orchestration cron n'est pas spécifiée.

---

### Sous-domaine 4 — Validation de présence

**Via lecteur de badge** (`card_reader_index`, `card_reader_check`) :
1. `card_reader_index` : voter `card_reader` (mécanisme à documenter en SPEC.4) ; liste les créneaux en cours et à venir du jour.
2. `card_reader_check` (POST) : reçoit `swipe_code` (EAN13), vérifie l'intégrité EAN13, décode le badge, trouve le bénéficiaire.
3. Trouve les créneaux en cours (+10 min de tolérance) pour ce bénéficiaire → valide (`wasCarriedOut=true`) ceux qui ne l'étaient pas.
4. Dispatch `ShiftValidatedEvent` → `TimeLog::TYPE_SHIFT_VALIDATED` via listener.
5. Affiche le compteur de temps restant du cycle courant.
6. Si `swipeCardLogging=true` → dispatch `SwipeCardEvent` (logs de passage).

**🔴 card_reader_check sans authentification ni CSRF (SEC.2.2 + SEC.3.3)** : la route POST `/card_reader/check` n'a **aucun contrôle d'accès** (@Route sans @Security, pas de `denyAccessUnlessGranted`). N'importe qui connaissant un code EAN13 valide + un code de badge actif peut valider des créneaux ou lire les compteurs de temps d'un membre. La tolérance de +10 min élargit encore la fenêtre d'abus. Seul garde-fou : la vérification EAN13 et la validité du badge en base.
⚖️ **Sévérité canonique 🟠** (SEC.2.2 / I-SEC-4) : le gap auth+CSRF **isolé** est *Important*, gated par la validité du badge en base. Le **🔴** ci-dessus est la sévérité de la **chaîne** avec la forgeabilité des badges (Vigenère + `rand()`, C-SEC-4 / SEC.1.7) — cette criticité est **portée par C-SEC-4** ; ne pas la double-compter sur ce finding.

**Via interface admin** (`shift_validate_admin`, POST, ROLE_SHIFT_MANAGER, voter `ShiftVoter::VALIDATE`) :
- Bascule `wasCarriedOut` entre true et false (validation ↔ invalidation).
- Règle `forbid_own_shift_validate_admin`.
- Dispatch `ShiftValidatedEvent` ou `ShiftInvalidatedEvent`.

---

### Sous-domaine 5 — Mécanisme de cycle

**Deux modes selon `cycle_type`** :

| Mode | Déclencheur | `getStartOfCycle()` |
|------|-------------|---------------------|
| `abcd` | ISO week number | Remonte au dernier "lundi de semaine A" (week % 4 == 1). Tous les membres au même rythme. |
| (défaut / firstShiftDate) | `membership.firstShiftDate` | Start = firstShiftDate + N×28j (N = nombre de cycles écoulés). Cycle propre à chaque membre. |

**Durée toujours 28 jours** : `getEndOfCycle` = start + 27 jours, `lastCycleDate` dans `ShiftGenerateCommand` = J-28. **Hardcodé partout** avec un TODO "should use cycle_duration instead of hardcoded 28" (`MembershipService.php:155`, `ShiftGenerateCommand:168`). Aucun paramètre `cycle_duration` exposé.

**Application du frozenChange** (gap résolu de SPEC.2) :
- La commande `app:user:cycle_start` identifie les membres dont un nouveau cycle commence (`Membership::findWithNewCycleStarting(date, cycle_type)`).
- Elle dispatche `MemberCycleEndEvent` → le listener `MemberCycleEndEventListener` applique `frozenChange` : si `frozenChange=true`, le membre passe en `frozen=true` pour le nouveau cycle (ou le dégage selon la direction). C'est le seul mécanisme d'application automatique du gel demandé.

**Alerte mi-cycle** (`app:user:cycle_half`) : à mi-cycle, dispatch `MemberCycleHalfEvent` avec les créneaux déjà réservés → emails d'alerte aux membres qui n'ont pas encore tout réservé.

**⚠️ shift_cycle — confusion d'unité (DC.3/AP.1)** dans `ShiftService::isShiftBookable` (l.257) :
```php
// TODO refactor code to remove shift_cycle
$shift_cycle = $this->membershipService->getCycleNumber($member, $shift->getStart());
return $this->canBookDuration($beneficiary, $shift->getDuration(), $shift_cycle) or $this->canBookExtraShift($beneficiary, $shift);
```
`getCycleNumber` retourne le numéro de cycle **relatif** (peut valoir -1, 0, 1, 2…) mais `canBookDuration` attend `$cycle = 0` pour cycle courant, `1` pour cycle suivant. Les valeurs négatives ou >1 produisent des calculs incorrects → faux positifs DC.3 potentiels.

---

### Sous-domaine 6 — Exemptions de créneau

**Deux entités** :
- `ShiftExemption` : catalogue des motifs (maladie, congé parental…). CRUD admin via `admin_shiftexemption_*`.
- `MembershipShiftExemption` : instance d'exemption pour un membre sur une plage de dates. Unique par (membership, start) et (membership, end).

**Flux** :
1. Admin crée une exemption (`admin_membershipshiftexemption_new`) → vérifie que le membre n'a pas de créneaux planifiés sur la période (blocage si oui).
2. L'exemption est vérifiée avant toute réservation (`Membership::isCurrentlyExemptedFromShifts(date)`).
3. La génération des créneaux fixes respecte aussi l'exemption (`ShiftGenerateCommand:127`).
4. Suppression bloquée si le début est passé (sauf ROLE_SUPER_ADMIN).

---

### Sous-domaine 7 — TimeLog (compteur de temps)

Le `TimeLog` est le **journal comptable** du bénévolat. Chaque entrée a un `type`, un `time` (en minutes, smallint), et est rattachée à un `Membership` (et optionnellement à un `Shift`).

| Type (constante) | Valeur | Déclencheur |
|------------------|--------|-------------|
| `TYPE_SHIFT_VALIDATED` | 1 | Validation de présence (badge ou admin) |
| `TYPE_SHIFT_INVALIDATED` | 10 | Invalidation admin |
| `TYPE_SHIFT_FREED_SAVING` | 21 | Annulation avec compteur épargne |
| `TYPE_CYCLE_END` | 2 | Début de cycle (crédité/débité) |
| `TYPE_CYCLE_END_FROZEN` | 3 | Début de cycle (compte gelé) |
| `TYPE_CYCLE_END_EXPIRED_REGISTRATION` | 4 | Début de cycle (adhésion expirée) |
| `TYPE_CYCLE_END_EXEMPTED` | 6 | Début de cycle (exempté) |
| `TYPE_CYCLE_END_SAVING` | 7 | Début de cycle (compteur épargne) |
| `TYPE_REGULATE_OPTIONAL_SHIFTS` | 5 | Régulation bénévolat facultatif |
| `TYPE_SAVING` | 20 | Compteur épargne +/- |
| `TYPE_CUSTOM` | 0 | Ajout manuel admin (`timelog_new`) |

Le champ `requestRoute` trace la route à l'origine de l'entrée (audit trail technique).

**Règles de protection** :
- Création manuelle : ROLE_SHIFT_MANAGER, garde `forbid_own_timelog_new_admin`.
- Suppression : ROLE_SUPER_ADMIN uniquement.
- Les TimeLog sont cascadés à la suppression de la `Membership` (ON DELETE CASCADE).

---

### Sous-domaine 8 — Verrouillage de bucket (locked)

Un admin ROLE_SHIFT_MANAGER peut verrouiller/déverrouiller un bucket (`bucket_lock_unlock`) via `ShiftVoter::LOCK`. Le verrou s'applique à **tous les créneaux du bucket** (même horaire+job). Un créneau verrouillé (`locked=true`) n'est plus réservable.

---

### Données — récapitulatif des entités

| Entité | Champs clés | Relations |
|--------|-------------|-----------|
| `Shift` | `start`(datetime), `end`(datetime), `wasCarriedOut`(bool), `locked`(bool), `fixe`(bool), `bookedTime`, `createdAt` | N-1 `job`→Job(EAGER), N-1 `shifter`→Beneficiary, N-1 `booker`→User, N-1 `lastShifter`→Beneficiary, N-1 `position`→PeriodPosition(onDelete=SET NULL), N-1 `formation`→Formation(onDelete=SET NULL), N-1 `createdBy`→User, 1-N `timeLogs`, 1-N `freeLogs`→ShiftFreeLog |
| `Period` | `dayOfWeek`(smallint 0-6 Lun-Dim), `start`(time), `end`(time), `createdAt`, `updatedAt` | N-1 `job`→Job(EAGER), 1-N `positions`→PeriodPosition(cascade=persist+remove), N-1 `createdBy`, N-1 `updatedBy`→User |
| `PeriodPosition` | `weekCycle`(string 1, nullable: A/B/C/D), `bookedTime`, `createdAt`, `updatedAt` | N-1 `period`→Period, N-1 `shifter`→Beneficiary(nullable), N-1 `booker`→User, N-1 `formation`→Formation(onDelete=CASCADE), 1-N `shifts`, 1-N `freeLogs`→PeriodPositionFreeLog |
| `TimeLog` | `time`(smallint, minutes), `type`(smallint), `description`(nullable), `requestRoute`(nullable), `createdAt` | N-1 `membership`→Membership(onDelete=CASCADE), N-1 `shift`→Shift(nullable, onDelete=SET NULL), N-1 `createdBy`→User |
| `MembershipShiftExemption` | `start`(date), `end`(date), `description`, `createdAt` | N-1 `membership`→Membership(onDelete=CASCADE), N-1 `shiftExemption`→ShiftExemption, N-1 `createdBy`→User |
| `ShiftBucket` | (non persisté, calculé) | Collection de `Shift` partageant start+end+job |

---

### Règles métier — `isShiftBookable` (ShiftService:207)

Contrôles appliqués dans l'ordre :
1. Créneau passé, verrouillé ou déjà réservé → **refus**
2. `lastShifter` ≠ beneficiary → **refus** (réservé pour quelqu'un d'autre)
3. Formation requise non obtenue → **refus**
4. `newUserStartAsBeginner` + bucket vide (`isShiftEmpty`) → **refus** (débutant ne peut pas être le premier)
5. Chevauchement horaire (`forbidShiftOverlapTime` minutes de marge) → **refus**
6. Membre exempté à la date du créneau → **refus**
7. Membre retiré (`withdrawn`) → **refus**
8. `firstShiftDate > shift.start` → **refus** (créneau antérieur à la date d'entrée)
9. Membre gelé → **refus** si créneau ≤ fin de cycle courant ; si créneau > fin de cycle courant et `!frozenChange` → **refus** également
10. Quota cycle : `canBookDuration(beneficiary, shift.duration, shift_cycle) OR canBookExtraShift(...)` → si refus des deux → **refus**

### Règles métier — `canFreeShift` (ShiftService:267)

1. Créneau sans shifter → **refus**
2. Shifter ≠ beneficiary → **refus**
3. (non admin uniquement) Créneau passé ou en cours → **refus**
4. (non admin) `use_fly_and_fixed` + créneau fixe + `!fly_and_fixed_allow_fixed_shift_free` → **refus**
5. (non admin) `use_time_log_saving` + délai mini non respecté → **refus**
6. (non admin) `use_time_log_saving` + solde épargne insuffisant → **refus**

---

### Routes — inventaire complet (~45)

| Route | Méthode / chemin | Contrôle d'accès |
|-------|------------------|------------------|
| `booking` | GET\|POST `/booking/` | ROLE_USER |
| `booking_by_day` | GET\|POST `/booking/day/{day}/{beneficiary}/{cycle}` | ROLE_USER (si beneficiary fourni) |
| `bucket_show` | GET `/booking/bucket/{id}/show` | public (anonyme possible) |
| `bucket_show_for_beneficiary` | GET `/booking/bucket/{id}/show/for/{beneficiary}/cycle/{cycle}` | ROLE_USER |
| `shift_book` | POST `/shift/{id}/book` | ROLE_USER |
| `shift_free` | POST `/shift/{id}/free` | ROLE_USER + voter FREE |
| `shift_accept_reserved` | **GET** `/shift/{id}/accept` | voter `accept` |
| `shift_reject_reserved` | **GET** `/shift/{id}/reject` | voter `reject` |
| `shift_contact_form` | GET\|POST `/shift/{id}/contact_form` | **public** (⚠️ pas de @Security) |
| `shift_widget` | GET `/shift/widget` | **public** |
| `period_index` | GET\|POST `/period/` | ROLE_USER |
| `booking_admin` | GET\|POST `/booking/admin` | ROLE_SHIFT_MANAGER |
| `admin_bucket_show` | GET `/booking/admin/bucket/{id}/show` | ROLE_SHIFT_MANAGER |
| `bucket_edit` | GET\|POST `/booking/bucket/{id}/edit` | ROLE_SHIFT_MANAGER |
| `bucket_lock_unlock` | POST `/booking/bucket/{id}/lock` | ROLE_SHIFT_MANAGER + voter LOCK |
| `bucket_delete` | DELETE `/booking/bucket/{id}` | ROLE_ADMIN |
| `shift_new` | GET\|POST `/shift/new` | ROLE_SHIFT_MANAGER |
| `shift_book_admin` | GET\|POST `/shift/{id}/book_admin` | ROLE_SHIFT_MANAGER |
| `shift_free_admin` | POST `/shift/{id}/free_admin` | ROLE_SHIFT_MANAGER + voter FREE |
| `shift_validate_admin` | POST `/shift/{id}/validate_admin` | ROLE_SHIFT_MANAGER + voter VALIDATE |
| `shift_delete` | DELETE `/shift/{id}` | ROLE_ADMIN |
| `admin_period_index` | GET\|POST `/admin/period/` | ROLE_SHIFT_MANAGER |
| `admin_period_new` | GET\|POST `/admin/period/new` | ROLE_SHIFT_MANAGER |
| `admin_period_edit` | GET\|POST `/admin/period/{id}/edit` | ROLE_SHIFT_MANAGER |
| `admin_period_delete` | DELETE `/admin/period/{id}` | ROLE_ADMIN |
| `admin_period_copy` | GET\|POST `/admin/period/copy` | ROLE_ADMIN |
| `admin_periodposition_new` | POST `/admin/period/{id}/position/add` | ROLE_SHIFT_MANAGER |
| `admin_periodposition_delete` | DELETE `/admin/period/{id}/position/{position}` | ROLE_ADMIN |
| `admin_periodposition_book` | POST `/admin/period/{id}/position/{position}/book` | ROLE_SHIFT_MANAGER |
| `admin_periodposition_free` | POST `/admin/period/{id}/position/{position}/free` | ROLE_SHIFT_MANAGER |
| `admin_shifts_generation` | GET\|POST `/admin/period/generateShifts/` | ROLE_ADMIN |
| `admin_shiftexemption_index/new/edit/delete` | — | ROLE_SHIFT_MANAGER |
| `admin_membershipshiftexemption_index/new/edit/delete` | — | ROLE_USER_MANAGER |
| `admin_shiftfreelog_index` | GET `/admin/shiftfreelog/` | ROLE_SHIFT_MANAGER |
| `admin_periodpositionfreelog_index` | GET `/admin/periodpositionfreelog/` | ROLE_SHIFT_MANAGER |
| `timelog_new` | GET\|POST `/time_log/{id}/new` | ROLE_SHIFT_MANAGER (garde own) |
| `member_timelog_delete` | DELETE `/time_log/{id}/timelog_delete/{timelog_id}` | ROLE_SUPER_ADMIN |
| `card_reader_index` | GET `/card_reader/` | voter `card_reader` |
| `card_reader_check` | POST `/card_reader/check` | 🟠 **AUCUN** auth+CSRF (SEC.2.2/SEC.3.3 ; 🔴 en chaîne C-SEC-4) |
| `ambassador_shifttimelog_list` | — | AmbassadorController (ROLE_USER_VIEWER) |
| `ambassador_beneficiary_fixe_without_periodposition_list` | — | AmbassadorController (ROLE_USER_VIEWER) |

**Chevauchements** : `shift_contact_form` (→ SPEC.7) ; `card_reader_*` / badges / SwipeCard / codes d'accès physique (→ SPEC.4 domaine J transverse) ; génération intégrant `ClosingException`/`OpeningHour` (→ SPEC.6).

---

### Événements dispatché (cross SPEC.7)

| Événement | Déclencheur | Listener attendu |
|-----------|-------------|-----------------|
| `ShiftBookedEvent` | Réservation membre ou admin | Email confirmation |
| `ShiftFreedEvent` | Annulation (membre ou admin) | Email annulation, ShiftFreeLog |
| `ShiftValidatedEvent` | Validation badge ou admin | TimeLog TYPE_SHIFT_VALIDATED |
| `ShiftInvalidatedEvent` | Invalidation admin | TimeLog TYPE_SHIFT_INVALIDATED |
| `ShiftDeletedEvent` | Suppression créneau ou bucket | Email si réservé |
| `ShiftReservedEvent` | Pré-réservation (generate) | Email au lastShifter |
| `ShiftReminderEvent` | `app:shift:reminder` (cron) | Email rappel |
| `ShiftAlertsEvent` | `app:shift:send_alerts` (cron) | Email/Mattermost alertes créneaux vides |
| `PeriodPositionFreedEvent` | Libération position fixe | Email au bénévole |
| `MemberCycleEndEvent` | `app:user:cycle_start` | Gel/dégel, TimeLog cycle |
| `MemberCycleHalfEvent` | `app:user:cycle_half` | Email alerte mi-cycle |

---

### Tests existants

**`tests/Unit/Entity/ShiftTest.php`** (553 lignes) — très complet : `getDuration`, `getIsPast/Current/Future/Upcoming`, `isFixe`, `isBefore`, `isFirstByShifter`, `getTmpToken`, formats d'affichage de dates. Bonne couverture de l'entité.

**`tests/Unit/Entity/ShiftBucketTest.php`** (439 lignes) — `addShift`, `canBookInterval`, `filterByFormations`, `compareShifts`, `getShiftWithMinId`. Couverture complète de l'entité virtuelle.

**`tests/Unit/Service/ShiftServiceUnitTest.php`** (512 lignes) — `isBeginner`, `canBookDuration`, `canBookOnCycle`, `canBookSomething`, `canFreeShift`, `isShiftBookable`. Tests unitaires avec mocks extensifs. Couvre les principales règles métier.

**`tests/Integration/Service/ShiftServiceTest.php`** (339 lignes) — tests d'intégration avec DB. Couvre les cas de cycle, exemption, gel.

**`tests/Unit/Service/PeriodServiceTest.php`** — `getWeekCycleArray` uniquement.

**SmokeTest** : `booking`, `booking_admin` (codes retour uniquement).

---

### Gaps

**Sécurité (cross SEC)** :
- 🟠 `card_reader_check` : aucune authentification, aucun CSRF → validation de présence possible par n'importe qui avec un badge EAN13 valide. Lecture des compteurs de temps membres. *(Canonique 🟠 = gap isolé, SEC.2.2 / I-SEC-4 ; 🔴 uniquement en chaîne avec la forgeabilité des badges C-SEC-4 / SEC.1.7.)*
- 🟠 `shift_contact_form` : aucune annotation @Security → public de facto. Un anonyme peut appeler la route avec n'importe quel `{id}` de shift et envoyer un email aux co-bénévoles (spam SMTP). *(Aligné sur SEC.1.3 / I-SEC-2 🟠.)*
- 🟠 `shift_accept_reserved` / `shift_reject_reserved` : mutations via **GET** (pas de CSRF, rejouables). *(Aligné sur SEC.1.5 / I-SEC-3 🟠.)*

**Code / dette (cross AP.1, D.5)** :
- 🟠 Duplication `createShift*Form` (5 méthodes) entre `ShiftController` et `BookingController` — TODO dans les commentaires.
- 🟠 `firstShiftDate` mis à jour à deux endroits identiques (`bookShiftAction` + `bookShiftAdminAction`).
- 🟠 `admin_shifts_generation` lance `app:shift:generate` via `Application::run()` depuis un controller HTTP — risque timeout, output ANSI dans flash message.
- 🟠 `shift_cycle` dans `isShiftBookable` : confusion d'unité (`getCycleNumber` retourne relatif, `canBookDuration` attend 0/1). TODO inline.
- 🟠 Durée de cycle 28 jours hardcodée dans 3 endroits (TODO inline dans `MembershipService`).
- 🟡 `admin_period_copy` ne copie pas les shifters des positions (TODO inline).
- 🟡 `FixShiftMissingPositionCommand` : ne fonctionne pas pour `cycle_type=abcd` (exit code 1 explicite, TODO weekCycle).
- 🟡 `app:shift:free` (libération pré-réservés) : coordination cron non documentée avec `reserve_new_shift_to_prior_shifter_delay`.

**Non testé** :
- `app:shift:generate`, `app:user:cycle_start`, `app:user:cycle_half`, `app:shift:free` : aucun test de commande.
- `card_reader_check` : aucun test fonctionnel.
- Génération en mode `reserve_new_shift_to_prior_shifter` et flow pré-réservation complet.
- Validation/invalidation de présence (admin + badge) : parcours métier non couvert.
- Mode `use_time_log_saving` : `canFreeShift` avec épargne ; création/décrémentation du compteur.
- Mode `fly_and_fixed` : génération de créneaux fixes, interdiction d'annulation.
- `admin_period_copy` : comportement des shifters sur clone.
- Exemptions : test de la règle de blocage à la création.

**Ambigu / à confirmer** :
- Voter `card_reader` (qui y a accès ? ROLE_SHIFT_MANAGER ? Rôle dédié ?) — à documenter en SPEC.4.
- Voter `accept`/`reject` sur `Shift` — règles exactes (qui peut accepter/rejeter une pré-réservation pour qui ?).
- Voter `ShiftVoter::LOCK` — qui peut verrouiller ? (ROLE_ADMIN ? ROLE_SHIFT_MANAGER ?) — à confirmer dans `ShiftVoter`.
- `schedule` : route listée en SPEC.1 domaine B — non trouvée dans les controllers lus (probable `AmbassadorController` ou `DefaultController`).
- `ambassador_shifttimelog_list` et `ambassador_beneficiary_fixe_without_periodposition_list` : routes ambassadeur partiellement dans SPEC.3 — à documenter complètement en SPEC.6 (administration) ou transverse.
- `ShiftExemption` (catalogue) vs `MembershipShiftExemption` (instance) : double CRUD (`admin_shiftexemption_*` via `AdminShiftExemptionController` + `admin_membershipshiftexemption_*`) — relation et droits à clarifier.

