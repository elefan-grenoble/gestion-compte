# SPEC.4 — Spec : Authentification & Autorisation

- [x] **SPEC.4** — Spec : Authentification & Autorisation

Sources lues : `security.yaml`, `AuthenticationSuccessHandler`, `KeycloakAuthenticator`, `OAuthController`, `SwipeCardController`, `UserController`, `AdminController` (routes auth), `CardReaderController`, `CodeController`, `UpdateIgloohomeCodeCommand`, `VerifyCodeChangeCommand` ; `SwipeCardVoter`, `CodeVoter`, `ShiftVoter`, `UserVoter`, `MembershipVoter` ; entités `User`, `SwipeCard`, `Code` ; `SwipeCardHelper` (Vigenère) ; `SwipeCardEventListener`.
Croisé avec : DC.2 (AuthenticationSuccessHandler null), SEC.1.7 (Vigenère), SEC.2.2 + SEC.3.3 (card_reader/check sans auth → SPEC.3), AP.7 (CodeEventListener corps commenté), SPEC.2 (token temporaire md5 / oidc gap), SPEC.3 (voter accept/reject/lock/card_reader).

---

## SPEC.4 — Authentification & Autorisation (+ Domaine J : Accès physique)

### Vocabulaire essentiel

| Terme | Entité / concept | Rôle |
|-------|-----------------|------|
| **User** | `User` (FOSUserBundle `BaseUser`) | Identifiants (email, username, mot de passe bcrypt, rôles). Toujours associé 1-1 à un `Beneficiary` pour les adhérents. |
| **Rôle** | string ROLE_* stocké en JSON dans `fos_user.roles` | Attribution de droits. Géré via hiérarchie Symfony. |
| **Badge / SwipeCard** | `SwipeCard` | Carte NFC physique associée à un `Beneficiary`. Porte un `code` (EAN13 sur 12 chiffres) stocké en clair. Peut être activé/désactivé. Sert à l'auth passwordless et à la validation de présence. |
| **Code de porte** | `Code` | Code numérique d'accès au local (typiquement 4 chiffres), géré en rotation. Ouvert/fermé. Associé à un `registrar` (User). |
| **Voter** | classe Symfony `Voter` | Décide l'accès à un objet métier pour un attribut donné (ex. `view`, `edit`). Plusieurs voters peuvent opérer sur le même attribut, le `decisionManager` les agrège (strategy : `affirmative` par défaut). |
| **Vigenère** | `App\Helper\SwipeCard` | Algorithme de chiffrement symétrique basé sur XOR+base64, clé `SWIPE_CARD_SECRET`. Utilisé pour obfusquer les codes de badge dans les URLs et les tokens de notifications email. |
| **OIDC / Keycloak** | `KeycloakAuthenticator` (Guard SF4) | Auth OpenID Connect entrante depuis Keycloak (instance Scopeli uniquement). Crée ou met à jour le `User`/`Beneficiary`/`Membership` à chaque connexion. |
| **OAuth sortant** | FOSOAuthServerBundle + `ClientRegistry` | Expose un serveur OAuth 2.0 (SSO sortant, ex. vers Nextcloud/GitLab). Distinct de l'OIDC entrant. |
| **PlaceIP** | `App\Helper\PlaceIP` | Vérifie que la requête provient de l'IP locale du local coopératif (configurable). Garde-fou pour les actions physiques (appairage de badge, création de membre, génération de code). |

---

### Hiérarchie des rôles (`security.yaml:4-13`)

```
ROLE_USER                    ← base : adhérent connecté
  └─ ROLE_ADMIN_PANEL        ← accès au panneau /admin/
       ├─ ROLE_SHIFT_MANAGER  ← gestion des créneaux
       ├─ ROLE_USER_VIEWER    ← lecture fiches membres
       │    └─ ROLE_USER_MANAGER  ← mutations membres (gel, fermeture)
       ├─ ROLE_FINANCE_MANAGER ← cotisations/paiements
       └─ ROLE_PROCESS_MANAGER ← notes de version

ROLE_ADMIN = [ROLE_USER_MANAGER, ROLE_FINANCE_MANAGER,
              ROLE_SHIFT_MANAGER, ROLE_PROCESS_MANAGER]
ROLE_SUPER_ADMIN ⊇ ROLE_ADMIN
ROLE_OAUTH_LOGIN ⊇ ROLE_USER   ← clients OAuth API
```

Implication : `ROLE_USER_MANAGER` implique `ROLE_USER_VIEWER` implique `ROLE_ADMIN_PANEL` implique `ROLE_USER`. L'`ROLE_ADMIN` absorbe tous les rôles "manager" sauf le chemin ROLE_ADMIN_PANEL direct (qui n'est pas parent de ROLE_SHIFT_MANAGER en soi — ROLE_SHIFT_MANAGER est enfant de ROLE_ADMIN_PANEL).

**Mécanisme switch_user** (`security.yaml:28-30`) : ROLE_ADMIN peut usurper l'identité d'un autre utilisateur via le paramètre `_login_as`. Aucune route dédiée — paramètre GET/POST dans n'importe quelle requête. L'impersonification crée un token avec `ROLE_PREVIOUS_ADMIN`.

