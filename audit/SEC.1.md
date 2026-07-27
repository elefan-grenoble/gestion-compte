# SEC.1 — Configuration sécurité Symfony

- [x] **SEC.1** — Configuration sécurité Symfony

Lire `config/packages/security.yaml`. Firewalls, access_control, voters. Gaps → TODO.

**Fichiers lus** : `config/packages/security.yaml`, `config/packages/framework.yaml`, `src/EventListener/OidcFirewallListener.php`, `src/Helper/SwipeCard.php`, `src/Controller/ShiftController.php` (actions accept/reject/contactForm/widget), `src/Controller/DefaultController.php` (helloassoNotify), `src/Controller/BookingController.php` (bucket_show), `src/Controller/SwipeCardController.php` (swipe_in, qr, br), `src/Controller/CardReaderController.php`.

---

<a id="SEC.1-1"></a>
### 1. Absence de règle default-deny — modèle "opt-in" fragile (🔴)

La liste `access_control` couvre uniquement :
- `/oauth/v2/token` et `/oauth/v2/auth` (OAuth)
- `/login`, `/register`, `/resetting` (pages FOS publiques)
- `/admin/` → `ROLE_ADMIN_PANEL`
- `/api` → `IS_AUTHENTICATED_FULLY`

**Il n'existe aucune règle catch-all.** Toute route hors de ces préfixes est accessible à un utilisateur anonyme, sauf si le controller ajoute explicitement une annotation `@Security` ou un appel `denyAccessUnlessGranted()`. Ce modèle "opt-in" signifie qu'un controller sans annotation est silencieusement public. Le projet compte 42 controllers — la totalité de la surface d'exposition dépend de la vigilance des développeurs, sans filet de sécurité framework.

**Pattern recommandé** : ajouter une règle terminale `{ path: ^/, role: IS_AUTHENTICATED_REMEMBERED }` (ou `ROLE_USER`), puis créer des exceptions explicites pour les routes publiques connues (`^/$`, `^/login`, `^/sw/` pour les badges, `^/helloassoNotify`, etc.). Ce pattern "default-deny" est l'inverse de l'état actuel.

→ **TODO SYN.2** — effort M (audit de toutes les routes + ajout règle terminale + liste d'exceptions)

---

<a id="SEC.1-2"></a>
### 2. `switch_user` sans protection CSRF (🟠)

```yaml
switch_user:
  role: ROLE_ADMIN
  parameter: _login_as
```

La fonctionnalité d'impersonation est activée sur le firewall principal. Symfony 4.4 ne protège pas automatiquement les liens `switch_user` par CSRF — la protection est optionnelle (`check_csrf_token: true`). En l'état, n'importe quel lien GET avec `?_login_as=<username>` peut impersonater un utilisateur si l'admin est authentifié. Une faille XSS ou un lien piégé dans un email suffisent pour déclencher une impersonation.

Les templates utilisent `switch_user` comme liens GET standard (`beneficiary_card.html.twig:53` : `path('homepage', {'_login_as': beneficiary.user.username})`), sans formulaire POST ni token CSRF.

**Fix** : ajouter `check_csrf_token: true` dans la configuration `switch_user`. Le link Twig doit devenir un formulaire POST avec `{{ csrf_token('switch_user') }}`.

→ **TODO SYN.2** — effort XS (config + templates)

---

<a id="SEC.1-3"></a>
### 3. `/shift/{id}/contact_form` — envoi d'email sans authentification (🟠)

`ShiftController::contactFormAction` n'a ni `@Security` ni `denyAccessUnlessGranted`. Un utilisateur anonyme peut POST cette route et déclencher l'envoi d'un email via l'infrastructure SMTP de la coopérative. Le `$from` est extrait des données du formulaire et résolu via la DB, mais rien n'empêche un attaquant de fabriquer des requêtes répétées.

Vecteur : spam abuse de l'SMTP coopératif, usurpation partielle d'identité (le `from` visible dans l'email est le nom du bénéficiaire récupéré en DB, mais l'expéditeur est `transactional_mailer_user`).

**Fix** : ajouter `@Security("is_granted('ROLE_USER')")` sur l'action.

→ **TODO SYN.2** — effort XS

---

<a id="SEC.1-4"></a>
### 4. `/helloassoNotify` — webhook sans authentification ni rate-limit (🟠)

`DefaultController::helloassoNotify` (route `POST /helloassoNotify`) n'a aucune vérification d'authenticité. Le commentaire dans le code l'explique : Helloasso ne fournit la signature de webhook qu'aux "partenaires", donc le projet compense en refaisant un appel API pour vérifier le payload. Ce pattern est correct pour éviter les données forgées, mais :
- L'endpoint peut être spammé (chaque requête déclenche un appel API sortant vers Helloasso → DoS indirect via rate-limit de l'API Helloasso).
- Sans IP allowlist ni authentification basique, tout acteur peut interroger l'endpoint.

