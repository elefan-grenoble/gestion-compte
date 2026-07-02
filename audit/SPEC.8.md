# SPEC.8 — Spec : API & Intégrations externes

- [x] **SPEC.8** — Spec : API & Intégrations externes

Sources : `ApiController`, `OAuthController`, `OAuthEventListener`, `OidcFirewallListener`, `OidcLogoutHandler`, `KeycloakAuthenticator`, `DefaultController::helloassoNotify`, `src/Providers/` (`OauthAuthenticatorInterface`, `ClientCredentialOauthAuthenticator`, `CacheOauthAuthenticatorDecorator`, `Helloasso/HelloassoClient`, `Igloohome/IgloohomeClient`), entités OAuth (`Client`, `AccessToken`, `AuthCode`, `RefreshToken`), config `fos_oauth_server.yaml`, `knpu_oauth2_client.yaml`, `security.yaml`, paramètres `services.yaml` (`helloasso_*`, `igloohome_*`, `oidc_*`).

#### Note d'orientation — trois rôles OAuth distincts
La confusion la plus fréquente sur ce domaine : « OAuth » recouvre **trois mécaniques indépendantes**, chacune avec sa lib et son sens de circulation. Tout le reste de la spec s'organise autour de cette distinction.

| # | Rôle de gestion-compte | Lib / Bundle | Sens | Sert à |
|---|------------------------|--------------|------|--------|
| **1** | **Serveur OAuth2** (Identity Provider) | `FOSOAuthServerBundle` | gestion-compte **expose** son identité à des apps tierces | SSO sortant : Nextcloud, GitLab-like, etc. se connectent **avec** un compte gestion-compte |
| **2** | **Client OIDC** (Relying Party) | `knpu/oauth2-client` + `KeycloakAuthenticator` | gestion-compte **délègue** son login à Keycloak | SSO entrant : les membres se connectent à gestion-compte **via** Keycloak |
| **3** | **Client OAuth2 `client_credentials`** | `league/oauth2-client` (`src/Providers/`) | gestion-compte **consomme** des API tierces | Appels machine-à-machine sortants vers Helloasso & Igloohome |

Rôles 1 et 2 sont **mutuellement exclusifs en pratique** : quand `OIDC_ENABLE=true` (rôle 2 actif), `OidcFirewallListener` redirige `/login → /oauth/login` (Keycloak) et le serveur OAuth2 exposé (rôle 1) n'est plus le point d'entrée d'authentification — il reste techniquement monté mais sans usage documenté. Rôle 3 est orthogonal et coexiste avec n'importe quel mode d'auth.

