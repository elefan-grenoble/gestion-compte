# SPEC.1 — Cartographier les domaines fonctionnels

- [x] **SPEC.1** — Cartographier les domaines fonctionnels

`docker compose exec -T php php bin/console debug:router --format=txt`. 43 controllers, **~235 routes** au total dont **~205 routes applicatives** (hors profiler/wdt, liip_imagine, et le redirect `root`). Regroupement ci-dessous en **10 domaines fonctionnels** + 2 catégories transverses + 1 catégorie infra exclue.

⚠️ **Note Helloasso/Igloohome** : `debug:router` émet 2 warnings (`HelloassoClient`/`IgloohomeClient` __construct : argument null) car cette instance ne configure pas les variables `HELLOASSO_*`/`IGLOOHOME_*`. La table de routes est néanmoins complète et correcte — ces warnings concernent l'enregistrement des **commandes**, pas des routes. Confirme la dégradation gracieuse documentée en AP.9.

---

### Vue d'ensemble — domaines vs plan SPEC

| # | Domaine fonctionnel | Controllers principaux | Routes (~) | Couvert par |
|---|---------------------|------------------------|:----------:|-------------|
| A | **Adhérents / Bénéficiaires** | `MembershipController`, `BeneficiaryController`, `NoteController` + pre-users de `UserController` | 30 | **SPEC.2** |
| B | **Créneaux / Planning** | `ShiftController`, `BookingController`, `PeriodController`, `CardReaderController`, `TimeLogController`, `AdminPeriod*`, `AdminShift*`, `AdminMembershipShiftExemption*` | 45 | **SPEC.3** |
| C | **Authentification & Autorisation** | FOSUserBundle, `OAuthController` (Keycloak), `SwipeCardController`, `UserController` (rôles, install_admin) | 35 | **SPEC.4** |
| D | **Cotisations & Paiements** | `HelloassoController`, `RegistrationsController` | 13 | **SPEC.5** |
| E | **Administration & Configuration** | `AdminController`, `Code`, `Commission`, `Service`, `Task`, `Job`, `Formation`, `DynamicContent`, `EmailTemplate`, `SocialNetwork`, `ClosingException`, `OpeningHour`, `ProcessUpdate`, `Client` | 50 | **SPEC.6** |
| F | **Notifications & Emails** | `MailController`, `EmailTemplateController`, `shift_contact_form` | 5 (+ event-driven) | **SPEC.7** |
| G | **API & Intégrations externes** | `ApiController`, FOSOAuthServer, OIDC, webhook Helloasso, Igloohome (CLI) | 9 | **SPEC.8** |
| **H** | **🆕 Gouvernance / Assemblées générales (Events & Procurations)** | `EventController`, `AdminEventController`, `AdminEventKindController` | 22 | **SPEC.11** 🆕 (gap 1, tranché session 52) |
| I | **Pages publiques & Widgets embarqués** | `DefaultController`, `WidgetController` + actions `*_widget` | 9 | transverse (à répartir) |
| J | **Contrôle d'accès physique (Codes & Badges)** | `CodeController`, `SwipeCardController`, `CardReaderController`, Igloohome | 18 | transverse SPEC.3/4/6/8 |

> **Décompte H (réconciliation SYN.3)** : le domaine H a été chiffré précisément à **22 routes** en SPEC.11 (7 `event_*` + 11 `admin_event_*`/`admin_proxies_list` + 4 `admin_event_kind_*`), et non ~16 comme estimé initialement ici. Le total **~205 routes applicatives distinctes** en tient déjà compte : les routes H figuraient dans le dump `debug:router`, seule l'estimation par domaine était basse. ⚠️ La colonne « Routes (~) » **ne somme pas** à 205 — les domaines **I** (pages/widgets) et **J** (accès physique) ainsi qu'une partie de **G** sont des regroupements transverses dont les routes sont déjà comptées dans A/B/C/E.

---

### Détail par domaine