**Mitigation recommandée** : IP allowlist des serveurs Helloasso (documentés dans leur API) ou `Authorization: Bearer` secret partagé en attendant l'accès à la signature de webhook (disponible pour partenaires selon le commentaire).

→ **TODO SYN.2** — effort S (allowlist IP ou secret partagé)

---

<a id="SEC.1-5"></a>
### 5. `/shift/{id}/accept` et `/shift/{id}/reject` — voter seul comme guard (🟠)

Ces deux actions n'ont ni `@Security` ni `denyAccessUnlessGranted` préalable. La protection repose uniquement sur `$this->isGranted('accept'/'reject', $shift)` vérifié en milieu d'action, après que `$current_user` soit déjà extrait du `token_storage`. Pour un utilisateur anonyme :
- `getToken()->getUser()` retourne la chaîne `"anon."` (SF4 anonymous token)
- Si le `ShiftVoter` renvoie `ACCESS_ABSTAIN` pour un user anonyme, le résultat avec la stratégie `affirmative` et `allow_if_all_abstain: false` (défaut) est un refus — la protection tient.
- Mais ce comportement dépend d'une implémentation correcte du voter et d'une configuration non modifiée de l'access_decision_manager. C'est fragile.

En pratique : l'action `acceptReservedShiftAction` modifie un shift et dispatche un événement. La redirection avec flash error en cas de refus du voter est le seul filet visible. Un annotateur oublié dans un refactor futur pourrait briser ce filet.

**Fix** : ajouter `@Security("is_granted('ROLE_USER')")` sur les deux actions.

→ **TODO SYN.2** — effort XS

---

<a id="SEC.1-6"></a>
### 6. `has_role()` déprécié — supprimé en SF5 (🟠)

`HelloassoController.php:93` :
```php
@Security("has_role('ROLE_FINANCE_MANAGER')")
```
La fonction `has_role()` est **dépréciée depuis SF 4.0 et supprimée en SF5**. Le remplacement est `is_granted()`. Cette annotation ne compilera pas lors de la migration. C'est la seule occurrence dans le projet.

**Fix** : remplacer par `@Security("is_granted('ROLE_FINANCE_MANAGER')")`.

→ **TODO SYN.2** — effort XS

---

<a id="SEC.1-7"></a>
### 7. Chiffrement Vigenère pour les codes badge — non cryptographiquement sécurisé (🟠)

`src/Helper/SwipeCard.php` utilise le chiffre de Vigenère pour encoder/décoder les codes de badge dans les QR codes et URL. Deux problèmes cumulatifs :

**a) Vigenère n'est pas un chiffrement cryptographique**
- La clé est répétée cycliquement (`str_pad('', $length, $key)`). Si la clé est courte (ex. 16 chars) et les codes longs, la répétition est triviale à détecter via l'indice de coïncidence.
- La sécurité repose entièrement sur la confidentialité de `swipeCardSecret`. Si ce paramètre fuite (logs, erreur de config, compromission env), **tous les QR codes peuvent être forgés** — permettant l'accès physique à la coopérative (la route `/sw/in/{code}` authentifie l'utilisateur directement sans autre vérification).

**b) `rand()` au lieu de `random_int()` pour la génération des codes**
```php
$code = rand(0, pow(10, self::PADLENGTH));  // PADLENGTH = 8
```
`rand()` utilise le PRNG système (Mersenne Twister sur Linux) — **non cryptographiquement sécurisé**. Avec des timestamps connus, l'espace est prédictible. `random_int()` doit être utilisé.

**Note de contexte** : le QR code est scanné physiquement → le risque pratique dépend de la menace. Mais si un attaquant obtient un code badge légitime (ex. photo), il peut déduire la clé et forger d'autres codes.

**Remplacement recommandé** : HMAC-SHA256(`swipeCardSecret`, `code`) tronqué, ou un token aléatoire sécurisé (`random_bytes(16)`) stocké en DB. Effort M.

→ **TODO SYN.2** — effort M (remplacement cryptographique + migration des codes existants)

---

<a id="SEC.1-8"></a>
### 8. `KeycloakAuthenticator` actif même quand `OIDC_ENABLE=false` (🟡)

Le guard authenticator `App\Security\KeycloakAuthenticator` est enregistré inconditionnellement dans le firewall `main`. La note commentée (`#- keycloak_authenticator`) suggère une hésitation passée. Même avec `OIDC_ENABLE=false`, le `KeycloakAuthenticator` tente de gérer chaque requête. Si son `supports()` retourne vite `false` pour les requêtes sans contexte OIDC, l'overhead est négligeable, mais :
- C'est une surface de code active même sur les instances non-OIDC.
- Un bug futur dans `KeycloakAuthenticator` pourrait affecter Elefan (OIDC=false).

**Recommandation** : conditionner l'enregistrement du guard à `OIDC_ENABLE=true` (via `services.yaml` + tags conditionnels, ou deux fichiers de configuration d'environnement). Effort S.

→ **TODO SYN.2** — effort S (configuration conditionnelle)

---

<a id="SEC.1-9"></a>
### 9. `oauth_token` firewall — `security: false` (🟡)

