# EXTRA — Pistes découvertes en cours d'audit


> Nouvelles pistes identifiées pendant le traitement d'un item. Format : `- [ ] **[item-origine]** — description`

<!-- Les findings s'ajoutent ici au fil des sessions -->

<a id="extra-1"></a>
- [ ] **[D.5](audit/D.5.md)** — `BeneficiaryController::getErrorMessages()` (ligne 187) : méthode private jamais appelée de l'extérieur (seule la récursion interne subsiste). Rector ne l'a pas détectée en DC.1 (récursion self-référente). À vérifier manuellement en DC.3 comme candidat dead code.

<a id="extra-2"></a>
- [ ] **[DEP.3](audit/DEP.3.md)** — `custom_animation.less` non importée dans `app.js` (absente des `require()` en bas des imports LESS) : les animations CSS ne sont pas bundlées par webpack. En parallèle, `templates/period/index.html.twig:13` référence `{{ asset('bundles/app/css/custom_animation.css') }}` — URL de l'ère pré-webpack, invalide avec Encore (qui output dans `public/build/`). À documenter dans **D.3** addendum et **SYN.2**.

<a id="extra-3"></a>
- [ ] **[DEP.3](audit/DEP.3.md)** — `canvas-gauges` CDN HS : vérifier si `display_gauge` est activé chez Elefan et/ou Scopeli (variable de config ou flag d'instance). Si désactivé partout, la feature peut être retirée plutôt que corrigée. À confirmer en **CONFIG.2**.

<a id="extra-4"></a>
- [ ] **[SPEC.5](audit/SPEC.5.md)** — `fromActionObj()` et `fromPaymentObj()` dans `HelloassoPayment` : dead code (anciens mappings API Helloasso v3). Seul `createFromPayementObject()` (v5) est utilisé. À confirmer via `git log -S fromActionObj` + vérification migrations avant suppression. Candidat **DC.3**.

<a id="extra-5"></a>
- [ ] **[SPEC.5](audit/SPEC.5.md)** — `Registration::TYPE_CREDIT_CARD` (4) et `TYPE_DEFAULT` (5) : définis dans les constantes mais jamais assignés par le code actuel. Le formulaire `RegistrationType` commente `TYPE_CREDIT_CARD`. À vérifier dans les données de production (SELECT DISTINCT mode FROM registration) pour confirmer si ces valeurs existent en base avant suppression des constantes. Candidat **DC.3**.

<a id="extra-6"></a>
- [ ] **[SPEC.5](audit/SPEC.5.md)** — `confirmOrphan()` (`HelloassoController` l.264) : route GET mutante sans vérification de `payment.getRegistration()` avant dispatch d'`ORPHAN_SOLVE`. Contrairement à `resolveOrphan` (l.245) et `editPaymentAction` (l.185), ce cas peut créer une double-liaison si l'orphelin a déjà été résolu. Bug à corriger dans **SYN.2**.

<a id="extra-7"></a>
- [ ] **[SPEC.5](audit/SPEC.5.md)** — `view_abstract_registration` : `AbstractRegistration` est mappée sur une vue SQL (read-only). La migration créant cette vue est à identifier pour comprendre ce qu'elle agrège (Registration + type anonyme ?). Utile pour **SYN.1** (documentation) et pour comprendre `AbstractRegistration::TYPE_ANONYMOUS` (2).

<a id="extra-8"></a>
- [ ] **[DC.2](audit/DC.2.md)** — `AuthenticationSuccessHandler::onAuthenticationSuccess()` viole `AuthenticationSuccessHandlerInterface` : quand `$target` est absent, la méthode retourne `null` implicitement alors que l'interface exige un `Response`. Le `return;` supprimé par Rector masque davantage ce chemin. Bug à classer dans **SYN.2** (bugs, effort XS).

<a id="extra-9"></a>
- [ ] **[AP.7](audit/AP.7.md)** — `CodeEventListener::onCodeNew()` (L30-35) : corps entièrement commenté (seul `$this->logger->info(...)` subsiste). Le listener est enregistré dans le container mais sans logique — candidat suppression ou TODO d'implémentation. Confirmer en DC.3.

<a id="extra-10"></a>
- [ ] **[AP.7](audit/AP.7.md)** — `EmailingEventListener::onHelloassoTooEarly()` L257 : `die($e->getMessage())` tue le process PHP sur exception email. Bug critique — peut produire une page blanche en production lors d'un paiement Helloasso. Corriger en priorité (TODO AP.7.1). Cross-ref SYN.2.

<a id="extra-11"></a>
- [ ] **[TC.5](audit/TC.5.md)** — Aucune commande n'a d'option `--dry-run`, y compris les opérations irréversibles (`app:anonymize`, `app:member:close`, `app:shift:generate`). Recommandation UX opérationnelle : ajouter `--dry-run` aux commandes destructives (output de ce qui serait fait sans modifier la base). À porter dans **SYN.2** (ergonomie CLI, effort S par commande).

<a id="extra-12"></a>
- [ ] **[PERF.1](audit/PERF.1.md), [PERF.2](audit/PERF.2.md)** — Volumétries de prod requises pour valider la sévérité des findings PERF. Les comptages de lignes utilisés pendant l'audit (beneficiary: 56, proxy: 0, closing_exception: 0, shift: 51, membership: 56, time_log: 0) sont issus de la base de test — ils ne reflètent pas la production Elefan ni Scopeli. Avant de prioriser les correctifs PERF, refaire l'analyse avec une dump prod anonymisée (à fournir après anonymisation côté utilisateur). Les sévérités 🔴/🟡 sont des estimations raisonnées mais non confirmées sur données réelles.

<a id="extra-13"></a>
- [ ] **[CI.1](audit/CI.1.md) TODO CI.A** — `shivammathur/setup-php@verbose` dans `.github/workflows/ci.yaml` : `@verbose` est un alias comportemental flottant, pas un tag sémantique. Épingler à une version (`@v2` ou mieux un SHA digest) pour éliminer le risque supply chain. Effort XS.

<a id="extra-14"></a>
- [ ] **[CI.1](audit/CI.1.md) TODO CI.B** — Gap PHP 7.4 (CI) vs PHP 8.1 (container Docker / prod) : la CI valide du code PHP 7.4 alors que le runtime de déploiement est PHP 8.1. Ajouter PHP 8.1 à la matrice CI (ou remplacer 7.4 par 8.1 si 7.4 n'est plus ciblé). Effort XS (ajout d'entrée matrice + vérification des extensions requises). Cross-ref SF-PREP.

<a id="extra-15"></a>
- [ ] **[CI.1](audit/CI.1.md) TODO CI.C** — Pas de lint JS/CSS dans la CI (pas d'ESLint, pas de Stylelint). PHPStan couvre PHP uniquement. Si du code JS/CSS évolue, les erreurs de syntaxe ou de style ne sont pas détectées automatiquement. Optionnel — effort XS si les outils sont déjà présents dans `package.json`.

<a id="extra-16"></a>
- [ ] **[SPEC.3](audit/SPEC.3.md)** — `shift_accept_reserved` (GET `/shift/{id}/accept`) et `shift_reject_reserved` (GET `/shift/{id}/reject`) : mutations d'état via verbe GET — pas de protection CSRF, rejouables par un lien dans un email (le flow d'invitation les utilise exactement ainsi). Risque limité (le voter `accept`/`reject` vérifie l'identité), mais contraire aux bonnes pratiques REST. À porter dans **SYN.2** (sécurité, effort XS → POST + token CSRF).

<a id="extra-17"></a>
- [ ] **[SPEC.3](audit/SPEC.3.md)** — `shift_contact_form` (GET|POST `/shift/{id}/contact_form`) : aucune annotation `@Security` → route publique de facto. N'importe qui peut envoyer un email aux co-bénévoles d'un créneau en connaissant un `id` de `Shift`. Ajouter au moins `ROLE_USER`. À porter dans **SYN.2** (sécurité, effort XS).

<a id="extra-18"></a>
- [ ] **[SPEC.3](audit/SPEC.3.md)** — `FreeReservedShiftsCommand` (`app:shift:free <date>`) : doit être exécuté `reserve_new_shift_to_prior_shifter_delay` jours après `ShiftGenerateCommand`. Cette coordination n'est documentée ni dans le README ni dans les commandes elles-mêmes. Un mauvais ordonnancement cron laisse des pré-réservations en suspens indéfiniment. À documenter dans **SYN.1** + **SYN.2** (ergonomie CLI/ops).

<a id="extra-19"></a>
- [ ] **[SPEC.3](audit/SPEC.3.md)** — `FixShiftMissingPositionCommand` (`app:shift:fix_missing_position`) retourne exit code 1 explicitement pour `cycle_type=abcd` avec message d'erreur. Les instances avec `cycle_type=abcd` n'ont donc pas de commande de réparation des `Shift.position=null` issus de la migration `Version20211223205749`. À tracer dans **SYN.2** (dette technique, effort M pour implémenter le filtre weekCycle manquant).

<a id="extra-20"></a>
- [ ] **[SPEC.4](audit/SPEC.4.md)** — `swipe_qr` (GET `/sw/{code}/qr.png`) et `swipe_br` (GET `/sw/{code}/br.png`) : aucune annotation `@Security` → images téléchargeables sans authentification par quiconque connaissant le code Vigenère (présent dans les URLs des emails `swipe_in`). Permettent l'usurpation de badge (connexion passwordless + validation de présence). Ajouter `@Security("is_granted('ROLE_USER_MANAGER')")` ou restreindre au propriétaire du badge. Porter dans **SYN.2** (sécurité, effort XS).

<a id="extra-21"></a>
- [ ] **[SPEC.4](audit/SPEC.4.md)** — `code_change_done` (GET `/codes/close_all`) : aucune `@Security` ; authentification temporaire via token Vigenère URL (`username,code:id`) sans date d'expiration → rejouable indéfiniment. Le controller impersonifie l'utilisateur (`setToken`/`setToken(previousToken)`) pour fermer des codes tiers. Ajouter expiration au token ou exiger une session active. Porter dans **SYN.2** (sécurité, effort S).

<a id="extra-22"></a>
- [ ] **[SPEC.4](audit/SPEC.4.md)** — `user_install_admin` (GET\|POST `/user/install_admin`) : aucune `@Security` ; accessible sans authentification avant le premier setup (aucun SUPER_ADMIN en base). Risque de prise de contrôle si la route est atteinte par un tiers avant l'installation. Documenter la procédure de sécurisation réseau ou ajouter un guard d'environnement. Porter dans **SYN.2** (sécurité, effort XS).

<a id="extra-23"></a>
- [ ] **[SPEC.4](audit/SPEC.4.md)** — `user_add_role` (GET `/user/{id}/addRole/{role}`) : mutation via verbe GET sans protection CSRF — un lien forgé peut ajouter un rôle sans confirmation. Remplacer par POST + formulaire CSRF. Porter dans **SYN.2** (sécurité, effort XS).

<a id="extra-24"></a>
- [ ] **[SPEC.4](audit/SPEC.4.md)** — `CodeVoter::isLocationOk()` (l.151) : méthode privée qui duplique `PlaceIP::isLocationOk()`, avec commentaire `//\App\Security\UserVoter::isLocationOk DUPLICATED`. Refactorer pour injecter `PlaceIP` directement dans `CodeVoter` (comme dans `MembershipVoter`). Porter dans **SYN.2** (dette, effort XS).

<a id="extra-25"></a>
- [ ] **[SPEC.4](audit/SPEC.4.md)** — `CodeVoter` : fall-through de `case self::OPEN:` vers `case self::DELETE:` (PHP switch sans `break`) — un non-ROLE_ADMIN qui tente d'ouvrir un code fermé (`toggle`) passe par la branche DELETE (→ SUPER_ADMIN ou `canDelete()` = false). Asymétrie ouvrir/fermer non documentée. À clarifier si intentionnel et documenter, ou corriger avec un `break` et une règle explicite. Porter dans **SYN.2** (dette/bug, effort XS).

<a id="extra-26"></a>
- [ ] **[SPEC.4](audit/SPEC.4.md)** — `code_generate` : le code de porte est `rand(0, 9999)` (4 chiffres, peut produire `0000`), sans vérification de collision avec les codes ouverts existants. Si deux membres génèrent en parallèle, ils peuvent obtenir le même code. Porter dans **SYN.2** (robustesse, effort S).

<a id="extra-27"></a>
- [ ] **[SPEC.7](audit/SPEC.7.md)** — `EmailingEventListener::onAnonymousBeneficiaryCreated/Recall` + `onShiftReminder` + `MailerService::sendConfirmationEmailMessage` : appels `->findOneByCode(...)->getContent()` sans null-guard → exception fatale si le `DynamicContent` requis est absent de la base (codes: `PRE_MEMBERSHIP_EMAIL`, `SHIFT_REMINDER_EMAIL`, `WELCOME_EMAIL`). Ajouter une garde avec fallback vide ou message d'erreur lisible. Porter dans **SYN.2** (robustesse, effort XS × 4).

<a id="extra-28"></a>
- [ ] **[SPEC.7](audit/SPEC.7.md)** — `CycleStartCommand` (`app:user:cycle_start`) dispatche `MemberCycleEndEvent` : nommage trompeur — la commande s'appelle "start" mais déclenche "end". La chaîne réelle est `CycleStartCommand → member.cycle.end → TimeLogEventListener → member.cycle.start → EmailingEventListener`. À documenter dans **SYN.1** (architecture des crons, section cycle).

<a id="extra-29"></a>
- [ ] **[SPEC.7](audit/SPEC.7.md)** — `HelloassoEvent::RE_REGISTRATION_SUCCESS = 'helloasso.registration_success'` : constante nommée RE_REGISTRATION mais couvrant aussi la première adhésion — la branche email (template `registration.html.twig` vs `reregistration.html.twig`) se fait sur `registrations.count > 1` dans le listener, pas dans l'event. Nommage trompeur ; à renommer ou documenter.

<a id="extra-30"></a>
- [ ] **[SPEC.7](audit/SPEC.7.md)** — `onAnonymousBeneficiaryRecall` (l.116-118) : utilise `$this->container->get('App\Helper\SwipeCard')` alors que `$this->swipeCardHelper` est déjà injecté dans le constructeur (l.49). Incohérence d'injection — probable copier/coller de l'ancien code pré-refactoring. Porter dans **SYN.2** (dette, effort XS).

<a id="extra-31"></a>
- [ ] **[SPEC.7](audit/SPEC.7.md)** — SSTI via `DynamicContent` : `twig->createTemplate($content)` dans `onShiftReminder` (l.409), `onShiftAlerts` (l.489), `MattermostEventListener::onShiftAlerts` (l.43), `AmbassadorShiftTimeLogCommand` (l.82) et `MailController::sendAction` (l.156) exécute du Twig arbitraire depuis la base de données. Acceptable si ROLE_PROCESS_MANAGER est bien restreint (cross SPEC.6 gap firewall) — à confirmer que seuls des admins de confiance éditent ces contenus.

<a id="extra-32"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — 🟠 **Ordre des règles `access_control` (`security.yaml`)** : `^/api → IS_AUTHENTICATED_FULLY` (L59) précède `^/api/oauth/ → ROLE_OAUTH_LOGIN` (L60). Symfony applique la **première règle qui matche** → la règle L60 est **inatteignable** (dead rule). `ROLE_OAUTH_LOGIN` n'est imposé que par les annotations `@Security` sur `ApiController::userAction`/`nextcloudUserAction`, pas au niveau firewall. Correctif : placer `^/api/oauth/` **avant** `^/api`. Porter dans **SYN.2** (sécurité / défense en profondeur, effort XS).

<a id="extra-33"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — 🟡 **`api_gitlab_user` (`GET /api/v4/user`)** : protégé par `IS_AUTHENTICATED_FULLY` uniquement (ni `@Security ROLE_OAUTH_LOGIN`, ni règle access_control atteinte — cf. finding ci-dessus). Un membre authentifié **par session** (hors flux token OAuth) peut donc l'appeler, contrairement aux autres endpoints `/api/oauth/*`. De plus, la réponse contient de **nombreux champs codés en dur/factices** (`created_at`, `confirmed_at`, `two_factor_enabled`, `current_sign_in_at`…) simulant le contrat API GitLab v4. À clarifier : restreindre à `ROLE_OAUTH_LOGIN` si l'intention est OAuth-only, et documenter le shim. Porter dans **SYN.2** (sécurité + dette, effort XS).

<a id="extra-34"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — 🟡 **Webhook `/helloassoNotify` non authentifié et non signé** (`DefaultController::helloassoNotify`) : endpoint POST public, sans vérification de signature (réservée aux partenaires Helloasso — assumé dans le code). Mitigation existante : re-fetch du paiement via l'API Helloasso (`getPayment(data['id'])`) + idempotence `savePayments` (dédup `paymentId`). Risque résiduel : déclencheur public d'appels sortants authentifiés ; `data['id']` non validé est interpolé dans l'URL (`sprintf('payments/%s', $id)`) — **injection de chemin** dans l'URL de l'API Helloasso (domaine fixe `base_uri`, donc pas de SSRF arbitraire ; et le `id` provient du serveur Helloasso, pas d'un payload librement forgeable sans contrôler la source du webhook). Suivre l'évolution de l'API signature Helloasso pour durcir. Porter dans **SYN.2** (sécurité, effort S).

<a id="extra-35"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — 🟡 **`OidcFirewallListener` : protection par denylist fragile** : la désactivation des outils de compte local sous OIDC repose sur une **liste codée en dur de préfixes d'URI** (`str_starts_with`) + `str_contains(uri,'removeRole')`. Un renommage de route ou un nouveau point d'entrée d'écriture casse silencieusement la protection. Recommandation : passer à une liste blanche de routes autorisées, ou un attribut de route/`@Security` centralisé. Porter dans **SYN.2** (sécurité / robustesse, effort M).

<a id="extra-36"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — 🟡 **Pas de révocation de consentement OAuth** : `OAuthEventListener::onPostAuthorizationProcess` mémorise le consentement (`User.clients` M2M) mais aucune route UI ne permet à un membre de **révoquer** l'accès d'un `Client` précédemment autorisé. À évaluer : exposer une gestion des autorisations dans le profil membre. Porter dans **SYN.2** (fonctionnel / RGPD, effort M).

<a id="extra-37"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — 🟡 **`KeycloakAuthenticator::updateBeneficiary` : RAZ des rôles à chaque login OIDC** : `getUser()->setRoles([])` puis re-mapping depuis les claims Keycloak à **chaque connexion**. Toute attribution **locale** de rôle/formation/commission est écrasée au prochain login du membre (Keycloak fait autorité). Comportement à fort impact opérationnel, non documenté hors code. À documenter dans **SYN.1** (architecture OIDC).

<a id="extra-38"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — 🟢 **`api_swipe_in` sans garde login-as** : contrairement à `api_user`/`api_nextcloud_user`/`api_gitlab_user` (qui rejettent `ROLE_PREVIOUS_ADMIN`), `ApiController::swipeInAction` n'a pas la garde « DO NOT ALLOW OAUTH ON LOGIN AS ». Incohérence mineure (l'endpoint ne renvoie qu'un `success:true` sans donnée d'identité, impact faible). Porter dans **SYN.2** (cohérence, effort XS).

