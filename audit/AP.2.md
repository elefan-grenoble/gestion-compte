# AP.2 — Instanciations directes dans les controllers

- [x] **AP.2** — Instanciations directes dans les controllers

`grep -rn "new [A-Z]" src/Controller/` (hors Response, RedirectResponse, JsonResponse). Services/entités instanciés au lieu d'être injectés → TODO.

**Méthode** : grep complet sur `src/Controller/`, puis filtrage des patterns légitimes (HTTP responses, domain events, value objects `DateTime`/`Address`/`ArrayCollection`, entités dans les actions CRUD de création où le form binding est standard, contraintes Symfony).

**Patterns légitimes (non listés comme antipatterns)** :
- `Response`, `RedirectResponse`, `JsonResponse`, `StreamedResponse` — exclus par définition de l'item
- Domain events (`*Event`) — créés inline avant dispatch, pattern standard Symfony
- `DateTime`, `Address`, `ArrayCollection`, `Email` — value objects
- `new EntityName()` dans les actions `newAction`/`createAction` qui initialisent l'objet pour le formulaire — pattern form binding standard
- `BeneficiaryCanHost`, `FormEvent` — usage constraint/form standard Symfony

---

<a id="AP.2-1"></a>
### 1. `new Application($kernel)` — console runner dans la couche web (🟠)

| Fichier | Ligne | Commande lancée |
|---------|-------|----------------|
| `AdminController.php` | 284 | `app:import:users` (import CSV d'adhérents) |
| `AdminPeriodController.php` | 444 | `app:shift:generate` (génération de créneaux) |

Les deux actions instancient `new Application($kernel)` + `new ArrayInput([...])` + `new BufferedOutput()` pour exécuter une commande console depuis un controller web. Ce pattern couple fortement la couche HTTP à la couche CLI et empêche de tester la logique métier indépendamment du transport.

`AdminPeriodController::generateShiftsForDateAction` est déjà référencé en AP.1 (finding 2f). `AdminController::importAction` est un cas identique.

**Pattern correct** : extraire la logique dans un service (`ShiftGeneratorService`, `UserImportService`) appelé à la fois par le controller et par la commande. Aucun `Application` dans le controller.

→ **TODO SYN.2** — effort M par cas (2 cas)

---

<a id="AP.2-2"></a>
### 2. `new UsernamePasswordToken(...)` — fabrication manuelle de token d'authentification (🟠)

| Fichier | Ligne | Contexte |
|---------|-------|---------|
| `SwipeCardController.php` | 68 | Login passwordless par badge — `$token = new UsernamePasswordToken($user, $user->getPassword(), "main", $user->getRoles())` |
| `CodeController.php` | 252 | Impersonation temporaire pour confirmation de changement de code — `new UsernamePasswordToken($current_app_user, null, "main", $current_app_user->getRoles())` |

Les deux cas injectent un token directement dans `security.token_storage` en contournant le flux d'authentification Symfony normal (pas de `LoginManager`, pas de session guard, pas d'événement `security.interactive_login` correctement typé).

**Problèmes spécifiques** :
- `SwipeCardController` : utilise `$user->getPassword()` comme credentials du token — en SF4 ce paramètre est le "credentials" (non-null), pas le mot de passe haché, ce qui est ambigu et source de confusion.
- `SwipeCardController` dispatch bien `security.interactive_login` via `InteractiveLoginEvent` — mais le type de l'event est une string (ancienne API), pas la constante.
- `CodeController` : l'impersonation n'est jamais révoquée explicitement dans le code lisible — le token du contexte précédent est sauvegardé dans `$previousToken` mais aucune restauration trouvée dans le scope visible.

**Pattern correct en SF5+** : `LoginManager` ou `UserAuthenticatorInterface` (natifs). En SF4 : la voie recommandée est `security.token_storage` + event, mais via le `TokenStorageInterface` injecté, pas via `$this->get(...)`.

→ **SEC** section à croiser (SEC.1) + **TODO SYN.2** — effort M (sécurité, risque de régression auth)

---

