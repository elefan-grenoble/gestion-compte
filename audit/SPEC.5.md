# SPEC.5 — Spec : Cotisations & Paiements

- [x] **SPEC.5** — Spec : Cotisations & Paiements

Sources : `HelloassoController`, `RegistrationsController`, `HelloassoClient`, `HelloassoPaymentHandler`, `HelloassoEventListener`, `EmailingEventListener` (handlers helloasso), entités `HelloassoPayment`/`Registration`/`AbstractRegistration`, events `HelloassoEvent`, `HelloassoNotificationRequest`, `UpdateHelloAssoPaymentsCommand`, `HelloassoPaymentCommand`, `RegistrationType`, `MembershipService`.

#### Vocabulaire
- **Registration** : enregistrement d'une adhésion d'un `Beneficiary` — mode de paiement + montant + date. Peut être manuelle (CASH/CHECK/LOCAL) ou automatisée via Helloasso.
- **HelloassoPayment** : paiement reçu de la plateforme Helloasso, persisté localement avant liaison à une `Registration`.
- **Orphan payment** : `HelloassoPayment` sans `Registration` associée (email du payeur inconnu dans la base, ou bénéficiaire introuvable).
- **canRegister** : critère métier autorisant une nouvelle adhésion — vrai si l'adhésion courante expire dans moins de 28 jours.
- **TOO_EARLY** : état où un paiement Helloasso arrive mais où `canRegister` = false (adhésion encore trop loin de l'expiration).
- **Prolongement automatique** : si l'adhésion précédente n'est pas encore expirée au moment du paiement, la nouvelle date d'adhésion est fixée au lendemain de l'expiration (le membre ne « perd » pas de durée).

#### Acteurs
- **Membre** : initie le paiement sur Helloasso ; reçoit un email de confirmation ou un email « qui es-tu ? » (orphelin).
- **`ROLE_FINANCE_MANAGER`** : consulte/browse les paiements Helloasso, importe manuellement, édite les orphelins.
- **`ROLE_SUPER_ADMIN`** : supprime un `HelloassoPayment` (si non lié) ou une `Registration`.
- **Helloasso** : plateforme tierce — envoie un webhook POST sur `/helloassoNotify`, expose une API OAuth2 (Client Credentials).
- **Cron / CLI** : `app:member:update_payments` pour rattraper les paiements manqués ; `app:helloasso:payment` pour re-dispatcher un événement ou lister les orphelins.

#### Instances
- **Elefan** : Helloasso activé. Variables `HELLOASSO_*` définies (campaign slug, organization slug, client_id/secret, API URLs).
- **Scopeli** : vraisemblablement Helloasso désactivé. Toutes les variables Helloasso ont `default::` dans `services.yaml` → valeur vide si non définies. Si désactivé : webhook, browser, commands et flux automatique hors service. À confirmer.

#### Flux principal — Webhook (chemin nominal)

```
Membre paie sur Helloasso
       ↓
Helloasso POST /helloassoNotify  [PUBLIC — aucune auth requise]
  JSON: { eventType: "Payment", data: { id, state: "Authorized", ... } }
       ↓
HelloassoNotificationRequest::createFromRequest() → parse JSON
       ↓
isPaymentValidated() → true si eventType == "Payment" && state == "Authorized"
       ↓ [sinon: 200 OK sans traitement]
HelloassoClient::getPayment(paymentId)
  → re-fetch depuis API Helloasso (OAuth Bearer; org-scoped)
       ↓
HelloassoPaymentHandler::savePayments([payment])
  → findOneBy(['paymentId' => payment.id]) → skip si déjà en DB
  → createFromPayementObject() : amount / 100 (centimes → €), date, email, status
  → persist + flush
  → dispatch HelloassoEvent::PAYMENT_AFTER_SAVE
       ↓
HelloassoEventListener::onPaymentAfterSave()
  → findOneBy(['email' => strtolower(payment.email)])
  ┌── Trouvé → linkPaymentToUser()
  └── Non trouvé → email « qui es-tu ? » avec lien Vigenère encodé → ORPHAN flow
```

#### Flux — linkPaymentToUser (succès ou TOO_EARLY)

```
linkPaymentToUser(User, HelloassoPayment)
  → user.getBeneficiary() ?
    └── Non → throw LogicException 'user without beneficiary'  ← non rattrapée → 500
  → membership_service.canRegister(membership) ?
    ├── Non (expiration > 28j) → dispatch TOO_EARLY
    │     → EmailingEventListener::onHelloassoTooEarly()
    │           → renderView('emails/too_early_registration.html.twig') dans try/catch
    │           → die($e->getMessage()) si exception  ← BUG CRITIQUE (AP.7)
    │           → mailer.send()
    └── Oui → crée Registration (TYPE_HELLOASSO / mode=6)
          → date = expire+1j si encore valide, sinon payment.date
          → registration.createdAt = payment.date
          → si membership.withdrawn → setWithdrawn(false)  [réactivation auto]
          → persist + flush
          → dispatch RE_REGISTRATION_SUCCESS
                → EmailingEventListener::onHelloassoRegistrationSuccess()
                      → email « Re-adhésion » si registrations.count > 1
                      → email « Première adhésion » si count == 1
```

#### Flux — Résolution d'un orphelin (self-service)

```
Email envoyé au payeur → lien /helloasso/payment/{id}/resolve_orphan/{vigenere_code}
  (code = Vigenère(email du payeur))
       ↓
resolveOrphan() [GET, ROLE_USER]
  → vigenere_decode(code) == payment.email ?
    ├── Non → flash error, redirect homepage
    └── Oui, payment déjà lié → flash error, redirect homepage
        Oui, orphelin → affiche vue de confirmation
       ↓
confirmOrphan() [GET, ROLE_USER]  ← GET mutant, pas de CSRF
  → vigenere_decode(code) == payment.email ? → dispatch ORPHAN_SOLVE(payment, currentUser)
  → HelloassoEventListener::onOrphanSolve() → linkPaymentToUser(user, payment)
  [NB: ne vérifie PAS si payment.registration != null avant dispatch]
```

#### Flux — Import manuel (FINANCE_MANAGER)

```
GET /helloasso/browser → helloassoClient.getForms() → liste campagnes
GET /helloasso/browser/{formType}/{slug} → getFormPayments() + getFormDetails()
POST /helloasso/manualPaimentAdd/{paymentId} → getPayment() → savePayments([payment])
```

#### Flux — Commande CLI rattrapage

```
app:member:update_payments --delay='1 month'
  → getFormPayments('Membership', helloasso_campaign_slug, {from, page})
  → savePayments(results.data)
  → si page < totalPages → processPage(slug, from, page+1)  [récursif]
```

#### Flux — Enregistrement manuel (hors Helloasso)

```
GET/POST /{member_number}/newRegistration [MembershipVoter::EDIT]
  → RegistrationType form
  → modes (non-SUPER_ADMIN) : CASH(1), CHECK(2), LOCAL(3), HELLOASSO(6)
  → modes (SUPER_ADMIN) : CASH(1), CHECK(2), LOCAL(3) [TYPE_CREDIT_CARD commenté]
  → validations : montant > 0, date > expiration courante, pas d'auto-enregistrement
  → aucun événement dispatché (pas d'email de confirmation)

GET /registrations/ [FINANCE_MANAGER]
  → AbstractRegistration via vue SQL + SQL natif pour totaux par mode
  → filtrage date via query params 'from'/'to' (format Y-m-d)
```

#### Règles métier
1. `canRegister(membership)` : `getExpire() < now + 28 days`. Fenêtre de 28 jours non configurable (hardcodée).
2. `getExpire()` — deux modes selon le paramètre `registration_every_civil_year` :
   - **Annuel civil** : expire le 31 décembre de l'année de la dernière adhésion.
   - **Durée fixe** : `lastRegistration.date + registration_duration - 1 day`.
3. **Prolongement** : si l'adhésion précédente est encore valide à la date du paiement, la nouvelle date est `expire + 1 jour` (pas de perte de durée).
4. **Déduplication** : contrainte `UNIQUE` sur `helloasso_payment.payment_id` → un paiement Helloasso ne peut être enregistré qu'une fois.
5. **Montants** : API Helloasso v5 → centimes ; `createFromPayementObject` divise par 100. Les anciens mappings `fromActionObj`/`fromPaymentObj` (API v3) laissent les montants en euros (dead code).
6. **Suppression** : `HelloassoPayment` supprimable uniquement si `registration == null` (sinon flash error). `Registration` supprimable sauf si c'est la dernière du membership.
7. **Réactivation** : si `membership.withdrawn == true`, il est remis à `false` automatiquement sur un paiement Helloasso validé.
8. **Auto-enregistrement interdit** : le registrar ne peut pas être le membre lui-même pour `member_new_registration`.

#### Données

| Entité | Table | Champs clés |
|--------|-------|-------------|
| `HelloassoPayment` | `helloasso_payment` | `payment_id` UNIQUE, `amount` float, `email`, `status`, `campaign_id`, `registration_id` FK nullable (SET NULL on delete) |
| `Registration` | `registration` | `amount` **string**, `mode` int, `membership_id`, `registrar_id` (SET NULL), `created_at` |
| `AbstractRegistration` | `view_abstract_registration` | Vue SQL read-only ; `type` : 1=TYPE_MEMBER, 2=TYPE_ANONYMOUS |

Modes de paiement (`Registration.mode`) :

| Constante | Valeur | Origine | Exposé en formulaire |
|-----------|--------|---------|---------------------|
| TYPE_CASH | 1 | Manuel | Oui |
| TYPE_CHECK | 2 | Manuel | Oui |
| TYPE_LOCAL | 3 | Manuel (monnaie locale) | Oui |
| TYPE_CREDIT_CARD | 4 | Défini, commenté | Non (commenté dans form) |
| TYPE_DEFAULT | 5 | Provisioning OIDC | Non (assigné uniquement par `KeycloakAuthenticator::createMembership`, l.260 — pas via formulaire) |
| TYPE_HELLOASSO | 6 | Webhook / CLI | Oui (non-SUPER_ADMIN seulement) |

Variables d'env Helloasso (toutes `default::` → optionnelles) :
- `HELLOASSO_REGISTRATION_CAMPAIGN_URL` : URL publique de la campagne (rendue en Twig)
- `HELLOASSO_CAMPAIGN_SLUG` : slug utilisé par `UpdateHelloAssoPaymentsCommand`
- `HELLOASSO_CLIENT_ID`, `HELLOASSO_CLIENT_SECRET` : OAuth2 Client Credentials
- `HELLOASSO_ORGANIZATION_SLUG` : slug organisation pour les appels API
- `HELLOASSO_API_BASE_URL`, `HELLOASSO_API_AUTH_URL` : endpoints API

#### Cas limites
1. **User sans beneficiary** : `linkPaymentToUser` throw `LogicException('user without beneficiary')` non rattrapée → HTTP 500 lors du traitement webhook.
2. **`confirmOrphan` sur paiement déjà lié** : contrairement à `resolveOrphan` et `editPaymentAction`, `confirmOrphan` ne vérifie pas `payment.getRegistration()` avant de dispatcher `ORPHAN_SOLVE` → double-liaison potentielle.
3. **TOO_EARLY sans retry** : le paiement reste orphelin (sans Registration). Aucun mécanisme automatique de retry. Résolution manuelle via admin uniquement.
4. **Email dupliqué** : `findOneBy(['email' => ...])` retourne le premier résultat Doctrine (non déterministe si plusieurs `User` ont le même email).
5. **Webhook re-livré** : dédupliqué par `paymentId` → idempotent. Correct.
6. **Paiement non Authorized** : `isPaymentValidated()` = false → 200 OK sans traitement. Correct.
7. **Récursion `processPage`** : `UpdateHelloAssoPaymentsCommand` est récursive (appels imbriqués par page). Risque de stack overflow PHP sur de grands historiques.
8. **Amount type mismatch** : `Registration.amount` est une `string` en base ; `HelloassoPayment.amount` est un `float`. Le float est casté implicitement en string par MySQL au persist. Fragile selon la locale PHP (séparateur décimal).

#### Routes (13 + 1 cross-domaine)

| Nom | Méthode | URL | Accès | Contrôleur |
|-----|---------|-----|-------|-----------|
| `helloasso_notify` | POST | `/helloassoNotify` | **PUBLIC** (aucune auth) | `DefaultController` |
| `helloasso_payments` | GET | `/helloasso/payments` | ROLE_FINANCE_MANAGER | `HelloassoController` |
| `helloasso_browser` | GET | `/helloasso/browser` | ROLE_FINANCE_MANAGER | `HelloassoController` |
| `helloasso_campaign_details` | GET | `/helloasso/browser/{formType}/{slug}` | ROLE_FINANCE_MANAGER | `HelloassoController` |
| `helloasso_manual_paiement_add` | POST | `/helloasso/manualPaimentAdd/{paymentId}` | ROLE_FINANCE_MANAGER | `HelloassoController` |
| `helloasso_payment_remove` | DELETE | `/helloasso/payments/{id}` | ROLE_SUPER_ADMIN | `HelloassoController` |
| `helloasso_payment_edit` | GET/POST | `/helloasso/payment/{id}/edit` | ROLE_FINANCE_MANAGER | `HelloassoController` |
| `helloasso_resolve_orphan` | GET | `/helloasso/payment/{id}/resolve_orphan/{code}` | ROLE_USER | `HelloassoController` |
| `helloasso_confirm_resolve_orphan` | GET | `/helloasso/payment/{id}/confirm_resolve_orphan/{code}` | ROLE_USER | `HelloassoController` |
| `helloasso_orphan_exit_and_back` | GET | `/helloasso/payment/{id}/orphan_exit_and_back/{code}` | ROLE_USER | `HelloassoController` |
| `registrations` | GET/POST | `/registrations/` | ROLE_FINANCE_MANAGER | `RegistrationsController` |
| `registration_edit` | GET/POST | `/registrations/{id}/edit` | ROLE_FINANCE_MANAGER | `RegistrationsController` |
| `registration_remove` | DELETE | `/registrations/{id}/remove` | ROLE_SUPER_ADMIN | `RegistrationsController` |
| `member_new_registration` | GET/POST | `/{member_number}/newRegistration` | MembershipVoter::EDIT | `MembershipController` (cross SPEC.2) |

#### Événements

| Événement | Constante | Déclencheur | Listener |
|-----------|-----------|-------------|---------|
| `helloasso.payment_after_save` | `PAYMENT_AFTER_SAVE` | Après persist d'un paiement (webhook ou CLI) | `HelloassoEventListener::onPaymentAfterSave` → link ou email orphelin |
| `helloasso.orphan_solve` | `ORPHAN_SOLVE` | Admin ou self-service user résout l'orphelin | `HelloassoEventListener::onOrphanSolve` → linkPaymentToUser |
| `helloasso.registration_success` | `RE_REGISTRATION_SUCCESS` | Après création d'une Registration Helloasso | `EmailingEventListener::onHelloassoRegistrationSuccess` → email confirmation |
| `helloasso.too_early` | `TOO_EARLY` | canRegister() = false | `EmailingEventListener::onHelloassoTooEarly` → email (BUG: die) |

#### Tests existants
- **Zéro test** sur le domaine Helloasso : aucun fichier de test ne couvre `HelloassoController`, `HelloassoEventListener`, `HelloassoPaymentHandler`, `HelloassoClient`, `HelloassoNotificationRequest`.
- **`SmokeTest`** : GET `/registrations/` → 200 OK (présence de route uniquement, sans données).
- **`MembershipServiceTest`** : teste `canRegister` et `getExpire` (couverture partielle de la fenêtre de réenregistrement).
- **Aucun test** sur `UpdateHelloAssoPaymentsCommand`, `HelloassoPaymentCommand`, `RegistrationType`.

#### Gaps / Findings

**Sécurité** :
- 🔴 **Webhook `/helloassoNotify` non authentifié** : accessible sans authentification (firewall `main` avec `anonymous: true`, aucune règle `access_control`). Le commentaire dans le code reconnaît que la vérification de signature Helloasso n'est pas implémentée (réservée aux partenaires). La mitigation actuelle — re-fetch du paiement depuis l'API Helloasso après réception — empêche l'injection de données forgées, mais : (a) n'importe qui peut déclencher le re-traitement d'un `paymentId` connu, (b) n'importe qui peut spammer l'endpoint et épuiser le rate-limit API Helloasso.
- 🟠 **`confirmOrphan` est une route GET qui mute l'état** sans protection CSRF. Le token Vigenère offre une validation faible (algorithme symétrique public, sans secret serveur) — quiconque obtient l'URL peut confirmer à la place du payeur.
- 🟡 **Vigenère** : même schéma que `code_change` (SPEC.4). Clé symétrique, algorithme réversible sans secret, ne constitue pas une signature cryptographique.

**Bugs** :
- 🔴 **`die($e->getMessage())`** dans `EmailingEventListener::onHelloassoTooEarly()` (l.257) : tue le processus PHP sur toute exception pendant le rendu du template `too_early_registration.html.twig`. Confirmé AP.7. Expose le message d'exception dans la réponse HTTP. À remplacer par log + propagation correcte.
- 🟠 **`linkPaymentToUser` : `LogicException('user without beneficiary')`** non rattrapée → HTTP 500 lors du traitement webhook. L'exception n'est pas loggée avant propagation.
- 🟠 **`confirmOrphan` ne vérifie pas `payment.getRegistration()`** avant dispatch → double-liaison potentielle si l'orphelin a été résolu entre-temps par l'admin ou un autre chemin.
- 🟡 **`processPage` récursive** : `UpdateHelloAssoPaymentsCommand` s'appelle récursivement par page. Risque de stack overflow PHP sur grand historique ; pattern itératif préférable.

**Code / dette** :
- 🟠 **Dead code : `fromActionObj()` et `fromPaymentObj()`** dans `HelloassoPayment` : deux anciens mappings (API Helloasso v3) abandonnés au profit de `createFromPayementObject()` (v5). À supprimer après vérification dans les migrations.
- 🟠 **`TYPE_CREDIT_CARD` (4)** : défini dans les constantes `Registration`, jamais assigné en production (formulaire commenté, aucun dispatch système). Dead code probable — à confirmer avant suppression. *(`TYPE_DEFAULT` (5), initialement suspecté dead code également, est en réalité assigné par `KeycloakAuthenticator::createMembership` lors du provisioning OIDC — confirmé en SPEC.8, ne pas supprimer.)*
- 🟡 **`Registration.amount` stockée en `string`** : incohérence de type avec `HelloassoPayment.amount` (`float`). La conversion implicite float→string dépend de la locale PHP.
- 🟡 **`UpdateHelloAssoPaymentsCommand` : `formType` hardcodé à `'Membership'`** et une seule `helloasso_campaign_slug`. Ne supporte pas plusieurs campagnes. Pas de mode `--dry-run`.
- 🟡 **`canRegister` fenêtre de 28 jours hardcodée** : `new \DateTime('+28 days')` non exposé en configuration.

**Non testé** :
- Flux webhook complet : parsing, dédup, `linkPaymentToUser`, TOO_EARLY, orphan.
- `HelloassoClient` : authentification OAuth, `getForms`, `getFormPayments`, `getPayment`.
- `HelloassoPaymentHandler` : idempotence de `savePayments`, dispatch d'événement.
- `UpdateHelloAssoPaymentsCommand` : pagination récursive, gestion d'erreur API.
- `registrations` : SQL natif (totaux agrégés par mode), filtrage date, pagination.
- `helloasso_resolve_orphan` / `helloasso_confirm_resolve_orphan` : validation Vigenère, cas orphelin déjà résolu.

**Ambigu / à clarifier** :
- ~~`view_abstract_registration` : quelle requête SQL sous-jacente ?~~ **Résolu** : migration `Version20190214200309.php` — `UNION ALL` de `registration` (type=1, TYPE_MEMBER) et `anonymous_beneficiary` (type=2, TYPE_ANONYMOUS, une ligne par bénéficiaire anonyme, sans jointure `Membership`).
- Scopeli : Helloasso activé ou non ? Si oui, même campagne ou slug différent ?
- `member_new_registration` dispatche-t-il un événement `member_new_registration` ? Non vu dans `MembershipController::newRegistration` — l'event du même nom semble dispatché ailleurs (cross SPEC.2 à confirmer).