**A — Adhérents / Bénéficiaires** (→ SPEC.2)
Gestion du cycle de vie d'un membre et de ses bénéficiaires, notes internes, onboarding.
- Membre : `member_show`, `member_new`, `member_join`, `member_edit_firewall`, `member_delete`, `member_flying`, `member_freeze`, `member_unfreeze`, `member_freeze_change`, `member_withdrawn`, `member_add_beneficiary`, `member_new_beneficiary`, `user_office_tools`, `admin_emails_csv`
- Onboarding / activation : `find_member_number`, `find_me`, `confirm`, `set_email` (⚠️ SEC.2/SEC.3 — account takeover)
- Bénéficiaire : `beneficiary_edit`, `beneficiary_set_main`, `beneficiary_detach`, `beneficiary_delete`
- Notes : `note_reply`, `note_edit`, `note_delete`
- Pré-inscrits (anonymous beneficiaries) : `pre_user_index`, `pre_user_recall`, `pre_user_delete`, `user_quick_new`, `user_self_register`
- **Chevauchements** : `member_new_registration` (→ aussi SPEC.5 cotisation), `member_join` (fusion d'adhésions — logique métier AP.1 finding 2b).

**B — Créneaux / Planning** (→ SPEC.3)
Cœur métier : réservation/libération de créneaux, périodes, validation de présence.
- Réservation membre : `booking`, `booking_by_day`, `bucket_show`, `bucket_show_for_beneficiary`, `shift_book`, `shift_free`, `shift_accept_reserved`, `shift_reject_reserved`, `timelog_new`
- Réservation admin : `booking_admin`, `admin_bucket_show`, `bucket_edit`, `bucket_lock_unlock`, `bucket_delete`, `shift_new`, `shift_book_admin`, `shift_free_admin`, `shift_validate_admin`, `shift_delete`, `member_timelog_delete`
- Validation présence : `card_reader_index`, `card_reader_check` (+ `root` → redirect `/cardReader`)
- Admin périodes : `admin_period_*` (index/new/edit/delete/copy), `admin_periodposition_*` (new/delete/book/free), `admin_shifts_generation`, `admin_periodpositionfreelog_index`
- Admin exemptions/logs : `admin_shiftexemption_*`, `admin_shiftfreelog_index`, `admin_membershipshiftexemption_*`
- Suivi ambassadeur : `ambassador_shifttimelog_list`, `ambassador_beneficiary_fixe_without_periodposition_list`
- Vue publique : `period_index`, `shift_widget`, `schedule`
- **Chevauchements** : `shift_contact_form` (→ SPEC.7), `card_reader_check`/badges (→ domaine J).

**C — Authentification & Autorisation** (→ SPEC.4)
- FOS login/logout : `fos_user_security_login`, `fos_user_security_check`, `fos_user_security_logout`
- FOS registration : `fos_user_registration_register`, `_check_email`, `_confirm`, `_confirmed`
- FOS resetting : `fos_user_resetting_request`, `_send_email`, `_check_email`, `_reset`
- FOS profil/mdp : `fos_user_profile_show`, `fos_user_profile_edit`, `fos_user_change_password`, `user_change_password`
- OIDC Keycloak : `oauth_login`, `oauth_logout`, `oauth_check` (instance-specific Scopeli)
- Badges (auth passwordless) : `swipe_in`, `swipe_show`, `swipe_qr`, `swipe_br`, `activate_swipe`, `enable_swipe`, `disable_swipe`, `delete_swipe`
- Gestion comptes/rôles (admin) : `user_index`, `non_member_users_list`, `admin_users_list`, `roles_list`, `user_install_admin`, `user_add_role`, `user_remove_role`, `user_delete`, `user_client_remove`, `user_import_csv`
- **Chevauchements** : badges (→ domaine J), gestion comptes (→ SPEC.6 admin).

**D — Cotisations & Paiements** (→ SPEC.5)
- Helloasso : `helloasso_notify` (webhook), `helloasso_payments`, `helloasso_browser`, `helloasso_campaign_details`, `helloasso_manual_paiement_add`, `helloasso_payment_remove`, `helloasso_payment_edit`, `helloasso_resolve_orphan`, `helloasso_confirm_resolve_orphan`, `helloasso_orphan_exit_and_back`
- Adhésions/cotisations : `registrations`, `registration_edit`, `registration_remove`, `member_new_registration` (cross SPEC.2)
- **Note instance** : Helloasso = instance-specific (Elefan ; Scopeli à confirmer CONFIG.2).

**E — Administration & Configuration** (→ SPEC.6)
CRUD des entités de configuration coopérative.
- Dashboard : `admin`
- Codes d'accès : `codes_list`, `code_edit`, `code_generate`, `code_toggle`, `code_change_done`, `code_delete` (cross domaine J)
- Commissions : `admin_commissions`, `commission_new`, `commission_edit`, `commission_add_beneficiary`, `commission_remove_beneficiary`, `commission_delete`
- Services/Tâches : `service_*`, `service_navlist`, `tasks_list`, `task_*`, `job_*`
- Formations : `formation_*`
- Contenu dynamique / templates : `dynamic_content_list`, `dynamic_content_edit`, `email_template_*` (cross SPEC.7)
- Horaires/fermetures : `admin_openinghour_*`, `admin_openinghour_kind_*`, `admin_closingexception_*`
- Réseaux sociaux : `admin_socialnetwork_*`
- Notes de version : `process_update_list`, `process_update_count_unread`, `process_update_new`, `process_update_edit`, `process_update_delete`
- Clients OAuth : `client_list`, `client_new`, `client_edit`, `client_delete` (cross SPEC.8)

**F — Notifications & Emails** (→ SPEC.7)
Essentiellement **piloté par événements** (cf. AP.7 — `EmailingEventListener`, 13 types d'emails). Routes directes :
- Mailing admin : `mail_edit`, `mail_edit_one_beneficiary`, `mail_bucketshift`, `mail_send`
- Templates : `email_template_*` (cross SPEC.6)
- Contact créneau : `shift_contact_form` (cross SPEC.3)

**G — API & Intégrations externes** (→ SPEC.8)
- API interne : `api_swipe_in`, `api_user`, `api_nextcloud_user` (`/api/oauth/nextcloud_user`), `api_gitlab_user` (`/api/v4/user`)
- Serveur OAuth (SSO sortant) : `fos_oauth_server_token`, `fos_oauth_server_authorize`
- OIDC entrant (Keycloak) : `oauth_login/logout/check` (cross SPEC.4)
- Webhook Helloasso : `helloasso_notify` (cross SPEC.5)
- Igloohome (serrures) : pas de route — piloté par `UpdateIgloohomeCodeCommand` (cross domaine J)

---

### ⚠️ Gaps du plan SPEC — domaines non couverts par SPEC.2-8

**Gap 1 — Gouvernance / Assemblées générales & Procurations (domaine H) — NON PRÉVU dans le plan.**
`EventController` expose un domaine fonctionnel **entièrement distinct** : la gestion d'événements associatifs (typiquement les AG) avec un système de **procurations (proxies)** :
- Public/membre : `event_index`, `event_detail`, `event_widget`, `event_proxy_give` (donner procuration), `event_proxy_take` (recevoir), `event_proxy_find_beneficiary`, `event_proxy_lite_delete`
- Admin : `admin_event_*`, `admin_event_kind_*`, `admin_proxies_list`, `admin_event_proxies_list`, `admin_event_proxy_edit/delete`, `admin_event_signatures`

Ce domaine (votes/quorum/émargement en AG) ne se réduit ni à SPEC.2 (membres) ni à SPEC.6 (admin CRUD). **Recommandation** : ajouter un **SPEC.5bis ou SPEC.11 — Gouvernance & Assemblées générales** au plan, OU le traiter explicitement comme sous-section de SPEC.6 avec un avertissement de complétude. À trancher avec l'utilisateur avant SPEC.6 (cf. question ci-dessous).

**Gap 2 — Contrôle d'accès physique (domaine J) transverse.**
Les **codes d'accès** (`CodeController` — codes de porte rotatifs), les **badges** (`SwipeCardController` + chiffrement Vigenère SEC.1.7), le **lecteur de badge** (`CardReaderController`) et l'intégration **Igloohome** (serrures connectées) forment une chaîne fonctionnelle cohérente « accès physique au local » qui traverse SPEC.3 (validation créneau), SPEC.4 (auth badge), SPEC.6 (gestion codes) et SPEC.8 (Igloohome). **Recommandation** : traiter comme une **sous-section transverse dédiée** dans SPEC.4 (auth) avec cross-refs, plutôt que de l'éparpiller. Forte composante sécurité (SEC.1.7, SEC.2.2).

**Gap 3 — Widgets embarqués (domaine I) transverse.**
5 actions `*_widget` (`event_widget`, `shift_widget`, `closingexception_widget`, `openinghour_widget`, `widget`) génèrent des fragments HTML embarquables sur des sites externes. Préoccupation de **présentation transverse**, pas un domaine métier — à documenter par domaine concerné + une note transverse dans SPEC.6 (générateurs de widgets admin : `*_widget_generator`).

---

### Catégorie infra — EXCLUE des specs

Routes techniques sans valeur fonctionnelle métier : `_preview_error`, `_wdt`, `_profiler_*` (11 routes, dev only), `liip_imagine_filter` + `liip_imagine_filter_runtime` (cache d'images, infra), `root` (redirect `/cardReader` → `/card_reader`).

---

### Conséquences pour SPEC.2-10

1. **Ordre de traitement** : A (SPEC.2) et B (SPEC.3) sont les plus gros et les plus interdépendants (membre ↔ créneau via `TimeLog`/cycle). Les traiter en premier.
2. **Le domaine H (Gouvernance/AG)** → ✅ **tranché (session 52)** : spec dédiée **SPEC.11**.
3. **Le domaine J (accès physique)** → ✅ **tranché (session 52)** : sous-section transverse de **SPEC.4**.
4. Chaque spec devra porter les **annotations multi-instance** (SPEC.9) : Helloasso, OIDC/Keycloak, Igloohome, `use_fly_and_fixed` sont des features instance-specific déjà identifiées (CONFIG.2/CONFIG.3).