<a id="AP.2-3"></a>
### 3. `new QrCode($url)` — bibliothèque QR dans le controller (🟡)

`SwipeCardController::_getQr($url)` (ligne 280) instancie `Endroid\QrCode\QrCode` directement, configure 6 paramètres et retourne un data URI base64. Cette logique (choix de taille, correction d'erreur, couleurs, encoding) n'est pas configurable et est non testable isolément.

**Pattern correct** : un `QrCodeService` injecté avec les paramètres dans la config. La méthode de controller appelle `$this->qrCodeService->generateDataUri($url)`.

→ **TODO SYN.2** — effort S

---

<a id="AP.2-4"></a>
### 4. `new Markdown` — parser Markdown dans le controller (🟡)

`MailController.php:148` instancie `Michelf\Markdown` directement, configure `$parser->hard_wrap = true`, et transforme le contenu. Cette configuration est non-centralisée — si un autre endroit utilise Markdown, les options divergeront.

Note : `Michelf\Markdown` n'est pas autowirable par défaut (pas de tag Symfony). Un alias de service dans `services.yaml` avec `arguments: { hard_wrap: true }` résoudrait l'injection.

→ **TODO SYN.2** — effort XS

---

<a id="AP.2-5"></a>
### 5. `new GravatarHelper(new GravatarApi())` — service externe sans injection (🟡)

`ApiController.php:111` : instanciation chaînée d'un service tiers déjà partiellement abandonné (DEP.2 — `ornicar/gravatar-bundle`). Les imports `GravatarApi`/`GravatarHelper` dans `AdminController` et `RegistrationsController` sont déjà identifiés comme morts (DEP.2). Ici, c'est l'unique usage réel en PHP.

Si le bundle est remplacé par une extension Twig custom (effort S selon DEP.2), ce contrôleur bénéficiera de la même refonte.

→ **TODO SYN.2** — résolu en même temps que le remplacement `ornicar/gravatar-bundle` (DEP.2)

---

<a id="AP.2-6"></a>
### 6. `new Paginator($qb)` — infrastructure répétée dans 6 controllers (🟡)

| Controller | Occurrences |
|-----------|-------------|
| `AmbassadorController.php` | 4 |
| `AdminController.php` | 1 |
| `AdminEventController.php` | 1 |
| `AdminMembershipShiftExemptionController.php` | 1 |
| `AdminPeriodPositionFreeLogController.php` | 1 |
| `AdminShiftFreeLogController.php` | 1 |

9 occurrences de `new Paginator($qb)` en tout. La configuration est identique à chaque fois (pas de paramètre `fetchJoinCollection` explicite). Un trait ou une méthode helper `paginate(QueryBuilder $qb): Paginator` dans un `AbstractAppController` éliminerait le couplage à la classe Doctrine.

→ **TODO SYN.2** — effort XS (1 trait, 9 appels à remplacer)

---

<a id="AP.2-7"></a>
### 7. `new ShiftBucket()` dupliqué entre controllers (🟡)

`ShiftController.php:692` et `WidgetController.php:45` créent tous deux des `ShiftBucket` dans des boucles identiques. Le DTO est instancié inline dans chaque controller au lieu de passer par un service de construction du planning. Ce point est lié à AP.1 (logique métier dans les controllers).

→ **TODO SYN.2** — couvert par la refonte générale des fat controllers (AP.1)

---

### Résumé

| Gravité | Finding | Controllers | Effort |
|---------|---------|-------------|--------|
| 🟠 | `new Application($kernel)` — console runner en HTTP | 2 | M par cas |
| 🟠 | `new UsernamePasswordToken(...)` — fabrication token auth | 2 | M (sécurité) |
| 🟡 | `new Paginator($qb)` — infrastructure répétée | 6 | XS |
| 🟡 | `new QrCode($url)` — lib QR non injectée | 1 | S |
| 🟡 | `new Markdown` — parser non injecté | 1 | XS |
| 🟡 | `new GravatarHelper(new GravatarApi())` — résolu via DEP.2 | 1 | XS |

