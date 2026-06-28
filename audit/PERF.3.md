# PERF.3 — Cache applicatif

- [x] **PERF.3** — Cache applicatif


  **Infrastructure cache en place**

  - **`cache.yaml`** : `cache.app` utilise le FilesystemAdapter par défaut (adaptateurs Redis/APCu en commentaire — non configurés).
  - **`config/packages/prod/doctrine.yaml`** : deux pools déclarés — `doctrine.system_cache_pool` (metadata + query cache → `cache.system`) et `doctrine.result_cache_pool` (result cache → `cache.app`). Config **prod uniquement**, pas de dev.

  **Usages existants**

  | Fichier | Mécanisme | TTL | Problème |
  |---|---|---|---|
  | `src/Providers/CacheOauthAuthenticatorDecorator.php:22` | `new FilesystemAdapter()` direct | 600 s ou expiry token | Hors DI — ne suit pas `cache.app` |
  | `src/Repository/ShiftRepository.php:32` | `new FilesystemAdapter()` static | 5 s | Hors DI — ne suit pas `cache.app` |

  **Anomalie #1 — `new FilesystemAdapter()` hors DI (2 occurrences)**
  `CacheOauthAuthenticatorDecorator` (L22) et `ShiftRepository::functionsResultCache()` (L32) instancient `FilesystemAdapter` directement, sans injection de dépendance. Conséquences :
  - Ne suivent pas la configuration `cache.app` (si l'opérateur passe à Redis/APCu, ces caches restent sur le filesystem).
  - Pas purgés par `php bin/console cache:pool:clear`.
  - Namespace par défaut vide → risque de collision de clés avec d'autres usages filesystem.

  **Anomalie #2 — Doctrine result cache configuré mais jamais activé**
  La `result_cache_driver` déclarée dans `prod/doctrine.yaml` crée le pool mais n'active pas automatiquement le cache sur les requêtes. Il faut appeler explicitement `->enableResultCache()` (Doctrine 2.6+) ou l'ancien `->useResultCache(true)` sur chaque `Query`. Grep sur tout `src/` : **aucun appel**. La configuration est du dead config.

  **Anomalie #3 — Autocomplete lourd embarqué inline, sans cache**
  Les widgets Twig (`templates/form/fields.html.twig:19,37,86`) injectent la liste complète des adhérents/membres en JSON inline dans chaque page HTML contenant un champ autocomplete :
  ```twig
  data: {{ beneficiary_service.autocompleteBeneficiaries | json_encode(...) | raw }}
  data: {{ membership_service.autocompleteMemberships   | json_encode(...) | raw }}
  ```
  Ces appels déclenchent `BeneficiaryRepository::findAllActive()` et `MembershipRepository::findAllActive()` — full table reads (~3 000+ lignes, cf. PERF.2). **Aucune mise en cache.** 8+ contrôleurs utilisent `AutocompleteBeneficiaryType` ; si une page contient deux champs autocomplete différents, `findAllActive()` est appelé deux fois dans la même requête.

  **Recommandations TODO (par priorité)**
  1. 🟡 **Autocomplete data** — Mettre en cache `getAutocompleteBeneficiaries()` et `getAutocompleteMemberships()` via `CacheInterface` injecté (TTL ~5 min, ou invalidation sur événement membership write). Gain : supprime les full table reads répétés sur toutes les pages admin avec formulaire.
  2. 🟡 **Corriger les deux `new FilesystemAdapter()`** — Injecter `CacheInterface $cache` (ou un pool nommé) dans `CacheOauthAuthenticatorDecorator` et `ShiftRepository` pour qu'ils utilisent le pool central configuré.
  3. 🔵 **Activer Doctrine result cache sur les requêtes read-mostly** — Listes de référentiel stables (formations, jobs, opening hours) : ajouter `->enableResultCache(3600, 'cache_key')` sur les queries concernées pour tirer parti du pool déjà configuré en prod.

---

