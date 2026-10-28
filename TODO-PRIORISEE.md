# TODO priorisée — gestion-compte

> Consolidation des findings actionnables de l'audit technique (`audit/README.md`, sections D → SPEC + EXTRA).
> Produit en **SYN.2**. Chaque entrée référence sa section d'origine entre `[crochets]` pour retrouver le détail dans `audit/README.md`.
>
> Cette TODO est un **backlog d'état des lieux**, pas un plan d'exécution. Aucun code métier n'a été modifié pendant l'audit.

> **Suite de tests (lots 0→7).** Les entrées révélées ou épinglées par la suite de tests citent le test qui les couvre (**Test :** `Classe::méthode`) ; *(incomplete)* signale un test écrit sur le comportement cible, tant que le bug ou la faille est ouvert. Les entrées marquées ✅ sont corrigées (hash du commit). Les ID des tests (`SHIFT-QUOTA-CACHE`, `C-SEC-1`…) sont stables : `OpenFindingsTraceabilityTest` échoue si un test cite un ID absent de ce fichier. Voir [`doc/tests.md`](doc/tests.md).

## Légende

**Sévérité**
- 🔴 **Critique** — faille de sécurité exploitable, bug provoquant crash / corruption / feature cassée en prod, dépendance vulnérable.
- 🟠 **Important** — durcissement sécurité, bug latent atteignable, dette structurelle bloquant la maintenance ou la migration, gap de tests majeur.
- 🟡 **Mineur** — défense en profondeur, dead code, antipattern cosmétique, amélioration non urgente.

**Effort** (échelle harmonisée avec SF-PREP.2)
- **XS** : < 2h, mécanique, sans risque de régression.
- **S** : ~½ journée.
- **M** : ~1 journée.
- **L** : 2–5 jours.
- **XL** : > 5 jours / sprint, avec coordination externe.

