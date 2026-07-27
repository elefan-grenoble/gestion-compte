# AP.1 — Controllers fat

- [x] **AP.1** — Controllers fat

`find src/Controller -name "*.php" | xargs wc -l | sort -rn | head -20`. Lire les 5 plus longs. Controllers > 150 lignes ou avec logique métier directe → TODO.

**Vue d'ensemble** : 35 controllers, 10 949 lignes total. 19 controllers dépassent 150 lignes — seuil habituel pour qualifier de "fat". Les 5 plus longs : `MembershipController` (1 242), `ShiftController` (828), `BookingController` (718), `AdminPeriodController` (545), `AdminEventController` (494).

---

<a id="AP.1-1"></a>
### 1. Service locator généralisé — 3 patterns, 90+ occurrences, 24 controllers (🔴)

Ces appels contournent l'injection de dépendances et rendent les controllers non testables sans conteneur Symfony complet.

| Pattern | Occurrences | Alternative correcte |
|---------|-------------|----------------------|
| `$this->get('security.token_storage')` | **53** dans 24 controllers | `$this->getUser()` (disponible depuis SF2.6 via `AbstractController`) |
| `$this->get('security.authorization_checker')` | **26** dans 14+ controllers | `$this->isGranted()` / `$this->denyAccessUnlessGranted()` |
| `$this->get('twig')` | **11** dans plusieurs controllers | `$this->renderView()` (intégré à `AbstractController`) |

Distribution du pattern token_storage par controller : `ShiftController` (×8), `AdminPeriodController` (×6), `MembershipController` (×5), `EventController` (×4), puis 20 autres controllers.

→ **TODO SYN.2** — effort M (35 fichiers, mécanique, vérification manuelle requise pour les cas où `getUser()` ne suffit pas)

---

<a id="AP.1-2"></a>
### 2. Logique métier directe dans les controllers (🟠)

**a) `MembershipController::newAction` — auto-increment du numéro adhérent**
Lignes 747-751 : `findOneBy([], ['member_number' => 'DESC'])` + `getMemberNumber() + 1` directement dans le controller. Ce calcul est non atomique (race condition possible en concurrent), non testé, et devrait être dans `MembershipService`. (60 appels ORM directs au total dans `MembershipController`.)

**b) `MembershipController::joinAction` — fusion de deux adhésions**
Lignes 940-969 : boucle sur les bénéficiaires, `removeBeneficiary`/`addBeneficiary`/`setMembership`, suppression de l'adhésion source. Logique de fusion entière dans le controller — aucune transaction, pas de service dédié, non testable.

**c) `MembershipController::exportEmails` — CSV inline**
Lignes 1034-1055 : filtrage et formatage CSV des emails directement dans le controller (boucle, filtre `isTemporaryEmail`, `filter_var`, concaténation). Devrait être dans un service d'export ou une query Doctrine dédiée.

**d) `ShiftController` — logique `firstShiftDate` dupliquée**
Blocs identiques aux lignes 170-175 (bookShiftAction) et 227-232 (bookShiftAdminAction) :
```php
if ($member->getFirstShiftDate() == null) {
    $firstDate = new \DateTime('now');
    $firstDate->setTime(0, 0, 0);
    $member->setFirstShiftDate($firstDate);
    $em->persist($member);
}
```
Ce comportement appartient à `ShiftService::bookShift()`.

**e) `ShiftController::contactFormAction` — email construit et envoyé directement**
Lignes 614-666 : `MailerInterface` injecté, objet `Email` construit inline, `->bcc(...)`, `->html(renderView(...))`, `mailer->send()`. Bypass total de `MailerService`. Devrait passer par une méthode dédiée dans `MailerService`.

**f) `AdminPeriodController::generateShiftsForDateAction` — console command appelée depuis une action web**
Lignes 429-459 : `new Application($kernel)` + `ArrayInput` + `$application->run()` pour exécuter `app:shift:generate`. Couplage fort couche web → couche CLI. La logique de génération devrait être dans un service appelé à la fois depuis la commande et depuis le controller.

