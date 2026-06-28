# SEC.3 — CSRF

- [x] **SEC.3** — CSRF

`grep -rn "csrf_protection.*false\|'csrf'.*false" src/`. Formulaires non protégés → TODO.

**Fichiers lus** : `config/packages/framework.yaml`, `config/packages/security.yaml`, `src/Controller/ShiftController.php`, `src/Controller/CardReaderController.php`, `src/Controller/MembershipController.php`, `src/Controller/SwipeCardController.php`, `src/Controller/ProcessUpdateController.php`, `src/Controller/HelloassoController.php`, `src/Controller/BeneficiaryController.php`, `src/Controller/AmbassadorController.php`, `assets/js/barcode.js`, `templates/booking/_partial/shift_alone.html.twig`, `templates/booking/index.html.twig`, `templates/beneficiary/confirm.html.twig`, `templates/swipeCard/_partial/add_modal.html.twig`, `templates/swipeCard/_partial/list.html.twig`, `templates/admin/helloasso/browser.html.twig`.

---

<a id="SEC.3-1"></a>
### 1. Protection CSRF Symfony active — périmètre couvert (✅)

`framework.yaml` : `csrf_protection: ~` (activé avec les paramètres par défaut). La configuration `security.yaml` active `csrf_token_generator: security.csrf.token_manager` pour les firewalls `form_login`.

**Conséquence** : tout formulaire créé via `$this->createForm(Type::class)` ou `$this->createFormBuilder()->getForm()` inclut automatiquement un champ `_token` vérifié lors de `$form->isValid()`. Ce pattern couvre la très grande majorité des actions avec état : toutes les routes `DELETE` (via `createDeleteForm()`), les routes `freeze`, `unfreeze`, `withdrawn`, `flying`, `free`, `validate_admin`, `lock/unlock`, etc. (~30+ actions protégées).

La suite de ce finding documente les **exceptions** — routes qui échappent à ce mécanisme.

---

<a id="SEC.3-2"></a>
### 2. `shift_book` (POST `/shift/{id}/book`) — endpoint JSON sans CSRF (🟡)

`ShiftController::bookShiftAction` (ligne 139) lit le corps de la requête directement :
```php
$content = json_decode($request->getContent());
$beneficiaryId = $content->beneficiaryId;
```
Aucun formulaire Symfony, aucun token CSRF.

**Templates** :
- `booking/_partial/shift_alone.html.twig` (ligne 9) : formulaire HTML brut `<form method="post">` sans `{{ csrf_token() }}`, envoie `beneficiaryId` en form-encoded. → **Broken** : le controller attend du JSON et ignorera ce payload (le `json_decode` retournera `null`). Ce formulaire semble non fonctionnel (letoff buggy).
- `booking/index.html.twig` (ligne 229) : XHR `xhttp.send(JSON.stringify(body))` — pas de header `X-CSRF-Token`.

**Exploitabilité réduite** : un attaquant cross-site ne peut pas envoyer un `Content-Type: application/json` sans déclencher un preflight CORS (les navigateurs modernes bloquent les requêtes cross-origin avec Content-Type non simple). Toutefois :
- Aucun SameSite explicite sur le cookie de session (finding 7 ci-dessous).
- Le formulaire `shift_alone.html.twig` soumet en form-encoded vers un endpoint JSON → incohérence qui mérite correction indépendamment du CSRF.

→ **TODO SYN.2** — effort XS (ajouter vérification CSRF côté controller ou passer via Symfony Form ; corriger le formulaire broken)

---

<a id="SEC.3-3"></a>
### 3. `card_reader_check` (POST `/card_reader/check`) — formulaire JS sans CSRF (🟡)

`CardReaderController::checkAction` (ligne 62) lit le code badge via `$request->get('swipe_code')` — aucun formulaire Symfony, aucun token CSRF.

