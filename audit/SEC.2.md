# SEC.2 — Autorisation dans les controllers

- [x] **SEC.2** — Autorisation dans les controllers

`grep -rn "denyAccessUnlessGranted\|IsGranted\|isGranted" src/Controller/`. Actions sans vérification → TODO.

**Fichiers lus** : tous les fichiers `src/Controller/*.php` (43 controllers), `templates/beneficiary/confirm.html.twig`.

**Périmètre** : 43 controllers, ~180 actions publiques. Les 11 controllers `Admin*` utilisent tous le préfixe `admin/...` et sont couverts par la règle `access_control: ^/admin/ → ROLE_ADMIN_PANEL`. L'analyse ci-dessous porte sur les 32 controllers hors préfixe admin.

---

<a id="SEC.2-1"></a>
### 1. `setEmailAction` — modification d'email sans authentification (🔴)

`MembershipController::setEmailAction` (POST `/member/{id}/set_email`) n'a **aucune vérification d'autorisation** — ni `@Security`, ni `denyAccessUnlessGranted`, ni `isGranted`. N'importe quel utilisateur anonyme peut envoyer un POST avec un ID de bénéficiaire valide et une adresse email, et modifier l'adresse si l'email courant est un "email temporaire".

**Vecteur d'attaque** : énumération des IDs de bénéficiaires → changement de l'email temporaire vers une adresse contrôlée par l'attaquant → déclenchement du reset de mot de passe FOS (`/resetting/send-email`) → prise de contrôle du compte.

Le template `confirm.html.twig` (lui-même accessible publiquement) affiche le formulaire `set_email` pour les utilisateurs non authentifiés dont l'email est temporaire, ce qui rend le vecteur encore plus direct.