> ⚠️ **Réserve volumétrie (PERF)** : les sévérités des findings PERF sont des estimations raisonnées sur la base de test (peu de lignes). Elles doivent être reconfirmées sur un dump prod anonymisé Elefan/Scopeli avant priorisation définitive — voir [« Re-audit requis sur données de prod »](#re-audit-requis-sur-données-de-prod--perf1-perf2) en fin de document. Cf. [PERF.1](audit/PERF.1.md), [PERF.2](audit/PERF.2.md).

---

## 🔴 Critique

### Sécurité — failles exploitables

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| C-SEC-1 | **`setEmailAction` (POST `/member/{id}/set_email`) — ni auth ni CSRF → account takeover.** Un anonyme (ou une page externe forgée) modifie l'email d'un compte à email temporaire, puis déclenche un reset de mot de passe sur l'adresse qu'il contrôle. Fix : `@Security('ROLE_USER')` + conversion en Symfony Form (CSRF). Reconsidérer le flow d'activation anonyme. **Test :** `SetEmailSecurityTest::testAnonymousVisitorCannotSetTheEmailOfATemporaryAccount` ; `SetEmailSecurityTest::testAnotherMemberCannotSetTheEmailOfATemporaryAccount`. *(incomplete tant que ouverte ; `SmokeTest::testSetEmailIsPubliclyReachable` fige l'accès public)* | XS (+ M flow) | [SEC.2.1](audit/SEC.2.md#SEC.2-1), [SEC.3.4](audit/SEC.3.md#SEC.3-4) |
| C-SEC-2 | ✅ **Corrigé** (`66260967`, #1256). **Pas de règle `access_control` default-deny.** Tout controller sans `@Security` est silencieusement public (modèle opt-in fragile, ~42 controllers). Fix : règle terminale `{ path: ^/, role: IS_AUTHENTICATED_REMEMBERED }` + liste blanche d'exceptions publiques. **Test :** `AccessControlRulesTest::testAPathNoRuleMentionsRequiresALogin` ; `AccessControlRulesTest::testTheTerminalRuleComesLast` ; `AnonymousRouteAccessTest`. *Effet de bord : le lien accept/reject envoyé par email exige désormais un login — voir [SHIFT-ACCEPT-LINK](#décisions-produit-en-attente).* | M | [SEC.1.1](audit/SEC.1.md#SEC.1-1) |
| C-SEC-3 | **Upload images sans validation MIME/extension** (`Service::$logoFile`, `Event::$imgFile`) **+ stockage exécutable dans le document root** (`web/uploads/` sans `.htaccess` anti-exécution). Combinés : upload d'un `webshell.php` exécutable. Fix : `@Assert\Image(mimeTypes,maxSize)` + `web/uploads/.htaccess` (deny `\.php$`) + `namer_hash`. Atténué : routes `ROLE_ADMIN`. | S | [SEC.5 F1](audit/SEC.5.md#SEC.5-F1), [F2](audit/SEC.5.md#SEC.5-F2), [F3](audit/SEC.5.md#SEC.5-F3) |
| C-SEC-4 | **Codes de badge : chiffrement Vigenère + `rand()`.** Vigenère non cryptographique (clé répétée) ; si `swipeCardSecret` fuite, tous les QR/badges sont forgés (accès physique au local). `rand()` non sûr → codes prédictibles. Fix : HMAC-SHA256 tronqué ou token `random_bytes(16)` en DB + migration des codes. | M | [SEC.1.7](audit/SEC.1.md#SEC.1-7) |
| C-SEC-5 | ✅ **Corrigé** (`b0c7097`). **Token Vigenère `code_change_done` (GET `/codes/close_all`) sans expiration ni session** → rejouable indéfiniment ; impersonifie l'utilisateur pour fermer des codes tiers. Fix : expiration du token ou exiger une session active. **Test :** `CodeChangeDoneTokenTest::testAnExpiredLinkIsRefused` ; `CodeChangeDoneTokenTest::testALinkWithoutIssueTimeIsRefused`. | S | [SPEC.4](audit/SPEC.4.md) |
| C-SEC-6 | ✅ **Corrigé** (`66260967`, #1256). **Règle `access_control` `^/api/oauth/` inatteignable** : placée après `^/api → IS_AUTHENTICATED_FULLY` (Symfony applique la 1re règle qui matche). `ROLE_OAUTH_LOGIN` n'est imposé que par les annotations `@Security`. Fix : déplacer `^/api/oauth/` **avant** `^/api`. **Test :** `AccessControlRulesTest::testApiOauthRoutesRequireTheOauthLoginRole`. *Voir aussi I-SEC-14 : les firewalls `oauth_*` / `api` restent inopérants.* | XS | [SPEC.8](audit/SPEC.8.md) |

### Bugs confirmés (crash / corruption / feature cassée)

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| C-BUG-1 | ✅ **Corrigé** (`b0c7097`). **`EmailingEventListener::onHelloassoTooEarly()` L257 : `die($e->getMessage())`** tue le process PHP sur exception email → page blanche en prod lors d'un paiement Helloasso, sans log ni réponse propre. Fix : `throw` ou log + réponse propre. **Test :** `Unit\EmailingEventListenerTest::testAFailureBuildingTheTooEarlyEmailIsThrownNotExited`. | XS | [AP.7.1](audit/AP.7.md#AP.7-1), [LOG.2](audit/LOG.2.md) |
| C-BUG-2 | ✅ **Corrigé** (`b0c7097`). **`RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY` casté `bool:`** dans `services.yaml:147` : `7` jours → `true` (1). Délai de priorité aux anciens bénéficiaires faux en prod. Fix : retirer `bool:`. **Test :** `AuditQuickWinsRegressionTest::testThePriorShifterReservationDelayIsANumberOfDays` ; `ShiftGenerateCommandTest::testTellsTheFormerShifterForHowLongTheShiftIsReserved`. | XS | [CONFIG.1](audit/CONFIG.1.md) |
| C-BUG-3 | ✅ **Corrigé** (`b0c7097`). **`EventController::acceptProxyAction` (l.418-420) : appel de méthode sur un array.** `findBy()` retourne un array puis `$myproxy->getOwner()` → `Error`. Crash atteint quand le porteur dépasse `max_event_proxy_per_member`. Fix : `findOneBy` ou itérer. **Test :** `EventProxyTakeTest::testAMemberAlreadyHoldingTheMaximumOfProxiesIsRefusedPolitely`. | XS | [SPEC.11](audit/SPEC.11.md) |
| C-BUG-4 | **`CYCLE_DURATION` ignoré — durée de cycle hardcodée dans `MembershipService`** (5 points de cycle : `28` aux l.146, 147, 156, 181 ; `27` l.170). Calcul de cycle cassé si `CYCLE_DURATION ≠ '28 days'`. **Dormant** : `CYCLE_TYPE=abcd` non affecté (Elefan/Scopeli), mais risque à l'onboarding d'une nouvelle instance. La fenêtre `canRegister` `+28 j` (l.75) est **distincte** (cf. m-CFG-2). Fix : injecter `cycle_duration`. **Test :** `CycleStartCommandTest::testCycleLengthFollowsTheCycleDurationParameter`. *(incomplete)* **Précision :** le défaut est dans `MembershipRepository::findWithNewCycleStarting()` (`MOD(DATE_DIFF(...), 28)`, l.81 et l.107), pas seulement dans `MembershipService` ; le listener de fin de cycle lit bien `CYCLE_DURATION`. | S | [CONFIG.3](audit/CONFIG.3.md) |
| C-BUG-5 | ✅ **Corrigé** (`b0c7097`). **Feature « jauge » cassée en prod : `canvas-gauges` chargé via CDN rawgit.com** (fermé oct. 2019). Rendu dans `home_dashboard.html.twig` (`{% if display_gauge %}`). Fix : `require('canvas-gauges')` dans `app.js` (paquet npm déjà installé) — ou retirer la feature si `display_gauge` désactivé partout (à confirmer CONFIG.2). **Test :** `AuditQuickWinsRegressionTest::testPagesLoadNoThirdPartyScript`. | XS | [DEP.3](audit/DEP.3.md) |
| C-BUG-6 | **Système de migrations incohérent.** `schema:create` en test (bypasse les migrations), bloc migration commenté en prod (`dploy.sh`), table `migration_versions` absente → migrations jamais appliquées nulle part ; 98/99 sans garde d'idempotence (crash garanti si `migrate` lancé). Fix : synchroniser le tracking (`migrations:version --add --all`) + rétablir `migrate` au déploiement (coordination 2 instances). | M | [DB.2](audit/DB.2.md) |
| C-BUG-7 | **`swipe_qr` (`/sw/{code}/qr.png`) plante systématiquement** : le code utilise l'API endroid/qr-code v3 (`ErrorCorrectionLevel`) alors que la v4 est installée. Les badges QR ne sont pas générables ; seul `br.png` (code-barres) fonctionne. Fix : porter l'appel sur l'API v4 (ou épingler la v3). **Test :** aucun test dédié — `BadgeSecurityTest` documente l'écart et ne vérifie que le code-barres. | XS | lot 2 (#7) |
| BOOKING-EXPIRED | **Page de réservation (`BookingController::indexAction` l.106) : `+` unaire parasite devant `$remainder`** (`+ $remainder = $membership_service->getRemainder(...)`). Sur PHP 8 (prod : 8.1), un membre à l'adhésion expirée obtient une `TypeError` (500) au lieu du message « ton adhésion a expiré… ». Fix : retirer le `+`. **Test :** `BookingControllerTest::testAMemberWhoseRegistrationExpiredCannotBook` *(incomplete)*. | XS | lot 3 (#9) |

### Dépendances vulnérables

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| C-DEP-1 | **30 CVE PHP dans 14 paquets Symfony 4.4** (auth bypass `security-http`, SQLi `cache`, injection en-têtes `mime/mailer`, RCE template `twig`, ReDoS/Billion-Laughs `yaml`…). Toutes corrigées dans les versions 4.4.x maintenues. Fix : `composer update symfony/*` (sans rupture SF4). **Mesure de référence (lot 0) :** `composer audit` remonte 49 alertes sur 17 paquets (twig/twig 15, guzzlehttp/guzzle 9, guzzlehttp/psr7 4, plusieurs symfony/*) ; job CI non bloquant `composer-audit` (résumé de job). Hors Symfony : voir C-DEP-3. | S | [DEP.1](audit/DEP.1.md) |
| C-DEP-2 | **`simplemde` (éditeur markdown, dépendance de prod) : XSS sans correctif, paquet archivé depuis 2017.** Fix : remplacer par `EasyMDE` (fork maintenu, API compatible). | M | [DEP.1](audit/DEP.1.md), [DEP.3](audit/DEP.3.md) |
| C-DEP-3 | **Alertes `composer audit` hors Symfony** : twig/twig (15), guzzlehttp/guzzle (9), guzzlehttp/psr7 (4). Fix : `composer update` ciblé (sans rupture si les contraintes le permettent) puis rendre le job `composer-audit` bloquant. **Test :** job CI `composer-audit` (non bloquant). | S–M | lot 0 (#4), lot 6 (#10) |

---

## 🟠 Important

### Sécurité — durcissement

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| I-SEC-1 | **`switch_user` sans CSRF** — impersonation via lien GET `?_login_as=` (XSS ou lien piégé suffit). Fix : `check_csrf_token: true` + liens en POST + `csrf_token('switch_user')`. **Test :** `UserSecurityTest::testAGetLinkCannotSwitchUser`. *(incomplete)* | XS | [SEC.1.2](audit/SEC.1.md#SEC.1-2) |
| I-SEC-2 | ✅ **Fermé par le default-deny** (C-SEC-2). **`/shift/{id}/contact_form` — envoi d'email sans authentification.** Spam SMTP coopératif. Fix : `@Security('ROLE_USER')`. **Test :** `ShiftSecurityTest` ; `ShiftControllerContactFormTest`. *(tests ordinaires)* | XS | [SEC.1.3](audit/SEC.1.md#SEC.1-3), [SPEC.3](audit/SPEC.3.md) |
| I-SEC-3 | **`/shift/{id}/accept` et `/reject` — voter seul comme guard** (pas de `@Security`), mutation par GET sans CSRF, rejouable par lien email. Fix : `@Security('ROLE_USER')` + passage POST + token. **Test :** `ShiftSecurityTest::testAGetLinkCannotAcceptAReservedShift` ; `ShiftSecurityTest::testAGetLinkCannotRejectAReservedShift`. *(incomplete : mutation par GET sans CSRF)*. Le cas anonyme est fermé par le default-deny. | XS | [SEC.1.5](audit/SEC.1.md#SEC.1-5), [SPEC.3](audit/SPEC.3.md) |
| I-SEC-4 | **`/card_reader/check` (POST) — validation de créneau sans auth** (alors que `indexAction` est protégée). Fix : `denyAccessUnlessGranted('card_reader', user)`. **Sévérité canonique 🟠** : SPEC.3/SPEC.4 le notent 🔴 en tant que **chaîne** avec la forgeabilité des badges (C-SEC-4 / SEC.1.7) — la criticité est portée par C-SEC-4, ne pas double-compter ici. **Test :** `BadgeSecurityTest::testAnonymousVisitorCannotValidateAShiftWithABadgeNumber`. *(incomplete)* | XS | [SEC.2.2](audit/SEC.2.md#SEC.2-2), [SPEC.3](audit/SPEC.3.md) |
| I-SEC-5 | **`CommissionController` add/remove beneficiary — auth maison** → fatal error sur user anonyme (`"anon."->hasRole()`), et lecture `$_POST` direct. Fix : `@Security` + voter, `$request->request->get()`. **Test :** `CommissionSecurityTest::testAnAccountWithoutBeneficiaryIsRefusedRatherThanCrashing` ; `CommissionSecurityTest::testTheOwnerRemovesAMemberFromTheRequestData`. *(incomplete)*. Le cas anonyme est fermé par le default-deny. | XS | [SEC.2.3](audit/SEC.2.md#SEC.2-3) |
| I-SEC-6 | **SwipeCard — 4 routes POST sans CSRF** (`activate/enable/disable/delete`) : désactivation de badge (DoS d'accès) ou association de badge à un compte victime. Fix : Symfony Form + CSRF sur 4 actions + 3 templates. **Test :** `BadgeSecurityTest::testAForgedPostCannotDisableABadge` ; `…EnableABadge` ; `…PairABadge` ; `…DeleteABadge`. *(incomplete)* | S | [SEC.3.5](audit/SEC.3.md#SEC.3-5) |
| I-SEC-13 | **Autres POST sans token CSRF** : `card_reader_check` (validation créneau, formulaire JS forgé), `shift_book` (endpoint JSON ; le formulaire `shift_alone.html.twig` est en plus **cassé** — form-encoded vers endpoint JSON), `helloasso_manual_paiement_add` (atténué `ROLE_FINANCE_MANAGER`). Fix : vérif CSRF côté controller / Symfony Form ; corriger le formulaire cassé. **Test :** `ShiftControllerBookTest::testTheFormPostedByShiftAloneBooksTheShift`. *(incomplete)*. **Constat lot 3 :** le formulaire form-encoded posté vers l'endpoint JSON ne réserve pas le créneau. | XS | [SEC.3.2](audit/SEC.3.md#SEC.3-2), [SEC.3.3](audit/SEC.3.md#SEC.3-3), [SEC.3.6](audit/SEC.3.md#SEC.3-6) |
| I-SEC-14 | **Firewalls `oauth_token`, `oauth_authorize` et `api` inopérants** : `main` (`^/`) est déclaré en premier et capte toute requête ; `/api` n'est ni stateless ni limité à OAuth et accepte les cookies de session. Fix : réordonner les firewalls (spécifiques avant `main`) puis revalider les flux OAuth aval (Nextcloud/GitLab). **Test :** aucun (`AccessControlRulesTest` ne couvre que `access_control`). | S | lot 2 (#7) |
| SHIFT-CONTACT-FROM | **`/shift/{id}/contact_form` : l'expéditeur est lu dans un champ caché `from`** (`ShiftController` l.662) — tout membre connecté peut écrire aux co-shifters au nom du shifter (usurpation d'identité, phishing interne). Fix : déduire l'expéditeur de l'utilisateur connecté et vérifier qu'il fait partie du créneau. **Test :** `ShiftControllerContactFormTest::testAMemberCannotMailTheCoShiftersInTheNameOfTheShifter` *(incomplete)*. | XS | lot 3 (#9) |
| SHIFT-QUOTA-CACHE | **Quota de cycle contournable par le cache de 5 s** de `ShiftRepository::findShiftsForBeneficiaries()` (cache fichier partagé entre utilisateurs) : une seconde réservation immédiate ignore la première et dépasse le quota. Fix : ne pas cacher les cumuls qui conditionnent une écriture, ou invalider le cache à chaque réservation / libération. **Test :** `ShiftControllerBookTest::testTheQuotaHoldsForABookingMadeRightAfterAnother` *(incomplete)*. | S | lot 3 (#9) |
| I-SEC-7 | **`/helloassoNotify` — webhook sans auth ni rate-limit** : chaque requête déclenche un appel API sortant (DoS indirect) ; `data['id']` interpolé dans l'URL API (injection de chemin, domaine fixe). Fix : IP allowlist Helloasso ou secret partagé ; valider `id`. **Test :** `HelloassoNotifySecurityTest::testANotificationThatDoesNotComeFromHelloassoIsRefused` ; `HelloassoNotifySecurityTest::testAPaymentIdThatIsNotANumberIsRejected`. *(incomplete)*. **Aggravant (lot 2) :** sans identifiants HelloAsso configurés, l'endpoint lève un `TypeError` (voir m-BUG-1). | S | [SEC.1.4](audit/SEC.1.md#SEC.1-4), [SPEC.8](audit/SPEC.8.md) |
| I-SEC-8 | **`has_role()` déprécié (supprimé SF5)** dans `HelloassoController:93` — ne compilera pas à la migration. Fix : `is_granted('ROLE_FINANCE_MANAGER')`. Pré-requis du run Rector SF-PREP. | XS | [SEC.1.6](audit/SEC.1.md#SEC.1-6), [SF-PREP.3](audit/SF-PREP.3.md) |
| I-SEC-9 | **Badges `swipe_qr`/`swipe_br` (GET `.png`) sans `@Security`** : images téléchargeables par quiconque connaît le code Vigenère (usurpation de badge). Fix : restreindre au propriétaire ou `ROLE_USER_MANAGER`. **Test :** `BadgeSecurityTest::testAnonymousVisitorCannotDownloadABadgeImage` ; `BadgeSecurityTest::testAnotherMemberCannotDownloadABadgeImage`. *(incomplete ; seul `br.png` est exploitable tant que C-BUG-7 casse `qr.png`)* | XS | [SPEC.4](audit/SPEC.4.md) |
| I-SEC-10 | **`user_add_role` (GET) — ajout de rôle par lien forgé sans CSRF.** Fix : POST + CSRF. **Test :** `UserSecurityTest::testAGetLinkCannotGrantARole`. *(incomplete)* | XS | [SPEC.4](audit/SPEC.4.md) |
| I-SEC-11 | **`user_install_admin` accessible sans auth avant le 1er setup** (aucun SUPER_ADMIN en base) → prise de contrôle si atteinte par un tiers ; mot de passe initial potentiellement `password` (cf. I-CFG-2). Fix : guard d'environnement / secret one-time / commande CLI. **Test :** `UserSecurityTest::testAnonymousVisitorCannotCreateTheSuperAdminOfAFreshInstall`. *(incomplete)* | S | [SEC.2.5](audit/SEC.2.md#SEC.2-5), [SPEC.4](audit/SPEC.4.md) |
| I-SEC-12 | **`SUPER_ADMIN_INITIAL_PASSWORD=password` dans `.env.dist`** + `APP_SECRET` réel exposé dans l'historique git public (2020-2023, commit `a408661e`). Fix : placeholder `<change-me>` + validation refusant `password`/`changeme` en prod ; documenter régénération `APP_SECRET` à chaque déploiement ; alerter Elefan/Scopeli. | S | [SEC.7 F1](audit/SEC.7.md#SEC.7-F1), [F3](audit/SEC.7.md#SEC.7-F3) |

### Bugs / robustesse

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| I-BUG-1 | **`EventController::deleteProxyLiteAction` (l.358) : NPE** `$proxy->getOwner()->getUser()` sans null-guard sur proxy en attente (owner null), atteignable par URL forgée. | XS | [SPEC.11](audit/SPEC.11.md) |
| I-BUG-2 | **`Event::getProxiesByOwnerMembershipMainBeneficiary()` (l.432-436) : NPE** sur proxies en attente dans la collection. | XS | [SPEC.11](audit/SPEC.11.md) |
| I-BUG-3 | **`EventExtension::receivedProxies()` : TypeError** — signature `: array` mais `return null` si pas d'utilisateur connecté. | XS | [SPEC.11](audit/SPEC.11.md) |
| I-BUG-4 | **`EmailingEventListener` + `MailerService` : `findOneByCode(...)->getContent()` sans null-guard** (codes `PRE_MEMBERSHIP_EMAIL`, `SHIFT_REMINDER_EMAIL`, `WELCOME_EMAIL`) → exception fatale si le `DynamicContent` est absent. Fix : garde + fallback. **Test :** `EmailingEventListenerTest::testAnonymousBeneficiaryMailsSurviveAMissingDynamicContent` ; `EmailingEventListenerTest::testReminderSurvivesAMissingDynamicContent` ; `MailerServiceTest::testConfirmationEmailSurvivesAMissingWelcomeContent`. *(incomplete)* | XS ×4 | [SPEC.7](audit/SPEC.7.md) |
| I-BUG-5 | **`ShiftBookedEvent.$fromAdmin` jamais assigné** dans le constructeur → `isFromAdmin()` retourne toujours `null` (bug silencieux : les listeners ne distinguent pas admin/user). Fix : ajouter l'assignment, ou supprimer param+getter si la distinction n'est jamais exploitée. **Test :** `ShiftBookedEventTest::testTellsWhetherTheBookingCameFromAnAdmin`. *(incomplete)* | XS | [DC.2 D.1](audit/DC.2.md#DC.2-1) |
| I-BUG-6 | **`AuthenticationSuccessHandler::onAuthenticationSuccess()` viole l'interface** : retourne `null` implicite quand `$target` absent (interface exige `Response`). Fix : fallback `RedirectResponse('/')`. | XS | [DC.2 D.2](audit/DC.2.md#DC.2-2) |
| I-BUG-7 | **`confirmOrphan()` (`HelloassoController` l.264) : GET mutant sans vérif `payment.getRegistration()`** avant dispatch `ORPHAN_SOLVE` → double-liaison possible si l'orphelin est déjà résolu. | S | [SPEC.5](audit/SPEC.5.md) |
| I-BUG-8 | **`HelloassoNotificationRequest::createFromRequest()` — pas de validation JSON** : body non-JSON → `null`, message d'erreur trompeur. Fix : `if (!is_array($requestData)) throw`. | XS | [AP.9](audit/AP.9.md) |
| I-BUG-9 | **`ShiftFreeLogService` + `PeriodPositionFreeLogService` : `$request->get('_route')` sans null-guard** (CLI → `null`) → bug latent `BadMethodCallException` si appelés hors HTTP. Fix : null-guard, ou passer `?string $routeName` en paramètre. **Test :** `ShiftFreeLogServiceTest::testInitShiftFreeLogOutsideAnHttpRequest` ; `PeriodPositionFreeLogServiceTest::testInitPeriodPositionFreeLogOutsideAnHttpRequest`. *(incomplete)* | XS | [AP.6](audit/AP.6.md) |
| I-BUG-10 | **`getLastRegistration()->getDate()` sans null-guard** (éligibilité `give`/`take`) → NPE si une adhésion sans aucune registration atteint le contrôle. **Test :** `EventProxyNoRegistrationTest::testAMembershipWithoutRegistrationIsRefusedPolitely`. *(incomplete)*. **Confirmé atteignable (lot 3)** sur `/events/{id}/proxy/give` et `/take` : un bénéficiaire détaché reçoit une adhésion sans inscription. | XS | [SPEC.11](audit/SPEC.11.md) |
| I-BUG-11 | **`CloseMembershipCommand` l.62 : `setWithdrawnBy()` commenté (`//TODO`)** → `withdrawnBy` jamais renseigné lors d'une clôture automatique par le cron. Impossible de distinguer une clôture cron d'une clôture admin → traçabilité / intégrité de données perdue (irréversible). Fix : renseigner `withdrawnBy` (acteur système, ou null explicite documenté). **Test :** `CloseMembershipCommandTest::testRecordsWhoClosedTheMembership`. *(incomplete)* | XS | [D.5](audit/D.5.md), [SPEC.2](audit/SPEC.2.md) |
| I-BUG-12 | **`/member/new?code=<code invalide>` → 500** (route publique) : code d'invitation mal formé non géré. Fix : valider/décoder avec garde et rediriger avec un message. **Test :** aucun en PHPUnit ; le cas est écarté de `new_member_can_self_register.cy.js` (commentaire « Not covered »). | XS | lot 6 (#10) |
| SHIFT-NO-REFERER | **`free_admin` sans en-tête `Referer` → 500** (`RedirectResponse(null)`) alors que le créneau a bien été libéré : le gestionnaire voit une page d'erreur. Fix : redirection de repli. **Test :** `ShiftControllerFreeTest::testFreeingWithoutARefererStillRedirects` *(incomplete)*. | XS | lot 3 (#9) |
| SHIFT-FREE-FREE | **Libérer un créneau non réservé → `TypeError`** (`null` passé à `canFreeShift(Beneficiary …)`). Fix : garde sur `getShifter()` avant l'appel. **Test :** `ShiftControllerFreeTest::testFreeingAFreeShiftIsRefusedWithAMessage` *(incomplete)*. | XS | lot 3 (#9) |
| SHIFT-CONTACT-FREE | **Formulaire de contact d'un créneau sans shifter → crash** (`$shift->getShifter()->getId()`, `ShiftController` l.859). Fix : garde / refus explicite. **Test :** `ShiftControllerContactFormTest::testTheFormOfAFreeShiftDoesNotCrash` *(incomplete)*. | XS | lot 3 (#9) |
| BOOKING-FOREIGN-CHOICE | **Page de réservation : choisir un bénéficiaire d'une autre adhésion plante** au lieu d'être refusé proprement. Fix : valider le choix contre les bénéficiaires de l'adhésion. **Test :** `BookingControllerTest::testAMemberCannotChooseABeneficiaryOfAnotherMembership` *(incomplete)*. | XS | lot 3 (#9) |
| BOOKING-EMPTY-DAY | **Vue « jour » sans aucun créneau → crash.** Fix : gérer la collection vide. **Test :** `BookingControllerTest::testTheDayViewOfADayWithoutShiftDoesNotCrash` *(incomplete)*. | XS | lot 3 (#9) |
| CYCLE-FREEZE-FLUSH | **`TimeLogEventListener::onMemberCycleEnd()` persiste le gel/dégel en attente sans `flush()`, et `CycleStartCommand` ne flush jamais** : le dernier membre d'un run garde l'ancien état en base et est basculé à nouveau au cycle suivant. Fix : flush en fin de traitement (commande ou listener). **Test :** `TimeLogEventListenerTest::testCycleEndSavesAPendingFreezeToTheDatabase` *(incomplete)*. | XS | lot 5 (#8) |
| MAIL-SWIPECARD-DI | **`App\Helper\SwipeCard` privé mais récupéré par `$this->container->get()`** dans `EmailingEventListener` (`onAnonymousBeneficiaryRecall` l.114/116, `onCodeNew` l.672) : `get()` lève sur le conteneur compilé, donc les emails d'invitation et de changement de code échouent. Fix : utiliser le `SwipeCard $swipeCardHelper` déjà injecté (cf. I-ARCH-2). **Test :** `Integration\EmailingEventListenerTest::testSwipeCardHelperIsReachableFromTheContainer` *(incomplete)*. | XS | lot 5 (#8) |

### Dette structurelle / antipatterns significatifs

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| I-ARCH-1 | **Service locator généralisé : 90+ occurrences dans 24 controllers** (`$this->get('security.token_storage')` ×53, `authorization_checker` ×26, `twig` ×11). Non testable sans conteneur. Fix : `$this->getUser()`, `$this->isGranted()`, `$this->renderView()`. | M | [AP.1.1](audit/AP.1.md#AP.1-1) |
| I-ARCH-2 | **22 classes injectent `ContainerInterface` en service locator actif.** Le pire : `EmailingEventListener` (6 services + 7 params en `get()` lazy). Fix par catégorie : `getParameter()` → `ParameterBagInterface`/bind ; `get()` constructeur → injection directe ; `get()` lazy listeners → injection directe (SF4.4 résout les cycles par proxy). Supprimer le bloc legacy `has('templating')`. | M–L | [AP.4 C](audit/AP.4.md#AP.4-C) |
| I-ARCH-3 | **`UsernamePasswordToken` fabriqué à la main** (`SwipeCardController:68`, `CodeController:252`) — contourne le flux d'auth Symfony ; impersonation `CodeController` jamais révoquée explicitement. Risque de régression auth. | M | [AP.2.2](audit/AP.2.md#AP.2-2) |
| I-ARCH-4 | **SQL brut avec concaténation de table dans `RegistrationsController` (l.118-159)** — requêtes pivot d'agrégation (params bindés, pas d'injection, mais place et structure fragiles). Fix : `AbstractRegistrationRepository::getSumsByDateRange()`. | M | [AP.3.1](audit/AP.3.md#AP.3-1) |
| I-ARCH-5 | **`TimeLogEventListener` (420 l.) : logique comptable de bilan de cycle embarquée dans un listener** + dispatch interne de `MemberCycleStartEvent` (dépendance implicite à l'ordre des listeners). Fix : extraire `CycleAccountingService`, dispatch explicite depuis l'appelant. | M | [AP.7.4](audit/AP.7.md#AP.7-4) |
| I-ARCH-6 | **`ImportUsersCommand::execute()` ≈195 l. : création d'entités inline, aucune délégation** + `utf8_encode()` (supprimé PHP 8.2, l.117). Fix : extraire `ImportMemberService`; remplacer par `mb_convert_encoding`. | L (+ XS) | [AP.8.1](audit/AP.8.md#AP.8-1), [AP.8.2](audit/AP.8.md#AP.8-2) |
| I-ARCH-10 | **Autres commandes « god execute »** : `AnonymizeDataCommand` (~140 l. anonymisation inline), `ShiftGenerateCommand` (~113 l. génération de créneaux inline, `PeriodService` injecté mais non utilisé pour la génération). Fix : extraire la logique en services (`PeriodService::generateShiftsForDate()`). | M ×2 | [AP.8.2](audit/AP.8.md#AP.8-2), [AP.8.3](audit/AP.8.md#AP.8-3) |
| I-ARCH-7 | **`new Application($kernel)` — commande console lancée depuis un controller web** (`AdminController:284` import users, `AdminPeriodController:444` génération shifts). Couplage web↔CLI. Fix : extraire la logique en service partagé. | M ×2 | [AP.1.2f](audit/AP.1.md#AP.1-2), [AP.2.1](audit/AP.2.md#AP.2-1) |
| I-ARCH-8 | **2 migrations `ContainerAwareInterface`** (`Version20190218130524`, `Version20190402014558`) crashent avec doctrine/migrations 3.x (requis SF5). **Bloquant SF-PREP.** Fix : réécrire en SQL natif (`$this->addSql()`). | S | [DB.3 MIG.1](audit/DB.3.md#todo-MIG-1) |
| I-ARCH-9 | **`OidcFirewallListener` : protection par denylist d'URI hardcodées** (`str_starts_with` + `str_contains('removeRole')`). Un renommage de route casse silencieusement la protection. Fix : liste blanche de routes / attribut centralisé. | M | [SPEC.8](audit/SPEC.8.md), [AP.7.5](audit/AP.7.md#AP.7-5) |

### Tests prioritaires (failles SEC actives sans filet)

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| I-TEST-1 | **Tests fonctionnels couvrant les failles SEC** sur `BeneficiaryController`, `SwipeCardController`, `UserController` (0 % de couverture, findings account-takeover / auth badge / bootstrap admin). À écrire **avant** les correctifs SEC. | M | [TC.2](audit/TC.2.md) |
| I-TEST-2 | **Tests POST `ShiftController`** (book/free/contact_form/accept/reject) — 4.8 % de couverture sur 439 l. de logique métier critique. | M | [TC.2](audit/TC.2.md), [TC.1](audit/TC.1.md) |
| I-TEST-3 | **Tests d'autorisation** `CommissionController`, `CardReaderController` (POST check), `HelloassoController`. | S–M | [TC.2](audit/TC.2.md) |
| I-TEST-4 | **Tests unitaires quick-win sans mock** : `MailerService::isTemporaryEmail`/`getAllowedEmails`, `ShiftFreeLogService`, `PeriodPositionFreeLogService` (pattern déjà éprouvé). | S | [TC.3](audit/TC.3.md) |
| I-TEST-5 | **Faux positifs masquant des bugs** : `testRemainingToBookPartiallyBooked` / `testCanBookDurationWhenAlreadyFullyBooked` (aucun TimeLog injecté → testent « rien réservé ») ; `testHasPreviousValidShiftsWithDismissedShift` (passe pour la mauvaise raison). À réécrire avec de vrais `TimeLog` / `wasCarriedOut=false`. | S | [TC.4.1](audit/TC.4.md#TC-4-1), [TC.4.2](audit/TC.4.md#TC-4-2) |
| I-TEST-6 | **Tests fonctionnels commandes destructives** : `app:member:close`, `app:user:cycle_start` (`--date` déjà présent), `app:shift:generate`. 1/24 commandes testée (4,2 %). | M | [TC.5](audit/TC.5.md) |

---

## 🟡 Mineur

### Sécurité — défense en profondeur

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| m-SEC-1 | Session : `cookie_secure: auto` et `cookie_samesite: lax` absents (`framework.yaml`). | XS | [SEC.1.10](audit/SEC.1.md#SEC.1-10), [SEC.3.7](audit/SEC.3.md#SEC.3-7) |
| m-SEC-2 | 3 × `expr()->literal()` avec input utilisateur (échappement `PDO::quote` présent mais non idiomatique/fragile). Fix : `setParameter()`. | XS ×3 | [SEC.4.1](audit/SEC.4.md#SEC.4-1) |
| m-SEC-3 | Flash messages : `|raw` global (`layout.html.twig:53`) → stored-XSS potentiel via noms d'entités admin-saisis ; markup console (`<fg=…>`) et artefact debug `<>` affichés bruts. Fix : retirer `|raw` global, type `flash_html` dédié ; `strip_tags` sur l'output console. | S | [SEC.6](audit/SEC.6.md) |
| m-SEC-4 | `KeycloakAuthenticator` enregistré même si `OIDC_ENABLE=false` — surface active inutile sur instances non-OIDC. Fix : enregistrement conditionnel. | S | [SEC.1.8](audit/SEC.1.md#SEC.1-8) |
| m-SEC-5 | Import CSV `AdminController:260` sans contrainte MIME (`Assert\File`). Atténué : `ROLE_SUPER_ADMIN`. | XS | [SEC.5 F4](audit/SEC.5.md#SEC.5-F4) |
| m-SEC-6 | `event_detail` (GET `/events/{id}`) sans `@Security` (lieu/date/image d'AG publics alors que `event_index` exige `ROLE_USER`). | XS | [SPEC.11](audit/SPEC.11.md) |
| m-SEC-7 | `shift_contact_form`, `event_proxy_lite_delete`, `code_generate` (collision `rand(0,9999)`), provisioning OIDC `member_number = rand(10000,100000)` sans contrôle de collision. | XS–S | [SPEC.3](audit/SPEC.3.md), [SPEC.11](audit/SPEC.11.md), [SPEC.4](audit/SPEC.4.md), [SPEC.8](audit/SPEC.8.md) |
| m-SEC-8 | `.gitleaks.toml` absent → 1 482 faux positifs (`var/phpstan-dead-code/cache/`) feraient échouer un job CI `gitleaks detect --no-git`. Fix : allowlist `paths = ["var/"]`. | XS | [SEC.7 F4](audit/SEC.7.md#SEC.7-F4), [CI] |
| m-SEC-9 | Pas de révocation de consentement OAuth (RGPD) ; `api_gitlab_user` accessible par session (hors flux token) ; `api_swipe_in` sans garde login-as (incohérence mineure). | M / XS | [SPEC.8](audit/SPEC.8.md) |
| m-SEC-10 | **Flow d'onboarding public = surface d'énumération de membres** (`find_member_number`, `confirm`, `find_me` : recherche par prénom → ID → nom complet + email masqué). Intentionnel mais sans garde-fou. Fix : Symfony RateLimiter. | S | [SEC.2.6](audit/SEC.2.md#SEC.2-6) |
| m-SEC-11 | **`CodeVoter` : fall-through `case OPEN → DELETE`** (switch sans `break`) → asymétrie ouvrir/fermer non documentée ; `isLocationOk()` dupliqué (suppression couverte par m-DC-1). Clarifier l'intention ou corriger. | XS | [SPEC.4](audit/SPEC.4.md) |
| m-SEC-12 | **`anonymous_proxy` : gap d'autorisation UI-only.** Le flag masque/affiche les boutons de don/prise anonyme de procuration (`give.html.twig`, `card_action.html.twig`) mais n'est vérifié nulle part côté serveur — `giveProxyAction` (branche formulaire vide) et `acceptProxyAction` (`event_proxy_take`) ne testent jamais `$event->getAnonymousProxy()`. Un membre connaissant l'URL peut donner/prendre une procuration anonyme même si l'AG a `anonymous_proxy=false`. Fix : ajouter la vérification du flag dans les deux actions. | XS | [SPEC.11](audit/SPEC.11.md), [EXTRA #51](audit/EXTRA.md#extra-51) |

### Bugs mineurs

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| SHIFT-FREE-MESSAGE | `ShiftController::freeShiftAction()` (l.307, l.365) utilise `||` au lieu de `?:` : le flash de refus affiche `1` au lieu du motif. **Test :** `ShiftControllerFreeTest::testTheRefusalToFreeAStartedShiftSaysWhy` *(incomplete)*. | XS | lot 3 (#9) |
| CLI-GENERATE-TO | `app:shift:generate --to=<mal formé>` : `format()` appelé sur `false` (`Error` fatale) au lieu de sortir en statut 2 comme pour la date de début. **Test :** `ShiftGenerateCommandTest::testRejectsAMalformedEndDate` *(incomplete)*. | XS | lot 4 (#5) |
| CYCLE-EXPIRED-START | Une adhésion expirée déclenche quand même `MemberCycleStartEvent` après le `MemberCycleEndEvent` qui la marque expirée (comportement actuel, à confirmer comme voulu). **Test :** `TimeLogEventListenerTest::testCycleEndOfAnExpiredRegistrationOnlyMarksIt` *(fige le comportement du listener)*. | XS | lot 5 (#8) |
| m-BUG-1 | `/helloassoNotify` sur une instance sans identifiants HelloAsso → `TypeError` (client id `null`) au lieu d'une réponse propre. **Test :** `HelloassoNotifySecurityTest` (identifiants factices injectés pour éviter le cas). | XS | lot 2 (#7) |
| JS-INIT-COLLAPSIBLE | `initCollapsible is not defined` sur `/member/{id}/show` : fonction non exposée sur `global` dans `assets/js/app.js` (l'état ouvert/fermé des collapsibles n'est pas mémorisé). **Test :** filtre `KNOWN_APPLICATION_ERRORS` de `cypress/support/e2e.js` (à retirer au correctif). | XS | lot 1 (#6) |

### Dead code & nettoyage

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| m-DC-1 | **Rector DeadCode (1 commande, ~20 fichiers, risque nul)** : docblocks `@param/@return` redondants, variables intermédiaires inutiles, args null redondants, constructeurs délégants/vides, cases switch dupliqués sûrs, méthodes/propriétés privées mortes. | XS | [DC.4 A](audit/DC.4.md#DC.4-A) |
| m-DC-2 | **Suppressions manuelles sûres** : classe `Helper/Html2Pdf` (jamais utilisée), `FixtureGroupConsoleService` (dead code complet), méthodes privées `AmbassadorController::createNoteDeleteForm`, `BeneficiaryController::getErrorMessages`. | XS | [DC.4 B](audit/DC.4.md#DC.4-B), [AP.5](audit/AP.5.md), [EXTRA D.5](audit/EXTRA.md) |
| m-DC-3 | **À vérifier avant suppression (risque multi-instance / sémantique)** : 6 méthodes Repository « probablement mortes » (`findFromAutoComplete`, `findByString`, `findAllDisplayedHome`, `findByBeneficiary`, `findFirst`, `findReservedBefore`) + services autocomplete corrélés — nécessitent le tracking runtime (RT.2) avant suppression. `ShiftVoter` VALIDATE/LOCK : **ne pas** merger (escalade d'autorisation). | M | [DC.4 C](audit/DC.4.md#DC.4-C) |
| m-DC-4 | **Listeners stub à supprimer ou implémenter** : `CommissionEventListener::onJoin()` vide, `CodeEventListener::onCodeNew()` commenté, `EmailingEventListener::onMemberCreated()` corps `// TODO ?` (event `MemberCreatedEvent` dispatché mais aucun email — coquille ou oubli, à clarifier avec les mainteneurs). *Sévérité 🟡 retenue pour `onMemberCreated` (canonique vs SPEC.7 🟠) : aucun impact fonctionnel, l'email de bienvenue part via FOSUserBundle — stub mort.* | XS | [AP.7](audit/AP.7.md), [EXTRA AP.7](audit/EXTRA.md), [D.5](audit/D.5.md), [SPEC.7](audit/SPEC.7.md) |
| m-DC-5 | **Nettoyage JS** : retirer `material-icons-css`, `@hotwired/stimulus`, `@symfony/stimulus-bridge`, `cypress-dotenv`, `regenerator-runtime` (inutilisés) ; remplacer `@babel/plugin-proposal-class-properties` (déprécié) ; supprimer artefacts `jquery-3.6.js` + CSS pré-compilés committés. Lien cassé `custom_animation.css` dans `period/index.html.twig`. | S | [DEP.3](audit/DEP.3.md), [EXTRA DEP.3](audit/EXTRA.md) |
| m-DC-6 | **Dead config / vars mortes** : `DATABASE_TEST_HOST`, `DEV_MODE_ENABLED` (doc trompeuse), `HELLOASSO_API_KEY/PASSWORD` (`.env.oidc.test`), `Registration::TYPE_CREDIT_CARD`, `fromActionObj/fromPaymentObj` (Helloasso v3), `.docker/php.ini` non chargé. | XS | [CONFIG.1](audit/CONFIG.1.md), [EXTRA SPEC.5](audit/EXTRA.md), [SEC.5 F5](audit/SEC.5.md#SEC.5-F5) |

### Antipatterns / qualité

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| m-AP-1 | **Duplication controllers** : 5 `createShift*Form` × 3 controllers (→ `ShiftFormFactory`) ; `getErrorMessages`/`redirectToShow` × 5 (→ trait/`AbstractAppController`) ; `new Paginator($qb)` × 9 (→ helper). | S | [AP.1.3](audit/AP.1.md#AP.1-3), [AP.1.4](audit/AP.1.md#AP.1-4), [AP.2.6](audit/AP.2.md#AP.2-6) |
| m-AP-2 | **Logique métier inline dans controllers** : auto-increment `member_number` (non atomique), fusion d'adhésions (`joinAction`, sans transaction), export CSV inline, `firstShiftDate` dupliqué, email construit hors `MailerService`. | S–M | [AP.1.2](audit/AP.1.md#AP.1-2) |
| m-AP-3 | **Requêtes hors Repository** : `$em->createQueryBuilder()` dans 4 fichiers + filtres dynamiques inline dans 5 controllers admin + DQL/QB inline dans commandes. Fix : méthodes Repository nommées (`findFiltered`, etc.). | S | [AP.3.2](audit/AP.3.md#AP.3-2), [AP.3.3](audit/AP.3.md#AP.3-3), [AP.3.5](audit/AP.3.md#AP.3-5) |
| m-AP-4 | **`ProxyRepository` vide → logique éparpillée** (≥8 `findOneBy` inline dans `EventController`, 2 méthodes de délégation dans `EventService`, filtres dans `Event`). Consolider (`findGivenBy/findReceivedBy/findWaiting/findAllWithAssociations` — ce dernier résout aussi le N+1 PERF #2). | M | [SPEC.11](audit/SPEC.11.md), [AP.3.6](audit/AP.3.md#AP.3-6) |
| m-AP-5 | **`AdminEventController::editEventProxyAction` (~80 l.)** : 4 branches dupliquées + flash messages **en anglais** dans l'UI FR. Refactor + i18n. | M | [SPEC.11](audit/SPEC.11.md) |
| m-AP-6 | **Providers Helloasso/Igloohome** : `new FilesystemAdapter()` hors DI (cache non purgeable/testable, TTL hardcodé, clé = `$clientId` seul, TTL négatif non gardé) ; `new GuzzleHttp\Client()` à chaque appel ; repository non injecté ; pas de transaction dans `savePayments()` ; typos « payement » ×4 ; pas d'interface client. | XS–S | [AP.9](audit/AP.9.md), [PERF.3](audit/PERF.3.md) |
| m-AP-7 | **`strftime()` déprécié PHP 8.1+** (`EmailingEventListener` L277, L483) ; `ShiftGenerateCommand::lastCycleDate()` 28 j hardcodés (→ `cycle_duration`) ; `VerifyCodeChangeCommand` manipule `TokenStorage` en CLI ; `RandomSortMembersCommand` `echo` au lieu de `$output->write()`. | XS–S | [AP.7.2](audit/AP.7.md#AP.7-2), [AP.8.3](audit/AP.8.md#AP.8-3), [AP.8.4](audit/AP.8.md#AP.8-4) |
| m-AP-8 | Propriétés de dépendance `protected` non typées généralisées (services + listeners, aucune sous-classe) → passer en `private` typé. | XS | [AP.5](audit/AP.5.md) |
| m-AP-9 | **Services tiers instanciés dans les controllers** : `new QrCode()` (`SwipeCardController` → `QrCodeService`), `new Markdown` (`MailController` → alias service). `renderView()` dupliquée `EmailingEventListener`/`HelloassoEventListener` (→ trait/service). `HelloassoEventListener::linkPaymentToUser()` (logique d'enregistrement dans le listener → extraire service). | XS–S | [AP.2.3](audit/AP.2.md#AP.2-3), [AP.2.4](audit/AP.2.md#AP.2-4), [AP.7](audit/AP.7.md), [AP.7.5](audit/AP.7.md#AP.7-5) |
| m-AP-10 | **Commandes à logique inline (moindre priorité)** : `DoctorCommand`, `FixShiftMissingPositionCommand` (DQL inline + matching position ; ne gère pas `cycle_type=abcd`, cf. m-CLI-1), `UpdateIgloohomeCodeCommand` (persistance entités `Code` hors Repository). | S–M | [AP.8](audit/AP.8.md) |
| m-AP-11 | **Dette inline documentée (`// TODO`/`// FIXME`) à résorber au fil de l'eau** : `AdminShiftExemptionController` l.104 — suppression d'un `ShiftExemption` référencé par une `MembershipShiftExemption` → violation FK / erreur 500, sans pré-check (atténué `ROLE_ADMIN` ; **D.5 le classe 🟠**, SPEC.6 le revoit 🟡) ; `// FIXME $member->getMainBeneficiary()->getUser()` triplé (`BeneficiaryController` l.269, `MembershipController` l.153, `NoteController` l.140) → null-safety douteuse (`getMainBeneficiary()` peut être `null`) ; `Membership::getFrozen()` `@deprecated` sans enforcement (coexiste avec `isFrozen()`) ; `ShiftService` l.255 `shift_cycle` `// TODO refactor` encore actif ; `AdminPeriodController` l.381 — la copie de période n'offre pas l'option shifter/booker quand `use_fly_and_fixed` est actif. | S | [D.5](audit/D.5.md), [SPEC.6](audit/SPEC.6.md) |

### Performance (sous réserve volumétrie prod)

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| m-PERF-1 | **`/emails_csv` : N+1 + chargement mémoire de ~3000+ bénéficiaires.** ⬆️ **Probablement 🔴 sur la volumétrie réelle Elefan** (classé ici 🟡 par prudence faute de données prod confirmées — cf. réserve volumétrie). Fix : `BeneficiaryRepository::findAllWithMembership()` (JOIN FETCH) + filtre SQL + `StreamedResponse` chunked. | S | [PERF.1, PERF.2 #1] |
| m-PERF-2 | Collections non paginées : `/admin/events/proxies`, `/admin/closingexceptions/list` (croissance non bornée). Fix : `Paginator` (25/page) ou filtre saison. | S | [PERF.2 #2, #3] |
| m-PERF-3 | N+1 templates `admin commissions` + autocomplete (full table reads non cachés sur chaque page admin). Fix : JOIN FETCH + cache `CacheInterface` (TTL ~5 min). | S | [PERF.1 #3, PERF.3] |
| m-PERF-4 | Doctrine result cache configuré (prod) mais jamais activé (`enableResultCache()` absent) = dead config ; 2 `new FilesystemAdapter()` hors DI. | S | [PERF.3](audit/PERF.3.md) |

### Config, observabilité & schéma

| # | Finding | Effort | Source |
|---|---------|--------|--------|
| m-CFG-1 | `ROUTER_REQUEST_CONTEXT_SCHEME` non mappé → URLs CLI/emails en `http` même si instance `https`. `registration_manual_enabled` absent de `services.yaml`. Globales Twig court-circuitant les paramètres Symfony. Unités implicites non documentées dans `.env.dist`. | XS–S | [CONFIG.1](audit/CONFIG.1.md), [CONFIG.2](audit/CONFIG.2.md), [CONFIG.3](audit/CONFIG.3.md) |
| m-CFG-2 | **Valeurs métier hardcodées à externaliser en configuration** : fenêtres de validation badge `-120min`/`+60min` (`CodeVoter` l.130-132, dupliquées dans `UserVoter`/`MembershipVoter`) ; choix « nb bénéficiaires » `[1, 2]` figés (`SearchUserFormHelper` l.79) au lieu de `maximum_nb_of_beneficiaries_in_membership` ; seuil « nouveau bénéficiaire » ≤ 3 créneaux (`Beneficiary::isNew()` l.746) ; fenêtre `canRegister` `+28 days` (`MembershipService`). Distinct du cycle 28 j fonctionnel (cf. C-BUG-4) et des 28 j en commande (cf. m-AP-7). | S | [D.5](audit/D.5.md), [SPEC.5](audit/SPEC.5.md), [CONFIG.3](audit/CONFIG.3.md) |
| m-LOG-1 | **Zéro audit trail** : aucune action sensible (changement de rôle, suppressions, opérations financières, changement email/mdp) n'est tracée. Fix : logs `warning`/channel `security` avec `[actor_id, target_id, action, old/new]`. Priorité : rôles + suppressions (irréversibles). | M | [LOG.3](audit/LOG.3.md) |
| m-LOG-2 | Pas de rotation des logs prod (`stream` au lieu de `rotating_file`) ; `fingers_crossed(action_level: info)` quasi inutile ; 25 logs « label seul » sans contexte ; catches silencieux sur flux critiques (webhooks Helloasso, API, envois masse). | S | [LOG.1](audit/LOG.1.md), [LOG.2](audit/LOG.2.md) |
| m-DB-1 | Schéma DB désync (36 colonnes nullable sans `DEFAULT NULL` + `dynamic_content.type` sans défaut) → divergence déclarative, 0 impact runtime. Fix : 1 migration `migrations:diff`. Documenter les `down()` destructifs (TRUNCATE / DROP COLUMN / DELETE FROM) + gardes plateforme manquantes. | XS–S | [DB.1, DB.3 MIG.2/MIG.3] |
| m-CI-1 | `shivammathur/setup-php@verbose` (alias flottant → pin version/SHA) ; gap PHP 7.4 CI vs 8.1 prod (ajouter 8.1 à la matrice) ; pas de lint JS/CSS (optionnel). | XS | [CI.1](audit/CI.1.md) |
| m-CI-2 | **release-please : `extra-files` obsolète → bump de version silencieusement ignoré.** `release-please-config.json` déclare `extra-files: ["app/config/config.yml"]`, fichier issu de la structure Symfony 3.x **absent** du projet SF4 → aucun fichier de version n'est mis à jour à chaque release (bug silencieux). Fix : retirer l'entrée, ou la pointer vers le fichier réel portant la version. | XS | [D.4](audit/D.4.md) |
| m-CI-3 | **Durcir progressivement les garde-fous CI de la suite de tests** : `SYMFONY_DEPRECATIONS_HELPER=weak` → `max[self]=0` une fois la migration Symfony traitée (mesure lot 6 : ~501 dépréciations directes « self », 276 « direct », 1078 « indirect ») ; seuil `diff-cover` de 70 % à valider (voir [CI-DIFFCOVER-THRESHOLD](#décisions-produit-en-attente)) ; MariaDB 10.4 → 10.11 et fin de PHP 7.4 hors périmètre. **Test :** jobs CI `symfony-tests` (résumé de dépréciations, `make diff-coverage`). | S | lot 6 (#10) |
| m-CLI-1 | Aucune commande n'a `--dry-run`, y compris les irréversibles (`app:anonymize`, `app:member:close`, `app:shift:generate`). Coordination cron `app:shift:free` ↔ `ShiftGenerate` non documentée. `FixShiftMissingPositionCommand` ne gère pas `cycle_type=abcd`. | S | [TC.5](audit/TC.5.md), [SPEC.3](audit/SPEC.3.md) |

---

## Décisions produit en attente

Comportements constatés par la suite de tests où le code et l'intention ne se départagent pas sans arbitrage produit. Tant que la décision n'est pas prise, le test correspondant reste *incomplete* (ou fige le comportement actuel).

| # | Question | Test | Source |
|---|----------|------|--------|
| SHIFT-PREVIOUS-VALID | `ShiftService::hasPreviousValidShifts()` compare seulement les dates de début : un créneau passé **non effectué** (`wasCarriedOut=false`) compte comme « créneau valide précédent ». Faut-il l'exclure (le membre redevient débutant) ? | `ShiftServiceUnitTest::testHasPreviousValidShiftsWithPastShiftNotCarriedOut` *(incomplete)* | lot 1 (#6) |
| SHIFT-ACCEPT-LINK | Depuis le default-deny (C-SEC-2), le lien accept/reject envoyé par email exige un login ; la branche anonyme du voter est inatteignable. Garder le login obligatoire (recommandé, cf. I-SEC-3) ou réintroduire un lien signé à usage unique ? | `ShiftSecurityTest` (cas anonyme → `/login`) | lot 2 (#7) |
| MEMBER-FREEZE-SELF | `freeze` / `unfreeze` : un membre peut geler ou dégeler **immédiatement** son propre compte (le voter laisse passer le titulaire), alors que SPEC.2 réserve l'action aux gestionnaires et prévoit un gel « à la fin du cycle » (`freeze_change`) pour le membre. Quelle règle appliquer ? | `MembershipControllerStateTest::testAMemberCannotFreezeTheirOwnAccountAtOnce`, `…UnfreezeTheirOwnAccountAtOnce` *(incomplete)* | lot 3 (#9) |
| CI-DIFFCOVER-THRESHOLD | Seuil bloquant de couverture des lignes modifiées (`DIFF_COVER_THRESHOLD`, 70 % par défaut dans le Makefile) : à valider ou ajuster, y compris le périmètre (`src/` seulement). | job CI `symfony-tests` (étape « Check coverage of the changed lines ») | lot 6 (#10) |

---

## Constats de la suite de tests déjà corrigés

| # | Constat | Correctif |
|---|---------|-----------|
| TEST-FIXTURES-PHP8 | ✅ `doctrine:fixtures:load` échouait sur PHP 8 (« There is no active transaction » : le `TRUNCATE` du purger valide implicitement la transaction de l'executor). | `baa1a768` |
| TEST-COVERS-NOTHING | ✅ `@coversNothing` sur toutes les classes de test : PHPUnit n'enregistrait aucune couverture (baseline mesurée après correctif : 29,6 % des lignes de `src/`). | `b3db6fee` |
| TEST-CYPRESS-BOOK | ✅ Le test Cypress « book a shift » était un faux vert (sélecteur `a[href^="#book"]` sans correspondance : rien n'était réservé). | lot 1 (#6), `dc236183` |

---

## Chantiers futurs

### Migration Symfony 4.4 → 5.4 (puis 6.4) — [SF-PREP]

Trajectoire recommandée : **migrer tout le reste sur SF5.4, isoler le serveur OAuth en chantier dédié calé sur SF6.4** — décision d'architecture à valider avant toute implémentation (elle conditionne la trajectoire globale).

**Bloquants durs (composer)** :
- B1 — `doctrine/persistence ^1.0 → ^2.0` (cascade `doctrine/orm`). Simple.
- B2 — **FOSUserBundle → Security natif** : entité `User`, 14 templates, serveur de routes auth, flux reset/confirm, events `FOSUserEvents`. **Effort L (≈4-5 j)**, Rector n'aide pas (logique).
- B3 — **FOSOAuthServerBundle → ⚠️ pas de cible SF5.4 propre** (`league/oauth2-server-bundle` exige SF≥6.4). Re-modélisation des 4 entités + serveur de routes + admin + listener + **coordination SSO aval** (Nextcloud/GitLab) sur 2 instances. **Effort XL — chemin critique.**
- B4 — `ornicar/gravatar-bundle` → Twig Extension inline (~5 l.). Effort S.

**Bloquants de config** (mécaniques) : `platform.php 7.4→8.1`, `extra.symfony.require 4.4→5.4`, `symfony/flex 1.x→2.x`, retirer `conflict twig/twig`.

**Annotations** : ~1 107 annotations, ~1 106 automatisables par Rector (`withAttributesSets(symfony, doctrine, sensiolabs)` + `SecurityAttributeToIsGrantedAttributeRector`). 1 pré-requis manuel : `has_role()` (cf. I-SEC-8). Cleanup : imports `Sensio\...\Method` morts.

**Pré-requis de priorisation** : confirmer par instance la valeur d'`oidc_enable` et l'inventaire des apps aval OAuth (T1).

### Route Usage Tracker — [RT.2](audit/RT.2.md)

🟡 Mineur / **M (< 1 j)**. Spec technique complète dans `AUDIT.md` §RT.2 : variable `APP_INSTANCE`, table `route_usage` (unique `(route_name, instance)`), entité + repository upsert, `RouteUsageSubscriber` sur `kernel.terminate` (zéro impact latence), page admin de rapport. **Prérequis** : aucun mécanisme d'identification d'instance au runtime n'existe (cf. [RT.1](audit/RT.1.md), [CONFIG.2](audit/CONFIG.2.md)). **Débloque** la décision de suppression du dead code « à vérifier » (m-DC-3).

### Refactors structurants (long terme, lors de SF5)

- Éclater `EmailingEventListener` (713 l., 13 types d'emails) en services dédiés (`ShiftEmailService`, `MemberEmailService`, `HelloassoEmailService`). Effort L. [AP.7.3](audit/AP.7.md#AP.7-3)
- `MembershipController::showAction` — 18 formulaires dans une action ; 36 méthodes `createXxxForm()` au total → Form types / services par domaine. Effort L. [AP.1.5](audit/AP.1.md#AP.1-5), [AP.1.6](audit/AP.1.md#AP.1-6)
- Remplacer `sensio/framework-extra-bundle` (abandonné, bloquant SF6) — couvert par le run Rector annotations. Effort M. [DEP.2](audit/DEP.2.md)

### Re-audit requis sur données de prod — [PERF.1](audit/PERF.1.md), [PERF.2](audit/PERF.2.md)

Refaire l'analyse PERF (sévérités 🔴/🟡) sur un **dump prod anonymisé** Elefan/Scopeli : les comptages de l'audit viennent de la base de test et ne reflètent pas la production. À fournir après anonymisation côté utilisateur.

### Documentation (déjà partiellement traité en SYN.1)

`DOCUMENTATION.md` est voué à **remplacer `README.md`** à terme (décision post-audit) ; en attendant, il fait foi en cas de divergence — les inexactitudes du README sont recensées dans ses §2.1 et §4.

Gotchas à documenter signalés en cours d'audit : nommage trompeur `CycleStartCommand → MemberCycleEndEvent`, `HelloassoEvent::RE_REGISTRATION_SUCCESS` couvrant aussi la 1re adhésion, `KeycloakAuthenticator` RAZ des rôles à chaque login OIDC, création d'AG en deux temps, SSTI via `DynamicContent` (acceptable si `ROLE_PROCESS_MANAGER` restreint). Cf. [SPEC.7](audit/SPEC.7.md), [SPEC.8](audit/SPEC.8.md), [SPEC.11](audit/SPEC.11.md).
