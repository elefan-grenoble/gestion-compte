# AP.5 — Services avec état mutable

- [x] **AP.5** — Services avec état mutable

Services singleton qui ont des propriétés écrites après construction (risque entre requêtes).

**Contexte** : tous les services `src/Service/` sont des singletons Symfony (scope par défaut). Sous PHP-FPM (runtime actuel), chaque requête instancie un conteneur neuf → aucune fuite d'état entre requêtes. Le risque ne se matérialiserait qu'avec un runtime long-running (Roadrunner, FrankenPHP, Swoole).

**Finding 1 — `FixtureGroupConsoleService` : setter post-construction (et dead code)**
- Fichier : `src/Service/FixtureGroupConsoleService.php`
- Seul setter `$this->` en dehors d'un constructeur dans tout `src/Service/` : `setInput(InputInterface $input)` affecte `$this->input` après construction.
- La propriété `$input` est `null` par défaut ; `getGroups()` protège ce null, mais si `setInput()` était appelé l'état persisterait pour toute la durée de vie du service.
- **Critique** : `setInput()` n'est jamais appelé ailleurs dans le code. `FixtureGroupConsoleService` n'est injecté dans aucune Command, fixture ou autre classe — c'est du **dead code complet**.
- → **TODO** : supprimer `FixtureGroupConsoleService` (cross-référence DC).

**Finding 2 — Visibilité `protected` sur les propriétés de dépendance**
- Plusieurs services déclarent leurs dépendances `protected` au lieu de `private` :
  | Classe | Props `protected` |
  |---|---|
  | `MembershipService` | `$em`, `$registration_duration`, `$registration_every_civil_year`, `$cycle_type`, `$use_fly_and_fixed`, `$fly_and_fixed_entity_flying` (6) |
  | `TimeLogService` | `$em`, `$requestStack` (2) |
  | `ShiftFreeLogService` | `$em`, `$requestStack` (2) |
  | `PeriodPositionFreeLogService` | `$em`, `$requestStack` (2) |
  | `OpeningHourService` | `$em` (1) |
  | `OpeningHourKindService` | `$em` (1) |
- Aucune de ces classes n'est sous-classée dans le projet.
- La visibilité `protected` sans sous-classe est une fuite d'encapsulation : un futur sous-classe pourrait réassigner ces dépendances sans passer par le constructeur.
- → **TODO** : passer toutes ces propriétés en `private`.

**Finding 3 — EventListeners : même pattern `protected` généralisé**
- L'ensemble des 10+ EventListeners (`CodeEventListener`, `EmailingEventListener`, `TimeLogEventListener`, `CommissionEventListener`, `MattermostEventListener`, `ShiftFreeLogEventListener`, `PeriodPositionFreeLogEventListener`, `HelloassoEventListener`, `OidcFirewallListener`) déclare leurs dépendances `protected`.
- Aucun n'est sous-classé.
- Exception notable : `EmailingEventListener` mélange les styles — `$swipeCardHelper` est `private SwipeCard` (PHP 7.4 typé), tous les autres sont `protected` non typés. Incohérence sans risque runtime mais significative.
- → **TODO** : homogénéiser en `private` typé (PHP 7.4 typed properties).

**Verdict** :
| Risque | Sévérité | Impact runtime actuel |
|---|---|---|
| Setter post-construction (`FixtureGroupConsoleService`) | 🟢 Nul (dead code) | Aucun — jamais appelé |
| `protected` sur dépendances (services) | 🟡 Faible | Aucun — pas de sous-classe |
| `protected` sur dépendances (listeners) | 🟡 Faible | Aucun — pas de sous-classe |

Aucun état mutable actif inter-requêtes dans le stack PHP-FPM. Les antipatterns relevés sont des dettes de conception, pas des bugs.