<a id="extra-39"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — 🟢 **`KeycloakAuthenticator::createMembership` : `member_number = rand(10000,100000)` en fallback** : si aucun numéro d'adhérent n'est fourni par Keycloak ni le bénéficiaire, un numéro aléatoire est tiré, **sans vérification de collision**. Provisioning JIT pouvant créer des doublons de numéro. Porter dans **SYN.2** (robustesse, effort S). Cross-ref pattern identique à `code_generate` (EXTRA SPEC.4).

<a id="extra-40"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — ℹ️ **Correction d'un finding SPEC.5** : l'EXTRA `[SPEC.5]` indiquait que `Registration::TYPE_DEFAULT` (5) « n'est jamais assigné par le code actuel ». **Faux** : `KeycloakAuthenticator::createMembership` (l.260) fait `setMode(Registration::TYPE_DEFAULT)` lors du provisioning OIDC. La constante est donc utilisée — uniquement sur les instances OIDC. À intégrer à l'analyse DC.3 avant toute suppression de constante.

<a id="extra-41"></a>
- [ ] **[SPEC.8](audit/SPEC.8.md)** — ℹ️ **Résolution EXTRA SPEC.4 (`ROLE_OAUTH_LOGIN` non attribué, voir [SPEC.4](audit/SPEC.4.md))** : résolu en SPEC.8 §R4. Le rôle est dérivé dynamiquement par `FOSOAuthServerBundle` du **scope** `oauth_login` du token (firewall `fos_oauth`), ou mappé depuis un rôle Keycloak (`OIDC_ROLE_OAUTH_LOGIN`) — jamais stocké sur le `User`. Aucune action requise, documentation à reporter en SYN.1.

