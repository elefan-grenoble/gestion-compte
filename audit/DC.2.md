# DC.2 — Vérification manuelle des call sites à risque

- [x] **DC.2** — Vérification manuelle des call sites à risque

Pour `ShiftBookedEvent($fromAdmin)` : grep des appelants pour confirmer que l'argument n'est jamais passé. Note le résultat ici.

**Findings :**

---

<a id="DC.2-1"></a>
### 1. `ShiftBookedEvent($fromAdmin)` — BUG silencieux, PAS du dead code (🟠)

**Situation réelle (inverse de ce que Rector suggère) :**
- Le constructeur `__construct(Shift $shift, bool $fromAdmin)` reçoit `$fromAdmin` mais **ne l'assigne jamais** — la ligne `$this->fromAdmin = $fromAdmin;` est absente du corps.
- Les appelants passent l'argument de façon intentionnelle :
  | Fichier | Ligne | Valeur | Contexte |
  |---------|-------|--------|---------|
  | `ShiftController.php` | 179 | `false` | booking utilisateur (route admin) |
  | `ShiftController.php` | 235 | `true` | booking admin |
  | `ShiftController.php` | 518 | `false` | booking utilisateur (route self-service) |
- `isFromAdmin()` existe et retourne `$this->fromAdmin` — mais cette propriété est toujours non-initialisée → retourne toujours `null`.
- `TimeLogEventListener::onShiftBooked()` et `EmailingEventListener::onShiftBooked()` : aucun des deux n'appelle `isFromAdmin()`. **Le bug est silencieux** : les listeners n'ont actuellement aucun comportement conditionnel selon l'origine admin/user.

**Classification** : bug (assignment manquant dans le constructeur), pas dead code.
**Action recommandée** : ajouter `$this->fromAdmin = $fromAdmin;` dans le constructeur, OU supprimer le paramètre ET la méthode si la distinction admin/user n'est réellement jamais exploitée. À décider selon le comportement voulu.
→ **SYN.2** (TODO, catégorie bugs, effort XS)

---

<a id="DC.2-2"></a>
### 2. `App\Helper\Html2Pdf($container)` — classe entière dead code (🟠)

- `$container` est assigné dans le constructeur (`$this->container = $container`) mais `$this->container` n'est jamais lu dans aucune méthode de la classe.
- La classe `App\Helper\Html2Pdf` n'est **jamais importée, jamais autowirée, jamais instanciée** dans le projet. La seule référence `Html2Pdf` dans `MembershipController.php` est un `use Spipu\Html2Pdf\Tag\Html\U` (tag twig de la lib tierce, sans rapport).
- L'`import` `Container` et la propriété `$container` sont donc inutiles par voie de conséquence.

**Classification** : classe entière dead code, sûre à supprimer.
→ **SYN.2** (TODO, catégorie dead code, effort XS)

---

<a id="DC.2-3"></a>
### 3. `UserAdminType` + `UserWithBeneficiaryType` — constructeurs délégants (✅ sûrs)

Les deux étendent `UserType` et leur constructeur ne fait que `parent::__construct($tokenStorage)` sans aucune logique propre. Rector (`RemoveParentDelegatingConstructorRector`) peut les supprimer — PHP héritera automatiquement du constructeur parent.

**Classification** : dead code cosmétique, sûr à supprimer. Aucun appelant à vérifier.

---

<a id="DC.2-4"></a>
### 4. `AuthenticationSuccessHandler` — `return;` terminal (✅ sûr)

`onAuthenticationSuccess()` se termine par `return;` après un `return new RedirectResponse(...)` dans un `if`. Le `return;` final est redondant (fin de fonction, comportement identique). Supprimable sans impact.

**Note** : la méthode `onAuthenticationSuccess()` implémente `AuthenticationSuccessHandlerInterface` qui exige un retour `Response`. Quand `$target` est absent, la méthode retourne implicitement `null` — ce qui violerait l'interface. Bug potentiel secondaire (cf. EXTRA).

**Classification** : cosmétique sûr pour le `return;`. Le retour null reste un sujet distinct.
→ **EXTRA** : ajouter un finding sur le retour null implicite (violation d'interface).

---

<a id="DC.2-5"></a>
### 5. `CommissionEventListener` — `setOwn(null)` et `$container` inutilisé (✅ sûr)

- `$beneficiary->setOwn(null)` au `onLeave()` (ligne 38) : `setOwn(Commission $own = null)` a `null` comme valeur par défaut. Rector (`RemoveNullArgOnNullDefaultParamRector`) simplifie en `$beneficiary->setOwn()`. Cosmétique, sûr.
- Bonus : `Container $container` est reçu dans le constructeur, assigné à `$this->container`, mais jamais lu dans aucune méthode de la classe. Dead property.

**Classification** : cosmétique sûr. Le `Container $container` est un candidat supplémentaire pour DC.3 (dead properties via dead-code-detector).

---

### Résumé DC.2

| Item | Classification | Action | Effort |
|------|---------------|--------|--------|
| `ShiftBookedEvent.$fromAdmin` | 🟠 Bug silencieux | Ajouter assignment ou supprimer param+méthode | XS |
| `App\Helper\Html2Pdf` | 🟠 Classe entière dead | Supprimer la classe | XS |
| `UserAdminType` constructeur | ✅ Dead cosmétique | Rector safe | XS |
| `UserWithBeneficiaryType` constructeur | ✅ Dead cosmétique | Rector safe | XS |
| `AuthenticationSuccessHandler` return | ✅ Dead cosmétique | Rector safe | XS |
| `CommissionEventListener` setOwn(null) | ✅ Dead cosmétique | Rector safe | XS |
| `CommissionEventListener.$container` | 🟡 Dead property | Supprimer + retirer dépendance Container | XS |

