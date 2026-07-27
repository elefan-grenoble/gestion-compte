# AP.7 — Event listeners surchargés

- [x] **AP.7** — Event listeners surchargés

Lire `src/EventListener/`. Listeners > 50 lignes de logique métier → TODO.

**Périmètre** : 15 fichiers dans `src/EventListener/`, 1 864 lignes au total. Inventaire par taille :

| Fichier | Lignes | Statut |
|---------|--------|--------|
| `EmailingEventListener.php` | 713 | 🔴 Bloaté — God listener |
| `TimeLogEventListener.php` | 420 | 🟠 Bloaté — logique métier embarquée |
| `HelloassoEventListener.php` | 142 | 🟡 Logique enregistrement dans listener |
| `SetFirstPasswordListener.php` | 83 | 🟡 3 types d'événements mixés |
| `BeneficiaryInitializationSubscriber.php` | 82 | ✅ Acceptable |
| `OidcFirewallListener.php` | 76 | 🟡 URLs codées en dur |
| `MattermostEventListener.php` | 61 | ✅ Acceptable |
| `CommissionEventListener.php` | 54 | 🟡 onJoin() vide |
| *Autres (7 fichiers)* | ≤ 43 | ✅ OK |

---

<a id="AP.7-1"></a>
### 1. `EmailingEventListener` — God listener, 713 lignes (🔴)

Centralise l'envoi de 13 types d'emails différents dans une seule classe : `onAnonymousBeneficiaryCreated`, `onAnonymousBeneficiaryRecall`, `onBeneficiaryAdd`, `onMemberCreated`, `onHelloassoRegistrationSuccess`, `onHelloassoTooEarly`, `onShiftReserved`, `onShiftBooked`, `onShiftFreed`, `onShiftReminder`, `onShiftDeleted`, `onShiftAlerts`, `onMemberCycleStart`, `onMemberCycleHalf`, `onEventProxyCreated`, `onCodeNew`.

**Bug critique — `die()` en production (🔴)** : `onHelloassoTooEarly()` ligne 257 :
```php
} catch (\Exception $e) {
    die($e->getMessage());   // tue le process PHP
}
```
Un `Exception` lors de la construction de l'email (ex. template Twig manquant, SMTP non joignable) tue immédiatement le process PHP, sans log ni réponse HTTP propre. En production, cela se manifeste par une page blanche ou une erreur 500 sans trace.

**`strftime()` dépréciée PHP 8.1+ (🟡)** : utilisée à la ligne 277 (`strftime("%e %B", ...)`) et ligne 483 (`strftime("%A %e %B", ...)`). Dépréciée depuis PHP 8.1, supprimée en PHP 9. Le container tourne sur PHP 8.1 — des `E_DEPRECATED` sont émis silencieusement.

**`renderView()` dupliquée (🟡)** : méthode `renderView()` (L701–712) identique dans `EmailingEventListener` et `HelloassoEventListener` — même implémentation copy-pasteé pour déléguer au container Twig.

**Container injecté (AP.4 cross-ref)** : accès `$this->container->get('router')`, `get('twig')`, `get('App\Helper\SwipeCard')` inline dans les méthodes.

**Refactoring cible** : extraire des services dédiés (`ShiftEmailService`, `MemberEmailService`, `HelloassoEmailService`, `MiscEmailService`) avec injection directe de `MailerInterface`, `UrlGeneratorInterface`, `Environment` (Twig). Le listener ne fait que router les événements vers le bon service.

---

<a id="AP.7-2"></a>
### 2. `TimeLogEventListener` — Logique comptable embarquée, 420 lignes (🟠)