Le fichier `assets/js/barcode.js` crée dynamiquement un formulaire HTML au runtime :
```js
var form = $('<form ... action="' + barcode_submit_url + '" method="post">' +
    '<input type="text" name="swipe_code" value="' + barcode + '" />' +
    '</form>');
```
Aucun token CSRF dans ce formulaire généré.

**Action** : valide la participation d'un adhérent à un créneau en cours (`shift.validateShiftParticipation()` + `em->flush()`). Écriture en base.

**Contexte atténuant** : l'accès à la page card_reader est contrôlé par le voter `card_reader`, et le badge EAN-13 valide doit être connu de l'attaquant. La surface est donc limitée au terminal dédié. Mais si ce terminal est utilisé dans un navigateur généraliste, un attaquant qui connaît un code badge valide (prédictible via `rand()` — see SEC.1 finding 7) peut forger la requête.

Cross-référence : SEC.2 finding 2 pour l'absence d'auth, SEC.1 finding 7 pour la prévisibilité des codes.

→ **TODO SYN.2** — effort XS (ajouter `csrf_token` au formulaire JS, valider côté controller)

---

<a id="SEC.3-4"></a>
### 4. `set_email` (POST `/member/{id}/set_email`) — CSRF exploitable (🔴)

`MembershipController::setEmailAction` (ligne 425) lit l'email directement depuis le body :
```php
$email = $request->request->get('email');
$user->setEmail($email);
$em->flush();
```
Aucun formulaire Symfony, aucun token CSRF.

Template `beneficiary/confirm.html.twig` (ligne 106) :
```html
<form action="{{ path('set_email', {'id': beneficiary.id}) }}" method="post">
    <input type="email" name="email" placeholder="mon-email@..." />
    <button type="submit">Définir mon email</button>
</form>
```

**Attaque** : une page externe forge un formulaire POST vers `/member/{id}/set_email` avec un email contrôlé par l'attaquant. Si la victime est authentifiée et clique (ou si la page soumet automatiquement via JS), l'email en base est remplacé. L'attaquant peut ensuite déclencher un reset de mot de passe sur l'email qu'il contrôle → **account takeover**.

Note : l'`id` dans l'URL est l'ID Beneficiary, que l'attaquant doit connaître. Ces IDs sont séquentiels et exposés dans les URLs du profil.

Cross-référence : SEC.2 finding 1 (même action, flaggée pour l'absence de contrôle d'authentification robuste).

→ **TODO critique SEC-CSRF-1** — effort XS (convertir en Symfony Form avec CSRF, ou `isCsrfTokenValid()` explicite)

---

<a id="SEC.3-5"></a>
### 5. Badges SwipeCard — 4 routes POST sans CSRF (🟠)

Quatre actions dans `SwipeCardController` lisent les paramètres directement depuis `$request->get()` sans Symfony Form ni token CSRF :

| Route | Action | Effet |
|-------|--------|-------|
| POST `/swipe_card/activate` | `activateSwipeCardAction` | Associe un badge à un bénéficiaire |
| POST `/swipe_card/enable` | `enableSwipeCardAction` | Réactive un badge existant |
| POST `/swipe_card/disable` | `disableSwipeCardAction` | Désactive un badge |
| POST `/swipe_card/delete` | `deleteAction` (ROLE_ADMIN) | Supprime un badge |

Templates correspondants (`swipeCard/_partial/add_modal.html.twig`, `_partial/list.html.twig`, `_partial/disable_modal.html.twig`) : formulaires HTML bruts, aucun token CSRF.

**Risque** : un attaquant peut désactiver le badge d'une victime (déni de service sur l'accès coopératif) ou associer son propre badge à un compte victime. Nécessite l'ID bénéficiaire (séquentiel) et, pour `activate`, un code EAN-13 valide.

→ **TODO SYN.2** — effort S (4 actions + 3 templates à corriger)

---

<a id="SEC.3-6"></a>
### 6. `helloasso_manual_paiement_add` (POST) — sans CSRF, admin uniquement (🟡)