Le firewall `oauth_token` (pattern `^/oauth/v2/token`) a `security: false` — toute la couche sécurité Symfony est désactivée pour ce endpoint. FOSOAuthServerBundle gère sa propre authentification client (client_id + client_secret dans le corps POST). Ce pattern est courant pour les OAuth token endpoints. Cependant, sans la couche Symfony, les listeners de sécurité (notamment `OidcFirewallListener`) ne s'y appliquent pas non plus. À documenter dans la configuration finale pour les mainteneurs.

---

<a id="SEC.1-10"></a>
### 10. Session — `cookie_secure` non configuré (🟡)

`config/packages/framework.yaml` — session configurée avec `name: USERSSID` et `cookie_domain: "%env(ROUTER_REQUEST_CONTEXT_HOST)%"`, mais **sans `cookie_secure`**. La valeur par défaut PHP est `false` — les cookies de session sont transmis en HTTP clair. Sur une instance HTTPS (production), cela expose le cookie à un attaquant capable d'intercepter le trafic HTTP (ex. réseau local).

**Fix** : ajouter `cookie_secure: auto` (Symfony transmet le cookie en HTTPS uniquement si la requête est HTTPS, sinon HTTP). Effort XS.

→ **TODO SYN.2** — effort XS

---

<a id="SEC.1-11"></a>
### 11. Encodeur `bcrypt` sans coût explicite (🟡)

```yaml
encoders:
  FOS\UserBundle\Model\UserInterface: bcrypt
```

Sans `cost:` spécifié, PHP utilise son défaut (10). Aucun problème en SF4 avec bcrypt. Lors de la migration SF5+ (remplacement FOSUserBundle), l'encodeur `bcrypt` deviendra un `password_hasher`. L'absence de `migrate_on_login: true` dans ce contexte signifie qu'un changement d'algorithme (vers Argon2id, recommandé) nécessiterait un rehash manuel. Anticiper ce besoin dans le plan de migration.

→ Note pour **SF-PREP** — aucune action immédiate

---

<a id="SEC.1-12"></a>
### 12. `bucket_show` — données d'adhérent conditionnellement exposées (🟡)

`BookingController::showBucketAction` (route `GET /booking/bucket/{id}/show`) est publique. Elle rend un partial de créneau. Le code conditionne `display_names` à l'authentification :
```php
'display_names' => !is_null($this->security->getUser())
```
Mais le partial lui-même (`booking/_partial/bucket.html.twig`) doit être vérifié pour s'assurer qu'aucune donnée personnelle n'est exposée en mode anonyme. À croiser avec **SEC.2**.

---

<a id="SEC.1-13"></a>
### 13. `swipe_in` — authentification par GET sans CSRF (🟡)

La route `GET /sw/in/{code}` authentifie directement un utilisateur en injectant un token Symfony. Une requête GET suffisant à déclencher l'authentification est vulnérable si le code est prévisible ou si une image embarquée dans une page externe peut déclencher l'accès (navigateurs chargent les images GET automatiquement). Voir finding 7 (Vigenère) pour la cryptographie des codes. Le risque pratique est limité par la possession physique du badge.

---

### Résumé

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🔴 Critique | Pas de règle default-deny en `access_control` — routes non annotées accessibles anonymement | M |
| 🟠 Important | `switch_user` sans CSRF — impersonation via lien GET cliquable | XS |
| 🟠 Important | `/shift/{id}/contact_form` — envoi email sans authentification | XS |
| 🟠 Important | `/helloassoNotify` — webhook sans auth ni rate-limit | S |
| 🟠 Important | `/shift/{id}/accept` et `reject` — voter seul comme guard, pas de `@Security` | XS |
| 🟠 Important | `has_role()` déprécié (SF5-removed) dans `HelloassoController` | XS |
| 🟠 Important | Vigenère + `rand()` pour les codes badge — non cryptographique | M |
| 🟡 Mineur | `KeycloakAuthenticator` actif même avec `OIDC_ENABLE=false` | S |
| 🟡 Mineur | `oauth_token` firewall `security: false` — à documenter | — |
| 🟡 Mineur | `cookie_secure` absent — sessions HTTP sur HTTPS possible | XS |
| 🟡 Mineur | Encodeur `bcrypt` sans coût explicite — à anticiper pour SF5 migration | — |
| 🟡 Info | `bucket_show` — exposition conditionnelle des noms (à vérifier en SEC.2) | — |
| 🟡 Info | `swipe_in` — auth GET sans CSRF (risque limité par possession physique badge) | — |

**Cross-références** :
- Trouver **SEC.2** pour compléter l'inventaire des routes sans vérification (gap access_control)
- Finding 7 (Vigenère) croise avec AP.2 (finding 2 — `UsernamePasswordToken` manuel)
- Finding 3 (`contact_form`) croise avec AP.1 (finding 2e — email construit inline)
- Finding OIDC Listener (finding 8) croise avec AP.7 (finding 5 — OidcFirewallListener)

