# SPEC.9 — Annotations d'usage par instance

- [x] **SPEC.9** — Annotations d'usage par instance

Pour chaque spec : annoter "utilisé chez Elefan/Scopeli" si identifiable via CONFIG.2 ou RT.

**Nature de l'item** : couche d'annotation transversale, **pas une nouvelle spec narrative**. Consolide en un seul endroit les hypothèses d'instance jusqu'ici éparpillées dans SPEC.4-8 (toutes marquées « à confirmer »), CONFIG.2/3 et RT.1/2.

---

### Verdict de déterminabilité (à lire avant la matrice)

**L'état d'usage réel par instance n'est pas déterminable depuis le repository.** Trois faits, déjà établis ailleurs dans l'audit, le verrouillent :

1. **Aucune discrimination d'instance au runtime** (CONFIG.2 + RT.1, confirmé par lecture directe de `config/` et grep `src/`) : pas de variable `APP_INSTANCE`, pas de table de configuration en base, pas de feature-flag framework, aucune logique PHP conditionnelle « si Elefan… sinon Scopeli ». Elefan et Scopeli exécutent **le même code source** ; ils ne diffèrent que par leur fichier `.env` de déploiement.
2. **Les `.env` de prod sont des secrets inaccessibles** (règle 1 de l'environnement d'audit). Les seuls fichiers de configuration committés — `.env.dist`, `.env.test`, `.env.oidc.test` — portent des valeurs **par défaut / de test**, **pas** l'état réel d'Elefan ni de Scopeli. (Le `.env.dist` lui-même ne configure ni `HELLOASSO_*` ni `IGLOOHOME_*` — d'où les 2 warnings `debug:router` notés en SPEC.1 : c'est l'instance *de développement* du repo, pas une instance de prod.)
3. **Le seul mécanisme capable de produire la donnée réelle n'existe pas encore** : c'est précisément le Route Usage Tracker spécifié en **RT.2** (table `route_usage` clé `(route_name, instance)`, alimentée par un `kernel.terminate` subscriber, prérequis = créer la variable `APP_INSTANCE`). Tant qu'il n'est pas implémenté **et** déployé sur les deux instances, l'usage par instance reste non observé.

**Conséquence** : cette section ne livre **pas** une table de certitudes « Elefan = oui / Scopeli = non ». Elle livre une **matrice des leviers instance-specific** + leur **comportement** + l'**instance plausiblement concernée** (avec la source de l'hypothèse) + un **niveau de déterminabilité** explicite. Aucun état on/off n'est inventé : ce qui est indéterminable est marqué comme tel, avec la donnée exacte qui permettrait de trancher.

#### Seul fait dur sur les instances

Le repository **est** celui d'Elefan (`composer.json` : *« Web site to manage the cooperative grocery shop l'Elefan »* ; origine `github.com/elefan-grenoble/gestion-compte`). **Elefan est donc l'instance canonique / d'origine** ; Scopeli (et les autres coops) déploient des forks/déploiements du même tronc. Tout le reste, côté usage par instance, relève de l'hypothèse ou de l'indéterminé.

#### Légende de déterminabilité

| Niveau | Signification |
|---|---|
| 🟢 **Fait** | Dérivable du repo (code, config committée, origine du projet) — vrai indépendamment des `.env` de prod. |
| 🟡 **Hypothèse étayée** | Un indice existe (commentaire de code, listener dédié, branding, origine du repo) mais **non confirmé** — l'état réel dépend du `.env` de prod. |
| 🔴 **Indéterminé sans `.env`** | Aucun indice exploitable dans le repo ; l'état on/off par instance n'est connaissable que via le `.env` de prod ou la donnée RT.2. |

---

### Matrice 1 — Intégrations externes (instance-specific *par conception*)

Toutes utilisent `%env(default::VAR)%` → **dégradation gracieuse** : si l'instance ne configure pas le provider, la feature est inerte (cf. AP.9, SPEC.8). C'est le design qui les rend instance-specific, indépendamment de toute donnée de prod.

