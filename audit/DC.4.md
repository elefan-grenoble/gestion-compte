# DC.4 — Consolider en TODO

- [x] **DC.4** — Consolider en TODO

Tous les findings DC.1 + DC.2 + DC.3 → section TODO finale, avec flag "sûr à supprimer" vs "à vérifier manuellement".

**Méthode** : re-run `rector-dead-code.php` (config temporaire `SetList::DEAD_CODE` explicite) sur PHP 8.1 → **17 fichiers / 12 fichiers**. Comparaison avec DC.1 (run initial PHP 7.4), DC.2 (vérifications manuelles), DC.3 (dead-code-detector). Note : le run DC.1 reportait 23 fichiers avec un rector.php différent — le run actuel (PHP 8.1, `SetList::DEAD_CODE` explicite) est la référence pour cette consolidation.

---

<a id="DC.4-A"></a>
### A — Sûr à supprimer : Rector automatise (run `rector-dead-code.php`)

Ces changements sont **non-ambigus, cosmétiques ou mécaniquement vérifiés**. Un seul `vendor/bin/rector process src --config rector-dead-code.php` les applique tous, sauf les items B et C ci-dessous.

**A.1 — Docblocks @param / @return redondants** (`RemoveUselessParamTagRector` / `RemoveUselessReturnTagRector`)

| Fichier | Tags supprimés | Effort |
|---------|---------------|--------|
| `Repository/ShiftFreeLogRepository.php` | `@param` ×2 | XS |
| `Repository/ShiftRepository.php` | `@param` ×3 | XS |
| `Security/KeycloakAuthenticator.php` | `@param` + `@return` ×4 | XS |
| `Service/FixtureGroupConsoleService.php` | `@return` | XS |
| `Service/MailerService.php` | `@param` + `@return` ×3 | XS |
| `Service/MembershipService.php` | `@param` ×6, `@return` ×1 | XS |
| `Service/Picture/BasePathPicture.php` | `@param` ×2 | XS |
| `Service/ShiftService.php` | `@param` ×6 | XS |
| `Service/TimeLogService.php` | `@param` ×12 + trailing spaces | XS |

**A.2 — Variables intermédiaires inutiles** (`SimplifyUselessVariableRector`)

| Fichier | Méthode | Detail |
|---------|---------|--------|
| `Service/BeneficiaryService.php` | `getAutocompleteLabel()` | `$label .=` simplifié en return inline |
| `Service/TimeLogService.php` | `initCurrentCycleBeginningTimeLog()` | `$log = …; return $log;` → `return …;` |
| `Twig/Extension/AppExtension.php` | `markdown()` | `$html = …; return $html;` → `return …;` |
| `Security/SwipeCard::generateCode()` (DC.1) | — | variable locale inutile |

**A.3 — Appels null redondants** (`RemoveNullArgOnNullDefaultParamRector`)

| Fichier | Méthode | Detail |
|---------|---------|--------|
| `Repository/ShiftRepository.php` | `findInProgressAndUpcomingShiftsForMembership()` | `findShiftsForBeneficiaries($m->getBeneficiaries(), $now, null)` → sans `null` |
| `Security/KeycloakAuthenticator.php` | `updateCoMembership()` | `$oldMembership->setMainBeneficiary(null)` → `setMainBeneficiary()` |
| `EventListener/CommissionEventListener.php` (DC.1/DC.2) | `onLeave()` | `$beneficiary->setOwn(null)` → `setOwn()` |

**A.4 — Variable locale inutilisée** (`RemoveUnusedVariableAssignRector`)

| Fichier | Méthode | Detail |
|---------|---------|--------|
| `Security/UserVoter.php` | `canView()` | `$user = $token->getUser()` assigné mais jamais lu dans cette méthode |

**A.5 — Paramètre de constructeur + propriété inutilisés** (`RemoveUnusedConstructorParamRector` + `RemoveUnusedPrivatePropertyRector`)

