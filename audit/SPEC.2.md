# SPEC.2 — Spec : Adhérents / Bénéficiaires

- [x] **SPEC.2** — Spec : Adhérents / Bénéficiaires

Sources lues : `MembershipController` (1242 l.), `BeneficiaryController` (275 l.), `NoteController` (146 l.), `UserController` (pre-users), `MembershipVoter`, `MembershipService`, `CloseMembershipCommand` ; entités `Membership`, `Beneficiary`, `AnonymousBeneficiary`, `Note`, `Registration` ; events `MemberCreatedEvent`, `BeneficiaryAddEvent`, `BeneficiaryCreatedEvent`, `AnonymousBeneficiaryCreatedEvent`, `AnonymousBeneficiaryRecallEvent`.
Croisé avec : D.5 (TODOs internes), AP.1 (fat controller), SEC.2.1 + SEC.3.4 (account takeover), DC.3/DC.4 (méthodes mortes).

---

## SPEC.2 — Adhérents / Bénéficiaires

### Vocabulaire essentiel (lever l'ambiguïté Membership / Beneficiary / User)

Le modèle de données distingue **trois concepts** souvent confondus dans l'UI (où l'on parle d'« adhérent ») :

| Concept | Entité | Rôle |
|---------|--------|------|
| **Adhésion / compte adhérent** | `Membership` | Porte le **numéro d'adhérent** (`member_number`), l'historique de cotisations, le statut (gelé/volant/fermé), les créneaux. C'est l'unité de facturation et de cotisation. |
| **Bénéficiaire** | `Beneficiary` | Une **personne physique** rattachée à une adhésion (nom, prénom, téléphone, adresse). Une adhésion a 1 à N bénéficiaires ; l'un est le **bénéficiaire principal** (`mainBeneficiary`). |
| **Compte de connexion** | `User` (FOSUserBundle) | Identifiants (email/username, mot de passe, rôles). Relation **1-1** avec `Beneficiary`. Couvert en détail par SPEC.4 ; ici uniquement vu comme cible des flux d'onboarding. |
| **Pré-inscrit** | `AnonymousBeneficiary` | Personne en attente de finalisation d'adhésion : seul l'email est connu, pas encore de `User`/`Beneficiary`. Transformé en adhésion via lien d'invitation (code Vigenère). |

Une `Membership` sans bénéficiaire n'a pas de sens fonctionnel ; `getMainBeneficiary()` retombe automatiquement sur le premier bénéficiaire de la collection si `mainBeneficiary` n'est pas explicitement positionné (`Membership.php:328`).

**Acteurs** :
- **Anonyme** : onboarding public (find_me, find_member_number, confirm, set_email) + finalisation d'adhésion via lien d'invitation (member_new / member_add_beneficiary avec `code`).
- **ROLE_USER** (adhérent connecté) : voit/édite sa propre adhésion (via voter), demande un changement de gel (`freeze_change`), s'auto-réinscrit (`self_register`).
- **ROLE_USER_VIEWER** : consultation des fiches membres, post-its, liste des pré-inscrits.
- **ROLE_USER_MANAGER** : gel/dégel, fermeture/réouverture, statut volant, suppression de pré-inscrits.
- **ROLE_ADMIN** : fusion de comptes (`join`), tout ce qui précède.
- **ROLE_SUPER_ADMIN** : suppression d'adhésion, export CSV des emails.