| Levier | Comportement quand activé | Comportement quand absent | Instance plausible (source) | Déterminabilité | Domaine SPEC |
|---|---|---|---|---|---|
| **`HELLOASSO_*`** | Paiement d'adhésion via API HelloAsso v5 (OAuth) + webhook `/helloassoNotify` + écrans `admin/helloasso/*` | Paiement HelloAsso inactif ; adhésion par inscription manuelle (`REGISTRATION_MANUAL_ENABLED`) | **Elefan = on** (hypothèse forte : repo d'origine Elefan, `composer.json`, intégration historique — SPEC.5/AP.5) ; Scopeli = ? | 🟡 (Elefan) / 🔴 (Scopeli) | SPEC.5, SPEC.8 |
| **`OIDC_*` / `OIDC_ENABLE`** | SSO Keycloak : `/login → /oauth/login`, `OidcFirewallListener` désactive l'UI FOSUser (login, profile/edit, resetting, member/new) ; Keycloak fait autorité sur les rôles (RAZ à chaque login) | Auth locale FOSUserBundle (login/password, resetting, registration) | **Scopeli = on** / **Elefan = off** (hypothèse répétée SPEC.4/SPEC.8 + note SF-PREP T1) — **non confirmée** ; `OidcFirewallListener` est dans le tronc commun, sa présence ne prouve pas l'usage par une instance | 🟡 | SPEC.4, SPEC.8 |
| **`IGLOOHOME_*`** | Serrures connectées : génération/poussée de codes d'accès physiques (`app:code:update_igloohome`) | Commande `update_igloohome` échoue à l'enregistrement (credentials `null`) — inerte | Instance-specific, **laquelle = indéterminé** (aucun indice d'origine) | 🔴 | SPEC.8 (codes : SPEC.3/4/6) |
| **`LOGGING_MATTERMOST_ENABLED` / `LOGGING_MATTERMOST_*`** | Alertes/notifs poussées sur un channel Mattermost (handler Monolog `prod`) | Pas de notif Mattermost | Indéterminé par instance | 🔴 | SPEC.7, LOG.1 |

---

### Matrice 2 — Feature flags booléens (on/off par `.env`, état réel non committé)

Inventaire repris de **CONFIG.2** (tableau des flags). **Aucun** n'a d'état par instance dérivable du repo → tous 🔴 sauf mention contraire. Ce qui est documentable, c'est le **comportement** de chaque flag et le **domaine** qu'il affecte — utile pour SYN.1/SYN.3.

| Flag | Effet quand `true` | Domaine SPEC | Déterminabilité par instance |
|---|---|---|---|
| `USE_FLY_AND_FIXED` | Active le mode créneaux volant/fixe (concept `flying` ; cross CONFIG.3 `FLY_AND_FIXED_ENTITY_FLYING`) | SPEC.3 | 🔴 |
| `FLY_AND_FIXED_ALLOW_FIXED_SHIFT_FREE` | Autorise la libération d'un créneau fixe | SPEC.3 | 🔴 |
| `USE_CARD_READER_TO_VALIDATE_SHIFTS` | Validation de présence par lecteur de carte (`card_reader_*`) | SPEC.3 | 🔴 |
| `ALLOW_EXTRA_SHIFTS` | Réservation de créneaux supplémentaires (`MAX_TIME_IN_ADVANCE_TO_BOOK_EXTRA_SHIFTS`) | SPEC.3 | 🔴 |
| `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER` | Priorité des anciens bénéficiaires sur un nouveau créneau (délai = `*_DELAY`, 🔴 bug cast `bool:` CONFIG.1) | SPEC.3 | 🔴 |
| `USE_TIME_LOG_SAVING` | Épargne de temps (time banking) ; sections profil associées | SPEC.3 | 🔴 |
| `TIME_LOG_SAVING_SHIFT_FREE_ALLOW_ONLY_IF_ENOUGH_SAVING` | Libération de créneau conditionnée au solde d'épargne | SPEC.3 | 🔴 |
| `CODE_GENERATION_ENABLED` | Génération de codes d'accès physiques (`code_generate`, cf. EXTRA SPEC.4 collision `rand`) | SPEC.4 | 🔴 |
| `SWIPE_CARD_LOGGING` / `_ANONYMOUS` | Journalisation (anonyme ou non) des passages de badge | SPEC.4 | 🔴 |
| `ENABLE_PLACE_LOCAL_IP_ADDRESS_CHECK` | Filtrage par IP locale du lieu pour l'accès badge/code (`PlaceIP`) | SPEC.4 | 🔴 |
| `DISPLAY_GAUGE` | Jauge canvas-gauges — ⚠️ dépendance CDN HS (EXTRA DEP.3) : si off partout, feature retirable plutôt que corrigée | SPEC.6 | 🔴 (à trancher en priorité — DEP.3) |
| `DISPLAY_KEYS_SHOP` | Module boutique de clés | SPEC.6 | 🔴 |
| `DISPLAY_FREEZE_ACCOUNT` | Option de gel de compte membre (`member_freeze`) | SPEC.6, SPEC.2 | 🔴 |
| `DISPLAY_SWIPE_CARDS_SETTINGS` | Interface admin de gestion des cartes de swipe | SPEC.6 | 🔴 |
| `DISPLAY_OPENING_HOUR_OPEN_CLOSED_HEADER` | Bandeau ouvert/fermé en tête (cf. `OpeningHourService` D.4) | SPEC.6 | 🔴 |
| `DISPLAY_NAME_SHIFTERS` | Noms des bénéficiaires visibles publiquement (impact confidentialité — route `booking` anonyme, SP.5) | SPEC.3, SPEC.6 | 🔴 |
| `REGISTRATION_MANUAL_ENABLED` | Inscription manuelle (hors HelloAsso) — complémentaire de `HELLOASSO_*` ; ⚠️ absent de `services.yaml` (CONFIG.2) | SPEC.5, SPEC.2 | 🔴 |
| `REGISTRATION_EVERY_CIVIL_YEAR` | Cotisation calée sur l'année civile vs date d'adhésion glissante | SPEC.5 | 🔴 |
| `NEW_USERS_START_AS_BEGINNER` | Nouveaux membres en statut débutant (restreint l'accès badge, cf. `Beneficiary` ≤ 3 créneaux) | SPEC.2, SPEC.4 | 🔴 |
| `SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT` | Copie admin à chaque réservation — ⚠️ défaut hardcodé `true` dans `services.yaml:72` (CONFIG.1/3) | SPEC.7 | 🔴 |
| `PROFILE_DISPLAY_TASK_LIST` / `_TIME_LOG` / `_SHIFT_FREE_LOG` / `_PERIOD_POSITION_FREE_LOG` | Sections affichées dans le profil membre | SPEC.2 | 🔴 |
| `ADMIN_MEMBER_DISPLAY_SHIFT_FREE_LOG` / `_PERIOD_POSITION_FREE_LOG` | Colonnes affichées côté admin membre | SPEC.6 | 🔴 |
| `FORBID_OWN_SHIFT_BOOK/FREE/VALIDATE_ADMIN` / `FORBID_OWN_TIMELOG_NEW_ADMIN` | Restrictions des admins sur leurs propres actions (séparation des pouvoirs) | SPEC.6 | 🔴 |

---

### Matrice 3 — Paramètres métier différenciés par *valeur* (pas on/off)

Repris de **CONFIG.3**. Ici la différenciation inter-instance n'est pas binaire mais une **valeur** propre à chaque coop — toujours dans le `.env` de prod, donc 🔴, sauf l'indice ci-dessous.

| Paramètre | Différenciation | Instance plausible (source) | Déterminabilité |
|---|---|---|---|
| `CYCLE_TYPE` (`abcd` \| `*`) | Cycles alignés semaine ISO A/B/C/D vs cycles flottants | **Elefan = `abcd`** (CONFIG.3 finding : *« En pratique Elefan utilise abcd »*) ; Scopeli = indéterminé (aucune source dans le repo) | 🟡 (Elefan) / 🔴 (Scopeli) |
| `CYCLE_DURATION`, `DUE_DURATION_BY_CYCLE`, `MIN_SHIFT_DURATION`, `FORBID_SHIFT_OVERLAP_TIME` | Règles de durée des créneaux (⚠️ `CYCLE_DURATION` ignoré, hardcodé `28` — CONFIG.3 bug) | Valeur par instance | 🔴 |
| `FLY_AND_FIXED_ENTITY_FLYING` (`Beneficiary` \| `Membership`) | Entité porteuse du statut « volant » | Valeur par instance | 🔴 |
| `MAXIMUM_NB_OF_BENEFICIARIES_IN_MEMBERSHIP`, `REGISTRATION_DURATION` | Règles d'adhésion | Valeur par instance | 🔴 |
| `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS` | Seuil de dette de temps (heures, négatif — CONFIG.3) | Valeur par instance | 🔴 |
| Branding : `SITE_NAME`, `PROJECT_NAME`, `MAIN_COLOR`, `LOCAL_CURRENCY_NAME`, `ROUTER_REQUEST_CONTEXT_HOST` | Identité visuelle + domaine (`membres.lelefan.org` vs `membres.scopeli.coop`) | Distinct par instance *par nature* | 🟢 (le fait qu'ils diffèrent) / 🔴 (valeurs Scopeli exactes) |

---

### Synthèse — ce qui serait nécessaire pour lever l'indéterminé

Pour transformer les 🔴/🟡 ci-dessus en faits, dans l'ordre de coût croissant :

1. **Le plus direct** : obtenir les `.env` de prod d'Elefan et de Scopeli (ou un extrait des seules variables listées ici) auprès des admins d'instance. Une lecture suffit à figer toutes les colonnes. *Hors périmètre de cet audit (secrets, règle 1).*
2. **Observation réelle d'usage** (au-delà du simple on/off de config) : implémenter et déployer le **Route Usage Tracker (RT.2)** sur les deux instances. Donne non seulement l'état des flags mais l'usage *effectif* des routes par instance — la donnée qui guide vraiment dead-code et priorisation de migration.
3. **Confirmations ciblées à demander aux deux coops** (questions fermées, sans accès secret) :
   - Elefan & Scopeli : `OIDC_ENABLE` ? (tranche SPEC.4/SPEC.8 et la priorisation FOSUser→SF5)
   - Scopeli : `HELLOASSO_*` configuré, ou inscription manuelle / autre PSP ?
   - Les deux : `IGLOOHOME_*` utilisé ?
   - Les deux : `DISPLAY_GAUGE` (décide du sort de la dépendance CDN HS — EXTRA DEP.3) ?
   - Les deux : `CYCLE_TYPE` réellement `abcd` (et cycle de 28 jours) ? Si oui, le bug `CYCLE_DURATION` hardcodé `28` (CONFIG.3) reste dormant ; sinon il est actif.

### Note pour SYN.3 (cohérence des specs)

Le champ **`Instances`** du template SPEC doit, pour chaque spec, **renvoyer à cette matrice** plutôt que dupliquer une hypothèse. Convention recommandée :
- `Instances : toutes` quand la spec ne dépend d'aucun levier des matrices 1-3 (cœur commun).
- `Instances : conditionné par <FLAG> (cf. SPEC.9, 🔴/🟡)` quand un levier la gouverne.

Les annotations « à confirmer » éparses (SPEC.1 §domaines, SPEC.4 OIDC, SPEC.5/SPEC.8 Helloasso, SF-PREP T1) sont désormais **consolidées ici** ; SYN.1 (documentation multi-instance) et SYN.2 (TODO — confirmations §3 ci-dessus) s'appuient sur cette section comme source unique.