<a id="extra-42"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🔴 **`EventController::acceptProxyAction` (l.418-420) : appel de méthode sur un array** : `findBy()` retourne un array, puis `$myproxy->getOwner()->getFirstname()` → `Error: Call to a member function getOwner() on array`. Chemin atteint quand le porteur dépasse déjà `max_event_proxy_per_member`. Crash en production dans le message d'erreur. Corriger : utiliser `count()`/itérer, ou `findOneBy`. Porter dans **SYN.2** (bug, effort XS).

<a id="extra-43"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟠 **`EventController::deleteProxyLiteAction` (l.358) : NPE sur proxy en attente** : `$proxy->getOwner()->getUser()` sans null-guard → erreur fatale si `owner` est null (proxy anonyme/en attente). Atteignable par URL forgée (route GET). Ajouter un null-guard. Porter dans **SYN.2** (bug, effort XS).

<a id="extra-44"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟠 **`Event::getProxiesByOwnerMembershipMainBeneficiary()` (l.432-436) : NPE** : `$proxy->getOwner()->getMembership()->getMainBeneficiary()` sans null-guard ; les proxies en attente (owner null) dans la collection font échouer le filtre. (`getProxiesByOwner()` l.424-429 fait une simple comparaison d'identité — non concernée.) Porter dans **SYN.2** (bug, effort XS).

<a id="extra-45"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟠 **`EventExtension::receivedProxies()` : TypeError** : signature de retour `: array` mais `return null` quand aucun utilisateur connecté. Aligner sur `givenProxy()` (`: ?Proxy`) ou retourner `[]`. Porter dans **SYN.2** (bug, effort XS).

<a id="extra-46"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟠 **`event_detail` (GET `/events/{id}`) sans `@Security`** : détail d'événement (titre, description, date, **lieu**, image de l'AG) public alors que `event_index` exige `ROLE_USER`. Fuite d'information par incohérence. Ajouter `ROLE_USER` ou documenter l'intention publique. Porter dans **SYN.2** (sécurité, effort XS).

