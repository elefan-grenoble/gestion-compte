# AP.6 — Couplage Request → Service

- [x] **AP.6** — Couplage Request → Service

`grep -rn "Request \$request" src/Service/` — services qui dépendent de HTTP directement → TODO.

**Périmètre** : aucun service n'injecte `Request $request` directement (injection de scope obsolète depuis SF3). Les 3 cas identifiés injectent `RequestStack $requestStack` (pattern correct SF4) pour appeler `getCurrentRequest()` dans leurs méthodes.

---

### Services avec `RequestStack` — 3 fichiers (🟡)

| Service | Méthode qui accède au Request | Usage |
|---------|------------------------------|-------|
| `ShiftFreeLogService` | `initShiftFreeLog()` ligne 46 | `$request->get('_route')` → `ShiftFreeLog::requestRoute` |
| `TimeLogService` | `initTimeLog()` ligne 53 | `$request->get('_route')` → `TimeLog::requestRoute` |
| `PeriodPositionFreeLogService` | `initPeriodPositionFreeLog()` ligne 45 | `$request->get('_route')` → `PeriodPositionFreeLog::requestRoute` |

**Intention** : tracer dans les logs d'audit quelle route HTTP a déclenché la création de l'entrée. Le champ `requestRoute` est affiché en clair dans les vues admin (`templates/member/_partial/shift_free_logs.html.twig:56`, `period_position_free_logs.html.twig:52`, `timelog/_partial/table.html.twig:46`). L'intention est légitime et le `RequestStack` est l'injection canonique pour accéder à la requête courante dans un service SF4.

**Couplage implicite** : ces services deviennent HTTP-aware alors qu'ils n'ont pas de raison structurelle de l'être. Cela complique les tests unitaires (nécessité de mocker le `RequestStack` ou de pousser une requête factice dans la pile).

---

### Bug latent — 2 services sans null guard (🟠)

`TimeLogService::initTimeLog()` protège correctement :
```php
if ($request) {
    $log->setRequestRoute($request->get('_route'));
}
```

`ShiftFreeLogService::initShiftFreeLog()` (ligne 46) et `PeriodPositionFreeLogService::initPeriodPositionFreeLog()` (ligne 45) n'ont **pas ce garde** :
```php
$log->setRequestRoute($request->get('_route'));  // $request peut être null (CLI)
```

`getCurrentRequest()` retourne `null` hors contexte HTTP (commandes CLI, workers long-running). En l'état, aucun appel CLI de ces deux services n'existe — le bug est latent. Mais si un `InitShiftFreeLogCommand` ou `FixPeriodPositionCommand` venait à les utiliser, cela produirait une `BadMethodCallException` (appel sur `null`).

**Contexte des appelants actuels** :
- `ShiftFreeLogEventListener` et `PeriodPositionFreeLogEventListener` appellent ces services via le service locator (AP.4 catégorie C.3) — toujours depuis un contexte HTTP → pas de crash.
- `InitTimeLogCommand` et `FixTimeLogCommand` utilisent `TimeLogService` (qui est protégé) → OK.

---

### Patterns corrects

**Option A — Paramètre explicite (recommandé)** : passer `?string $routeName = null` aux méthodes `init*()`. L'appelant (controller ou event listener) extrait lui-même `$request->get('_route')` et le passe. Les services deviennent entièrement HTTP-agnostiques et testables sans mock `RequestStack`.

**Option B — Null guard minimal** : ajouter `if ($request)` dans `ShiftFreeLogService` et `PeriodPositionFreeLogService`. Corrige le bug latent sans refactorer l'interface. Services restent HTTP-aware.

---

### Cross-référence AP.4 — Voters et Helper

Les accès `request_stack` via service locator dans `ShiftVoter`, `MembershipVoter`, `CodeVoter` et `Helper/PlaceIP` sont du même registre mais classifiés en AP.4 (catégorie C.3). Non redoublés ici.

---

### Résumé

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🟠 Bug latent | `ShiftFreeLogService` + `PeriodPositionFreeLogService` : appel sans null guard | XS (Option B) |
| 🟡 Couplage | 3 services HTTP-aware via `RequestStack` | S (Option A) / XS (Option B) |
| — Cross-ref | Voters + PlaceIP : `request_stack` via service locator | Voir AP.4 |

→ **TODO SYN.2** — bug latent : `ShiftFreeLogService` et `PeriodPositionFreeLogService`, ajouter null guard (XS, effort minimal) ; refactoring Option A à envisager lors de la migration SF5 (passage des services à injection pure).

