# RT.2 — Rédiger la recommandation d'implémentation

- [x] **RT.2** — Rédiger la recommandation d'implémentation

Décrire dans la TODO : EventSubscriber sur `kernel.terminate`, upsert en base sur `(route_name, instance)`, page admin de rapport. Inclure le schéma de la table proposée. C'est une spec technique, pas du code.

  **Résultat**

  Spec technique du tracking de routes par instance. **Cet item ne produit pas de code — uniquement une spécification à intégrer dans SYN.2 (TODO priorisée).**

  ---

  ### Spec : Route Usage Tracker (tracking runtime multi-instance)

  **Objectif** : savoir quelles routes sont appelées sur chaque instance (Elefan, Scopeli) pour guider les décisions de dead code et de migration Symfony.

  #### Prérequis : variable `APP_INSTANCE`

  Ajouter dans le `.env` de chaque déploiement :
  ```
  APP_INSTANCE=elefan   # ou scopeli
  ```
  Déclarer dans `config/services.yaml` :
  ```yaml
  app_instance: '%env(APP_INSTANCE)%'
  ```
  Ajouter dans `.env.dist` avec commentaire explicatif.

  #### Schéma de la table proposée

  Table : `route_usage`

  | Colonne | Type | Contrainte | Description |
  |---|---|---|---|
  | `id` | `integer` | PK, AUTO_INCREMENT | — |
  | `route_name` | `varchar(255)` | NOT NULL | Nom Symfony de la route (ex. `admin_shift_index`) |
  | `instance` | `varchar(50)` | NOT NULL | Valeur de `APP_INSTANCE` au moment de l'appel |
  | `last_seen_at` | `datetime` | NOT NULL | Horodatage du dernier appel observé |
  | `hit_count` | `integer` | NOT NULL, DEFAULT 1 | Nombre de hits depuis le premier enregistrement |

  Index unique : `(route_name, instance)` — clé naturelle pour le upsert.

  Migration Doctrine à créer : `Version{YYYYMMDDHHMMSS}_route_usage_tracking.php`, suivant la convention des migrations existantes dans `src/Migrations/`.

  #### Entité Doctrine

  `src/Entity/RouteUsage.php` — annotations `@ORM\Table(name="route_usage")` + `@ORM\UniqueConstraint(columns={"route_name", "instance"})`. Champs : `$id`, `$routeName`, `$instance`, `$lastSeenAt`, `$hitCount`.

  Repository `src/Repository/RouteUsageRepository.php` avec méthode `upsert(string $routeName, string $instance): void` — `INSERT ... ON DUPLICATE KEY UPDATE hit_count = hit_count + 1, last_seen_at = NOW()` (via `EntityManager::getConnection()->executeStatement()`).

  #### EventSubscriber `kernel.terminate`

  `src/EventListener/RouteUsageSubscriber.php` — implémente `EventSubscriberInterface` (pattern cohérent avec `BeneficiaryInitializationSubscriber.php` existant).

  `kernel.terminate` est déclenché **après** que la réponse est envoyée au client : zéro impact sur la latence perçue.

  Logique `onKernelTerminate(TerminateEvent $event)` :
  1. Récupérer la route depuis `$event->getRequest()->attributes->get('_route')`.
  2. Ignorer si `null` (requêtes sans route Symfony, ex. assets).
  3. Ignorer les routes internes Symfony (`_profiler`, `_wdt`, `_error`).
  4. Appeler `RouteUsageRepository::upsert($routeName, $this->appInstance)`.

  Injection : `$appInstance` via `_defaults.bind` dans `services.yaml` (`$appInstance: '%app_instance%'`).

  Enregistrement dans `services.yaml` :
  ```yaml
  App\EventListener\RouteUsageSubscriber:
    tags:
      - { name: kernel.event_listener, event: kernel.terminate, method: onKernelTerminate }
  ```

  #### Page admin de rapport

  `src/Controller/AdminRouteUsageController.php` — `@Route("admin/route-usage")`, `@Security("is_granted('ROLE_ADMIN')")`.

  Action `index` : requête agrégée `SELECT route_name, instance, last_seen_at, hit_count FROM route_usage ORDER BY last_seen_at DESC`. Rendu dans `templates/admin/route_usage/index.html.twig`.

  Affichage : tableau avec colonnes `Route`, `Instance`, `Dernier appel`, `Nb hits`. Filtre par instance (dropdown). Bouton d'export CSV optionnel.

  Lien dans le menu admin (à ajouter dans le template de navigation admin existant).

  #### Estimation d'effort

  | Composant | Effort |
  |---|---|
  | Variable `APP_INSTANCE` + config | S (< 1h) |
  | Migration + entité + repository | S (2h) |
  | EventSubscriber | S (1h) |
  | Page admin + template | M (4h) |
  | **Total** | **M (< 1 jour)** |

  #### Risques

  - **Performance** : un upsert par requête HTTP. Acceptable pour un trafic coopératif (< 1000 req/h). Si le trafic augmente, mettre en cache en mémoire (APCu, Redis) et flusher périodiquement.
  - **Données en test** : ne pas activer en `test` env (ajouter une guard `if ($this->kernel->getEnvironment() === 'test') return;`).
  - **Migration SF5** : `kernel.terminate` et `EventSubscriberInterface` sont stables jusqu'à SF6+ — aucun impact de migration.

  **TODO SYN.2** : classer cet item 🟡 Mineur / M.

---