<a id="extra-47"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟡 **`event_proxy_lite_delete` (GET `/events/{id}/proxy/{proxy}/remove`) : mutation via GET sans CSRF** : suppression de procuration rejouable par lien. Passer en POST/DELETE + token. Cohérent avec les findings GET-mutant SPEC.3/SPEC.4. Porter dans **SYN.2** (sécurité, effort XS).

<a id="extra-48"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟡 **`ProxyRepository` vide → logique de requête éparpillée** : ≥ 8 `findOneBy`/`findBy` inline dans `EventController`, 2 méthodes de pure délégation query builder dans `EventService` (47 l.), et filtres de collection dans `Event`. Consolider dans `ProxyRepository` (`findGivenBy`, `findReceivedBy`, `findWaiting`, `findAllWithAssociations` — ce dernier résout aussi PERF cas #2 N+1). `EventService` deviendrait une fine façade ou disparaîtrait. Porter dans **SYN.2** (dette, effort M).

<a id="extra-49"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟡 **`AdminEventController::editEventProxyAction` (l.265-347)** : ~80 l., 4 branches d'appariement quasi dupliquées, flash messages **en anglais** exposés à l'admin (« proxy 12 saved ») — incohérent avec l'UI FR. Refactor + i18n. Porter dans **SYN.2** (dette, effort M).

<a id="extra-50"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟡 **Création d'AG en deux temps** : `need_proxy`/`anonymous_proxy`/`max_date_of_last_registration` indisponibles à la création (`EventType` ne les ajoute que si `getId()`). UX piégeuse. À documenter en **SYN.1** a minima.

<a id="extra-51"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — ❓ **`anonymous_proxy` : flag potentiellement non appliqué** : la branche anonyme de `giveProxyAction` (form vide → proxy giver/owner null) ne teste pas `$event->getAnonymousProxy()`. Vérifier côté templates si le flag gouverne réellement l'UI, sinon dead config. À confirmer en **CONFIG.2**.

<a id="extra-52"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — 🟡 **`getLastRegistration()->getDate()` sans null-guard** (`give`/`take` éligibilité) : NPE si une adhésion sans aucune registration atteint le contrôle. Porter dans **SYN.2** (robustesse, effort XS).

<a id="extra-53"></a>
- [ ] **[SPEC.11](audit/SPEC.11.md)** — ℹ️ **Décompte de routes domaine H** : SPEC.1 estimait ~16 routes pour le domaine H ; le décompte réel est **22** (7 `event_*` + 11 `admin_event_*`/`admin_proxies_list` + 4 `admin_event_kind_*`). Ajuster le total ~205 routes applicatives de SPEC.1 si une consolidation finale est faite en SYN.3.

