# AP.4 — Container injecté comme service locator

- [x] **AP.4** — Container injecté comme service locator

`grep -rn "ContainerInterface\|DependencyInjection\\\\Container" src/` hors `Kernel.php` → TODO.

**Périmètre** : 31 fichiers non-migration retournés par le grep. Après dépouillement, 3 catégories distinctes identifiées.

---

<a id="AP.4-A"></a>
### Catégorie A — Imports orphelins (7 fichiers — dead `use`, aucun service locator)

Ces fichiers importent `ContainerInterface`, `Container`, ou `ContainerBuilder` dans leur `use` mais ne l'injectent pas dans le constructeur et ne l'utilisent pas dans le corps de la classe.

| Fichier | Import mort | Remarque |
|---------|------------|---------|
| `Security/TaskVoter.php` | `ContainerInterface` | Non injecté — constructeur reçoit uniquement `AccessDecisionManagerInterface` |
| `Security/NoteVoter.php` | `ContainerInterface` | Idem |
| `Security/SwipeCardVoter.php` | `ContainerInterface` | Idem |
| `Twig/Extension/AppExtension.php` | `Container` | Non utilisé — `AppExtension` n'injecte aucun container |
| `Service/ShiftService.php` | `Container` | Non utilisé — `ShiftService` n'injecte aucun container |
| `Controller/AdminController.php` | `ContainerBuilder` | Classe de compilation DI (≠ service locator runtime) — import orphelin |
| `Controller/RegistrationsController.php` | `ContainerBuilder` | Idem |

**Action** : supprimer les 7 `use` statements. Effort XS (mécanique).

---

<a id="AP.4-B"></a>
### Catégorie B — Propriétés container mortes (2 fichiers — cross-référence DC.4)

| Fichier | Situation |
|---------|---------|
| `EventListener/CodeEventListener.php` | `Container $container` reçu dans le constructeur, assigné à `$this->container`, jamais lu dans aucune méthode. **Nouveau finding** — à ajouter à DC.4 catégorie A.5. |
| `EventListener/CommissionEventListener.php` | Idem — déjà documenté en DC.4.A.5. |

**Action** : supprimer le param constructeur + la propriété (même pattern que DC.4.A.5). Effort XS.

---

<a id="AP.4-C"></a>
### Catégorie C — Vrai service locator (22 fichiers actifs — antipattern à corriger)

Ces classes injectent `ContainerInterface` ou `Container` comme dépendance constructeur et appellent `$this->container->get()` ou `$this->container->getParameter()` à l'intérieur. Elles rendent le code non testable sans conteneur Symfony complet et masquent les vraies dépendances.

#### C.1 — `getParameter()` uniquement → remplacer par `ParameterBagInterface` (🟠)

Ces classes utilisent le container exclusivement pour lire des paramètres de configuration. Le remplacement est mécanique : injecter `ParameterBagInterface $params` et appeler `$params->get('name')`, ou binder les scalaires dans `services.yaml` (`bind: $projectName: '%project_name%'`).

| Fichier | Paramètres lus | Via |
|---------|---------------|-----|
| `EventListener/OidcFirewallListener.php` | `oidc_enable` (×1) | `getParameter()` dans `onKernelRequest` |
| `Security/KeycloakAuthenticator.php` | `oidc_roles_claim`, `oidc_roles_map`, `oidc_formations_claim`, `oidc_formations_map`, `oidc_commissions_claim`, `oidc_commissions_map`, `oidc_user_attributes_map` (×7) | `getParameter()` dans les méthodes auth |
| `Service/MembershipService.php` | `registration_duration`, `registration_every_civil_year`, `cycle_type`, `use_fly_and_fixed`, `fly_and_fixed_entity_flying` (×5) | `getParameter()` dans le constructeur |
| `Service/PeriodService.php` | `use_fly_and_fixed`, `fly_and_fixed_entity_flying` (×2) | `getParameter()` dans le constructeur |
| `Service/BeneficiaryService.php` | `use_fly_and_fixed`, `fly_and_fixed_entity_flying`, `member_withdrawn_icon`, `member_frozen_icon`, `beneficiary_flying_icon`, `member_flying_icon`, `member_exempted_icon`, `member_registration_missing_icon` (×8) | `getParameter()` constructeur + méthodes |
| `Service/SearchUserFormHelper.php` | `use_fly_and_fixed`, `fly_and_fixed_entity_flying`, `maximum_nb_of_beneficiaries_in_membership`, `member_withdrawn_icon`, `member_frozen_icon`, `member_exempted_icon`, `member_registration_missing_icon`, `user_account_enabled_icon`, `member_flying_icon`, `beneficiary_flying_icon` (×10) | `getParameter()` constructeur + méthodes |

→ **TODO SYN.2** — effort S par fichier (injection directe scalaire ou `ParameterBagInterface`)

#### C.2 — `get(service)` en constructeur → injection directe possible (🟠)

Ces classes résolvent leurs dépendances **immédiatement dans le constructeur** via `$container->get()`. Il n'y a pas de lazy loading, donc pas de problème de dépendance circulaire justifiant le pattern. Le service peut être injecté directement.

| Fichier | Service(s) résolu(s) | Alternative directe |
|---------|---------------------|-------------------|
| `Twig/Extension/MembershipExtension.php` | `'membership_service'` → `MembershipService` | Injecter `MembershipService $membershipService` directement |
| `Twig/Extension/BeneficiaryExtension.php` | `'beneficiary_service'` → `BeneficiaryService` | Injecter `BeneficiaryService $beneficiaryService` directement |
| `Security/ShiftVoter.php` | `'shift_service'` → `ShiftService` dans le constructeur | Injecter `ShiftService $shiftService` directement. `RequestStack` encore résolu via `->get('request_stack')` en runtime (voir C.3). |
| `Validator/Constraints/BeneficiaryCanHostValidator.php` | `'membership_service'` → `MembershipService` dans le constructeur ; `maximum_nb_of_beneficiaries_in_membership` via `getParameter()` | Injecter `MembershipService` directement + scalaire bindé |

