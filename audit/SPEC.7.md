# SPEC.7 — Spec : Notifications & Emails

- [x] **SPEC.7** — Spec : Notifications & Emails

Sources : `EmailingEventListener`, `HelloassoEventListener`, `MattermostEventListener`, `MailerService`, `MailController`, `ShiftController::contactFormAction`, `SendMassMailCommand`, `ShiftReminderCommand`, `SendShiftAlertsCommand`, `AmbassadorShiftTimeLogCommand`, `VerifyCodeChangeCommand`, `CycleStartCommand`, `CycleHalfCommand` ; `templates/emails/` (23 templates) ; `config/services.yaml` (paramètres `emails.*`, `LOGGING_MATTERMOST_*`) ; entités `DynamicContent`, `EmailTemplate`.

#### Vocabulaire
- **Email transactionnel** : email déclenché par une action utilisateur ou système (adhésion, réservation, génération de code…), envoyé unitairement via `symfony/mailer`.
- **Email d'alerte (batch)** : email envoyé à des responsables/admins signalant un état agrégé (créneaux insuffisants, membres en retard…), déclenché par cron.
- **Email de masse** : envoi groupé à un segment de membres, piloté manuellement via CLI (`app:user:mass_mail`) ou interface admin (`MailController`).
- **DynamicContent** : fragment HTML/Twig éditable en base de données par code (ex. `WELCOME_EMAIL`, `SHIFT_REMINDER_EMAIL`). Certains emails l'embarquent comme bloc variable rendu via `twig::createTemplate()`.
- **EmailTemplate** : modèle nommé (objet + corps HTML/Markdown) géré en base via CRUD admin. Utilisé **uniquement** depuis `MailController` pour envelopper le message Markdown admin ; aucun envoi automatique ne l'utilise.
- **Mattermost webhook** : canal push de notifications Markdown vers un salon Mattermost, alternatif ou complémentaire aux emails d'alertes créneaux.
- **Token Vigenère** : chaîne chiffrée symétrique (clé `swipe_card_secret`) embarquée dans les URLs des emails d'action (accept/reject créneau, fermeture code). Voir SEC cross SPEC.4.

#### Acteurs
- **Membre / Bénéficiaire** : destinataire principal des emails transactionnels (bienvenue, cycle, créneau…).
- **Responsable (`ROLE_USER_MANAGER`)** : expéditeur des emails ad hoc via `MailController` ; destinataire des alertes et des copies admin.
- **Système (cron)** : expéditeur des emails batch (rappels créneaux, alertes, cycles, retardataires).
- **Helloasso (webhooks)** : déclencheur indirect via `HelloassoEventListener` puis dispatch d'events internes.
- **Mattermost** : canal de notification sortant (pas un acteur humain).

#### Instances — 6 boîtes email
| Paramètre | Env vars | Rôle |
|-----------|----------|------|
| `emails.admin` | `EMAILS_ADMIN_ADDRESS/NAME` | Notifications admin système |
| `emails.contact` | `EMAILS_CONTACT_ADDRESS/NAME` | Contact public |
| `emails.formation` | `EMAILS_FORMATION_ADDRESS/NAME` | Emails liés aux formations |
| `emails.member` | `EMAILS_MEMBER_ADDRESS/NAME` | Emails membres (adhésion, bienvenue…) |
| `emails.noreply` | `EMAILS_NOREPLY_ADDRESS/NAME` | Expéditeur sans réponse |
| `emails.shift` | `EMAILS_SHIFT_ADDRESS/NAME` | Emails créneaux/bénévolat |
| `emails.sendable` | — | Agrège les 6 boîtes — whitelist pour mass mail et MailController |
| `transactional_mailer_user` | `TRANSACTIONAL_MAILER_USER` | Boîte spécifique première adhésion Helloasso et `shift_contact_form` |
| `emails.base_domain` | `EMAILS_BASE_DOMAIN` | Domaine pour détecter les emails temporaires `membres+N@<domain>` |

#### Composants d'infrastructure