Contient la logique métier du bilan de cycle (cycle turn-over accounting). `createCycleBeginningLog()` (L310–374, 65 lignes) calcule : soustraction de la cotisation due, redistribution de l'excédent en épargne, compensation de l'épargne en cas de déficit (avec règles d'éligibilité : créneaux ratés, libérations tardives). Cette logique n'appartient pas à un listener.

**Chaine implicite listener→listener** : `onMemberCycleEnd()` dispatche lui-même `MemberCycleStartEvent` (L259–260) :
```php
$dispatcher = $this->container->get('event_dispatcher');
$dispatcher->dispatch(new MemberCycleStartEvent(...), MemberCycleStartEvent::NAME);
```
L'exécution de `onMemberCycleStart` dans `EmailingEventListener` dépend implicitement de l'ordre d'exécution des listeners — dépendance invisible sans lire le code.

**Container injecté (AP.4 cross-ref)** : `time_log_service`, `membership_service`, `event_dispatcher` accédés via `$this->container->get()`.

**Refactoring cible** : extraire `CycleAccountingService` (logique de bilan) avec injection directe de `TimeLogService` et `MembershipService`. Le listener délègue à ce service et dispatche `MemberCycleStartEvent` explicitement depuis le controller ou command déclencheur.

---

<a id="AP.7-3"></a>
### 3. `HelloassoEventListener` — Logique d'enregistrement dans le listener, 142 lignes (🟡)

`linkPaymentToUser()` (L95–141) crée une entité `Registration`, vérifie `canRegister()`, ajuste la date d'adhésion, rouvre un compte clôturé, et dispatche `HelloassoEvent::RE_REGISTRATION_SUCCESS` — tout dans le listener. Erreurs silencieuses : deux `throw new \LogicException` commentés (L60, L100) ; les cas d'erreur sont ignorés silencieusement.

**Container injecté (AP.4 cross-ref)** : `membership_service`, `event_dispatcher`.

**Refactoring cible** : extraire `HelloassoRegistrationService::linkPaymentToUser()`. Effort S.

---

<a id="AP.7-4"></a>
### 4. `SetFirstPasswordListener` — 3 types d'événements mixés, 83 lignes (🟡)

Combine trois responsabilités dans une classe : listener Doctrine `prePersist` (L44), listener FOS UserBundle `onPasswordChanged` (L59), listener Kernel `forcePasswordChange` (L67). Les imports `FilterResponseEvent` et `GetResponseEvent` (L14-15) sont des noms de classes supprimés en SF5 — marqueurs de code SF4 pré-migration.

---

<a id="AP.7-5"></a>
### 5. `OidcFirewallListener` — URLs hardcodées, 76 lignes (🟡)

Liste de 14 chemins d'URL hardcodés (L43–62) pour le contrôle d'accès OIDC. Aucune référence aux noms de routes Symfony — si une route est renommée, la protection ne suit pas. Concerne une fonctionnalité instance-specific (Scopeli utilise OIDC, Elefan non). PHPDoc erroné ligne 28–30 (`@param PeriodPositionFreedEvent`) — copie de l'autre listener.

---

<a id="AP.7-6"></a>
### 6. `CommissionEventListener.onJoin()` — Stub vide (🟡)

`onJoin()` (L49–52) n'a aucun corps : seulement `$this->logger->info("Commission Listener: onJoin")`. Code jamais implémenté. Cross-ref DC.1.

---

<a id="AP.7-7"></a>
### 7. `CodeEventListener.onCodeNew()` — Corps entièrement commenté (EXTRA)

```php
public function onCodeNew(CodeNewEvent $event) {
    $this->logger->info("Code Listener: onCodeNew");
    //  $code = $event->getCode();
    //  $display = $event->getDisplay();
}
```
Listener enregistré mais sans logique — candidat à la suppression ou au TODO d'implémentation.

---

### Résumé

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🔴 Bug critique | `EmailingEventListener::onHelloassoTooEarly()` L257 : `die()` en production | XS (remplacer par throw ou log) |
| 🔴 God listener | `EmailingEventListener` 713 lignes, 13 types d'emails | L (split en services) |
| 🟠 Logique métier | `TimeLogEventListener` 420 lignes, bilan de cycle | M (extraire CycleAccountingService) |
| 🟡 Dépréciation | `strftime()` PHP 8.1+ dans `EmailingEventListener` L277, L483 | XS |
| 🟡 Duplication | `renderView()` copieée dans `EmailingEventListener` + `HelloassoEventListener` | XS (trait ou service) |
| 🟡 Logique métier | `HelloassoEventListener::linkPaymentToUser()` | S (extraire service) |
| 🟡 Chaîne implicite | `TimeLogEventListener::onMemberCycleEnd()` dispatche `MemberCycleStartEvent` | À documenter |
| 🟡 URLs hardcodées | `OidcFirewallListener` 14 chemins d'URL hardcodés | S |
| 🟡 Code mort | `CommissionEventListener::onJoin()` vide, `CodeEventListener::onCodeNew()` commenté | XS (supprimer) |

→ **TODO AP.7.1** — `EmailingEventListener` L257 : remplacer `die($e->getMessage())` par `throw $e` ou logging + réponse propre. Priorité haute — peut tuer le process en production.

→ **TODO AP.7.2** — `EmailingEventListener` L277, L483 : remplacer `strftime()` dépréciée par `\IntlDateFormatter` ou `\DateTime::format()` + conversion locale explicite.

→ **TODO AP.7.3** — Long terme : éclater `EmailingEventListener` en services dédiés (`ShiftEmailService`, `MemberEmailService`, `HelloassoEmailService`) lors de la migration SF5. Effort L.

→ **TODO AP.7.4** — Long terme : extraire `CycleAccountingService` depuis `TimeLogEventListener`. Supprimer le dispatch interne de `MemberCycleStartEvent`. Effort M.

→ **TODO AP.7.5** — `HelloassoEventListener` : extraire `linkPaymentToUser()` dans un service dédié. Effort S.