→ **TODO SYN.2** — effort XS–S par fichier

#### C.3 — `get(service)` à l'exécution (lazy) → EventListeners et Voters (🔴)

Ces classes appellent `$this->container->get()` **dans les méthodes d'event handling ou de vote**, pas dans le constructeur. Ce pattern est l'anti-pattern historique de Symfony 2/3 pour contourner les dépendances circulaires entre EventListeners et les services qu'ils déclenchent. En Symfony 4.4+, l'injection directe suffit : le DI container résout les cycles via des proxies générés automatiquement.

**`EmailingEventListener.php` — cas extrême (🔴)**
6 services différents résolus par `->get()` en runtime : `'router'`, `'twig'`, `'App\Helper\SwipeCard'`, `'membership_service'`, `'templating'` (legacy fallback Symfony 3), plus `'fos_oauth_server.client_manager'` (absent du code mais bundle toujours chargé). En plus, 7 paramètres lus dans le constructeur via `->getParameter()`. Le listener compte ~700 lignes — c'est à la fois le plus gros service locator et le plus gros listener du projet.

| Fichier | Services résolus lazily | Paramètres lus |
|---------|------------------------|---------------|
| `EmailingEventListener.php` | `router`, `twig`, `App\Helper\SwipeCard`, `membership_service`, `templating` (×legacy) | `due_duration_by_cycle`, `emails.member`, `emails.shift`, `wiki_keys_url`, `reserve_new_shift_to_prior_shifter_delay`, `locale`, `project_name`, `transactional_mailer_user` (×8) |
| `TimeLogEventListener.php` | `time_log_service`, `membership_service`, `event_dispatcher` | `due_duration_by_cycle`, `cycle_duration`, `registration_duration`, `max_time_at_end_of_shift`, `use_card_reader_to_validate_shifts`, `use_time_log_saving`, `time_log_saving_shift_free_min_time_in_advance_days` (×7) |
| `HelloassoEventListener.php` | `router`, `twig`, `App\Helper\SwipeCard`, `membership_service`, `event_dispatcher`, `templating` (×legacy) | `emails.member`, `project_name` (×2) |
| `MattermostEventListener.php` | `twig` | `locale` (×1) |
| `ShiftFreeLogEventListener.php` | `shift_free_log_service` | — |
| `PeriodPositionFreeLogEventListener.php` | `period_position_free_log_service` | — |

**Voters avec get() lazy (🟠)**

| Fichier | Services résolus lazily | Paramètres lus |
|---------|------------------------|---------------|
| `Security/CodeVoter.php` | `PlaceIP::class`, `shift_service` | `code_generation_enabled` (×2) |
| `Security/MembershipVoter.php` | `PlaceIP::class` | `oidc_enable` (×2) |
| `Security/UserVoter.php` | `PlaceIP::class` | `oidc_enable` |
| `Security/ShiftVoter.php` | `request_stack` (runtime, en plus du constructor) | — |

**Helper avec get() lazy (🟡)**

| Fichier | Services résolus lazily | Paramètres lus |
|---------|------------------------|---------------|
| `Helper/PlaceIP.php` | `request_stack` | `enable_place_local_ip_address_check`, `place_local_ip_address` (×2) |
| `Twig/Extension/ProcessUpdateExtension.php` | `doctrine` (→ ManagerRegistry) | — |

**Note sur `'templating'`** : le pattern `if ($this->container->has('templating')) … else if ($this->container->has('twig'))` dans `EmailingEventListener` et `HelloassoEventListener` est un vestige de compatibilité Symfony 3→4. `templating` n'existe plus en Symfony 4+. Ce code mort peut être simplifié en `$this->twig->render(...)` directement.

→ **TODO SYN.2** — effort M–L pour les EventListeners (EmailingEventListener en particulier), S pour les Voters et helpers.

---

### Migration path recommandé

| Pattern | Remplacement | Effort |
|---------|-------------|--------|
| `->getParameter('foo')` | `ParameterBagInterface $params` + `$params->get('foo')` | S |
| `->get(ServiceFoo::class)` en constructeur | Injecter `ServiceFoo $foo` directement | XS |
| `->get('router')` / `->get('twig')` en runtime | Injecter `RouterInterface $router` / `Environment $twig` directement | XS–S |
| `->get('service_id_string')` en runtime | Identifier le FQCN → injection directe | S |
| Bloc `has('templating')` legacy | Supprimer + injecter `Environment $twig` directement | XS |
| Imports orphelins | Supprimer les `use` | XS |

---

### Résumé

| Gravité | Catégorie | Fichiers | Effort |
|---------|----------|---------|--------|
| 🟡 Nettoyage | A — imports orphelins | 7 | XS |
| 🟡 Nettoyage | B — propriétés mortes (cross DC.4) | 2 | XS |
| 🟠 Important | C.1 — getParameter() → ParameterBagInterface | 6 | S |
| 🟠 Important | C.2 — get() en constructeur → injection directe | 4 | S |
| 🔴 Critique | C.3 — get() lazy EventListeners | 6 | M–L |
| 🟠 Important | C.3 — get() lazy Voters + helpers | 5 | S |

**Total affecté** : 22 classes avec service locator actif + 7 imports orphelins + 2 propriétés mortes = **31 fichiers**.