| Fichier | Detail |
|---------|--------|
| `Service/PeriodService.php` | `EntityManagerInterface $em` reçu et assigné (`$this->em = $em`) mais jamais lu — ni dans les méthodes, ni ailleurs dans la classe. Rector retire le param et la property. **Sûr.** |
| `EventListener/CommissionEventListener.php` (DC.2) | `Container $container` — même pattern, dead property |
| `EventListener/CodeEventListener.php` (AP.4) | `Container $container` — injecté, assigné à `$this->container`, jamais lu dans aucune méthode. Nouveau finding identifié en AP.4. |

**A.6 — Constructeurs délégants** (`RemoveParentDelegatingConstructorRector` — DC.1/DC.2)

Ces constructeurs ne font que `parent::__construct($param)` sans logique propre. PHP hérite automatiquement du parent si le constructeur est absent.

| Fichier |
|---------|
| `Form/UserAdminType.php` |
| `Form/UserWithBeneficiaryType.php` |

**A.7 — Constructeurs vides d'entités** (DC.2/D.2)

6 entités ont un `__construct() {}` totalement vide (confirmé visuellement). Sans propriétés à initialiser, PHP n'en a pas besoin.

| Entité |
|--------|
| `Entity/Code.php` |
| `Entity/DynamicContent.php` |
| `Entity/EmailTemplate.php` |
| `Entity/PeriodPosition.php` |
| `Entity/ProcessUpdate.php` |
| `Entity/Service.php` |

**A.8 — Switch cases dupliqués** (`RemoveDuplicatedCaseInSwitchRector`) — **vérifiés manuellement, sûrs**

| Fichier | Detail |
|---------|--------|
| `Security/SwipeCardVoter.php` | `case DISABLE:` et `case ENABLE:` ont des corps **identiques** (tous deux retournent `$this->own($swipeCard, $user)`). Rector merge en fallthrough. Sûr. |
| `Security/SwipeCardVoter.php` | `canPair(SwipeCard $swipeCard, User $user)` — `$swipeCard` n'est jamais utilisé dans le corps de la méthode (seul `$user` est exploité). Rector retire le param. Sûr. |
| `Security/MembershipVoter.php` | `case self::FLYING:` est seul et retourne `$this->canEdit()` — identique au groupe `FREEZE/OPEN/CLOSE/ROLE_ADD/ROLE_REMOVE/EDIT` qui précède. Rector le merge dans le groupe. Sûr. |

**A.9 — Méthode privée morte** (`RemoveUnusedPrivateMethodRector` — DC.2/D.2)

| Fichier | Méthode | Detail |
|---------|---------|--------|
| `Security/CodeVoter.php` | `isLocationOk()` | Commentaire explicite `// DUPLICATED from UserVoter`. La méthode n'est jamais appelée en interne — le voter délègue à `PlaceIP::isLocationOk()`. Rector la supprime. |
| `Security/CodeVoter.php` | `canDelete(Code $code, User $user)` | Les deux paramètres sont inutilisés dans le corps (qui retourne `false`). Rector retire les params. Corps à 1 ligne. |

---

<a id="DC.4-B"></a>
### B — Sûr à supprimer manuellement (grep confirmé, pas de règle Rector)

Ces items nécessitent une suppression manuelle de fichier ou de méthode, non couverts par le run Rector.

**B.1 — Classe entière morte (DC.2)**

| Fichier | Detail |
|---------|--------|
| `Helper/Html2Pdf.php` | Jamais importée, jamais autowirée, jamais instanciée dans `src/`. L'`use Spipu\Html2Pdf\Tag\Html\U` dans `MembershipController` n'a aucun lien. La propriété `$container` injected dans le constructeur est inutilisée (dead property bonus). **Supprimer le fichier entier.** |

**B.2 — Méthodes privées mortes (DC.2/D.2/EXTRA)**

| Fichier | Méthode | Detail |
|---------|---------|--------|
| `Controller/AmbassadorController.php` | `createNoteDeleteForm()` | Définie mais aucun appel `$this->createNoteDeleteForm` dans le fichier. Confirmé par grep. |
| `Controller/BeneficiaryController.php` | `getErrorMessages()` | Méthode private. Seul appel : `$this->getErrorMessages($child)` dans sa propre récursion. Aucun appelant externe. Rector ne la détecte pas (récursion self-référente crée un faux positif de vivacité). |