→ **TODO SYN.2** — items a, b, c, e : effort S-M chacun ; d : XS ; f : M

---

<a id="AP.1-3"></a>
### 3. Duplication des `createShift*Form` entre 3 controllers (🟠)

Documentée dans D.5 (TODO comments), confirmée ici. 5 méthodes copiées-collées :

| Méthode | BookingController | ShiftController | MembershipController |
|---------|:-----------------:|:---------------:|:--------------------:|
| `createShiftBookAdminForm` | ✓ | ✓ | |
| `createShiftDeleteForm` | ✓ | ✓ | |
| `createShiftFreeForm` | ✓ | ✓ | |
| `createShiftFreeAdminForm` | ✓ | ✓ | ✓ |
| `createShiftValidateInvalidateAdminForm` | ✓ | ✓ | ✓ |

**Solution** : un `ShiftFormFactory` service (ou un Symfony Form type avec options) centralise ces 5 méthodes. Les 3 controllers l'injectent et appellent les méthodes nommées.

→ **TODO SYN.2** — effort S (1 service à créer, 3 controllers à simplifier)

---

<a id="AP.1-4"></a>
### 4. Duplication de `getErrorMessages` et `redirectToShow` (🟠)

| Méthode | Controllers concernés |
|---------|----------------------|
| `private function getErrorMessages(Form $form)` | BeneficiaryController, CodeController, ServiceController, TaskController, UserController — **5 copies identiques** |
| `private function redirectToShow(Membership\|User $member)` | BeneficiaryController, MembershipController, NoteController, TimeLogController, UserController — **5 copies** (légèrement variantes) |

`getErrorMessages` : corps strictement identique dans les 5 controllers. Candidat évident pour un trait ou une méthode `protected` dans un `AbstractAppController` (extension d'AbstractController).

`redirectToShow` : variations mineures (type de l'entité, gestion du token temporaire). Un trait avec 2 surcharges (Membership, User) suffit.

→ **TODO SYN.2** — effort S

---

<a id="AP.1-5"></a>
### 5. `MembershipController::showAction` — 18 formulaires construits dans une seule action (🟡)

La méthode `showAction` (lignes 83-214 = 131 lignes) construit : `flyingForm`, `freezeForm`, `unfreezeForm`, `freezeChangeForm`, `withdrawnForm`, `deleteForm`, `noteNewForm`, `noteEditForms[n]`, `noteDeleteForms[n]`, `new_notes_form[n]`, `registrationForm`, `detachBeneficiaryForms[b]`, `deleteBeneficiaryForms[b]`, `beneficiaryForm`, `timeLogNewForm`, `timeLogDeleteForms[t]`, `shiftFreeForms[s]`, `shiftValidateInvalidateForms[s]`. Ce volume de préparation de vue dans le controller est le symptôme le plus visible du problème structurel : pas de séparation "préparation de formulaire" / "logique d'action".

→ **TODO SYN.2** — effort L (refactoring structurant, dépend d'abord de ShiftFormFactory)

---

<a id="AP.1-6"></a>
### 6. 36 méthodes `private createXxxForm()` au total (🟡)

36 méthodes de ce type réparties dans les controllers. Elles ne sont pas testables isolément, non réutilisables entre controllers, et gonflent mécaniquement la taille de chaque classe. L'émergence naturelle de ce problème est le pattern de duplication documenté en point 3. La solution systémique est de les sortir dans des Form types ou des services dédiés.

→ **TODO SYN.2** — effort L (transversal, à traiter par domaine)

---

### Résumé

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🔴 | Service locator (token_storage, auth_checker, twig) — 90+ occurrences, 24 controllers | M |
| 🟠 | Logique métier directe (member number, join, CSV, firstShiftDate, email, console command) | S–M chacun |
| 🟠 | `createShift*Form` × 5 méthodes × 3 controllers | S |
| 🟠 | `getErrorMessages` + `redirectToShow` × 5 controllers | S |
| 🟡 | `showAction` de MembershipController — 18 formulaires dans une action | L |
| 🟡 | 36 méthodes `createXxxForm` en tout | L |