**Fix** : ajouter `@Security("is_granted('ROLE_USER')")` ou `denyAccessUnlessGranted('ROLE_USER')` en début d'action. La logique d'activation pour les utilisateurs sans compte doit passer par un token à usage unique (actuellement le flow Vigenère est utilisé pour l'invitation initiale).

→ **TODO SYN.2** — effort XS (ajout contrôle auth) ; reconsidérer le flow d'activation anonyme (effort M)

---

<a id="SEC.2-2"></a>
### 2. `CardReaderController::checkAction` — validation de créneau sans authentification (🟠)

`CardReaderController::indexAction` (GET `/card_reader/`) est protégé par `denyAccessUnlessGranted('card_reader', $this->getUser())`. En revanche, `checkAction` (POST `/card_reader/check`) **n'a aucune vérification d'autorisation**. La route est accessible sans être authentifié sur l'interface web.

Avec un code EAN13 valide (obtenu en photographiant un badge, ou en le déduisant via l'espace de codes prévisible — voir SEC.1 finding 7, `rand()` non sécurisé), un attaquant peut directement valider la participation d'un adhérent à un créneau sans passer par le terminal de pointage.

**Fix** : ajouter `denyAccessUnlessGranted('card_reader', $this->getUser())` en début de `checkAction`, identique à `indexAction`.

→ **TODO SYN.2** — effort XS

---

<a id="SEC.2-3"></a>
### 3. `CommissionController` — auth custom non-Symfony, fatal error anonyme (🟠)

`addBeneficiaryAction` (POST `/commissions/{id}/add_beneficiary/`) et `removeBeneficiaryAction` (POST `/commissions/{id}/remove_beneficiary/`) utilisent un pattern d'autorisation maison :

```php
$current_app_user = $this->get('security.token_storage')->getToken()->getUser();
if (! $current_app_user->hasRole('ROLE_SUPER_ADMIN') && ! $current_app_user->getBeneficiary()->getOwnedCommissions()->contains($commission)) {
    throw $this->createAccessDeniedException();
}
```

Pour un utilisateur anonyme, `getToken()->getUser()` retourne la chaîne `"anon."` en SF4. Appeler `->hasRole()` sur une chaîne produit un **PHP Fatal Error** → réponse 500, ce qui bloque l'accès mais expose un stack trace en `APP_ENV=dev` et crée une erreur de log inutile en production.

De plus, `removeBeneficiaryAction` utilise `$_POST['beneficiary']` (superglobal PHP) au lieu de `$request->request->get('beneficiary')` — un antipattern qui contourne l'abstraction Symfony.

**Fix** : remplacer par `@Security("is_granted('ROLE_USER')")` en annotation de méthode + `denyAccessUnlessGranted` avec un voter dédié ou un check `isGranted` standard. Remplacer `$_POST` par `$request->request->get()`.

→ **TODO SYN.2** — effort XS (migration auth) ; cross-ref AP section (superglobal `$_POST`)

---

<a id="SEC.2-4"></a>
### 4. `BookingController::indexByDayAction` — auth conditionnelle (🟡)

`indexByDayAction` (GET+POST `/booking/day/{day}/{beneficiary}/{cycle}`) protège les données de bénéficiaire conditionnellement :

```php
if (!is_null($beneficiary))
    $this->denyAccessUnlessGranted('ROLE_USER');
```

Sans paramètre `{beneficiary}`, la route est accessible anonymement et rend les créneaux du jour (noms des bénéficiaires inclus si `display_name_shifters=true`). Croiser avec **CONFIG.2** pour savoir si `display_name_shifters` est activé chez Elefan/Scopeli.

→ Note pour **SYN.2** (mineur, à confirmer selon config)

---

<a id="SEC.2-5"></a>
### 5. `UserController::installAdminAction` — bootstrap sans auth (🟡)

`installAdminAction` (GET+POST `/user/install_admin`) n'a pas de `@Security`. Son comportement dépend de l'état de la base :

- Si aucun `ROLE_SUPER_ADMIN` en DB → crée le super admin depuis les paramètres de config (`super_admin.initial_password`, `super_admin.username`) **sans authentification**.
- Si un super admin existe → vérifie `isGranted('ROLE_ADMIN')`.

En production non initialisée, **n'importe qui peut POST sur cette route et déclencher la création du super admin** avec les credentials du fichier de config (potentiellement des valeurs par défaut connues). Ce risque est temporaire (limité à la fenêtre entre déploiement et première initialisation), mais non documenté et non protégé.

**Recommandation** : protéger via un secret one-time passé en query param, ou via une commande Symfony CLI uniquement (supprimer la route en prod). Documenter dans **SYN.2**.

→ **TODO SYN.2** — effort S

---

<a id="SEC.2-6"></a>
### 6. Actions publiques by design — inventaire et risques résiduels (🟡)

Les routes suivantes sont intentionnellement publiques (flow d'activation de compte, widgets, OAuth) :

| Route | Controller::action | Justification | Risque résiduel |
|-------|-------------------|--------------|-----------------|
| GET `/` | `DefaultController::indexAction` | Homepage | Faible (display conditionnel) |
| GET `/about` | `DefaultController::aboutAction` | Info publique | Aucun |
| GET `/events/widget` | `EventController::widgetAction` | Widget embarqué | Aucun |
| GET `/events/{id}` | `EventController::detailAction` | Event public | Faible (données événement) |
| GET `/closingexceptions/widget` | `ClosingExceptionController::widgetAction` | Widget embarqué | Aucun |
| GET `/openinghours/widget` | `OpeningHourController::widgetAction` | Widget embarqué | Aucun |
| GET `/widget/` | `WidgetController::widgetAction` | Widget embarqué | Aucun |
| GET `/oauth/login`, `/oauth/callback`, `/oauth/logout` | `OAuthController` | OAuth flow | Aucun |
| GET+POST `/beneficiary/find_member_number` | `BeneficiaryController::findMemberNumberAction` | Onboarding (trouver son numéro) | Moyen — énumération de bénéficiaires par prénom |
| POST `/beneficiary/{id}/confirm` | `BeneficiaryController::confirmAction` | Onboarding step | Moyen — expose nom + email anonymisé pour tout ID |
| GET+POST `/member/find_me` | `MembershipController::activeUserAccountAction` | Onboarding | Moyen — idem confirm |
| GET+POST `/member/add_beneficiary` | `MembershipController::addBeneficiaryAction` | Invitation link (token Vigenère) | Moyen — dépend de la sécurité du token (SEC.1 finding 7) |
| GET+POST `/member/new` | `MembershipController::newAction` | Invitation link OU voter (admin) | Moyen — même remarque |
| GET `/sw/in/{code}` | `SwipeCardController::swipeInAction` | Badge auth | Documenté SEC.1 finding 13 |
| GET `/sw/{code}/qr.png` | `SwipeCardController::qrAction` | QR code affiché après auth | Faible (Vigenère requis) |
| GET `/sw/{code}/br.png` | `SwipeCardController::brAction` | Barcode affiché après auth | Faible (Vigenère requis) |
| GET `/ambassador/phone/{member_number}` | `AmbassadorController::showAction` | Simple redirect vers member_show (protégé) | Aucun |

**Risque transversal — énumération de membres** : `findMemberNumberAction`, `confirmAction`, et `activeUserAccountAction` forment un flow public qui permet à n'importe qui de rechercher un adhérent par prénom, obtenir son ID, et voir son nom complet + email masqué. Ce flow est intentionnel (activation de compte) mais constitue une surface d'énumération. L'ajout d'un rate-limit (ex. Symfony RateLimiter) réduirait ce risque.

→ Note pour **SYN.2** (recommandation rate-limit, effort S)

---

<a id="SEC.2-7"></a>
### 7. Pattern `denyAccessUnlessGranted` sans `@Security` — surface fragile (🟡)

Plusieurs actions protègent via voter (`denyAccessUnlessGranted`) en milieu de méthode sans annotation `@Security` en amont. La protection tient si le voter lève une exception, mais le point d'entrée n'est pas déclaratif et peut être fragilisé par un refactor. Exemples :

| Controller | Action | Pattern |
|------------|--------|---------|
| `NoteController` | `noteEditAction`, `deleteNoteAction` | `denyAccessUnlessGranted` seul |
| `MembershipController` | `freezeAction`, `unfreezeAction`, `freezeChangeAction`, `newRegistration`, `newBeneficiary` | `denyAccessUnlessGranted` seul |
| `BeneficiaryController` | `editBeneficiaryAction`, `setAsMainBeneficiaryAction`, `detachBeneficiaryAction`, `deleteBeneficiaryAction` | `denyAccessUnlessGranted` seul |
| `ShiftController` | `acceptReservedShiftAction`, `rejectReservedShiftAction` | `isGranted` conditionnel seul (SEC.1 finding 5) |
| `ShiftController` | `contactFormAction` | Aucune vérification (SEC.1 finding 3) |
| `BookingController` | `indexByDayAction` | `denyAccessUnlessGranted` conditionnel |

Ce pattern se dissoudrait automatiquement si la recommandation SEC.1 finding 1 (règle default-deny en `access_control`) était appliquée.

---

### Résumé SEC.2

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🔴 Critique | `setEmailAction` — email modifiable sans auth → vecteur account takeover | XS |
| 🟠 Important | `card_reader/check` — validation de créneau sans auth | XS |
| 🟠 Important | `CommissionController` — auth custom → fatal error anonyme + `$_POST` direct | XS |
| 🟡 Mineur | `installAdminAction` — bootstrap sans auth (fenêtre temporaire) | S |
| 🟡 Mineur | `indexByDayAction` — auth conditionnelle, données créneaux visibles anonymement | S |
| 🟡 Info | Flow onboarding public (find_member_number, confirm, find_me) — énumération membres | Rate-limit S |
| 🟡 Info | Pattern `denyAccessUnlessGranted` sans `@Security` — résolu par default-deny SEC.1 | — |

**Cross-références** :
- Finding 1 (`setEmailAction`) croise avec SEC.1 finding 1 (no default-deny) et SEC.1 finding 7 (Vigenère)
- Finding 2 (`card_reader/check`) croise avec SEC.1 finding 7 (`rand()` codes prévisibles)
- Finding 7 (pattern fragile) résolu par SEC.1 finding 1 (default-deny)
- `confirmAction` / `find_member_number` croisent avec SEC.1 finding 12 (`bucket_show` exposition conditionnelle)

