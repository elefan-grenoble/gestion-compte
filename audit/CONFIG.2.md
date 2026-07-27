# CONFIG.2 — Mécanisme de personnalisation par instance

- [x] **CONFIG.2** — Mécanisme de personnalisation par instance

Comment Elefan et Scopeli configurent-ils leur instance différemment ? Paramètres Symfony, table de config en base, feature flags ? `grep -rn "getParameter\|ParameterBagInterface" src/`. Résultat → documentation finale + specs (SPEC.9).

  **Résultat**

  Méthode : lecture de `config/services.yaml`, `config/packages/twig.yaml`, `.env.dist` ; grep exhaustif `getParameter` (84 appels) et `ParameterBagInterface` dans `src/`.

  ### Architecture de configuration : 3 couches, tout par variables d'environnement

  Il n'existe **aucune table de configuration en base** (pas d'entité `Parameter`/`Config`/`Setting` dans `src/Entity/`), **aucun feature flag framework** (Flipper, Unleash, etc.), et **aucune variable `APP_INSTANCE`** ou mécanisme d'identification de déploiement au runtime. Elefan et Scopeli sont deux déploiements indépendants du même code, différenciés uniquement par leur fichier `.env` (ou équivalent CI/CD).

  **Couche 1 — Variables d'environnement** : définies dans `.env` / `.env.local` / secrets CI.
  **Couche 2 — Paramètres Symfony** : `config/services.yaml` déclare ~130 paramètres nommés (ex. `cycle_type`, `oidc_enable`) mappés depuis les vars via `%env(TYPE:VAR)%`.
  **Couche 3 — Consommation** par deux vecteurs :
  - **`_defaults.bind`** dans `services.yaml` (lignes 176–222) : injection automatique par nom d'argument PHP dans les services/controllers.
  - **`getParameter('name')`** : 84 appels directs depuis controllers (`AbstractController`), event listeners et services via `ContainerAwareTrait` (anti-pattern Symfony 5+ : préférer injection constructeur).
  - **Globales Twig** : `config/packages/twig.yaml` expose ~60 paramètres directement dans les templates.

  ### Catégories fonctionnelles (8 groupes)

  | Catégorie | Variables clés | Exemple de différenciation |
  |---|---|---|
  | **Infrastructure** | `APP_ENV`, `DATABASE_URL`, `MAILER_DSN`, `PHP_*` | Docker/déploiement, non applicatif |
  | **Identité coopérative** | `SITE_NAME`, `PROJECT_NAME`, `MAIN_COLOR`, `LOCAL_CURRENCY_NAME` | Branding Elefan vs Scopeli |
  | **Membres/adhésion** | `REGISTRATION_DURATION`, `MAXIMUM_NB_OF_BENEFICIARIES_IN_MEMBERSHIP`, `REGISTRATION_EVERY_CIVIL_YEAR` | Règles d'adhésion propres à chaque coop |
  | **Créneaux** | `CYCLE_DURATION`, `CYCLE_TYPE`, `DUE_DURATION_BY_CYCLE`, `MIN_SHIFT_DURATION`, `FORBID_SHIFT_OVERLAP_TIME` | Règles métier des créneaux |
  | **Feature flags booléens** | voir tableau ci-dessous | Activation/désactivation de modules |
  | **Intégrations externes** | `HELLOASSO_*`, `IGLOOHOME_*`, `OIDC_*` | Services tiers différents par instance |
  | **Sécurité** | `ENABLE_PLACE_LOCAL_IP_ADDRESS_CHECK`, `SWIPE_CARD_SECRET`, `SUPER_ADMIN_*` | Configuration sécurité par déploiement |
  | **UI / icônes** | `MEMBER_*_ICON`, `MEMBER_*_BACKGROUND_COLOR`, `BENEFICIARY_*_ICON` | Personnalisation visuelle des statuts |

  ### Feature flags booléens (on/off par instance)

  Ce sont les vrais leviers de comportement différencié entre instances :

  | Variable | Fonctionnalité |
  |---|---|
  | `OIDC_ENABLE` | Authentification SSO Keycloak |
  | `USE_FLY_AND_FIXED` | Mode créneaux volant/fixe |
  | `USE_CARD_READER_TO_VALIDATE_SHIFTS` | Validation par lecteur de carte |
  | `USE_TIME_LOG_SAVING` | Épargne de temps (time banking) |
  | `CODE_GENERATION_ENABLED` | Génération de codes d'accès physiques |
  | `DISPLAY_GAUGE` | Jauge canvas-gauges (dépendance CDN HS — cf. EXTRA [DEP.3]) |
  | `DISPLAY_KEYS_SHOP` | Module boutique de clés |
  | `DISPLAY_FREEZE_ACCOUNT` | Option gel de compte membre |
  | `DISPLAY_SWIPE_CARDS_SETTINGS` | Interface gestion cartes de swipe |
  | `ALLOW_EXTRA_SHIFTS` | Créneaux supplémentaires autorisés |
  | `REGISTRATION_MANUAL_ENABLED` | Inscription manuelle (non HelloAsso) |
  | `LOGGING_MATTERMOST_ENABLED` | Notifications Mattermost |
  | `DISPLAY_OPENING_HOUR_OPEN_CLOSED_HEADER` | Bandeau ouvert/fermé en tête |
  | `DISPLAY_NAME_SHIFTERS` | Noms des bénéficiaires visibles publiquement |
  | `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER` | Réservation prioritaire aux anciens bénéficiaires |
  | `ENABLE_PLACE_LOCAL_IP_ADDRESS_CHECK` | Filtrage IP pour accès lieu |
  | `NEW_USERS_START_AS_BEGINNER` | Nouveaux membres = statut débutant |
  | `REGISTRATION_EVERY_CIVIL_YEAR` | Cotisation calée sur l'année civile |
  | `FLY_AND_FIXED_ALLOW_FIXED_SHIFT_FREE` | Libération de créneaux fixes autorisée |
  | `TIME_LOG_SAVING_SHIFT_FREE_ALLOW_ONLY_IF_ENOUGH_SAVING` | Libération conditionnelle au solde |
  | `SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT` | Copie email confirmation créneau à l'admin |
  | `SWIPE_CARD_LOGGING` / `SWIPE_CARD_LOGGING_ANONYMOUS` | Journalisation des passages carte |
  | `PROFILE_DISPLAY_TASK_LIST` / `_TIME_LOG` / `_SHIFT_FREE_LOG` / `_PERIOD_POSITION_FREE_LOG` | Sections affichées dans le profil membre |
  | `ADMIN_MEMBER_DISPLAY_SHIFT_FREE_LOG` / `_PERIOD_POSITION_FREE_LOG` | Colonnes affichées côté admin |
  | `FORBID_OWN_SHIFT_BOOK/FREE/VALIDATE_ADMIN` / `FORBID_OWN_TIMELOG_NEW_ADMIN` | Restrictions des admins sur leurs propres actions |

  ### Findings

  #### 🟡 Inconsistance dans `twig.yaml` : accès direct env vs paramètre nommé
  Dans `config/packages/twig.yaml`, certaines globales Twig court-circuitent le paramètre Symfony défini dans `services.yaml` et lisent directement `%env()%` :
  ```yaml
  # twig.yaml L63 — court-circuite le paramètre services.yaml L19
  display_swipe_cards_settings: '%env(bool:DISPLAY_SWIPE_CARDS_SETTINGS)%'
  # vs pattern cohérent :
  display_freeze_account: '%display_freeze_account%'  # passe par le paramètre
  ```
  Autres globales concernées (ligne Twig / ligne services.yaml) : `cycle_type` (L17/L12), `registration_manual_enabled` (L19/absent de services.yaml), `use_card_reader_to_validate_shifts` (L68/L162), `fly_and_fixed_entity_flying` (L70/L48), `fly_and_fixed_allow_fixed_shift_free` (L71/L49), `display_name_shifters` (L67/L17), `oidc_enable` (L104/L107), `oidc_issuer`/`oidc_client_id` (L105-106/absents de services.yaml), `oidc_formations_map`/`oidc_commissions_claim`/`oidc_commissions_map` (L111-113/définis en paramètre). Si un paramètre nommé est redéfini en `services.yaml` (cast, valeur par défaut), la globale Twig ne reflète pas le changement. **TODO mineur : uniformiser pour passer systématiquement par le paramètre Symfony dans twig.yaml.**

  #### 🟡 Paramètre `registration_manual_enabled` absent de `services.yaml`
  Consommé comme globale Twig (`twig.yaml:19`) mais **non déclaré en paramètre Symfony** dans `services.yaml`. La var d'env `REGISTRATION_MANUAL_ENABLED` est accessible uniquement via Twig, pas via `getParameter()` dans le code PHP. Si du code PHP en a besoin, il doit faire `$this->getParameter('env(bool:REGISTRATION_MANUAL_ENABLED)')` (syntaxe non standard) — pas de bug actuel mais fragilité. **TODO mineur : déclarer en paramètre nommé dans `services.yaml`.**

  #### 🔵 Aucun mécanisme d'identification d'instance au runtime
  Conséquence directe pour RT.1 : il faudra créer une variable (`APP_INSTANCE=elefan|scopeli`) pour alimenter le tracking de routes recommandé en RT.2.

  #### 🔵 Anti-pattern `getParameter()` via `ContainerAwareTrait` (84 appels)
  L'injection par `getParameter()` depuis le container est un anti-pattern déprécié depuis Symfony 4 (et interdit dans Symfony 5+). La migration vers SF5 (SF-PREP) devra remplacer les 84 appels par injection constructeur ou `_defaults.bind`. À noter dans **SF-PREP.2**.

