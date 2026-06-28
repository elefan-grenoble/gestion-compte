# LOG.2 — Ce qui est loggé

- [x] **LOG.2** — Ce qui est loggé

`grep -rn "logger->\|LoggerInterface" src/`. Événements métier critiques non loggés ? `catch` silencieux ? → TODO.

  **Classes loggant via LoggerInterface (7)**

  | Classe | Canal | Contenu |
  |--------|-------|---------|
  | `EventListener/EmailingEventListener` | `app` | 13× `info("Emailing Listener: on<Event>")` — label seul, pas de contexte |
  | `EventListener/TimeLogEventListener` | `app` | 6× `info("Time Log Listener: on<Event>")` — label seul |
  | `EventListener/CommissionEventListener` | `app` | 2× `info("Commission Listener: onLeave/onJoin")` — label seul |
  | `EventListener/MattermostEventListener` | `app` | 1× `info("Mattermost Listener: onShiftAlerts")` — label seul |
  | `EventListener/ShiftFreeLogEventListener` | `app` | 1× `info("Shift Free Log Listener: onShiftFreed")` — label seul |
  | `EventListener/PeriodPositionFreeLogEventListener` | `app` | 1× `info("PeriodPosition Free Log Listener: onPeriodPositionFreed")` — label seul |
  | `EventListener/CodeEventListener` | `app` | 1× `info("Code Listener: onCodeNew")` — label seul |
  | `Twig/Extension/AppExtension` | `app` | `error("QR Code/Barcode generation error: ".$e->getMessage())` — correct |
  | `Controller/SwipeCardController` | `app` | `error($exception->getMessage())` sur catch générique — message seul |
  | `Controller/CodeController` | `app` | 5× `info("CODE : <action>", ['username' => ...])` — contexte username |
  | `Command/UpdateHelloAssoPaymentsCommand` | `app` | `info("Fetching page N")` + count résultats — correct |
  | `Providers/Helloasso/HelloassoPaymentHandler` | `app` | `info("Payment already in db")`, `info("Processing payment #N for email")` — bon niveau de détail |

  **Pattern commun : "label seul" = logs inutiles**

  Les 25 appels des 7 event listeners (Emailing, TimeLog, Commission, Mattermost, ShiftFreeLog, PeriodPositionFreeLog, CodeEvent) se résument à `logger->info("XxxListener: onSomeEvent")`. Aucun ID d'entité (shift, member, beneficiary), aucun contexte métier. En prod, avec le filtre `warning` du handler `file` et le buffer `fingers_crossed(action_level: info)` flushé quasi instantanément (cf. LOG.1), ces lignes INFO n'atteignent même pas le fichier de log — elles sont émises dans le vide.

  **Couche service : aucun logger**

  Zéro injection de `LoggerInterface` dans `src/Service/`. Les services (`MembershipService`, `TimeLogService`, `SwipeCardHelper`, etc.) ne tracent rien — toute la logique métier non triviale (calcul de cycles, compteurs de temps, cotisations) est silencieuse.

  **Couche controller critique : aucun logger**

  | Controller | Actions sensibles non tracées |
  |------------|-------------------------------|
  | `UserController` | `addRole()` L238, `removeRole()` L205 — admin ROLE_ADMIN, ROLE_SUPER_ADMIN |
  | `MembershipController` | `setFrozen(true/false)` L546/575, `setWithdrawn()` L655/664, création membre L807 |
  | `ShiftController` | booking, freeing, validation manuelle — tracé uniquement via event listeners (labels seuls) |
  | `BeneficiaryController` | ré-activation de compte (`setWithdrawn(false)`) L143 |
  | `AdminController` | (pas de logger non plus) |

  **Catches silencieux ou problématiques (6)**

  1. 🔴 **`EmailingEventListener:256-258`** — `catch (\Exception $e) { die($e->getMessage()); }` — **`die()` en plein event listener**. Si la construction de l'email Helloasso `TOO_EARLY` lève une exception (Twig, SMTP, etc.), la requête meurt en renvoyant le message brut de l'exception en HTTP 200 vide, sans log Monolog, sans flash, sans stack trace structurée. Comportement identique à un crash silencieux côté monitoring.

  2. 🟡 **`BookingController:358-362`** — `catch (Exception $ex)` sans log ni flash : le parsing de la semaine/année du formulaire de filtre échoue silencieusement, fallback sur `$defaultFrom/$defaultTo`. L'utilisateur ne voit rien, les logs non plus.

  3. 🟡 **`DefaultController:174,188`** — deux catches sur les webhooks Helloasso entrants : `InvalidArgumentException` → HTTP 422, `ClientExceptionInterface` → HTTP 500 — aucun log. Les échecs de webhooks de paiement sont invisibles dans Monolog.

  4. 🟡 **`HelloassoController:83,101,125`** — 3 catches `ClientExceptionInterface` → `addFlash('error', ...)` seulement. Toute indisponibilité de l'API HelloAsso disparaît des logs.

  5. 🟡 **`MailController:167-169`** — `catch (TransportExceptionInterface)` : les adresses en échec sont collectées dans `$errored[]` puis affichées en flash, jamais loggées. Aucune trace structurée d'un envoi en masse partiellement raté.

  6. 🔵 **`HelloassoEventListener`** (pas de `catch`, mais pas de logger) — `linkPaymentToUser()` peut atteindre deux branches commentées (`//throw new LogicException(...)`) pour "user not found" et "user cannot register yet" : les cas d'anomalie sont convertis en sous-events (`TOO_EARLY`, `RE_REGISTRATION_SUCCESS`) mais aucun `logger->warning()` n'accompagne la décision. Si l'email Helloasso ne correspond à aucun utilisateur, seul un email est envoyé au payeur — aucune trace en log.

  **Commande `CloseMembershipCommand` : `$output->writeln()` ≠ Monolog**

  La fermeture massive de comptes (`setWithdrawn(true)`) est tracée via `$output->writeln()` uniquement — visible uniquement en mode interactif (cron sans capture de sortie = silencieux). Pas de logger injecté : zéro trace Monolog ni Mattermost.

  **Authentification OIDC/Keycloak : aucun log**

  `KeycloakAuthenticator` dispatche `BeneficiaryCreatedEvent` lors de la première connexion SSO, mais n'a pas de logger. La création de compte via SSO n'est pas tracée. `AuthenticationSuccessHandler` et `OidcLogoutHandler` n'ont pas non plus de logger : les connexions/déconnexions réussies sont invisibles.

  **Findings**

  - 🔴 **`die()` dans `EmailingEventListener`** (L256-258) : supprimer la clause `try/catch` ou la remplacer par un log `error` + re-throw ; `die()` n'a pas sa place dans un event listener.
  - 🟡 **Labels sans contexte** : les 25 logs "Listener: onEvent" sont du bruit. Les remplacer par des messages avec IDs d'entités (ex. `"Shift #%d booked by member #%d"`) ou les supprimer. Requiert SF5 pour injection propre de channels nommés.
  - 🟡 **Catches sans log sur les flux critiques** : `DefaultController` (webhooks Helloasso), `HelloassoController` (appels API), `MailController` (envois en masse) devraient logger au moins en `warning` avant de retourner une réponse HTTP d'erreur.
  - 🟡 **`CloseMembershipCommand`** : injecter `LoggerInterface` et logger chaque fermeture de compte (membre + date) en `info`, et le total en `notice`.
  - 🔵 **Couche service/controller sans logger** : à corriger en priorité lors du refactor SF5 — les actions sensibles (role, frozen, withdrawn) devraient être tracées avec actor + target + nouvelle valeur.
  - 🔵 **Auth OIDC non tracée** : loguer `info("OIDC login: user %s")` et `info("OIDC: new beneficiary created %s")` dans `KeycloakAuthenticator`.