---

**Acteurs** :
- **Anonyme** : accès public (login, reset password, registration FOS, `swipe_in`, `shift_accept/reject_reserved` via token URL, `card_reader_index`/`card_reader_check` depuis l'IP locale, `code_change_done` via token Vigenère).
- **ROLE_USER** (adhérent) : profil FOS, changement de mot de passe, appairage/activation/désactivation de badge (propre), liste des codes de porte si non-débutant avec créneau actif.
- **ROLE_USER_VIEWER** : lecture fiches membres, listes admin (users, rôles, pré-inscrits), `swipe_show`.
- **ROLE_USER_MANAGER** : désactivation de badges (admin override), mutations membres.
- **ROLE_ADMIN** : ajout/retrait de rôles (sauf ROLE_ADMIN), toggle codes (open/close), suppression de badges.
- **ROLE_SUPER_ADMIN** : import CSV utilisateurs, suppression User, ajout ROLE_ADMIN à un user, suppression de codes, `user_install_admin` (second admin).
- **Système** : `UpdateIgloohomeCodeCommand`, `VerifyCodeChangeCommand` (tâches cron).
- **Keycloak (Scopeli)** : IdP externe ; le `KeycloakAuthenticator` réconcilie les identités à chaque callback.

**Instances** :
- **Toutes** : authentification FOS (login/logout/reset/profil), gestion des rôles, badges SwipeCard, lecteur de badge.
- **Scopeli uniquement** (`oidc_enable=true`) : flux OIDC Keycloak (`oauth_login/logout/check`) ; `MembershipVoter::canEdit` retourne toujours `false` (identité déléguée à Keycloak) ; `UserVoter` retourne `false` pour tous les utilisateurs authentifiés (le lecteur de badge fonctionne en mode anonyme depuis l'IP locale).
- **Elefan probable** (`code_generation_enabled=true`) : génération de codes de porte rotatifs par les membres (CodeVoter::GENERATE).
- **Igloohome** : serrures connectées, instance-specific (paramètres `IGLOOHOME_*`).

---

### Sous-domaine 1 — Authentification FOSUserBundle (form-based)

**Flux principal — Login** :
1. GET `/login` → formulaire (username/email + password + CSRF token).
2. POST `/login_check` → FOSUserBundle vérifie les credentials (bcrypt), crée la session Symfony.
3. Symfony appelle `AuthenticationSuccessHandler::onAuthenticationSuccess()` (via `security.interactive_login`).
4. **⚠️ Bug DC.2** : si la requête ne contient pas de `target_path`, la méthode retourne `null` implicitement au lieu d'une `Response`. Elle viole `AuthenticationSuccessHandlerInterface` (qui exige `Response`). En pratique, le handler est enregistré comme listener sur `security.interactive_login` via `onSecurityInteractiveLogin` qui appelle `onAuthenticationSuccess` mais **ignore sa valeur de retour** — le vrai handler de succès est le composant Symfony, pas cette classe. La redirection par défaut de FOSUserBundle prend le relais. Le bug est masqué mais peut causer des erreurs de type dans un contexte strict.
5. Après connexion, si `oidc_enable=true`, le firewall Keycloak (`KeycloakAuthenticator`) est le seul Guard actif — mais les deux firewalls cohabitent dans `security.yaml`.

**Flux — Reset password** : flow standard FOSUserBundle (request → email → check_email → reset). Pas de personnalisation notable.

**Flux — Registration** : FOS expose les routes `register`, `check_email`, `confirm`, `confirmed`. L'app n'en fait pas usage direct (l'onboarding passe par `member_new` + lien d'invitation). Ces routes **restent exposées et actives** même si l'auto-inscription n'est pas voulue.

**Changement de mot de passe** :
- `fos_user_change_password` : flow standard FOS.
- `user_change_password` (`UserController`) : form custom avec `IS_AUTHENTICATED_FULLY` (vérifié inline, pas par @Security). Valide la correspondance des deux mots de passe. Dispatch `FOSUserEvents::USER_PASSWORD_CHANGED`.

**Bootstrap admin** (`user_install_admin`) :
- Route sans @Security. Logique inline : si aucun `ROLE_SUPER_ADMIN` n'existe → crée l'admin depuis `emails.admin`/`super_admin.initial_password`/`super_admin.username` (paramètres injectés). Si déjà présent → nécessite `ROLE_ADMIN` pour créer un admin supplémentaire.
- **⚠️ Risque bootstrap** : avant la première installation, n'importe qui peut accéder à cette route et créer le super-admin. À sécuriser par réseau ou par accès direct au serveur.

---

### Sous-domaine 2 — Authentification OIDC Keycloak (Scopeli)

**Flux principal** (`KeycloakAuthenticator`, Guard SF4) :
1. L'utilisateur clique « Se connecter via Keycloak » → `oauth_login` → redirect vers Keycloak (`KnpU\OAuth2ClientBundle`).
2. Keycloak callback → `oauth_check` → `KeycloakAuthenticator::supports()` (route === `oauth_check`) → `getCredentials()` → `fetchAccessToken`.
3. `getUser()` : récupère le `KeycloakResourceOwner` (claims JWT Keycloak).
4. **Réconciliation** : cherche d'abord par `openid` (Beneficiary) ; si absent, par email (User) ; si absent, crée un nouveau Beneficiary+Membership+Registration.
5. **Mise à jour systématique** : à chaque connexion, `updateBeneficiary()` sync prénom/nom/téléphone/adresse/email et **remplace entièrement les rôles et formations** depuis les claims Keycloak (`oidc_roles_claim`, `oidc_roles_map`, `oidc_formations_claim`, `oidc_formations_map`, `oidc_commissions_claim`, `oidc_commissions_map`).
6. **Co-membership** (`updateCoMembership`) : si le claim `co_member_number` est présent → rattache le bénéficiaire à l'adhésion existante et supprime l'ancienne. Logique complexe avec plusieurs `flush()` imbriqués sans transaction globale.
7. Succès → redirect vers `homepage` (`KeycloakAuthenticator::onAuthenticationSuccess`).
8. `oauth_logout` → si `oidc_enable=true`, construit l'URL de logout Keycloak avec `redirect_uri` absolu.

**Mapping des attributs** : via `oidc_user_attributes_map` (paramètre JSON) — clés pointées par notation pointée (ex. `attributes.member_number`). Si `firstname`, `lastname`, `member_number`, `email` sont absents du token → exception lancée, connexion échouée.

**Conséquences de l'OIDC sur les autres domaines** :
- `MembershipVoter::canEdit()` : retourne toujours `false` si `oidc_enable=true` → l'adhérent ne peut pas éditer sa propre adhésion.
- `UserVoter` : retourne `false` pour tout utilisateur authentifié si `oidc_enable=true` → card_reader accessible uniquement depuis l'IP locale sans login.
- `MembershipVoter::canView()` délègue à `canEdit()` → même avec `ROLE_USER_VIEWER`, si `oidc_enable=true` et l'utilisateur n'est pas SUPER_ADMIN/ADMIN/USER_MANAGER, il n'a pas accès (⚠️ gap SPEC.2 — accès des viewers en mode OIDC à clarifier).

**Gaps OIDC** :
- `member_number` OIDC (`openid_member_number`) vs `member_number` DB : le setter `setMemberNumber()` sur la `Membership` (l.106 `KeycloakAuthenticator`) écrase le numéro à chaque connexion avec la valeur Keycloak — risque de drift si Keycloak et DB désynchronisés.
- `createMembership()` (l.241) : `member_number = rand(10000,100000)` si aucun `openid_member_number` → collision possible avec les numéros séquentiels de la DB.
- `updateCoMembership()` : plusieurs `flush()` imbriqués sans transaction → état incohérent possible si l'une des opérations échoue (orphelinage de `Membership` ou de `Beneficiary`).

---

### Sous-domaine 3 — Auth passwordless par badge (SwipeCard)

**Flux principal — Connexion par QR/badge** (`swipe_in`, route publique GET `/sw/in/{code}`) :
1. L'URL est générée via `swipe_qr` (QR code PNG) ou `swipe_br` (barcode PNG) : le `{code}` est le code de la `SwipeCard` **chiffré en Vigenère** (base64(XOR(code, key))).
2. `swipeInAction` décode via `vigenereDecode($code)` → code brut de la carte.
3. Cherche la `SwipeCard` active (code + `enable=true`) via `findLastEnable`.
4. Si trouvée : crée directement un `UsernamePasswordToken` avec le User et ses rôles → injecte dans `security.token_storage` → dispatch `security.interactive_login`.
5. Redirect vers homepage. Flash d'erreur si carte introuvable ou inactive.

**⚠️ Sécurité SEC.1.7** : le code brut est transmis chiffré en Vigenère (XOR + base64, clé fixe `SWIPE_CARD_SECRET`). L'algorithme ne fournit ni intégrité ni fraîcheur — un code Vigenère intercepté est rejouable indéfiniment. Si `SWIPE_CARD_SECRET` fuite, tous les badges du système sont compromis (pas de changement de code possible sans changer la clé et réinitialiser les QR).

**Gestion des badges (SwipeCardController, routes `/sw/...`)** :

| Action | Route | Accès |
|--------|-------|-------|
| Appairage (nouveau badge) | `activate_swipe` POST | ROLE_USER + voter `PAIR` |
| Réactivation | `enable_swipe` POST | ROLE_USER + voter `ENABLE` |
| Désactivation | `disable_swipe` POST | ROLE_USER + voter `DISABLE` |
| Suppression | `delete_swipe` POST | ROLE_ADMIN + voter `DELETE` |
| Affichage admin | `swipe_show` GET `/{id}/show` | ROLE_USER_MANAGER |
| QR code PNG | `swipe_qr` GET `/{code}/qr.png` | ⚠️ **AUCUN** (public) |
| Barcode PNG | `swipe_br` GET `/{code}/br.png` | ⚠️ **AUCUN** (public) |

**⚠️ swipe_qr / swipe_br sans auth** : n'importe qui connaissant un code Vigenère valide (lisible dans les emails d'invitation ou dans les URLs de login) peut télécharger le QR ou barcode de n'importe quel badge. Ces images permettent l'usurpation d'accès physique (validation de présence via `card_reader_check`) et la connexion passwordless via `swipe_in`.

**SwipeCardVoter** — règles détaillées :
- `PAIR` : ROLE_SUPER_ADMIN/ADMIN/USER_MANAGER toujours OK ; sinon : le bénéficiaire n'a aucun badge **ou** aucun badge actif. Une carte désactivée ne bloque pas le rattachement d'une nouvelle.
- `ENABLE` / `DISABLE` : ROLE_SUPER_ADMIN/ADMIN/USER_MANAGER toujours OK ; sinon : propriétaire de la carte (`card.beneficiary.user === currentUser`).
- `DELETE` : ROLE_SUPER_ADMIN/ADMIN/USER_MANAGER uniquement. `@Security("is_granted('ROLE_ADMIN')")` double-gate sur le controller.

**Logging des passages** (`SwipeCardEventListener`) : si `swipe_card_logging=true`, chaque passage badge dispatche `SwipeCardEvent::SWIPE_CARD_SCANNED` → `SwipeCardLog(date, counter, swipeCard)` en base. Si `swipe_card_logging_anonymous=true`, le log est créé sans lien vers la `SwipeCard` (anonymisation).

---

### Sous-domaine 4 — Gestion des comptes et des rôles

**Routes admin** (dans `AdminController` sous `/admin/`) :

| Route | Chemin | Accès | Description |
|-------|--------|-------|-------------|
| `user_index` | GET\|POST `/admin/users` | ROLE_USER_MANAGER | Liste paginée + filtres + export CSV + envoi mail groupé |
| `non_member_users_list` | GET `/admin/non_member_users` | ROLE_ADMIN | Users sans `Beneficiary` lié |
| `admin_users_list` | GET `/admin/admin_users` | ROLE_ADMIN | Users avec ROLE_ADMIN (avec form de suppression) |
| `roles_list` | GET `/admin/roles` | ROLE_ADMIN | Tableau des rôles avec comptages |
| `user_import_csv` | GET\|POST `/admin/importcsv` | ROLE_SUPER_ADMIN | Import batch via `app:import:users` (via `Application::run()` — même antipattern qu'`admin_shifts_generation`) |

**Routes dans `UserController`** :

| Route | Accès | Règle métier |
|-------|-------|-------------|
| `user_add_role` GET | ROLE_ADMIN | ROLE_ADMIN seul ne peut pas s'attribuer ROLE_ADMIN → exige ROLE_SUPER_ADMIN |
| `user_remove_role` GET\|POST | ROLE_ADMIN | Même restriction pour retrait ROLE_ADMIN |
| `user_delete` DELETE | ROLE_SUPER_ADMIN | Supprime le User (cascade ORM) |
| `user_client_remove` GET\|POST | IS_AUTHENTICATED_FULLY + (ROLE_ADMIN OU soi-même) | Retire un client OAuth du compte |
| `user_install_admin` GET\|POST | Aucune (logique inline) | Bootstrap ou ajout admin |
| `user_change_password` GET\|POST | IS_AUTHENTICATED_FULLY (inline) | Change le mot de passe de l'utilisateur courant |

**⚠️ user_add_role / user_remove_role via GET** : mutations d'état (ajout/retrait de rôle) via verbe GET — pas de protection CSRF. Un lien forgé peut ajouter ou retirer un rôle.

---

### Voter architecture — synthèse

| Voter | Sujet | Attributs | Accès minimal |
|-------|-------|-----------|--------------|
| `UserVoter` | `User` (ou null pour `card_reader`) | VIEW, EDIT, CLOSE, FREEZE, FREEZE_CHANGE, CREATE, ANNOTATE, ACCESS_TOOLS, CARD_READER | Voir détail ci-dessous |
| `MembershipVoter` | `Membership` | VIEW, EDIT, BOOK, OPEN, CLOSE, FREEZE, FREEZE_CHANGE, FLYING, CREATE, ANNOTATE, ACCESS_TOOLS, BENEFICIARY_ADD, ROLE_ADD, ROLE_REMOVE | Voir SPEC.2 |
| `ShiftVoter` | `Shift` | BOOK, FREE, REJECT, ACCEPT, LOCK, VALIDATE | Voir SPEC.3 |
| `SwipeCardVoter` | `SwipeCard` | PAIR, ENABLE, DISABLE, DELETE | Voir sous-domaine 3 |
| `CodeVoter` | `Code` | VIEW, GENERATE, EDIT, OPEN, CLOSE, DELETE | Voir sous-domaine J.1 |
| `NoteVoter` | `Note` | EDIT, DELETE | ROLE_USER_VIEWER ou auteur |
| `DynamicContentVoter` | `DynamicContent` | EDIT | ROLE_ADMIN |
| `EmailTemplateVoter` | `EmailTemplate` | EDIT | ROLE_ADMIN |
| `ProcessUpdateVoter` | `ProcessUpdate` | EDIT, DELETE, NEW | ROLE_PROCESS_MANAGER |
| `TaskVoter` | `Task` | — | (hors périmètre SPEC.4) |

**UserVoter — logique CARD_READER** : attribut spécial sans sujet `User` requis.
- Utilisateur non authentifié : accès si `PlaceIP::isLocationOk()` (IP locale uniquement).
- Utilisateur authentifié + `oidc_enable=true` : **toujours refusé** (y compris ADMIN).
- Utilisateur authentifié + `oidc_enable=false` : SUPER_ADMIN → toujours OK ; ADMIN → OK ; USER_MANAGER → OK ; sinon switch case CARD_READER → `return true` (tous les utilisateurs connectés).
- **⚠️ Implication** : en mode OIDC (Scopeli), seul l'accès depuis l'IP locale *sans session* permet d'utiliser le lecteur de badge. Toute session authentifiée est bloquée.

**ShiftVoter — ACCEPT/REJECT sans login** (croisé SPEC.3) :
- Si l'utilisateur n'est pas connecté, le voter ne refuse pas directement pour ces deux attributs (`user = null`).
- Vérifie via `canReject/canAccept` : si connecté → `user.beneficiary === shift.lastShifter` ; si non connecté → `request.token == shift.getTmpToken(lastShifter.id)`.
- `Shift::getTmpToken(id)` : `md5(shift.id . id . shift.start.timestamp())` — MD5, pas de clé secrète, composantes prévisibles. Moins robuste que le token temporaire de `Membership`.

---

### Sous-domaine J — Contrôle d'accès physique (transverse)

#### J.1 — Codes de porte rotatifs (`CodeController`, `CodeVoter`)

**Concept** : un code de porte est un entier (typiquement 4 chiffres) stocké en clair dans `Code.value`. Plusieurs codes peuvent coexister, certains `closed=true` (archives), d'autres `closed=false` (actifs). Le principe de rotation : un membre génère un nouveau code, l'affiche sur le boîtier physique, puis ferme les anciens (`code_change_done`).

**Flux — Consultation** (`codes_list`) :
1. ROLE_USER requis (gateway @Security).
2. ROLE_ADMIN : voit les 100 derniers codes (ouverts + fermés).
3. Non-admin : voit les 10 codes ouverts + 3 fermés récents.
4. `denyAccessUnlessGranted('view', $codes[0])` : CodeVoter::VIEW → accès si (a) `code.registrar === user` OU (b) non-débutant (`!isBeginner(beneficiary)`) ET créneau actif dans la fenêtre [-120min, +60min] (`isBeneficiaryHasShifts`).
5. **⚠️** : si aucun code ouvert n'existe (`!count($codes)`), redirect homepage sans voter — accès toujours refusé si table vide. Risque de lock-out opérationnel.

**Flux — Génération** (`code_generate`, `CODE_GENERATION_ENABLED=true`) :
1. Vérifie d'abord que l'utilisateur peut voir les anciens codes (même règle que `codes_list`).
2. Si l'utilisateur a déjà un code ouvert → affiche le code existant (pas de doublon).
3. Sinon : génère `rand(0, 9999)` (4 chiffres), crée `Code`, dispatch `CodeNewEvent`.
4. **⚠️ CodeEventListener::onCodeNew() corps commenté (AP.7)** : `CodeNewEvent` est dispatchée mais le listener n'exécute rien (corps commenté). Probablement un email d'alerte était prévu.
5. **⚠️ CodeVoter::GENERATE** : exige en plus que l'utilisateur ne soit pas le `registrar` du code ouvert le plus récent ET que `PlaceIP::isLocationOk()` → le membre doit être physiquement au local pour générer un code.

**Flux — Confirmation de changement** (`code_change_done`, route sans @Security) :
- **Deux chemins** : (a) utilisateur connecté → session normale ; (b) utilisateur non connecté → token Vigenère dans l'URL (`token=vigenereEncode(username + ',code:' + id)`).
- En mode (b), le controller **impersonnifie temporairement** l'utilisateur via `setToken(UsernamePasswordToken)`, effectue les fermetures de codes, puis restaure le token précédent.
- Ferme les codes des autres membres qui étaient visibles par cet utilisateur (selon `CodeVoter::VIEW`).
- **⚠️** : le token Vigenère dans l'URL (envoyé par email par `VerifyCodeChangeCommand`) n'a pas de date d'expiration et est rejouable indéfiniment.

**Flux — Vérification de changement** (`VerifyCodeChangeCommand`, `app:code:verify_change`) :
- Si plus d'un code ouvert existe ET le plus récent a été créé dans les `last_run` heures → impersonifie ce registrar (via `TokenStorage::setToken`) pour évaluer `CodeVoter::VIEW` sur les anciens codes.
- **⚠️** : impersonification en CLI sans Request complète → `PlaceIP::isLocationOk()` échoue (IP nulle en CLI → la vérification `$checkIps=false` ou `in_array(null, $ips)` détermine le résultat). Comportement dépendant de la valeur de `enable_place_local_ip_address_check`.

**CodeVoter — logique OPEN/CLOSE/DELETE** :
```
VIEW / CLOSE  : ROLE_ADMIN OU canView()
GENERATE      : code_generation_enabled ET (ROLE_ADMIN OU (canView ET IP locale ET pas mon propre code du jour))
OPEN / EDIT   : ROLE_ADMIN; sinon fall-through vers DELETE
DELETE        : ROLE_SUPER_ADMIN; sinon canDelete() → toujours false
```
**⚠️ Fall-through OPEN→DELETE** : un non-ROLE_ADMIN qui tente `open` (code fermé → toggle) passe par la branche DELETE → refus. Asymétrie non documentée : fermer un code est permis à un viewer actif ; rouvrir un code est réservé à ROLE_ADMIN.

**Intégration Igloohome** (`UpdateIgloohomeCodeCommand`, `app:code:update_igloohome`) :
- S'authentifie auprès de l'API Igloohome (`IgloohomeClient`) pour créer un code PIN avec une plage de validité (start/end ISO 8601).
- Enregistre le code dans la table `code` (registrar = `super_admin.username`) et ferme les anciens codes ouverts.
- En cas d'échec API : envoie un email d'alerte aux `alert_recipients` et retourne exit code 1.
- **Instance-specific** : activé uniquement si les variables `IGLOOHOME_*` sont configurées (Elefan probable).
- **⚠️** : le code Igloohome est stocké **en clair** dans `Code.value` (comme les autres codes). Pas de chiffrement différencié.

#### J.2 — Badges NFC / SwipeCard (auth physique)

Voir **Sous-domaine 3** ci-dessus pour l'appairage, l'activation/désactivation et le login via badge.

**Entité `SwipeCard`** : `code` (string 50, unique, EAN12 sur 12 chiffres — le 13e est le checksum, stripped à l'appairage) ; `enable` (bool nullable) ; `disabled_at` (datetime nullable) ; `number` (integer, ordre d'appairage) ; `beneficiary` (N-1) ; `logs` (1-N `SwipeCardLog`).
Note : `getEnable()` retourne `false` si `disabled_at` est non-null, indépendamment de `enable`. Double condition sécurisante mais `setEnable(false)` set les deux (cohérent).

#### J.3 — Lecteur de badge (`CardReaderController`)

Couvert en détail en **SPEC.3 Sous-domaine 4**. Synthèse du point de vue autorisation :
- `card_reader_index` : voter `card_reader` (UserVoter) — voir règles ci-dessus.
- `card_reader_check` (POST) : **aucune auth, aucun CSRF** (SEC.2.2 + SEC.3.3) — toute personne avec un EAN13 valide peut valider des créneaux.

#### J.4 — Serrures Igloohome (CLI uniquement)

Voir **J.1** ci-dessus. Aucune route HTTP — uniquement via `app:code:update_igloohome`. Typiquement orchestré par un cron.

---

### Données — récapitulatif des entités

| Entité | Champs clés | Relations |
|--------|-------------|-----------|
| `User` | `email`(unique), `username`(unique), `password`(bcrypt), `enabled`(bool), `last_login`(datetime), `roles`(JSON array) | 1-1 `beneficiary`→Beneficiary(EAGER, nullable), N-N `clients`→Client, 1-N `annotations`→Note, 1-N `processUpdates` |
| `SwipeCard` | `code`(string 50, unique, EAN12), `enable`(bool nullable), `disabled_at`(datetime nullable), `number`(int), `createdAt` | N-1 `beneficiary`→Beneficiary, 1-N `logs`→SwipeCardLog |
| `SwipeCardLog` | `date`(datetime), `counter`(int, minutes restants du cycle) | N-1 `swipeCard`→SwipeCard(nullable si anonyme) |
| `Code` | `value`(string 255, nullable), `closed`(bool), `createdAt` | N-1 `registrar`→User(onDelete=SET NULL) |

---

### Routes — inventaire complet (~35 + domaine J ~18)

**Domaine C — Auth & Autorisation**

| Route | Méthode / chemin | Contrôle d'accès |
|-------|------------------|------------------|
| `fos_user_security_login` | GET `/login` | public |
| `fos_user_security_check` | POST `/login_check` | public (form submit) |
| `fos_user_security_logout` | GET `/logout` | IS_AUTHENTICATED |
| `fos_user_registration_register` | GET\|POST `/register/` | IS_AUTHENTICATED_ANONYMOUSLY |
| `fos_user_registration_check_email` | GET `/register/check-email` | IS_AUTHENTICATED_ANONYMOUSLY |
| `fos_user_registration_confirm` | GET `/register/confirm/{token}` | IS_AUTHENTICATED_ANONYMOUSLY |
| `fos_user_registration_confirmed` | GET `/register/confirmed` | IS_AUTHENTICATED |
| `fos_user_resetting_request` | GET `/resetting/request` | IS_AUTHENTICATED_ANONYMOUSLY |
| `fos_user_resetting_send_email` | POST `/resetting/send-email` | IS_AUTHENTICATED_ANONYMOUSLY |
| `fos_user_resetting_check_email` | GET `/resetting/check-email` | IS_AUTHENTICATED_ANONYMOUSLY |
| `fos_user_resetting_reset` | GET\|POST `/resetting/reset/{token}` | IS_AUTHENTICATED_ANONYMOUSLY |
| `fos_user_profile_show` | GET `/profile/` | IS_AUTHENTICATED_FULLY |
| `fos_user_profile_edit` | GET\|POST `/profile/edit` | IS_AUTHENTICATED_FULLY |
| `fos_user_change_password` | GET\|POST `/profile/change-password` | IS_AUTHENTICATED_FULLY |
| `oauth_login` | GET `/oauth/login` | public (redirect Keycloak) |
| `oauth_logout` | GET `/oauth/logout` | public (redirect Keycloak logout si oidc_enable) |
| `oauth_check` | GET `/oauth/callback` | Guard `KeycloakAuthenticator` |
| `swipe_in` | GET `/sw/in/{code}` | **public** (auth passwordless) |
| `activate_swipe` | POST `/sw/activate` | ROLE_USER + voter PAIR |
| `enable_swipe` | POST `/sw/enable` | ROLE_USER + voter ENABLE |
| `disable_swipe` | POST `/sw/disable` | ROLE_USER + voter DISABLE |
| `delete_swipe` | POST `/sw/delete` | ROLE_ADMIN + voter DELETE |
| `swipe_show` | GET `/sw/{id}/show` | ROLE_USER_MANAGER |
| `swipe_qr` | GET `/sw/{code}/qr.png` | ⚠️ **AUCUN** |
| `swipe_br` | GET `/sw/{code}/br.png` | ⚠️ **AUCUN** |
| `user_index` | GET\|POST `/admin/users` | ROLE_USER_MANAGER |
| `non_member_users_list` | GET `/admin/non_member_users` | ROLE_ADMIN |
| `admin_users_list` | GET `/admin/admin_users` | ROLE_ADMIN |
| `roles_list` | GET `/admin/roles` | ROLE_ADMIN |
| `user_import_csv` | GET\|POST `/admin/importcsv` | ROLE_SUPER_ADMIN |
| `user_add_role` | **GET** `/user/{id}/addRole/{role}` | ROLE_ADMIN (+ rule : SUPER_ADMIN for ROLE_ADMIN) |
| `user_remove_role` | GET\|POST `/user/{id}/removeRole/{role}` | ROLE_ADMIN (+ rule) |
| `user_delete` | DELETE `/user/{id}` | ROLE_SUPER_ADMIN |
| `user_install_admin` | GET\|POST `/user/install_admin` | ⚠️ **AUCUN** (logique inline) |
| `user_change_password` | GET\|POST `/user/change_password` | IS_AUTHENTICATED_FULLY (inline) |
| `user_client_remove` | GET\|POST `/user/{username}/remove_client/{client_id}` | IS_AUTHENTICATED_FULLY + (ROLE_ADMIN OU soi-même) inline |

**Domaine J — Accès physique**

| Route | Méthode / chemin | Contrôle d'accès |
|-------|------------------|------------------|
| `codes_list` | GET `/codes/` | ROLE_USER + CodeVoter::VIEW |
| `code_edit` | GET\|POST `/codes/new` | ROLE_USER + (admin ou ...) |
| `code_generate` | GET\|POST `/codes/generate` | ROLE_USER + CodeVoter::GENERATE (+ IP locale) |
| `code_toggle` | GET\|POST `/codes/{id}/toggle` | ROLE_USER + CodeVoter::OPEN ou CLOSE |
| `code_change_done` | GET `/codes/close_all` | ⚠️ **AUCUN** (token Vigenère URL ou session) |
| `code_delete` | DELETE `/codes/{id}` | CodeVoter::DELETE (SUPER_ADMIN uniquement) |
| `card_reader_index` | GET `/card_reader/` | UserVoter::CARD_READER (voir règles) |
| `card_reader_check` | POST `/card_reader/check` | ⚠️ **AUCUN** (SEC.2.2) |
| CLI `app:code:update_igloohome` | — | système (cron) |
| CLI `app:code:verify_change` | — | système (cron) |

---

### Événements dispatché

| Événement | Déclencheur | Listener |
|-----------|-------------|---------|
| `security.interactive_login` | `swipe_in` (badge), login form | `AuthenticationSuccessHandler::onSecurityInteractiveLogin` |
| `FOSUserEvents::USER_PASSWORD_CHANGED` | `user_change_password` | FOSUserBundle (email de confirmation) |
| `FOSUserEvents::REGISTRATION_SUCCESS` | `member_new` → création User | FOSUserBundle (email d'activation) |
| `BeneficiaryCreatedEvent` | `KeycloakAuthenticator::updateBeneficiary` (nouveau) | `EmailingEventListener` |
| `CodeNewEvent` | `code_generate` | `CodeEventListener::onCodeNew` (**corps commenté**) |
| `SwipeCardEvent::SWIPE_CARD_SCANNED` | `card_reader_check` (si logging) | `SwipeCardEventListener` → `SwipeCardLog` |

---

### Tests existants

**`SmokeTest`** : couvre les flux login (form render, login valide, login invalide, routes protégées → redirect /login), `card_reader_index` (200 si connecté), routes admin (200 admin). Aucun test sur le flow badge, les voters, le flow OIDC, ou la gestion des rôles.

**`AdminControllerTest`** : uniquement `user_import_csv` via `app:import:users` (commande CLI, pas la route HTTP). Teste l'import CSV de 50 users avec et sans commissions.

**Pas de tests** pour : `swipe_in`, `activate_swipe/enable_swipe/disable_swipe`, voters (`SwipeCardVoter`, `CodeVoter`, `UserVoter`, `ShiftVoter::ACCEPT/REJECT`), flow OIDC Keycloak, `user_add_role/remove_role`, `code_generate/code_change_done`, `card_reader_check`.

---

### Gaps

**Sécurité** :
- 🟠 `card_reader_check` : aucune auth, aucun CSRF — déjà documenté SPEC.3, cross-ref SEC.2.2. *(Canonique 🟠, cf. I-SEC-4 ; 🔴 uniquement en chaîne avec la forgeabilité des badges C-SEC-4 / SEC.1.7.)*
- 🟠 `swipe_qr` / `swipe_br` : aucune @Security → QR et barcode de badge téléchargeables par quiconque connaissant le code Vigenère. Le code Vigenère figure dans les emails (lien `swipe_in`) et dans les URLs cliquées : risque d'exfiltration via logs d'accès, referers ou screenshots. Permet usurpation de badge + connexion passwordless.
- 🟠 Chiffrement Vigenère (SEC.1.7) : XOR+base64, clé fixe sans rotation — ni intégrité, ni fraîcheur. Touche : login par badge, confirmation de changement de code, tokens accept/reject de pré-réservation (SPEC.3). Remplacement recommandé par HMAC signé + expiration (ex. JWT court).
- 🟡 `code_change_done` : aucune @Security, auth via token Vigenère URL sans expiration → rejouable indéfiniment ; impersonification temporaire en session sans revocation.
- 🟡 `user_install_admin` : accessible sans auth avant le premier setup → risque si la route est atteinte avant l'installation (pas de protection réseau documentée).
- 🟡 `AuthenticationSuccessHandler::onAuthenticationSuccess` (DC.2) : retourne `null` implicitement → viole l'interface. Bug masqué par la façon dont le listener est enregistré, mais peut causer des erreurs de type si Symfony strict-types evolue.
- 🟡 `user_add_role` via GET : mutation CSRF-less — un lien peut ajouter un rôle sans confirmation.
- 🟡 `ShiftVoter::ACCEPT/REJECT` token : `md5(shift.id . lastShifter.id . shift.start.timestamp())` — MD5 sans secret, composantes prévisibles (id séquentiels, timestamp dans le futur connu). Moins robuste que le token de Membership qui utilise `uniqid` de session.
- 🟡 `VerifyCodeChangeCommand` : impersonification CLI de l'utilisateur pour évaluer `CodeVoter::VIEW` — le contexte PlaceIP peut être incorrect en CLI selon la valeur de `enable_place_local_ip_address_check`.
- 🟡 `updateCoMembership` (KeycloakAuthenticator) : plusieurs `flush()` imbriqués sans transaction → état incohérent possible sur Scopeli.

**Code / dette** :
- 🟠 `CodeVoter::isLocationOk()` (l.151) : méthode privée qui duplique exactement `PlaceIP::isLocationOk()` — commentaire `//DUPLICATED` présent. Refactorer pour utiliser `PlaceIP` directement.
- 🟠 `CodeVoter::OPEN` fall-through vers `DELETE` : asymétrie non documentée ouvrir/fermer code. Un non-admin peut fermer mais pas ouvrir un code.
- 🟠 `KeycloakAuthenticator::createMembership()` : `member_number = rand(10000,100000)` si pas d'`openid_member_number` → collision possible avec les numéros séquentiels.
- 🟡 `CodeVoter::canView()` : fenêtres temporelles hardcodées `PT2H` (−2h) et `PT1H` (+1h) avec `TODO put in conf`.
- 🟡 `code_generate` : code aléatoire `rand(0, 9999)` → peut produire `0000` (all-zero), pas de vérification de collision.

**Non testé** :
- Tout le flux OIDC (Keycloak) : `KeycloakAuthenticator::getUser()`, mapping attributs, co-membership, sync rôles/formations/commissions.
- `swipe_in` (login par badge) et tous les `SwipeCardVoter` attributs.
- `CodeVoter` : VIEW/GENERATE avec débutant, avec créneau actif, avec IP locale, fall-through OPEN→DELETE.
- `UserVoter::CARD_READER` en mode OIDC vs non-OIDC.
- `user_add_role` / `user_remove_role` : restrictions ROLE_ADMIN vs SUPER_ADMIN.
- `code_change_done` en mode non-connecté (token Vigenère).

**Ambigu / à clarifier** :
- `fos_user_registration_*` : ces routes sont-elles utilisées ou désactivées dans la configuration de chaque instance ? Si l'auto-inscription est non voulue, elles devraient être désactivées.
- `ROLE_OAUTH_LOGIN` : rôle présent dans la hiérarchie mais aucun code ne semble l'attribuer explicitement — semble être automatiquement accordé par FOSOAuthServerBundle aux clients OAuth. À confirmer en SPEC.8.
- Comportement complet en mode `oidc_enable=true` côté Scopeli pour les vues membres : quels attributs MembershipVoter permettent encore l'accès (SUPER_ADMIN/ADMIN court-circuitent le check oidc mais USER_MANAGER est bloqué) ? À confirmer.
- `swipe_card_logging_anonymous` : les logs anonymes ne pointent pas vers une `SwipeCard` — comment relier les passages à un membre a posteriori ?