**`MailerService`** (`src/Service/MailerService.php`) — implémente `FOSMailerInterface` :
- `sendConfirmationEmailMessage()` → `welcome.html.twig` (bienvenue + lien confirmation compte)
- `sendResettingEmailMessage()` → `forgot.html.twig` (réinitialisation mot de passe)
- `getAllowedEmails()` → liste `emails.sendable` pour whitelist mass mail
- `isTemporaryEmail()` → détecte `membres+N@<domain>` (pattern regex)
- Injecté via `mailer_service:` nommé dans `services.yaml` (l.491)

**`EmailingEventListener`** (`src/EventListener/EmailingEventListener.php`) — hub central, 13 handlers :
- Inscrit via `emailing_event_listener:` nommé dans `services.yaml` (l.376)
- Injecte le `Container` complet (anti-pattern Symfony 4+ — cross Gaps)
- `renderView()` : fallback `templating → twig` (copié aussi dans `HelloassoEventListener`)
- `$sendEmailCopyToAdminForBookedShift` injecté via `_defaults.bind` (l.222)

**`HelloassoEventListener`** (`src/EventListener/HelloassoEventListener.php`) :
- `onPaymentAfterSave` : tente de lier le paiement à un User par email ; si inconnu → `helloasso_wrong_email.html.twig` + URL de résolution orpheline
- `onOrphanSolve` : réexécute le linkage via `linkPaymentToUser()` (pas d'email direct)
- `linkPaymentToUser()` : dispatch `helloasso.too_early` ou `helloasso.registration_success` selon `canRegister()`

**`MattermostEventListener`** (`src/EventListener/MattermostEventListener.php`) :
- `onShiftAlerts` : POST HTTP vers `mattermost_hook_url` via GuzzleHttp
- Template configurable via `DynamicContent::SHIFT_ALERT_MARKDOWN` ou fallback `markdown/shift_alerts_default.md.twig`

#### Flux email complet

**Transactionnels (HTTP-bound)**

| Événement / déclencheur | Listener | Template | Boîte expéditrice | Destinataire |
|-------------------------|----------|----------|-------------------|-------------|
| `anonymous_beneficiary.created` (`MemberController`) | `EmailingEventListener::onAnonymousBeneficiaryCreated` | `needInfo.html.twig` | `emails.member` | Email anonyme |
| `anonymous_beneficiary.recall` (`AdminMemberController`) | `EmailingEventListener::onAnonymousBeneficiaryRecall` | `needInfoRecall.html.twig` | `emails.member` | Email anonyme |
| `beneficiary.add` (`BeneficiaryController`) | `EmailingEventListener::onBeneficiaryAdd` | `new_beneficiary.html.twig` | `emails.member` | MainBeneficiary du foyer |
| `member.created` (`MemberController`) | `EmailingEventListener::onMemberCreated` | — **(stub vide, TODO ?)** | — | — |
| `helloasso.payment_after_save` (`HelloassoController`) | `HelloassoEventListener::onPaymentAfterSave` | `helloasso_wrong_email.html.twig` (si email inconnu) | `emails.member` | Email payeur Helloasso |
| `helloasso.registration_success` (dispatch par `linkPaymentToUser`) | `EmailingEventListener::onHelloassoRegistrationSuccess` | `registration.html.twig` (1ère adhésion) **ou** `reregistration.html.twig` (renouvellement) | `transactional_mailer_user` (1ère) / `emails.member` (renouvellement) ⚠️ | Bénéficiaire |
| `helloasso.too_early` (dispatch par `linkPaymentToUser`) | `EmailingEventListener::onHelloassoTooEarly` | `too_early_registration.html.twig` | `emails.member` | Bénéficiaire |
| `shift.reserved` (`FreeReservedShiftsCommand`) | `EmailingEventListener::onShiftReserved` | `shift_reserved.html.twig` + URLs accept/reject Vigenère | `emails.shift` | Dernier bénévole du créneau |
| `shift.booked` (`ShiftController`) | `EmailingEventListener::onShiftBooked` | `shift_booked_confirmation.html.twig` + `shift_booked_archive.html.twig` (si flag) | `emails.shift` | Bénéficiaire (+ copie admin si `SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT=true`) |
| `shift.freed` (`ShiftController`) | `EmailingEventListener::onShiftFreed` | `shift_freed.html.twig` | `emails.shift` | Bénéficiaire (si présent) |
| `shift.deleted` (`ShiftController`) | `EmailingEventListener::onShiftDeleted` | `shift_deleted.html.twig` | `emails.shift` | Bénéficiaire (si présent) |
| `event.proxy.created` (`EventController`, SPEC.11) | `EmailingEventListener::onEventProxyCreated` | `proxy_owner.html.twig` + `proxy_giver.html.twig` (2 emails) | `emails.member` | Owner + Giver de la procuration |
| `code.new` (`CodeController`) | `EmailingEventListener::onCodeNew` | `code_new.html.twig` + URL `code_change_done` Vigenère | `emails.shift` | Registrar du code |
| `shift_contact_form` direct (`ShiftController::contactFormAction`) | — (pas d'event, direct) | `coshifter_message.html.twig` | `transactional_mailer_user` | Co-bénévoles du créneau (BCC) |
| FOS `fos_user.registration.confirm` | `MailerService::sendConfirmationEmailMessage` | `welcome.html.twig` | `emails.member` | Nouvel utilisateur |
| FOS `fos_user.resetting.reset_request` | `MailerService::sendResettingEmailMessage` | `forgot.html.twig` | `emails.member` | Utilisateur |

**Batch (cron-bound)**

| Commande | Événement dispatché | Listener | Template | Boîte | Destinataires |
|----------|---------------------|----------|----------|-------|--------------|
| `app:user:cycle_start` | `member.cycle.end` → `TimeLogEventListener` → `member.cycle.start` | `EmailingEventListener::onMemberCycleStart` | `cycle_start.html.twig` | `emails.shift` | Tous les bénéficiaires du foyer |
| `app:user:cycle_half` | `member.cycle.half` | `EmailingEventListener::onMemberCycleHalf` | `cycle_half.html.twig` | `emails.shift` | MainBeneficiary uniquement ⚠️ |
| `app:shift:reminder <date>` | `shift.reminder` | `EmailingEventListener::onShiftReminder` | `shift_reminder.html.twig` | `emails.shift` | Bénéficiaire de chaque créneau |
| `app:shift:send_alerts <date> <jobs>` | `shift.alerts` + `shift.alerts.mattermost` | `EmailingEventListener::onShiftAlerts` + `MattermostEventListener::onShiftAlerts` | `shift_alerts_default.html.twig` / `DynamicContent::SHIFT_ALERT_EMAIL` + `shift_alerts_default.md.twig` / `DynamicContent::SHIFT_ALERT_MARKDOWN` | `emails.shift` | `--emails` (optionnel) + Mattermost (optionnel) |
| `app:shift:send_late_shifters` | — (direct, pas d'event) | — | `shift_late_alerts_default.html.twig` / `DynamicContent::SHIFT_LATE_ALERT_EMAIL` | `emails.shift` | `--emails` (optionnel) |
| `app:shift:verify_change` | — (direct, pas d'event) | — | `code_need_change_confirmation.html.twig` + URL `code_change_done` Vigenère | `emails.shift` | Registrar du code |

**Envoi admin (interface web)**

| Route | Accès | Destinataires | Moteur | Usage EmailTemplate |
|-------|-------|--------------|--------|---------------------|
| `mail_edit` + `mail_send` | ROLE_USER_MANAGER | Bénéficiaires filtrés + non-membres | `MailController` direct | Optionnel : `EmailTemplate::getContent()` enveloppe le Markdown |
| `mail_edit_one_beneficiary` | ROLE_USER_MANAGER | 1 bénéficiaire ciblé | `MailController` direct | idem |
| `mail_bucketshift` | ROLE_USER_MANAGER | Bénéficiaires d'un même créneau-heure | `MailController` direct | idem |
| `app:user:mass_mail` (CLI) | Admin CLI | Membres actifs en **BCC** (bat = test) | `SendMassMailCommand` direct | Non — lit un fichier HTML local |

#### Règles métier
1. **Expéditeur autorisé** : `MailController` et `SendMassMailCommand` vérifient que l'expéditeur est dans `emails.sendable`. Les envois transactionnels utilisent directement `emails.member`/`emails.shift`/`transactional_mailer_user` sans whitelist.
2. **Cycle start** : email envoyé à TOUS les bénéficiaires du foyer si 3 conditions cumulées : membre non gelé + `firstShiftDate < date` + créneaux du cycle actuel < `due_duration_by_cycle`.
3. **Cycle half** : email envoyé au `MainBeneficiary` uniquement, si `firstShiftDate < date` + créneaux < `due_duration_by_cycle`. Asymétrie avec cycle_start (tous les bénéficiaires).
4. **Copie admin booking** : contrôlée par `SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT` (défaut: `true`, env override). Copie envoyée à `emails.shift`.
5. **Réservation préférentielle** : `shift.reserved` génère un email avec lien accept/reject encodé Vigenère (`Shift::getTmpToken(beneficiaryId)`) et le délai `reserve_new_shift_to_prior_shifter_delay`.
6. **Mass mail BCC** : `SendMassMailCommand` met les membres en `bcc` (anti-spam), l'expéditeur en `to`. En mode BAT (`--bat`), envoi unique à l'email de test.
7. **Cycle start command — chaîne indirecte** : `app:user:cycle_start` dispatche `MemberCycleEndEvent` → `TimeLogEventListener::onMemberCycleEnd()` crée les logs + dispatche `MemberCycleStartEvent` si le membre n'est pas gelé → `EmailingEventListener::onMemberCycleStart()` envoie l'email. La commande est nommée du point de vue utilisateur (cycle qui *commence*) mais le code dispatch un event END.
8. **Shift alerts** : envoyé uniquement si `count(alerts) > 0` ET `--emails` fourni (email) / `--mattermostUrl` fourni (Mattermost) — aucun envoi si aucune option n'est précisée.
9. **`CommissionJoinOrLeaveEvent`** : malgré le cross-ref SPEC.6, aucun email n'est envoyé sur `commission.join`/`commission.leave` — `CommissionEventListener` gère uniquement la persistance de `Beneficiary::own`.
10. **Locale** : `EmailingEventListener` et `MattermostEventListener` appellent `setlocale(LC_TIME, $locale)` au constructeur (pour `strftime()` dans les sujets/corps d'emails).

#### Données
| Entité / Paramètre | Rôle |
|-------------------|------|
| `DynamicContent` | Blocs HTML/Twig injectés dans les emails par code : `PRE_MEMBERSHIP_EMAIL`, `WELCOME_EMAIL`, `SHIFT_REMINDER_EMAIL`, `SHIFT_ALERT_EMAIL`, `SHIFT_ALERT_MARKDOWN`, `SHIFT_LATE_ALERT_EMAIL` |
| `EmailTemplate` | Modèle nommé (sujet + corps) utilisé uniquement depuis `MailController` — aucun envoi transactionnel automatique |
| `HelloassoPayment` | Payload webhook Helloasso (montant, email payeur, date) |
| `Shift::getTmpToken(beneficiaryId)` | Token Vigenère dans URLs accept/reject du `shift_reserved` email |
| `Code::getId()` + username | Token Vigenère dans URLs `code_change_done` des emails code |
| `EMAILS_*_ADDRESS/NAME` (×6) | 12 env vars pour les 6 boîtes (adresse + nom d'expéditeur) |
| `EMAILS_BASE_DOMAIN` | Domaine de détection des emails temporaires |
| `TRANSACTIONAL_MAILER_USER` | Boîte dédiée Helloasso première adhésion + `shift_contact_form` |
| `SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT` | Flag copie admin sur booking (défaut: `true`) |
| `LOGGING_MATTERMOST_URL/ENABLED/LEVEL/CHANNEL` | Mattermost (doublon : sert aussi au handler Monolog de logging applicatif — cross CONFIG.3) |

#### Cas limites
- **`DynamicContent` absent → NPE** : `onAnonymousBeneficiaryCreated`, `onAnonymousBeneficiaryRecall` (l.75/112), `onShiftReminder` (l.408) et `MailerService::sendConfirmationEmailMessage` (l.83) appellent `->findOneByCode(...)->getContent()` sans null-guard. Si le code DynamicContent manque en base → exception fatale à l'envoi. **TODO SYN.2** (robustesse, effort XS).
- **`onShiftAlerts`/`MattermostEventListener` : DynamicContent absent géré** : les deux handlers `onShiftAlerts` vérifient le retour de `findOneByCode()` avant usage → fallback template fichier. Cohérence à imposer partout (ci-dessus).
- **`shift_contact_form` sans `@Security`** : n'importe qui connaissant un `id` de `Shift` peut envoyer un email aux co-bénévoles — cross EXTRA[SPEC.3].
- **`onMemberCreated` stub vide** : l'event `member.created` est dispatché mais `EmailingEventListener::onMemberCreated()` ne fait rien (commentaire `// TODO ?`). L'email de bienvenue passe par `MailerService::sendConfirmationEmailMessage` (FOSUserBundle), flux indépendant.

#### Routes (SPEC.7)
| Route | Méthode | URL | Accès | Action email |
|-------|---------|-----|-------|-------------|
| `mail_edit` | GET\|POST | `/admin/mail/` | ROLE_USER_MANAGER | Formulaire envoi de mail (recherche bénéficiaires) |
| `mail_edit_one_beneficiary` | GET\|POST | `/admin/mail/to/{id}` | ROLE_USER_MANAGER | Formulaire mail vers 1 bénéficiaire |
| `mail_bucketshift` | GET\|POST | `/admin/mail/to_bucket/{id}` | ROLE_USER_MANAGER | Formulaire mail vers bénéficiaires d'un créneau-heure |
| `mail_send` | POST | `/admin/mail/send` | ROLE_USER_MANAGER | Envoi effectif (markdown → HTML + EmailTemplate optionnel) |
| `shift_contact_form` | GET\|POST | `/shift/{id}/contact_form` | ⚠️ aucune @Security | Email direct aux co-bénévoles d'un créneau |
| `fos_user_registration_confirm` | GET | `/register/confirm/{token}` | public | Confirmation compte → `MailerService::sendConfirmationEmailMessage` |
| `fos_user_resetting_reset` | GET\|POST | `/resetting/reset/{token}` | public | Reset mot de passe → `MailerService::sendResettingEmailMessage` |

#### Événements
| Événement | Classe | Déclencheur | Listener email |
|-----------|--------|-------------|---------------|
| `shift.reserved` | `ShiftReservedEvent` | `FreeReservedShiftsCommand` | `EmailingEventListener::onShiftReserved` |
| `shift.booked` | `ShiftBookedEvent` | `ShiftController` | `EmailingEventListener::onShiftBooked` |
| `shift.freed` | `ShiftFreedEvent` | `ShiftController` | `EmailingEventListener::onShiftFreed` |
| `shift.reminder` | `ShiftReminderEvent` | `ShiftReminderCommand` | `EmailingEventListener::onShiftReminder` |
| `shift.deleted` | `ShiftDeletedEvent` | `ShiftController` | `EmailingEventListener::onShiftDeleted` |
| `shift.alerts` | `ShiftAlertsEvent` | `SendShiftAlertsCommand` | `EmailingEventListener::onShiftAlerts` |
| `shift.alerts.mattermost` | `ShiftAlertsMattermostEvent` | `SendShiftAlertsCommand` | `MattermostEventListener::onShiftAlerts` |
| `member.cycle.end` | `MemberCycleEndEvent` | `CycleStartCommand` (⚠️ nommage trompeur) | `TimeLogEventListener::onMemberCycleEnd` → dispatch `member.cycle.start` |
| `member.cycle.start` | `MemberCycleStartEvent` | `TimeLogEventListener` (indirect) | `EmailingEventListener::onMemberCycleStart` |
| `member.cycle.half` | `MemberCycleHalfEvent` | `CycleHalfCommand` | `EmailingEventListener::onMemberCycleHalf` |
| `member.created` | `MemberCreatedEvent` | `MemberController` | `EmailingEventListener::onMemberCreated` (stub vide) |
| `anonymous_beneficiary.created` | `AnonymousBeneficiaryCreatedEvent` | `MemberController` | `EmailingEventListener::onAnonymousBeneficiaryCreated` |
| `anonymous_beneficiary.recall` | `AnonymousBeneficiaryRecallEvent` | `AdminMemberController` | `EmailingEventListener::onAnonymousBeneficiaryRecall` |
| `beneficiary.add` | `BeneficiaryAddEvent` | `BeneficiaryController` | `EmailingEventListener::onBeneficiaryAdd` |
| `event.proxy.created` | `EventProxyCreatedEvent` | `EventController` (SPEC.11) | `EmailingEventListener::onEventProxyCreated` (2 emails) |
| `helloasso.payment_after_save` | `HelloassoEvent::PAYMENT_AFTER_SAVE` | `HelloassoController` | `HelloassoEventListener::onPaymentAfterSave` |
| `helloasso.registration_success` | `HelloassoEvent::RE_REGISTRATION_SUCCESS` ⚠️ | `HelloassoEventListener::linkPaymentToUser` | `EmailingEventListener::onHelloassoRegistrationSuccess` (branche sur `registrations.count`) |
| `helloasso.too_early` | `HelloassoEvent::TOO_EARLY` | `HelloassoEventListener::linkPaymentToUser` | `EmailingEventListener::onHelloassoTooEarly` |
| `helloasso.orphan_solve` | `HelloassoEvent::ORPHAN_SOLVE` | `HelloassoController` | `HelloassoEventListener::onOrphanSolve` (pas d'email) |
| `code.new` | `CodeNewEvent` | `CodeController` | `EmailingEventListener::onCodeNew` |
| `commission.join` / `commission.leave` | `CommissionJoinOrLeaveEvent` | `CommissionController` (SPEC.6) | `CommissionEventListener` → **pas d'email** (persistance uniquement) |

#### Commandes batch (SPEC.7)
| Commande | Envoi si | Option template |
|----------|----------|----------------|
| `app:user:cycle_start [--date]` | membre non gelé + firstShiftDate passée + manque créneaux | — |
| `app:user:cycle_half [--date]` | firstShiftDate passée + manque créneaux | — |
| `app:shift:reminder <date>` | créneau avec bénéficiaire ce jour | — |
| `app:shift:send_alerts <date> <jobs> [--emails] [--emailTemplate] [--mattermostUrl] [--mattermostTemplate]` | alertes trouvées + option fournie | `DynamicContent::SHIFT_ALERT_EMAIL` / `::SHIFT_ALERT_MARKDOWN` |
| `app:shift:send_late_shifters [--emails] [--emailTemplate]` | retardataires trouvés + `--emails` fourni | `DynamicContent::SHIFT_LATE_ALERT_EMAIL` |
| `app:shift:verify_change` | code ancien encore visible par user | — |
| `app:user:mass_mail <from> <subject> <file> [--bat] [--tolerance] [--frozen] [--exclude_non_member]` | expéditeur whitelisté + fichier lisible | fichier HTML local (Twig commenté, l.92-94) |

#### Tests existants
- Aucun test unitaire ou fonctionnel spécifique à `EmailingEventListener`, `HelloassoEventListener`, `MattermostEventListener` ou `MailerService` n'a été identifié.
- `SmokeTest` couvre les routes `mail_edit`, `mail_send` (status HTTP) sans assertion métier.

#### Gaps / Findings

**Bugs** :
- 🔴 **`die($e->getMessage())` dans `onHelloassoTooEarly`** (l.257) : tue le process PHP sur exception de rendu Twig en production — page blanche sur un flux de paiement Helloasso. Cross EXTRA[AP.7]. **TODO SYN.2**.
- 🟠 **`findOneByCode()->getContent()` sans null-guard** dans `onAnonymousBeneficiaryCreated` (l.75), `onAnonymousBeneficiaryRecall` (l.112), `onShiftReminder` (l.408) et `MailerService::sendConfirmationEmailMessage` (l.83) : si le `DynamicContent` requis est absent de la base, exception fatale à l'envoi. **TODO SYN.2** (effort XS chacun : ajouter garde + fallback vide ou exception lisible).
- 🟡 **`onMemberCreated` stub vide** (l.174-179, `// TODO ?`) : event dispatché mais aucun email envoyé. Intentionnel ou oubli ? À clarifier avec les mainteneurs. Le flow FOSUserBundle (confirmation d'email) envoie bien le bienvenue — mais `member.created` n'est jamais exploité. *(Sévérité canonique 🟡 = dead/stub sans impact fonctionnel, cohérent avec D.5 finding 4 et m-DC-4 : l'email de bienvenue partant via FOSUserBundle, il n'y a **pas** de notification manquante.)*

**Incohérences** :
- 🟠 **`CycleStartCommand` dispatche `MemberCycleEndEvent`** : la commande s'appelle `app:user:cycle_start` mais dispatch `member.cycle.end`. Fonctionnel (END → TimeLog → START par cascade), mais trompeur pour tout développeur lisant la commande. À documenter dans **SYN.1**.
- 🟠 **Incohérence de boîte expéditrice Helloasso** : première adhésion → `transactional_mailer_user` (sans `from_name`, l.210) ; renouvellement → `emails.member` (avec `from_name`). Même flux utilisateur, comportement différent.
- 🟠 **`HelloassoEvent::RE_REGISTRATION_SUCCESS` couvre aussi la première adhésion** : le nom de la constante est trompeur (`RE_REGISTRATION`) alors qu'elle vaut `helloasso.registration_success` et couvre les deux cas. Seule la logique dans `onHelloassoRegistrationSuccess` (`count > 1`) différencie les deux.
- 🟠 **Asymétrie destinataires cycle** : `onMemberCycleStart` envoie à TOUS les bénéficiaires du foyer ; `onMemberCycleHalf` envoie uniquement au `MainBeneficiary`. Non documenté — potentiellement intentionnel.

**Architecture / dette** :
- 🟠 **Anti-pattern container injection** : `EmailingEventListener`, `HelloassoEventListener`, `CommissionEventListener`, `MattermostEventListener` injectent le `Container` complet plutôt que des dépendances explicites. Empêche la vérification statique des dépendances et l'écriture de tests unitaires. **TODO SYN.2** (effort M — refactoring progressif).
- 🟠 **`renderView()` dupliqué** : `EmailingEventListener` et `HelloassoEventListener` portent chacun la même méthode `renderView()` (fallback `templating → twig`). Candidat extraction dans un trait ou service partagé.
- 🟠 **`onAnonymousBeneficiaryRecall` : incohérence injection `SwipeCard`** (l.116-118) : utilise `$this->container->get('App\Helper\SwipeCard')` alors que `$this->swipeCardHelper` est déjà injecté dans le constructeur.
- 🟡 **`EmailTemplate` déconnecté des envois automatiques** : l'entité `EmailTemplate` (CRUD SPEC.6) n'est utilisée que dans `MailController::sendAction` pour envelopper un corps Markdown admin. Aucun envoi transactionnel ni batch ne l'utilise.
- 🟡 **`app:user:mass_mail` : Twig commenté** (l.92-94) : le rendu Twig du corps est commenté — si le fichier HTML contient des variables Twig, elles ne seront pas résolues.
- 🟡 **`LOGGING_MATTERMOST_*` ambigu** : ces paramètres servent à la fois le handler Monolog (logging applicatif) et `MattermostEventListener` (alertes créneaux). La distinction n'est pas évidente ; `MattermostEventListener` reçoit l'URL directement via `ShiftAlertsMattermostEvent`, pas via ces paramètres.

**Sécurité** :
- 🟠 **Tokens Vigenère sans expiration** dans les liens email (`shift_reserved` accept/reject, `code_change_done`, `member_new`/`member_add_beneficiary`) — chiffrement symétrique faible, rejouables indéfiniment. Cross SPEC.4 EXTRA.
- 🟡 **SSTI potentielle sur `DynamicContent`** : `twig::createTemplate($dynamicContent->getContent())` exécute du Twig arbitraire depuis la base de données dans `onShiftReminder`, `onShiftAlerts` et `MattermostEventListener`. Acceptable si seuls des admins éditent `DynamicContent` (ROLE_PROCESS_MANAGER) — à vérifier que ce rôle est bien restreint (cross SPEC.6 gaps).
- 🟠 **`shift_contact_form` sans `@Security`** — route publique de facto, envoie des emails aux co-bénévoles sans authentification (spam SMTP). Cross EXTRA[SPEC.3]. **TODO SYN.2** (effort XS : ajouter `@Security("is_granted('ROLE_USER')")`). *(Aligné sur SEC.1.3 / I-SEC-2 🟠.)*
- 🟡 **`mail_send` : Twig arbitraire en contexte bénéficiaire** : `twig::createTemplate($content)` dans `MailController::sendAction` permet d'injecter du Twig dans le corps de l'email. Réservé à ROLE_USER_MANAGER — niveau de risque faible mais à noter.