`HelloassoController::helloassoManualPaimentAddAction` (ligne 120) : `$request->get("formType")` et `$request->get("slug")` sont lus sans formulaire Symfony. Le vrai traitement (`getPayment($paymentId)`) est récupéré côté API HelloAsso (le `paymentId` vient de l'URL, pas du body), donc le risque concret est limité à forcer l'enregistrement d'un paiement existant dans la base.

Accès : `@Security("is_granted('ROLE_FINANCE_MANAGER')")` — exposition réduite aux gestionnaires financiers.

Template `admin/helloasso/browser.html.twig` (ligne 78) : formulaire HTML brut, pas de token CSRF.

→ **TODO SYN.2** — effort XS

---

<a id="SEC.3-7"></a>
### 7. Session `cookie_samesite` non configuré — défense en profondeur absente (🟡)

`framework.yaml` session :
```yaml
session:
    handler_id: session.handler.native_file
    name: USERSSID
    cookie_domain: "%env(ROUTER_REQUEST_CONTEXT_HOST)%"
```
Absence de `cookie_samesite: lax` (ou `strict`).

En 2024+, les navigateurs Chromium appliquent `SameSite=Lax` par défaut aux cookies sans attribut explicite — ce qui atténue les CSRF classiques sur les navigateurs modernes. Mais :
- Pas de garantie sur les navigateurs anciens (terminaux card-reader).
- `Lax` autorise les navigations top-level GET mais ne couvre pas les POSTs cross-site déclenchés sans navigation.
- L'absence d'un attribut explicite signifie que le comportement dépend du navigateur, pas de la configuration serveur.

**Fix** : ajouter `cookie_samesite: lax` (ou `strict`) dans `framework.yaml`. Complément, pas substitut, aux tokens CSRF.

→ **TODO SYN.2** — effort XS (une ligne de config)

---

<a id="SEC.3-8"></a>
### 8. `process_update_count_unread` (POST) — read-only, devrait être GET (🔵 Info)

`ProcessUpdateController::countUnreadAction` — route POST qui ne fait que lire un compteur (SELECT). Pas d'écriture en base, donc pas de risque CSRF proprement dit. Mais sémantiquement incohérent : une opération idempotente de lecture ne devrait pas utiliser POST.

Le AJAX dans `layout.html.twig` envoie `date` en POST alors qu'un GET avec query param serait plus correct et plus cacheable.

→ **TODO SYN.2** — effort XS (changer en GET + query param)

---

### Résumé SEC.3

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🔴 Critique | `set_email` POST — CSRF exploitable → account takeover | XS |
| 🟠 Important | SwipeCard (activate/enable/disable/delete) — 4 routes sans CSRF, manipulation badges | S |
| 🟡 Mineur | `card_reader_check` — formulaire JS sans CSRF (atténué : voter + code EAN13) | XS |
| 🟡 Mineur | `helloasso_manual_paiement_add` — sans CSRF (atténué : ROLE_FINANCE_MANAGER) | XS |
| 🟡 Mineur | `shift_book` — JSON sans CSRF (atténué : CORS preflight ; formulaire template broken) | XS |
| 🟡 Mineur | Session sans `cookie_samesite` — défense en profondeur manquante | XS |
| 🔵 Info | `process_update_count_unread` POST read-only → devrait être GET | XS |
| ✅ OK | ~30+ routes avec état — protégées via Symfony Form CSRF automatique | — |

**Cross-références** :
- Finding 4 (`set_email`) croise SEC.2 finding 1 (même action, auth insuffisante)
- Finding 3 (`card_reader_check`) croise SEC.2 finding 2 (même action, auth insuffisante) et SEC.1 finding 7 (codes prédictibles)
- Finding 2 (`shift_book`) : le formulaire broken dans `shift_alone.html.twig` mérite un TODO séparé (bug fonctionnel, pas seulement sécurité)