---

<a id="DC.4-C"></a>
### C — À vérifier manuellement (risque sémantique ou faux positifs multi-instance)

**C.1 — ⚠️ Risque d'escalade d'autorisation — ShiftVoter** (`RemoveDuplicatedCaseInSwitchRector`)

Rector veut merger `case self::VALIDATE:` dans `case self::LOCK:`. **Ce n'est PAS un vrai doublon** :
- VALIDATE actuel : `if (admin) return true; else return false;` (non-admin = toujours refusé)
- LOCK : `if (admin) return true; else return $this->canAccept($shift, $user);` (non-admin = logique d'acceptation)

Après merge Rector, VALIDATE retournerait `canAccept()` pour les non-admins — **élargissement potentiel de l'autorisation**. À vérifier avec les mainteneurs avant d'appliquer.

**C.2 — Repository methods "probablement mortes" (DC.3)**

Non appelées dans `src/` ni `templates/` (grep confirmé). Peuvent être utilisées par une instance externe ou une intégration API. Nécessitent tracking runtime (RT.1-2) avant suppression définitive.

| Méthode | Fichier |
|---------|---------|
| `findFromAutoComplete()` | `Repository/BeneficiaryRepository.php` |
| `findByString()` | `Repository/CommissionRepository.php` |
| `findAllDisplayedHome()` | `Repository/EventRepository.php` |
| `findByBeneficiary()` | `Repository/PeriodPositionRepository.php` |
| `findFirst()` | `Repository/ShiftRepository.php` |
| `findReservedBefore()` | `Repository/ShiftRepository.php` |

**C.3 — Service methods "probablement mortes" (DC.3)**

| Méthode | Fichier | Note |
|---------|---------|------|
| `getAutocompleteBeneficiaries()` | `Service/BeneficiaryService.php` | Nom corrélé à `findFromAutoComplete()` (C.2) |
| `getAutocompleteMemberships()` | `Service/MembershipService.php` | Idem |
| `isClosed()` | `Service/OpeningHourService.php` | Templates n'utilisent que `isOpen()` |
| `setInput()` | `Service/FixtureGroupConsoleService.php` | Aucun appelant trouvé |

**C.4 — ShiftService methods à vérification approfondie requise (DC.3)**

`canBookExtraShiftBucket`, `getBeneficiariesWhoCanBook`, `getBeneficiariesWhoCanBookForCycle`, `getFirstBookable`, `getShiftsForBeneficiary`, `removeEmptyShift` — probables faux positifs liés à la cascade controllers/Twig du DC.3, mais non confirmés par grep simple. Vérification manuelle cas par cas recommandée avant toute suppression.

---

<a id="DC.4-D"></a>
### D — Bugs déguisés en dead code (ne pas supprimer — corriger)

**D.1 — Assignment manquant (DC.2)**

`Event/ShiftBookedEvent.php` : le constructeur reçoit `bool $fromAdmin` mais ne l'assigne jamais (`$this->fromAdmin = $fromAdmin;` est absent). `isFromAdmin()` retourne toujours `null`. Les 3 appelants dans `ShiftController` passent `true`/`false` intentionnellement. **Fix** : ajouter l'assignment — OU supprimer param + getter si la distinction admin/user n'est jamais exploitée par les listeners.

**D.2 — Violation d'interface (EXTRA/DC.2)**

`Security/AuthenticationSuccessHandler::onAuthenticationSuccess()` : quand `$target` est absent, la méthode retourne `null` implicitement. `AuthenticationSuccessHandlerInterface` exige un `Response`. **Fix** : ajouter un fallback (`new RedirectResponse('/')` ou exception).

---

### Résumé consolidé

| Catégorie | Items | Effort total | Risque |
|-----------|-------|-------------|--------|
| A — Rector safe | 9 groupes, ~20 fichiers | XS (1 commande) | Nul |
| B — Manuel safe | 3 suppressions | XS | Nul |
| C — À vérifier | 4 groupes, ~16 méthodes | M | Moyen (multi-instance, sécurité) |
| D — Bugs | 2 correctifs | XS | Bas |

---