#### Vocabulaire
- **Serveur OAuth2 / fournisseur d'identité (rôle 1)** : gestion-compte agit comme `authorization server`. Un `Client` (app tierce enregistrée) demande une autorisation ; le membre consent ; un `AccessToken` est émis ; l'app interroge `/api/*` au nom du membre.
- **`Client` (entité OAuth)** : application tierce enregistrée (CRUD admin en SPEC.6). Possède `redirectUris`, `randomId`, `secret`, et une relation `ManyToMany` avec les `User` qui l'ont autorisée (consentement persisté). Optionnellement rattaché à un `Service` (lien métier SPEC.6).
- **Scope `oauth_login`** : seul scope supporté (`fos_oauth_server.yaml`). Un token portant ce scope confère `ROLE_OAUTH_LOGIN`.
- **`ROLE_OAUTH_LOGIN`** : rôle accordé **dynamiquement** par le firewall `fos_oauth` à partir du scope du token — **jamais stocké en base sur le `User`** (résolution de l'EXTRA SPEC.4, voir Règles métier §R4). Dans la hiérarchie : `ROLE_OAUTH_LOGIN → ROLE_USER`.
- **Client OIDC / Relying Party (rôle 2)** : gestion-compte délègue l'authentification à Keycloak. `KeycloakAuthenticator` (un `SocialAuthenticator` KnpU) reçoit le jeton Keycloak, en extrait le `KeycloakResourceOwner`, puis crée/met à jour le `Beneficiary` local.
- **Provisioning OIDC** : à chaque login Keycloak, le `Beneficiary` est intégralement re-synchronisé depuis les claims Keycloak (identité, adresse, rôles, formations, commissions, co-adhésion). **Keycloak fait autorité** : tout rôle/formation local est écrasé (`setRoles([])` puis re-population — voir §R6, finding majeur).
- **`openid` / `openIdMemberNumber`** : champs du `Beneficiary` liant un compte local à une identité Keycloak (clé d'appariement = `keycloakUser->getId()` ; numéro d'adhérent porté par le claim).
- **Client `client_credentials` (rôle 3)** : `ClientCredentialOauthAuthenticator` obtient un jeton machine via le grant `client_credentials` (`league/oauth2-client` `GenericProvider`), mis en cache par `CacheOauthAuthenticatorDecorator`. Consommé par `HelloassoClient` et `IgloohomeClient`.
- **Webhook Helloasso** : POST entrant non authentifié sur `/helloassoNotify` (détail du flux en SPEC.5 ; ici on documente l'**infrastructure** et la posture de sécurité).

#### Acteurs
- **App tierce (OAuth client)** : Nextcloud, outil GitLab-compatible, ou tout `Client` enregistré. Consomme `/api/oauth/user`, `/api/oauth/nextcloud_user`, `/api/v4/user`, `/api/swipe/in` avec un `AccessToken`.
- **Membre (`ROLE_USER`)** : autorise un `Client` (consentement) ; ou se connecte via Keycloak (OIDC). Côté API, son identité est exposée aux apps tierces qu'il a autorisées.
- **Keycloak** : fournisseur d'identité externe (rôle 2). Émet les jetons, porte les claims rôles/formations/commissions, gère le logout global.
- **Helloasso** : plateforme de paiement (rôle 3 sortant + webhook entrant). API OAuth2 `client_credentials`.
- **Igloohome** : serrures connectées (rôle 3 sortant uniquement). API OAuth2 `client_credentials`. **Aucune route HTTP** — piloté exclusivement par CLI (`app:code:update_igloohome`, cross SPEC.4).
- **`ROLE_PREVIOUS_ADMIN`** (impersonation / login-as) : explicitement **interdit** d'accès aux endpoints OAuth identité (`api_user`, `api_nextcloud_user`, `api_gitlab_user`) — garde « DO NOT ALLOW OAUTH ON LOGIN AS ».

#### Instances
- **Elefan** : Helloasso activé (`HELLOASSO_*` définies). OIDC vraisemblablement **désactivé** (`OIDC_ENABLE=false`) → auth locale FOSUserBundle + serveur OAuth2 exposé potentiellement utilisé. Igloohome : à confirmer (serrures du local).
- **Scopeli** : OIDC vraisemblablement **activé** (Keycloak) → login délégué, routes de gestion de compte local désactivées par `OidcFirewallListener`. Helloasso/Igloohome à confirmer.
- **Toutes** : tous les paramètres `helloasso_*`/`igloohome_*`/`oidc_*` utilisent `%env(default::VAR)%` → valeur vide si non définie, dégradation gracieuse (cf. AP.9). Les warnings `debug:router` sur `HelloassoClient`/`IgloohomeClient` (SPEC.1) confirment cette instance-ci sans intégration configurée.

#### Flux principal — Rôle 1 : serveur OAuth2 (gestion-compte fournit l'identité)

```
App tierce (Nextcloud…) veut authentifier un membre
       ↓
Redirige vers  GET /oauth/v2/auth?client_id=…&redirect_uri=…&response_type=code&scope=oauth_login
       ↓  [firewall oauth_authorize : login form si non authentifié]
Membre se connecte (FOSUser) puis voit l'écran de consentement
       ↓
fos_oauth_server.pre_authorization_process  → OAuthEventListener::onPreAuthorizationProcess
       ↓  setAuthorizedClient( user.isAuthorizedClient(client) )   // déjà consenti ?
Membre approuve
       ↓
fos_oauth_server.post_authorization_process → OAuthEventListener::onPostAuthorizationProcess
       ↓  user.addClient(client) + persist   // consentement mémorisé (M2M user↔client)
Redirection vers redirect_uri avec ?code=…
       ↓
App tierce  POST /oauth/v2/token  (code → access_token)   [firewall security:false]
       ↓
App tierce  GET /api/oauth/user (ou nextcloud_user / v4/user)  Authorization: Bearer <token>
       ↓  [firewall api : fos_oauth, stateless, ROLE_OAUTH_LOGIN via scope]
JSON { email, username, … }
```

Trois représentations d'identité selon le consommateur :
- **`/api/oauth/user`** : minimal — `{ email, username }`.
- **`/api/oauth/nextcloud_user`** : format Nextcloud — `{ email, displayName, identifier, groups[] }` (les `groups` = noms des `Group` FOSUser du membre).
- **`/api/v4/user`** : **shim API GitLab v4** — renvoie un objet utilisateur GitLab avec de **nombreux champs codés en dur / factices** (`created_at: "2012-05-23…"`, `confirmed_at`, `two_factor_enabled: false`, etc.). Compatibilité avec des intégrations attendant le contrat GitLab. Cf. EXTRA pour la dette.

#### Flux principal — Rôle 2 : client OIDC (Keycloak fournit l'identité)

```
OIDC_ENABLE=true
       ↓
Membre va sur /login  → OidcFirewallListener intercepte → redirige /oauth/login
       ↓
OAuthController::login → clientRegistry.getClient('keycloak')->redirect()
       ↓
Keycloak authentifie, redirige /oauth/callback (route oauth_check)
       ↓
KeycloakAuthenticator::supports (route==oauth_check) → getCredentials (fetchAccessToken)
       ↓
getUser($credentials) :
  - fetchUserFromToken → KeycloakResourceOwner (claims)
  - appariement : Beneficiary.openid == keycloakUser.getId() ?
      • trouvé        → updateBeneficiary + updateCoMembership
      • email connu   → lie openid au compte existant, enable(true)
      • inconnu       → crée Beneficiary + Membership (member_number depuis claim)
  - updateBeneficiary : RAZ rôles/formations/commissions puis re-mapping depuis claims
       ↓
onAuthenticationSuccess → redirect homepage

Logout : /logout → oidc_logout_handler → redirect /oauth/logout
       → OAuthController::logout → Keycloak getLogoutUrl(redirect_uri=homepage)  [logout global SSO]
```

#### Flux principal — Rôle 3 : client `client_credentials` (API sortantes)

```
HelloassoClient / IgloohomeClient   ->  getClient() (Guzzle)
       ↓  Authorization: Bearer …
CacheOauthAuthenticatorDecorator::getToken(authUrl, clientId, clientSecret)
       ↓  cache HIT (clé = clientId, TTL = expiry du token, fallback 600s) → token caché
       ↓  cache MISS
ClientCredentialOauthAuthenticator::getToken
       ↓  league/oauth2-client GenericProvider->getAccessToken('client_credentials')
token machine
```

Surface d'appels :
- **HelloassoClient** : `getForms()`, `getFormPayments()`, `getFormDetails()`, `getPayment()` (détail métier en SPEC.5).
- **IgloohomeClient** : `regenerateCode(start, end)` → POST `algopin/hourly` (variance=1, accessName=start) → code de serrure (détail métier en SPEC.4).

#### Règles métier
- **R1 — Consentement persisté (rôle 1).** Un membre n'autorise un `Client` qu'une fois : `onPostAuthorizationProcess` fait `user.addClient(client)`. Aux autorisations suivantes, `onPreAuthorizationProcess` lit `user.isAuthorizedClient(client)` (test `getClients()->contains()`) et pré-coche l'autorisation. Pas de mécanisme de **révocation** côté UI identifié (la relation M2M n'est défaite par aucune route — gap).
- **R2 — Login-as bloqué sur l'identité OAuth.** `api_user`, `api_nextcloud_user`, `api_gitlab_user` rejettent (`AccessDenied`) si `ROLE_PREVIOUS_ADMIN` est présent : un admin en impersonation ne peut pas exfiltrer l'identité OAuth d'un membre. **Incohérence** : `api_swipe_in` n'a pas cette garde (cf. EXTRA).
- **R3 — Compte désactivé/retiré filtré.** `ApiController::getUser()` renvoie `{user:false}` si le bénéficiaire est `withdrawn` ou le compte `!isEnabled()` → l'identité n'est pas exposée aux apps tierces pour un membre inactif.
- **R4 — Origine de `ROLE_OAUTH_LOGIN` (résolution [SPEC.4](SPEC.4.md), ~L5623 dans l'ancienne version monolithique).** Le rôle **n'est attribué par aucun code applicatif**. Il provient du `FOSOAuthServerBundle` : le firewall `fos_oauth` (firewalls `main` et `api`) transforme les **scopes** du token en rôles ; le scope supporté `oauth_login` (`fos_oauth_server.yaml`) → `ROLE_OAUTH_LOGIN`. En mode OIDC, il peut aussi être mappé depuis un rôle Keycloak (`OIDC_ROLE_OAUTH_LOGIN`, `oidc_roles_map`). **Conclusion : rôle dérivé du token, jamais stocké sur le `User`.** ⇒ marque l'EXTRA SPEC.4 comme résolu.
- **R5 — Désactivation des outils de compte local sous OIDC.** Quand `OIDC_ENABLE=true`, `OidcFirewallListener` lève `AccessDenied` sur une **liste codée en dur** de préfixes d'URI (`/profile/edit`, `/member/new|edit|join`, `/resetting/request`, `/registrations`, `/helloasso`, `/services`, `/admin/clients`, `/admin/importcsv`, `/user/quick_new|pre_users`, `/ambassador/*`, +`str_contains(uri,'removeRole')`) et redirige `/login → /oauth/login`. Logique : sous Keycloak, la gestion d'identité/rôles est déportée → on interdit les écritures locales concurrentes. **Fragile** (denylist par préfixe de chaîne, cf. EXTRA).
- **R6 — Keycloak fait autorité sur rôles/formations/commissions (finding majeur).** `KeycloakAuthenticator::updateBeneficiary` fait `getUser()->setRoles([])` (RAZ) **à chaque connexion**, puis re-peuple depuis les claims (`oidc_roles_map`/`oidc_formations_map`/`oidc_commissions_map`). Conséquence métier : **toute attribution locale de rôle/formation/commission est écrasée au prochain login OIDC du membre**. Les formations/commissions absentes des claims sont retirées. À documenter explicitement (SYN.1) — comportement non évident et à fort impact opérationnel.
- **R7 — Provisioning JIT et fusion de co-adhésion.** Un membre inconnu de Keycloak est créé à la volée (`Beneficiary` + `Membership`, `Registration` mode `TYPE_DEFAULT`, montant 0). `updateCoMembership` gère la **fusion/scission de `Membership`** sur la base du claim `co_member_number` (transfert des proxies, notes, exemptions ; suppression de l'ancienne adhésion). Fallback `member_number = rand(10000,100000)` si aucun numéro fourni — risque de collision (cf. EXTRA).
- **R8 — Token machine mis en cache par `clientId` (rôle 3).** `CacheOauthAuthenticatorDecorator` (FilesystemAdapter) cache le jeton avec clé = `clientId`, TTL aligné sur l'expiry réel du token (fallback 600 s). Helloasso et Igloohome ayant des `clientId` distincts, pas de collision. La clé n'inclut pas l'`authUrl` (collision théorique si deux fournisseurs partageaient un `clientId` — improbable).
- **R9 — Webhook Helloasso : confiance zéro dans le payload.** `/helloassoNotify` ne vérifie **ni authentification ni signature** (la signature Helloasso est réservée aux partenaires — commentaire dans le code). Mitigation présente : le handler **re-récupère** le paiement via l'API Helloasso (`getPayment(data['id'])`) avant persistance, et `savePayments` est idempotent (dédup par `paymentId`). Posture analysée en EXTRA.

#### Données
| Entité | Rôle | Champs clés | Notes |
|---|---|---|---|
| `Client` | 1 | `randomId`, `secret`, `redirectUris`, `service` (M2O), `users` (M2M) | étend `FOS\OAuthServerBundle\Entity\Client`. CRUD admin → SPEC.6 |
| `AccessToken` | 1 | `token` (unique, 191), `client` (M2O, `onDelete=CASCADE`), `user` (nullable) | jeton émis aux apps tierces |
| `AuthCode` | 1 | idem AccessToken | code d'autorisation intermédiaire |
| `RefreshToken` | 1 | idem AccessToken | rafraîchissement |
| `User.clients` | 1 | M2M `inversedBy` Client | **consentements** mémorisés |
| `Beneficiary.openid` / `openIdMemberNumber` | 2 | string | appariement compte local ↔ identité Keycloak |
| (aucune entité) | 3 | — | tokens machine en cache filesystem, pas en base |

#### Routes
| Route | Méthode | Path | Sécurité (effective) | Rôle |
|---|---|---|---|---|
| `fos_oauth_server_authorize` | GET\|POST | `/oauth/v2/auth` | firewall `oauth_authorize` (form_login) | 1 |
| `fos_oauth_server_token` | POST | `/oauth/v2/token` | firewall `oauth_token` : **`security: false`** | 1 |
| `api_swipe_in` | POST | `/api/swipe/in` | `@Security ROLE_OAUTH_LOGIN` | 1 |
| `api_user` | GET | `/api/oauth/user` | `@Security ROLE_OAUTH_LOGIN` + blocage login-as | 1 |
| `api_nextcloud_user` | GET | `/api/oauth/nextcloud_user` | `@Security ROLE_OAUTH_LOGIN` + blocage login-as | 1 |
| `api_gitlab_user` | GET | `/api/v4/user` | `IS_AUTHENTICATED_FULLY` (**pas** `ROLE_OAUTH_LOGIN`) + blocage login-as | 1 |
| `oauth_login` | GET | `/oauth/login` | publique (déclenche redirect Keycloak) | 2 |
| `oauth_logout` | GET | `/oauth/logout` | publique | 2 |
| `oauth_check` | GET | `/oauth/callback` | firewall `main` (guard Keycloak) | 2 |
| `helloasso_notify` | POST | `/helloassoNotify` | **aucune** (publique) | 3 |

→ **10 routes** (l'estimation « 9 » de SPEC.1 comptait probablement par couple méthode+path ; `fos_oauth_server_authorize` répond en GET et POST sur un même path, et l'une des deux représentations identité avait été omise). Les routes admin Helloasso (`helloasso_*`) sont documentées en SPEC.5/6 ; Igloohome n'expose **aucune route** (CLI only, SPEC.4).

**Configuration firewall/access_control déterminante** (`security.yaml`) :
- Firewall `api` : `pattern ^/api`, `fos_oauth: true`, `stateless: true`, `anonymous: false`.
- Firewall `oauth_token` : `security: false` (le endpoint token doit être joignable sans session).
- `access_control` : `^/api → IS_AUTHENTICATED_FULLY` (L59) **précède** `^/api/oauth/ → ROLE_OAUTH_LOGIN` (L60). Ordre = premier match gagne ⇒ **la règle L60 est inatteignable** ; c'est l'annotation `@Security` au niveau contrôleur qui porte réellement `ROLE_OAUTH_LOGIN` sur les routes `oauth/*` (cf. EXTRA, finding 🟠).

#### Événements
| Événement | Émis par | Écouté par | Effet |
|---|---|---|---|
| `fos_oauth_server.pre_authorization_process` | FOSOAuthServerBundle | `OAuthEventListener::onPreAuthorizationProcess` | pré-coche le consentement si déjà autorisé |
| `fos_oauth_server.post_authorization_process` | FOSOAuthServerBundle | `OAuthEventListener::onPostAuthorizationProcess` | persiste le consentement (`user.addClient`) |
| `security.interactive_login` | Symfony | `AuthenticationSuccessHandler` | (auth locale — cf. SPEC.4) |
| `kernel.request` | Symfony | `OidcFirewallListener::onKernelRequest` | redirection/denial sous OIDC (R5) |
| `beneficiary.created` | `KeycloakAuthenticator` (provisioning) | `BeneficiaryCreatedEvent` listeners | init d'un membre créé via OIDC |
| (logout) | Symfony | `OidcLogoutHandler::onLogoutSuccess` | redirige vers `/oauth/logout` (logout SSO) |

Côté rôle 3, le webhook Helloasso dispatche `helloasso.payment_after_save` (chaîne détaillée en SPEC.5/7).

#### Tests existants
- **Aucun test** ne couvre les trois rôles OAuth : pas de test fonctionnel sur `/api/*`, le flux d'autorisation FOSOAuth, `KeycloakAuthenticator`, `OidcFirewallListener`, le webhook `/helloassoNotify`, ni les providers `client_credentials`.
- `SmokeTest` couvre l'auth **locale** (login form, redirections anonymes) mais aucune route `^/api` ni `^/oauth`.
- Les `HelloassoClient`/`IgloohomeClient` (appels réseau) ne sont pas mockés/testés.
- **Gap de test majeur** sur tout le domaine — surface d'intégration critique non couverte.

#### Gaps
- 🟠 **Ordre `access_control`** : `^/api` masque `^/api/oauth/` → la règle `ROLE_OAUTH_LOGIN` au niveau firewall ne s'applique jamais (défense en profondeur reposant uniquement sur les annotations contrôleur). Détail + correctif en EXTRA.
- 🟡 **`/api/v4/user` sans `ROLE_OAUTH_LOGIN`** : seul `IS_AUTHENTICATED_FULLY` est exigé (ni annotation, ni règle access_control atteinte). Un membre authentifié par **session** (pas via token OAuth) peut donc l'appeler. Champs de réponse en partie **factices/codés en dur** (contrat GitLab simulé).
- 🟡 **Webhook `/helloassoNotify` non authentifié et non signé** (R9) — mitigation par re-fetch API, mais endpoint POST public déclenchant des appels sortants.
- 🟡 **`OidcFirewallListener` : denylist fragile** (préfixes de chaîne codés en dur) — un renommage de route casse silencieusement la protection ; approche par liste blanche préférable.
- 🟡 **Pas de révocation de consentement OAuth** : la relation `User.clients` n'est défaite par aucune route UI.
- 🟡 **`KeycloakAuthenticator` : RAZ des rôles à chaque login** (R6) — comportement à fort impact, non documenté hors code.
- 🟢 **`api_swipe_in` sans garde login-as** — incohérence mineure avec les autres endpoints identité.
- **Documentation** : les trois rôles OAuth, la matrice OIDC on/off par instance, et l'autorité Keycloak sur les rôles sont à consolider en SYN.1.