**Instances** :
- **Toutes** : cycle de vie membre, bénéficiaires, notes, onboarding.
- **⚠️ Différence majeure OIDC (Scopeli)** : si `oidc_enable=true`, le `MembershipVoter` **refuse tout `canEdit`/`canView` non-admin** (`MembershipVoter.php:78,138`). La gestion d'identité est déléguée à Keycloak ; l'édition de membre par l'adhérent lui-même est désactivée. Chez Elefan (`oidc_enable=false`), l'adhérent peut éditer sa propre adhésion.
- **Volant/Fixe** : le concept `flying` n'a de sens que si `use_fly_and_fixed=true` (cross CONFIG.3, SPEC.3).
- **Cycle de cotisation** : `registration_every_civil_year` (cotisation calée sur l'année civile) vs `registration_duration` (durée glissante, ex. `1 year`) — détermine `getExpire()` (`MembershipService.php:84`).
- **Plafond bénéficiaires** : `maximum_nb_of_beneficiaries_in_membership` (cross D.5 finding 6 : valeurs `[1,2]` hardcodées dans le form de recherche).

---

### Sous-domaine 1 — Cycle de vie de l'adhésion (Membership)

**Flux principal — création d'une adhésion** (`member_new`, `MembershipController::newAction`) :
1. Deux points d'entrée : (a) **admin** authentifié avec droit `create` (voter → `PlaceIP::isLocationOk()`, restriction IP du local) ; (b) **anonyme** via lien d'invitation portant `?code=` (email chiffré Vigenère décodé → `AnonymousBeneficiary`).
2. Calcul du `member_number` : `findOneBy([], ['member_number'=>'DESC'])` + 1 — **dans le controller, non atomique** (AP.1 finding 2a).
3. Création `Membership` + `Beneficiary` principal + `User` + `Registration` initiale (montant/mode repris du pré-inscrit si Helloasso : montant `--`).
4. Dispatch `FOSUserEvents::REGISTRATION_SUCCESS` (création du compte, mail d'activation) puis `MemberCreatedEvent`.
5. Si le pré-inscrit déclarait des co-bénéficiaires (`beneficiaries_emails`), création d'un `AnonymousBeneficiary` par email avec `joinTo` = bénéficiaire principal + dispatch `AnonymousBeneficiaryCreatedEvent` (mail d'invitation à chacun). Le pré-inscrit source est supprimé.

**Flux — consultation** (`member_show`) : action « fourre-tout » de 131 lignes construisant **18 formulaires** (AP.1 finding 5). Accès via voter `view`, ou via **token temporaire** dans l'URL pour les non-admins (voir Règles métier).

**Règles métier** :
- `member_number <= 0` → redirection homepage (numéros réservés/techniques masqués) (`MembershipController.php:85`).
- **Statuts mutuellement documentés** : `withdrawn` (fermé), `frozen` (gelé), `frozenChange` (changement de gel demandé en fin de cycle), `flying` (volant). `setWithdrawn(false)` réinitialise `withdrawnDate` + `withdrawnBy` (`Membership.php:344`).
- **Gel/dégel** : `freeze`/`unfreeze` (manager) positionnent `frozen` et remettent `frozenChange=false`. `freeze_change` (adhérent sur sa propre adhésion, voter) bascule `frozenChange` — appliqué en fin de cycle. Après un `freeze_change` sur sa propre adhésion, redirection vers `fos_user_profile_show`, sinon vers la fiche membre.
- **Fermeture/réouverture** (`withdrawn`) : `withdrawn=true` exige `close`, `withdrawn=false` exige `open`. Trace `withdrawnDate` + `withdrawnBy` (utilisateur courant) à la fermeture manuelle.
- **Clôture automatique** (`app:member:close <delay>`, `CloseMembershipCommand`) : ferme les adhésions dont la cotisation n'est pas renouvelée après `delay`. Positionne `withdrawn=true`, `withdrawnDate=now`, `frozen=false`. **⚠️ `withdrawnBy` reste null** (`CloseMembershipCommand.php:62`, TODO D.5 finding 1) → impossible de distinguer clôture cron vs clôture manuelle.
- **Réinscription** (`member_new_registration`) : montant prix libre > 0 obligatoire ; un adhérent **ne peut pas enregistrer/modifier sa propre (ré)adhésion** ; la nouvelle date doit être postérieure à l'expiration courante. `getExpire()` dépend de `registration_every_civil_year` vs `registration_duration` (multi-instance).
- **Expiration / à jour** : `isUptodate()` = `getExpire() > today` ; `canRegister()` = expire dans < 28 jours (`MembershipService.php:72`, **28 hardcodé** — cross D.5 finding 3).
- **Suppression** (`member_delete`, SUPER_ADMIN) : cascade ORM sur registrations, beneficiaries, notes, proxies, timeLogs, exemptions (`Membership.php:80-126`).

**Flux — fusion de comptes** (`member_join`, ADMIN) : déplace tous les bénéficiaires de `fromMember` vers `destMember`, puis supprime `fromMember`. Garde-fous : comptes distincts, et somme des bénéficiaires ≤ plafond. **⚠️ Aucune transaction** (`flush()` en deux temps après `setMainBeneficiary(null)`) — AP.1 finding 2b ; risque d'état incohérent si le 2e flush échoue.

---

### Sous-domaine 2 — Bénéficiaires (Beneficiary)

**Flux principal** :
- **Ajout** (`member_new_beneficiary`, voter `BENEFICIARY_ADD` → `isLocationOk`) : valide d'abord `BeneficiaryCanHost` sur le principal ; si KO → invite à refaire une adhésion. Plafond `maximum_nb_of_beneficiaries_in_membership` vérifié (avec un **`<=` discutable** : la comparaison `count() <= max` autorise potentiellement max+1 — à confirmer). Dispatch `BeneficiaryAddEvent`.
- **Ajout depuis pré-inscrit** (`member_add_beneficiary`, anonyme via `code`) : le pré-inscrit doit avoir un `joinTo` ; rattache le nouveau bénéficiaire à l'adhésion cible et supprime le pré-inscrit. Redirige vers `fos_user_registration_check_email`.
- **Édition** (`beneficiary_edit`, voter `edit` sur l'adhésion).
- **Bénéficiaire principal** (`beneficiary_set_main`) : repositionne `mainBeneficiary`.
- **Détachement** (`beneficiary_detach`) : retire le bénéficiaire de l'adhésion et lui crée **sa propre nouvelle adhésion** (nouveau `member_number`). Refus sur le bénéficiaire principal.
- **Suppression** (`beneficiary_delete`, voter `edit` ; admin ou token temporaire).

**Règles métier** :
- `Beneficiary::isMain()` = identité avec `membership.mainBeneficiary` (`Beneficiary.php:390`).
- `Beneficiary::isNew()` = `shifts.count() <= 3` (**seuil hardcodé**, D.5 finding 10 ; utilisé par `CodeVoter` pour brider l'accès badge des débutants — cross SPEC.4).
- Relation `Beneficiary 1-1 User` **non nullable** (`user_id NOT NULL`, `Beneficiary.php:71`) : un bénéficiaire a toujours un compte de connexion (créé via FOSUserBundle).
- Champs `openid` / `openid_member_number` : rattachement OIDC (instance Scopeli, cross SPEC.4).

---

### Sous-domaine 3 — Notes internes (Note)

Deux usages d'une même entité `Note` :
- **Note de membre** : `subject` = `Membership`. Affichée sur la fiche membre. Création via `ambassador_new_note` (AmbassadorController, hors périmètre strict). Édition/réponse/suppression ici.
- **Post-it** (`subject = null`) : mémo libre de l'office, listé dans `user_office_tools`. Déduplication à la création (même auteur + même texte + subject null → refus).

**Routes** (⚠️ **double préfixe** `note` : la classe est `@Route("note")` et les méthodes `@Route("/note/{id}/...")` → chemins réels `/note/note/{id}/...`) :
- `note_reply` (POST, ROLE_USER_VIEWER) : crée une note enfant, hérite du `subject` et reprend l'arborescence (`parent`).
- `note_edit` (GET|POST, voter `edit`).
- `note_delete` (DELETE, voter `delete`).

**Données** : `Note(text, author→User, subject→Membership nullable, parent→Note nullable, children, createdAt)` — auto-référence pour les fils de réponses.

---

### Sous-domaine 4 — Onboarding & pré-inscrits (AnonymousBeneficiary)

**Flux principal — activation de compte (public)** :
1. `find_me` (`activeUserAccountAction`) : l'adhérent saisit son numéro → rend `confirm.html.twig` avec son bénéficiaire principal.
2. `find_member_number` : recherche par prénom (`findActiveFromFirstname`) → liste de bénéficiaires candidats, lien vers `confirm`.
3. `confirm` (POST, public, `id` = Beneficiary) : page de confirmation affichant nom + email (masqué si temporaire).
4. `set_email` (POST, **id = Beneficiary**) : si l'email courant est « temporaire » (`MailerService::isTemporaryEmail`) et le nouvel email valide → remplace l'email du `User`. Permet à l'adhérent pré-inscrit de fixer son vrai email.

**Flux — pré-inscription par l'office** :
- `user_quick_new` (ROLE_USER_VIEWER) : crée un `AnonymousBeneficiary` (email + co-bénéficiaires + montant/mode) → dispatch `AnonymousBeneficiaryCreatedEvent` (mail d'invitation avec lien `code`).
- `pre_user_index` : liste des pré-inscrits (tri `createdAt DESC`).
- `pre_user_recall` (ROLE_USER_VIEWER) : renvoie l'invitation, set `recallDate`. ⚠️ Redirige vers `referer` brut (open-redirect mineur potentiel).
- `pre_user_delete` (**GET**, ROLE_USER_MANAGER) : ⚠️ mutation via verbe GET (pas de CSRF, idempotence trompeuse).
- `user_self_register` (ROLE_USER) : page de ré-adhésion proposée si `canRegister()`.

**Données** : `AnonymousBeneficiary(email unique, beneficiaries_emails CSV, amount, mode, joinTo→Beneficiary nullable, registrar→User, createdAt, recallDate)`. Le lien `joinTo` distingue « nouvelle adhésion » (null) de « ajout à une adhésion existante » (non-null) — bifurcation gérée dans `newAction` (redirige vers `member_add_beneficiary`).

---

### Mécanisme transverse — token temporaire d'accès membre (sécurité)

Pour permettre à un adhérent **non-admin** d'accéder à sa fiche `member_show` et aux actions liées sans `ROLE_USER_MANAGER`, l'app génère un **token temporaire** passé en query param :
```
Membership::getTmpToken($key) = md5(id . member_number . $key . date('d'))
// $key = session('token_key') . user.username   (Membership.php:164)
```
- Vérifié dans `MembershipVoter::canEdit` (`MembershipVoter.php:152-156`).
- **Rotation quotidienne** (`date('d')` = jour du mois → en réalité rotation mensuelle cyclique, pas vraiment 24 h glissantes : un token est rejouable le même jour de chaque mois).
- Couplé à `session('token_key')` (régénéré à chaque passage par `member_edit_firewall`).
- **Note sécurité** : MD5 + composantes prévisibles (id, member_number séquentiels) ; la robustesse repose sur `token_key` (uniqid de session). À durcir (cross SPEC.4 / SEC.1).

---

### Données — récapitulatif des entités du domaine

| Entité | Champs clés | Relations |
|--------|-------------|-----------|
| `Membership` | `member_number` (bigint), `withdrawn`+`withdrawnDate`+`withdrawnBy`, `frozen`, `frozen_change`, `flying`, `firstShiftDate`, `createdAt` | 1-N `Registration`, 1-N `Beneficiary`, 1-1 `mainBeneficiary`, 1-N `Note`, 1-N `TimeLog`, 1-N `Proxy`(giver), 1-N `MembershipShiftExemption` |
| `Beneficiary` | `lastname`, `firstname`, `phone`, `flying`, `openid`, `openid_member_number`, `createdAt` | 1-1 `User` (NOT NULL), 1-1 `Address`, N-1 `Membership`, 1-N `Shift`, N-N `Commission`/`Task`/`Formation`, 1-N `SwipeCard`, 1-N `Proxy`(owner) |
| `AnonymousBeneficiary` | `email` (unique), `beneficiaries_emails`, `amount`, `mode`, `createdAt`, `recallDate` | 1-1 `joinTo`→`Beneficiary`, N-1 `registrar`→`User` |
| `Note` | `text`, `createdAt` | N-1 `author`→`User`, N-1 `subject`→`Membership`(nullable), self-ref `parent`/`children` |
| `Registration` | `date`, `amount`, `mode` (const TYPE_CASH/CHECK/LOCAL/CREDIT_CARD/HELLOASSO/DEFAULT) | N-1 `Membership`, N-1 `registrar`→`User`, 1-1 `HelloassoPayment` |

---

### Routes — inventaire complet (~30)

| Route | Méthode / chemin | Contrôle d'accès |
|-------|------------------|------------------|
| `member_show` | GET `/member/{member_number}/show` | voter `view` (ou token tmp) |
| `member_new` | GET\|POST `/member/new` | voter `create` (IP) **ou** anonyme via `code` |
| `member_new_registration` | GET\|POST `/member/{member_number}/newRegistration` | voter `edit` |
| `member_new_beneficiary` | GET\|POST `/member/{member_number}/newBeneficiary` | voter `beneficiary_add` (IP) |
| `member_add_beneficiary` | GET\|POST `/member/add_beneficiary` | **anonyme** via `code` |
| `member_edit_firewall` | GET\|POST `/member/edit` | ROLE_USER_VIEWER |
| `member_flying` | POST `/member/{id}/flying` | ROLE_USER_MANAGER + voter `flying` |
| `member_freeze` | POST `/member/{id}/freeze` | voter `freeze` |
| `member_unfreeze` | POST `/member/{id}/unfreeze` | voter `freeze` |
| `member_freeze_change` | POST `/member/{id}/freeze_change` | voter `freeze_change` |
| `member_withdrawn` | POST `/member/{id}/withdrawn` | ROLE_USER_MANAGER + voter `close`/`open` |
| `member_delete` | DELETE `/member/{id}` | ROLE_SUPER_ADMIN |
| `member_join` | GET\|POST `/member/join` | ROLE_ADMIN |
| `set_email` | POST `/member/{id}/set_email` (id=Beneficiary) | 🔴 **AUCUN** (SEC.2.1 + SEC.3.4) |
| `find_me` | GET\|POST `/member/find_me` | **public** |
| `user_office_tools` | GET\|POST `/member/office_tools` | ROLE_USER_VIEWER |
| `admin_emails_csv` | GET `/member/emails_csv` | ROLE_SUPER_ADMIN |
| `beneficiary_edit` | GET\|POST `/beneficiary/{id}/edit` | voter `edit` |
| `beneficiary_set_main` | GET `/beneficiary/{id}/set_main` | voter `edit` |
| `beneficiary_detach` | POST `/beneficiary/{id}/detach` | voter `edit` |
| `beneficiary_delete` | DELETE `/beneficiary/{id}` | voter `edit` (admin ou token) |
| `find_member_number` | GET\|POST `/beneficiary/find_member_number` | **public** |
| `confirm` | POST `/beneficiary/{id}/confirm` (id=Beneficiary) | **public** |
| `note_reply` | POST `/note/note/{id}/reply` | ROLE_USER_VIEWER |
| `note_edit` | GET\|POST `/note/note/{id}/edit` | voter `edit` |
| `note_delete` | DELETE `/note/note/{id}` | voter `delete` |
| `user_quick_new` | GET\|POST `/user/quick_new` | ROLE_USER_VIEWER |
| `pre_user_index` | GET `/user/pre_users` | ROLE_USER_VIEWER |
| `pre_user_recall` | GET `/user/pre_users/{id}/recall` | ROLE_USER_VIEWER |
| `pre_user_delete` | **GET** `/user/pre_users/{id}/delete` | ROLE_USER_MANAGER |
| `user_self_register` | GET `/user/self_register` | ROLE_USER |

**Chevauchements** : `member_new_registration` (cotisation → SPEC.5) ; intégration Helloasso dans `newAction`/`Registration::TYPE_HELLOASSO` (→ SPEC.5) ; `member_new`/`member_add_beneficiary` déclenchent l'auth FOSUserBundle (→ SPEC.4) ; tous les events de ce domaine alimentent les emails (→ SPEC.7).

---

### Tests existants

**`tests/Functional/Controller/MembershipControllerTest.php`** (15 tests) — couverture **surface HTTP** uniquement :
- `find_me` : 200, présence du champ, numéro inexistant.
- `office_tools` / `emails_csv` : matrice anonyme(302) / admin(200) / user(403).
- Routes admin GET 200 (`member_show`, `member_edit_firewall`, `member_join`).
- Routes redirigeant (`newRegistration`, `newBeneficiary`).
- `member_new` 200 admin ; `add_beneficiary` sans code → refus.
- Méthodes HTTP : 405 sur GET pour les routes POST-only (flying/freeze/unfreeze/freeze_change/withdrawn) et DELETE-only (`member_delete`).

**`tests/Unit/Service/BeneficiaryServiceTest.php`** (16 tests) : statut/icône/displayName/warning, `getCycleShiftDurationSum`, interaction fly_and_fixed. **N'est pas dans le périmètre controller mais couvre la logique d'affichage bénéficiaire.**

**`SmokeTest`** : `find_me`, `office_tools`, `emails_csv` (codes de retour).

---

### Gaps

**Sécurité (cross SEC)** :
- 🔴 `set_email` : aucune auth + aucun CSRF → **account takeover** (SEC.2.1 + SEC.3.4). Le plus grave du domaine.
- 🟡 `pre_user_delete` et `beneficiary_set_main` : mutations via **GET** (pas de CSRF, préchargeables).
- 🟡 Flow d'énumération (`find_member_number` → `confirm` → `find_me`) : recherche publique d'adhérents par prénom (SEC.2 finding 6, recommandation rate-limit).
- 🟡 `pre_user_recall` : redirection vers `referer` non validé.

**Cohérence / dette (cross AP.1, D.5)** :
- 🟠 `member_number` non atomique (race condition) — dupliqué dans `newAction` ET `beneficiary_detach`.
- 🟠 `joinAction` : fusion sans transaction.
- 🟠 `withdrawnBy` jamais renseigné par le cron de clôture (traçabilité perdue).
- 🟡 `showAction` : 18 formulaires dans une action ; pattern `// FIXME $member->getMainBeneficiary()->getUser()` triplé (Membership/Beneficiary/Note `redirectToShow`) — null-safety si pas de bénéficiaire principal (D.5 finding 9).
- 🟡 `BeneficiaryController::getErrorMessages()` : méthode privée morte (DC.4 B.2).
- 🟡 Plafond bénéficiaires : comparaison `count() <= max` dans `newBeneficiary` (off-by-one possible) à confirmer vs `count() >= max` dans `joinAction`.

**Non testé** :
- Aucun test ne **soumet** réellement les formulaires de cycle de vie (freeze/unfreeze/withdrawn/flying) ni ne vérifie les transitions d'état et la traçabilité (`withdrawnBy`, `withdrawnDate`).
- `member_join` (fusion), `beneficiary_detach` (création d'adhésion dérivée), `member_new` avec `code` (parcours pré-inscrit) : aucun test fonctionnel du comportement métier.
- `MembershipService::getExpire/isUptodate/canRegister` non testés alors qu'ils portent la logique multi-instance (`registration_every_civil_year`).
- `CloseMembershipCommand` (clôture cron) non testé.

**Ambigu / à documenter** :
- Comportement complet en mode `oidc_enable=true` (Scopeli) : quelles actions restent accessibles ? À confirmer en CONFIG.2 + SPEC.4.
- Double préfixe de route `note` (`/note/note/...`) : intentionnel ? Probable vestige de refactor.
- Sémantique exacte de `frozenChange` appliqué « en fin de cycle » : par quel mécanisme (commande cron ?) — à relier à SPEC.3 (cycles).

