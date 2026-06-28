# TC.1 — Rapport de couverture

- [x] **TC.1** — Rapport de couverture

`docker compose exec -T php composer test-coverage 2>&1`. % global et par namespace. Résultat → TODO (zones non couvertes).

**Méthode** : `composer test-coverage` génère uniquement du HTML (via xdebug) ; or xdebug **n'est pas installé** dans le container PHP 8.1 actuel (`.docker/Dockerfile`). Couverture obtenue via `pcov` installé temporairement : `php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text`. 350 tests verts, aucune régression.

### Résultat global

| Métrique | Couvert | Total | % |
|----------|---------|-------|---|
| Méthodes | 621 | 1 783 | **34.8%** |
| Lignes   | 3 405 | 12 474 | **27.3%** |

### Couverture par namespace

| Namespace | Méthodes | Lignes |
|-----------|----------|--------|
| `App\Command` | 54.8% (46/84) | 28.3% (369/1 305) |
| `App\Controller` | 13.7% (43/313) | 18.4% (918/4 992) |
| `App\Entity` | 48.1% (377/784) | 46.3% (680/1 470) |
| `App\Event` | 2.7% (2/74) | 2.0% (2/99) |
| `App\EventListener` | 10.0% (7/70) | 6.1% (48/787) |
| `App\Form` | 18.4% (26/141) | 17.6% (172/980) |
| `App\Helper` | 10.0% (1/10) | 2.8% (1/36) |
| `App\Monolog` | 60.0% (3/5) | 88.9% (16/18) |
| `App\Providers` | 5.9% (1/17) | 1.6% (2/125) |
| `App\Repository` | 27.5% (19/69) | 41.7% (303/727) |
| `App\Security` | 27.0% (17/63) | 20.1% (115/573) |
| `App\Service` | 52.9% (55/104) | 56.7% (656/1 156) |
| `App\Twig` | 52.3% (23/44) | 65.7% (115/175) |
| `App\Validator` | 20.0% (1/5) | 25.8% (8/31) |

### Zones non couvertes — priorité haute (0% lignes, classe substantielle)

| Classe | Lignes | Priorité |
|--------|--------|----------|
| `App\EventListener\EmailingEventListener` | 410 | 🔴 Critique |
| `App\Controller\ShiftController` *(4.8%)* | 439 | 🔴 Critique |
| `App\Controller\AmbassadorController` | 185 | 🔴 |
| `App\EventListener\TimeLogEventListener` | 146 | 🔴 |
| `App\Controller\UserController` | 159 | 🔴 |
| `App\Controller\SwipeCardController` | 154 | 🔴 |
| `App\Security\KeycloakAuthenticator` *(2.7%)* | 221 | 🔴 |
| `App\Controller\MailController` | 117 | 🟠 |
| `App\Controller\HelloassoController` | 116 | 🟠 |
| `App\Controller\BeneficiaryController` | 115 | 🟠 |
| `App\Controller\AdminMembershipShiftExemptionController` | 113 | 🟠 |
| `App\EventListener\HelloassoEventListener` | 65 | 🟠 |
| `App\Repository\MembershipRepository` | 67 | 🟠 |
| `App\Providers\Helloasso\HelloassoClient` | 51 | 🟠 |
| `App\Providers\Igloohome\IgloohomeClient` | 26 | 🟡 |

### Observation sur la structure de couverture

- `App\Event` à 2% : les classes Event sont des data-carriers (constructeur + getters) ; les 2% couverts sont les champs accédés par les tests indirects. Faible valeur à tester unitairement.
- `App\EventListener` à 6.1% : **vrai angle mort**. `EmailingEventListener` (410L) et `TimeLogEventListener` (146L) orchestrent l'envoi de mails et la gestion des logs de temps — critique pour l'intégrité des données.
- `App\Controller` à 18.4% : les tests fonctionnels existants couvrent peu de routes ; `ShiftController` (439L, 4.8%) et `BookingController` (366L, 13.7%) sont les classes les plus massives sans couverture significative.
- `App\Providers` à 1.6% : `HelloassoClient` et `IgloohomeClient` (intégrations tierces) ne sont pas testés. Justifiable par le besoin de mocks HTTP, mais risqué pour les montées de version.
- `App\Service` à 56.7% : le namespace le mieux couvert avec `App\Monolog`. `SearchUserFormHelper` (558L) est couvert à 48% via les tests d'intégration.

### Finding TC.1 — Résumé

| Statut | Finding |
|--------|---------|
| 🔴 Critique | Couverture globale à 27% lignes sur 12 474 lignes de code source |
| 🔴 Critique | `EmailingEventListener` (410L) et `ShiftController` (439L) à <5% — chemins métier critiques sans filet |
| 🔴 Critique | `KeycloakAuthenticator` (221L) à 2.7% — authentification quasi non testée |
| 🟠 Majeur | `App\EventListener` (787L) à 6.1% — event-driven logic non testée |
| 🟠 Majeur | `App\Providers` (125L) à 1.6% — intégrations Helloasso et Igloohome sans test |
| 🟡 Moyen | xdebug absent du Dockerfile PHP 8.1 — `composer test-coverage` ne fonctionne pas en l'état |
| 🔵 Info | `App\Event` à 2% : normal pour des data-carriers, pas une priorité |

