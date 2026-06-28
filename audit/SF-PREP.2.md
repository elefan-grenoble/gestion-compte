# SF-PREP.2 — Évaluer l'effort de remplacement des bundles bloquants

- [x] **SF-PREP.2** — Évaluer l'effort de remplacement des bundles bloquants

Pour FOSUserBundle et FOSOAuthServerBundle : lire leur usage réel dans `src/` et `config/`. Estimer l'effort de migration vers les alternatives natives. Résultat → TODO avec estimations S/M/L/XL.

  **Résultat**

  Analyse de l'usage réel des deux bundles bloquants (B2 et B3 de SF-PREP.1) et estimation de l'effort de remplacement. Échelle d'effort : **S** (< 0.5 j) · **M** (0.5–2 j) · **L** (2–5 j) · **XL** (> 5 j, risque/coordination externe).

  ---

  ### Décomposition fonctionnelle préalable — deux préoccupations OAuth à NE PAS confondre

  Le code mêle deux rôles OAuth opposés ; seul le premier est bloquant.

  | Rôle | Brique | Ce que ça fait | Statut migration |
  |---|---|---|---|
  | **Serveur OAuth2** (fournisseur d'identité) | `friendsofsymfony/oauth-server-bundle` (B3) | gestion-compte **délivre** des tokens à des apps tierces (Nextcloud, GitLab… via `/oauth/v2/token`, `/oauth/v2/auth`, `/api/oauth/*`) | **Bloquant — à remplacer** |
  | **Client OAuth2 / OIDC** (consommateur) | `knpuniversity/oauth2-client-bundle` (v2.17, compat SF5 ✓) + `App\Security\KeycloakAuthenticator` + `App\Providers\*OauthAuthenticator*` | gestion-compte **consomme** un IdP externe (Keycloak) quand `oidc_enable=true`, et des API tierces (Helloasso, Igloohome) | **Non bloquant** — déjà sur bundle maintenu, hors scope |

⚠️ **Toggle d'instance déterminant** : `OidcFirewallListener` (`src/EventListener/OidcFirewallListener.php`) **désactive** les routes FOSUser (login, /profile/edit, /resetting/request, /member/new…) quand `oidc_enable=true` et redirige `/login → /oauth/login`. Une instance sous Keycloak (OIDC) n'utilise donc PAS l'UI d'auth de FOSUserBundle — mais elle utilise toujours **le modèle de stockage User** (table `fos_user`) et **le serveur FOSOAuth** pour ses apps aval. À confirmer par instance (Elefan / Scopeli) avant de prioriser : si une instance est 100 % OIDC, le risque UX de la migration FOSUser y est moindre, mais le travail entité/serveur reste identique.

  ---

  ### B2 — FOSUserBundle → Security natif Symfony

  **Cible** : abandon total du bundle (déprécié, non supporté SF6+). Remplacement par Security natif + `symfonycasts/reset-password-bundle` + `symfonycasts/verify-email-bundle` (chemin documenté, `make:auth` / `make:registration-form`). La v3.x du bundle existe mais ne fait que repousser le problème → **non recommandée**.

  **Points d'accroche réels relevés :**

  | Zone | Fichier(s) | Détail | Effort |
  |---|---|---|---|
  | Entité | `src/Entity/User.php` | `extends FOS\UserBundle\Model\User`. La classe de base fournit `username/email/password/salt/enabled/roles/lastLogin/confirmationToken/passwordRequestedAt` + canonical fields. Migration = matérialiser ces champs en colonnes réelles + implémenter `UserInterface` + `PasswordAuthenticatedUserInterface` + réimplémenter `addRole/hasRole/removeRole/isEnabled/getRoles`. Garder `@ORM\Table(name="fos_user")` pour **éviter une migration DB**. | **M** |
  | Méthodes héritées consommées | tout `src/` + `templates/` | `hasRole` ×35, `setEnabled` ×13, `addRole` ×8, `removeRole` ×6, `isEnabled` ×6, `setPlainPassword` ×5, `getRoles` ×4, `setLastLogin` ×3, `getConfirmationToken` ×2. Toutes à reporter sur l'entité/trait (pas de changement d'appelant si signatures conservées). | inclus ci-dessus |
  | Config security | `config/packages/security.yaml` | `encoders: FOS\UserBundle\Model\UserInterface: bcrypt` → `password_hashers`. Provider `fos_user.user_provider.username_email` → provider Doctrine custom (login par **username OU email**, à réimplémenter). `form_login` provider/check_path. Le `KeycloakAuthenticator` (guard) est déjà natif. | **M** |
  | Routes auth | `config/routes.yaml` (`@FOSUserBundle/.../all.xml`) | Import à supprimer ; recréer controllers + routes login/logout/registration/resetting/profile/change_password. **Conserver les noms de routes** `fos_user_*` (réf. en dur ci-dessous) OU faire un find/replace global. | **L** |
  | Routes référencées en dur | `UserController:292`, `MembershipController:624,909`, `DefaultController:54`, `SwipeCardController:65`, `MailerService:85,113` | `fos_user_profile_show`, `fos_user_registration_check_email`, `fos_user_security_login`, `fos_user_registration_confirm`, `fos_user_resetting_reset`. | inclus |
  | Mailer | `src/Service/MailerService.php` | `implements FOS\UserBundle\Mailer\MailerInterface` ; `sendConfirmationEmailMessage` / `sendResettingEmailMessage` basées sur `getConfirmationToken`. Découpler de l'interface FOS, déléguer les tokens à reset-password/verify-email-bundle. | **S** |
  | Events | `UserController:133`, `MembershipController:337,788,900`, `SetFirstPasswordListener` | `FOSUserEvents::USER_PASSWORD_CHANGED` (×1 dispatch + listener `onPasswordChanged`) ; `FOSUserEvents::REGISTRATION_SUCCESS` (×3 dispatches, déclenche `SetFirstPasswordListener`). Remplacer par events applicatifs maison. | **M** |
  | Forms | `RegistrationType` (déjà `AbstractType` autonome), `UserType`/`UserWithBeneficiaryType` (custom, **n'étendent PAS** les form types FOS) | Faible impact : retirer le câblage `fos_user.yaml` (`registration.form.type`, `profile.form.type`), garder les classes. | **S** |
  | Templates | `templates/bundles/FOSUserBundle/` | **14 templates** override (layout, Profile, Registration, Resetting ×6, Security/login, ChangePassword) à rebrancher sur les nouveaux controllers/chemins. | **M** |
  | Config bundle | `config/packages/fos_user.yaml`, `config/bundles.php` | Supprimer ; reporter `group_class: App\Entity\Formation` (les "groupes" FOS = Formations, voir `User::getGroups()`) et `from_email`. | **S** |

Note : `OidcFirewallListener` importe `FOS\UserBundle\Event\GetResponseUserEvent` mais ne s'en sert pas (méthode typée `RequestEvent`) → import mort à supprimer au passage.

  **Effort agrégé B2 : `L` (≈ 4–5 j).** Chemin balisé (nombreux guides, `make:*`), faible risque externe, mais volume réel (entité + 14 templates + serveur de routes auth + flux reset/confirm). **Rector n'aide pas** ici (logique, pas annotations).

  ---

  ### B3 — FOSOAuthServerBundle → ⚠️ pas de chemin SF5 propre

  **Constat de l'état de l'art (WebSearch, juin 2026)** : le successeur officiel coordonné avec la core team est **`league/oauth2-server-bundle`** (ex-`trikoder/oauth2-bundle`) — mais il **exige Symfony ≥ 6.4**. Il n'existe **aucune cible stable pour le palier SF5.4** :
  - rester sur FOSOAuth `2.0.0-alpha.0` (instable, bundle abandonné) → **non viable** ;
  - intégration custom directe sur la lib agnostique `league/oauth2-server` pour SF5.4 → **travail sur mesure conséquent** ;
  - **sauter le palier** : reporter la migration du serveur OAuth jusqu'à l'arrivée sur SF6.4 (séquencer SF5.4 d'abord en gardant temporairement FOSOAuth si l'install le permet, ou viser directement 6.4 pour ce bloc).

🔧 **Décision d'architecture à prendre AVANT de coder** (règle 9) : ce bloc conditionne la trajectoire de migration globale. Option recommandée à valider avec l'utilisateur : **migrer SF4.4→5.4 sur tout le reste, traiter le serveur OAuth comme un chantier dédié calé sur SF6.4** plutôt que d'écrire une intégration `league/oauth2-server` jetable pour SF5.4.

  **Points d'accroche réels relevés :**

  | Zone | Fichier(s) | Détail | Effort |
  |---|---|---|---|
  | Entités | `src/Entity/{Client,AccessToken,AuthCode,RefreshToken}.php` | 4 entités `extends FOS\OAuthServerBundle\Entity\*`. À re-modéliser sur le schéma `league` (modèle différent : pas d'entités Doctrine côté league par défaut, repositories à implémenter). | **L** |
  | Config | `config/packages/fos_oauth_server.yaml`, `config/bundles.php`, `config/routes.yaml` | `db_driver`, 4 classes, form authorize, `user_provider` ; routes `token.xml` + `authorize.xml`. À réécrire intégralement. | **M** |
  | Firewall | `config/packages/security.yaml` | `fos_oauth: true` sur firewalls `main` + `api` ; firewalls `oauth_token`/`oauth_authorize`. À remplacer par l'authenticator de token du nouveau bundle. | **M** |
  | Admin clients | `src/Controller/ClientController.php` | CRUD via `fos_oauth_server.client_manager.default` (`createClient`/`updateClient`). Réécrire contre le nouveau gestionnaire de clients. | **M** |
  | Form client | `src/Form/ClientType.php` | Grant types via constantes `OAuth2::*` (lib `friendsofsymfony/oauth2-php`). Remapper sur les grants du nouveau lib. | **S** |
  | Listener autorisation | `src/EventListener/OAuthEventListener.php` | `OAuthEvent` pre/post-authorization (lie User↔Client, `isAuthorizedClient`/`addClient`). À réimplémenter sur le cycle d'événements du nouveau bundle. | **M** |
  | Lien User↔Client | `src/Entity/User.php` | `ManyToMany Client`, `isAuthorizedClient/getClients/addClient`, import `ClientInterface`. À conserver/adapter. | **S** |
  | Consommateurs aval | `src/Controller/ApiController.php` (`ROLE_OAUTH_LOGIN`) | Endpoints `/api/oauth/user`, `/api/oauth/nextcloud_user`, `/api/v4/user` (GitLab) → **SSO réel d'apps tierces**. Grants utilisés : `auth_code` (SSO Nextcloud/GitLab) + `client_credentials`/`user_credentials` (API). Compat tokens/endpoints à préserver, sinon re-enrôler les clients sur **chaque instance**. | **coordination** |

  **Effort agrégé B3 : `XL` (> 5 j + coordination externe).** Pas de remplacement drop-in pour SF5.4 ; re-modélisation complète des 4 entités + serveur de routes + admin + listener, **plus** la coordination SSO avec les apps aval (Nextcloud, GitLab) sur les deux instances. C'est le **chemin critique** de toute la migration SF.

  ---

  ### B4 (rappel SF-PREP.1) — ornicar/gravatar-bundle → inline

  Déjà utilisé inline dans `ApiController:111` (`new GravatarHelper(new GravatarApi())`). Remplacement trivial par calcul d'URL Gravatar maison (~5 lignes : `md5(strtolower(trim($email)))`). **Effort : `S`.**

  ---

  ### TODO consolidée (estimations S/M/L/XL)

  | # | Tâche | Effort | Dépendances / Risque |
  |---|---|---|---|
  | T1 | Confirmer par instance (Elefan/Scopeli) la valeur de `oidc_enable` et l'inventaire des apps aval consommant le serveur OAuth | **S** | Prérequis de priorisation |
  | T2 | Entité `User` : matérialiser les champs BaseUser en colonnes, implémenter `UserInterface`/`PasswordAuthenticatedUserInterface`, garder `fos_user` comme nom de table | **M** | — |
  | T3 | `security.yaml` natif : `password_hashers`, provider username-or-email custom, `form_login` | **M** | T2 |
  | T4 | Controllers + routes auth (login/logout/registration/resetting/profile/change-password) + reset-password & verify-email bundles ; rebrancher 14 templates ; découpler `MailerService` | **L** | T2, T3 |
  | T5 | Remplacer events `FOSUserEvents::*` (REGISTRATION_SUCCESS ×3, USER_PASSWORD_CHANGED) par events maison ; nettoyer import mort dans `OidcFirewallListener` | **M** | T4 |
  | T6 | **Décision archi** serveur OAuth : intégration `league/oauth2-server` custom pour SF5.4 **vs** report sur SF6.4 (recommandé) | **S** (décision) | Bloque T7 |
  | T7 | Remplacer le serveur OAuth (4 entités, routes token/authorize, firewall, admin ClientController, OAuthEventListener) | **XL** | T6 + coordination SSO aval |
  | T8 | Gravatar inline (retirer `ornicar/gravatar-bundle`) | **S** | — |

  **Synthèse effort migration des bloquants : B2 = `L`, B3 = `XL` (chemin critique, pas de cible SF5.4), B4 = `S`.** Recommandation : traiter B2+B4 dans le palier SF5.4, isoler B3 en chantier dédié séquencé sur SF6.4.

