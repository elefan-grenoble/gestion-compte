# D.5 — Annotations internes

- [x] **D.5** — Annotations internes

`grep -rn "@deprecated\|@todo\|@fixme\|TODO\|FIXME\|HACK" src/` — inventaire complet. Certains révèlent des intentions non documentées ou des comportements à expliquer dans les specs.

**25 annotations trouvées** dans 14 fichiers. Regroupées par type de problème.

---

### Groupe 1 — Bugs potentiels / comportements incorrects

**1. `CloseMembershipCommand.php:62` — `setWithdrawnBy()` jamais appelé (🟠)**
`$member->setWithdrawnBy(); //TODO` est commenté. Quand la commande cron clôture automatiquement une adhésion expirée, l'attribut `withdrawnBy` reste `null`. Il est impossible de distinguer une clôture automatique (cron) d'une clôture manuelle par un admin. Traçabilité perdue. → **TODO priorisée SYN.2**

**2. `AdminShiftExemptionController.php:104` — erreur 500 en cas de suppression référencée (🟠)**
`// TODO: error 500 if shiftExemption is used in membershipShiftExemption`. La suppression d'un motif d'exemption (`ShiftExemption`) référencé par une `MembershipShiftExemption` provoquera une violation de contrainte FK → erreur 500. Aucune vérification préalable ni gestion d'erreur. → **TODO priorisée SYN.2**

**3. `MembershipService.php:155` — durée de cycle hardcodée à 28 jours (🟠)**
`// TODO should use cycle_duration instead of hardcoded 28`. La méthode `getStartOfCycle()` calcule les dates via `28 * $cycleOffset` jours. Si une instance configure `cycle_duration` différemment (ex: 14 jours), le calcul est faux. Potentiellement incorrect en multi-instance. → **CONFIG.3** + **TODO SYN.2**

**4. `EmailingEventListener.php:178` — email de création de membre non implémenté (🟡)**
`onMemberCreated()` est déclaré, écouté, mais son corps est `// TODO ?`. L'événement `MemberCreatedEvent` ne déclenche aucun email. L'email de bienvenue n'existe pas, ou l'écouteur est une coquille oubliée. → **SPEC.7** (notifications) + **TODO SYN.2**

---

### Groupe 2 — Valeurs magiques non configurables

**5. `CodeVoter.php:130-132` — fenêtres de badge hardcodées (🟡)**
`-120min` et `+60min` sont les bornes de la fenêtre de validation badge (accès swipe autorisé 2h après fin créneau, 1h avant début). Ces valeurs devraient être dans la config. Dupliquées dans `UserVoter` et `MembershipVoter` (méthode `isLocationOk()` copiée-collée, cf. D.2). → **CONFIG.3** + **TODO SYN.2**

**6. `SearchUserFormHelper.php:79` — choix "nb bénéficiaires" hardcodés (🟡)**
Les choix `[1, 2]` sont fixes au lieu d'utiliser `maximum_nb_of_beneficiaries_in_membership`. Si un paramètre instance autorise 3+ bénéficiaires, le formulaire de recherche affiche des options incomplètes. → **CONFIG.3** + **TODO SYN.2**

---

### Groupe 3 — Duplication de code (confirmée par les TODOs)

**7. `BookingController` + `MembershipController` + `ShiftController` — 7 méthodes `createShift*Form` dupliquées (🟠)**
Chaque TODO (`// TODO: how to avoid having same createShift*Form in ShiftController ?`) documente explicitement la duplication : `createShiftBookAdminForm`, `createShiftDeleteForm`, `createShiftFreeForm`, `createShiftFreeAdminForm`, `createShiftValidateInvalidateAdminForm` dans 3 controllers. Même pattern observé sur `getErrorMessages` (5 controllers : `BeneficiaryController`, `UserController`, `TaskController`, `ServiceController`, `CodeController`) et `redirectToShow` (4 controllers : `BeneficiaryController`, `MembershipController`, `NoteController`, `TimeLogController`). → **AP** section + **TODO SYN.2**

---

### Groupe 4 — Incertitudes architecturales et dette technique

**8. `BeneficiaryController.php:187` — `getErrorMessages()` privée probablement morte (🟡)**
Le TODO original est `// TODO: check if this function is ever used ?!`. Grep de tous les appelants : aucun appel externe dans `BeneficiaryController`. Seule la récursion interne `$this->getErrorMessages($child)` existe. Candidat dead code non détecté par Rector (récursion self-référente). → **EXTRA** (vérification DC.3)

**9. `BeneficiaryController.php:269` / `MembershipController.php:153` / `NoteController.php:140` — pattern `// FIXME` triplé (🟡)**
`$user = $member->getMainBeneficiary()->getUser(); // FIXME` dans 3 `redirectToShow()` différents. Le FIXME sans explication suggère un problème connu mais non résolu : null-safety (`getMainBeneficiary()` peut être `null` pour une adhésion sans bénéficiaire principal), ou duplication de logique token temporaire hors admin. Ces 3 copies seront probablement affectées par le même bug silencieux. → **TODO SYN.2**

**10. `Beneficiary::isNew()` ligne 746 — seuil hardcodé à 3 créneaux (🟡)**
`TODO: move to Membership? Look at registration data instead?` — la notion de "nouveau bénéficiaire" (≤ 3 créneaux) est définie dans l'entité mais utilisée dans `CodeVoter` pour bloquer l'accès badge aux débutants. La règle métier n'est ni configurable, ni documentée. La question de localisation (entité vs `Membership`) n'est pas résolue. → **SPEC.4** (auth) + **CONFIG.3**

**11. `Membership::getFrozen()` ligne 457 — `@deprecated` sans enforcement (🟡)**
`@deprecated illogic isFlying, isWithdrawn but getFrozen`. La méthode `getFrozen()` coexiste avec `isFrozen()`. Sans enforcement, des appelants peuvent utiliser l'ancienne API sans avertissement. → **TODO SYN.2** (nettoyage, effort S)

**12. `ShiftService.php:255` — `shift_cycle` identifié à supprimer mais encore actif (🟡)**
`// TODO refactor code to remove shift_cycle` — le concept `shift_cycle` est utilisé dans `canBookShift()` comme intermédiaire pour `canBookDuration()`, alors que le commentaire indique qu'il devrait utiliser les shifts directement (via `TimeLog`). Refactoring attendu mais non planifié. → **TODO SYN.2**

**13. `FixShiftMissingPositionCommand.php:52` — commande de réparation sans filtre weekCycle (🟡)**
`// TODO : add filter on weekCycle` — la commande applique la réparation à tous les cycles sans distinction. Pour un planning multi-cycles (A/B/C), l'absence de filtre peut affecter des shifts de cycles non concernés. → **TODO SYN.2**

**14. `AdminPeriodController.php:381` — feature gap `use_fly_and_fixed` + copie période (🟡)**
`// TODO: if use_fly_and_fixed, give option to chose if shifter/booker is copied as well`. Lors de la duplication d'une période, l'option de copier le shifter/booker n'est pas proposée quand `use_fly_and_fixed` est activé. Feature incomplète. → **SPEC.3** (créneaux) + **TODO SYN.2**

---

**Résumé des gravités :**
| Gravité | Count | Findings |
|---------|-------|---------|
| 🟠 Important | 4 | FK violation suppression exemption ; withdrawnBy manquant ; cycle_duration hardcodé ; duplication createShift*Form |
| 🟡 Mineur | 10 | Valeurs magiques non configurables ; FIXME triplé ; méthode @deprecated ; etc. |

→ Tous les items "→ TODO SYN.2" alimenteront la TODO priorisée finale.

---

