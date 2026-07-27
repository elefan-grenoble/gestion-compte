# AP.8 — Commandes sans délégation service

- [x] **AP.8** — Commandes sans délégation service

Lire `src/Command/`. Commandes > 30 lignes dans `execute()` sans déléguer → TODO.

**Périmètre** : 25 fichiers dans `src/Command/`, 2 743 lignes au total.

---

### Classification par niveau de délégation

**Bien structurées (délèguent correctement) :**

| Commande | Patron |
|----------|--------|
| `UpdateHelloAssoPaymentsCommand` | `execute()` 10 lignes → `HelloassoPaymentHandler::savePayments()` + pagination récursive |
| `AmbassadorShiftTimeLogCommand` | délègue à `sendAlertsByEmail()` méthode privée + `MailerInterface` |
| `SendShiftAlertsCommand` | extrait `computeAlerts()` (27 lignes), reste un dispatcher d'événements |
| `CycleStartCommand` / `CycleHalfCommand` | boucle légère + `EventDispatcher` + `MembershipService` |
| `HelloassoPaymentCommand` | acceptable (37 lignes, 2 branches simples) |
| `ShiftReminderCommand` | acceptable (33 lignes, délègue via dispatcher) |

**Surchargées — logique métier inline dans `execute()` :**

---

<a id="AP.8-1"></a>
#### 1. `ImportUsersCommand` — execute() ≈ 195 lignes (🔴)

Hérite de `CsvCommand` pour la gestion CSV, mais `execute()` contient intégralement la logique de création d'entités : lookup/création `Membership`, création `Beneficiary`, `User`, `Address`, `Registration`, affectation des commissions — tout en boucle inline. Aucune délégation à un service.

**`utf8_encode()` dépréciée (🟡)** : L117 `array_map("utf8_encode", $data)`. Dépréciée depuis PHP 8.1, supprimée en PHP 8.2. Remplacement : `mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1')` ou détection d'encodage source.

**Refactoring cible** : extraire `ImportMemberService::importFromRow()`. Effort L.

---

<a id="AP.8-2"></a>
#### 2. `AnonymizeDataCommand` — execute() ≈ 140 lignes (🟠)

Logique d'anonymisation entièrement inline : boucle sur `Beneficiary`, `Commission`, `Event`, suppressions en bulk via `createQueryBuilder(...)->delete()`. Seul helper : `randomValue()` (private, 4 lignes). Acceptable pour un outil one-shot de maintenance, mais la logique de génération de données factices n'appartient pas au runner de commande.

---

<a id="AP.8-3"></a>
#### 3. `ShiftGenerateCommand` — execute() ≈ 113 lignes (🟠)

Boucle date × période × position inline : requête `Period::findBy`, création `Shift`, gestion des créneaux fixés/pré-réservés/libres, flush par date. `PeriodService` est injecté mais n'est utilisé que pour `getWeekCycleArray()` — la génération elle-même n'y est pas déléguée.

**Constante hardcodée (🟡)** : `lastCycleDate()` (L168–173) calcule 28 jours fixés en dur. Ce délai correspond à `cycle_duration` qui est un paramètre de configuration — il devrait utiliser `$this->params->get('cycle_duration')`.

**Refactoring cible** : déplacer la logique de génération dans `PeriodService::generateShiftsForDate()`. Effort M.

---

<a id="AP.8-4"></a>
#### 4. `DoctorCommand` — execute() ≈ 100 lignes (🟡)

Trois branches de correction inline (phone, status, registration). Acceptable pour un outil "doctor" de maintenance ad hoc, mais rend difficile le test unitaire de chaque fix.

---

<a id="AP.8-5"></a>
#### 5. `VerifyCodeChangeCommand` — execute() ≈ 58 lignes (🟠)

Manipule le `TokenStorageInterface` en CLI (L90) pour forcer un contexte de sécurité fictif :
```php
$token = new UsernamePasswordToken($last->getRegistrar(), ..., $last->getRegistrar()->getRoles());
$this->token_storage->setToken($token);
```
Anti-pattern : le `TokenStorage` est conçu pour le contexte HTTP (stackable request scope). En CLI, injecter un token manuellement pour appeler un Voter (`CodeVoter::VIEW`) est fragile — le résultat dépend de l'implémentation interne du Voter. L'appel à `$this->authorization_checker->isGranted(CodeVoter::VIEW, $code)` devrait être remplacé par un appel direct à la logique de visibilité du code (méthode de service ou de l'entité).

Construction email inline (L106–116).

**Refactoring cible** : extraire la logique de visibilité de code dans une méthode `Code::isVisibleTo(User $user)` ou `CodeService::isVisible()`. Effort S.

---

<a id="AP.8-6"></a>
#### 6. `FixShiftMissingPositionCommand` — execute() ≈ 82 lignes (🟡)

QueryBuilder DQL inline (L63–91) + boucle de déduplication par jour (L96–105) directement dans `execute()`. Requête de correction `UPDATE App:Shift s SET s.position` via DQL inline (L108). Logique de matching de position sans abstraction.

---

<a id="AP.8-7"></a>
#### 7. `RandomSortMembersCommand` — execute() ≈ 65 lignes (🟡)

QueryBuilder avec 4 joins inline + génération CSV ligne par ligne dans `execute()`. `echo $csv` utilisé à la place de `$output->writeln()` si pas de fichier (L109) — mélange de canaux de sortie.

---

<a id="AP.8-8"></a>
#### 8. `UpdateIgloohomeCodeCommand` — execute() ≈ 50 lignes (🟡)

Délègue la création du code API à `IgloohomeClient::regenerateCode()` (bon), mais création et fermeture des entités `Code` inline dans execute(). Logique de persistance hors Repository.

---

### Résumé

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🔴 God execute | `ImportUsersCommand` 195 lignes, entités créées inline | L |
| 🟠 God execute | `AnonymizeDataCommand` 140 lignes, anonymisation inline | M |
| 🟠 God execute | `ShiftGenerateCommand` 113 lignes, génération shifts inline | M |
| 🟠 Anti-pattern | `VerifyCodeChangeCommand` : TokenStorage manipulé en CLI | S |
| 🟡 Dépréciation | `ImportUsersCommand` L117 : `utf8_encode()` PHP 8.2 removed | XS |
| 🟡 Constante hardcodée | `ShiftGenerateCommand::lastCycleDate()` : 28 jours fixes au lieu de `cycle_duration` | XS |
| 🟡 Mélange sortie | `RandomSortMembersCommand` L109 : `echo` au lieu de `$output->write()` | XS |
| 🟡 Logic inline | `DoctorCommand`, `FixShiftMissingPositionCommand`, `UpdateIgloohomeCodeCommand` | S–M |

→ **TODO AP.8.1** — `ImportUsersCommand::execute()` : extraire la logique de création d'adhérent dans `ImportMemberService`. Priorité M (lisibilité + testabilité). Effort L.

→ **TODO AP.8.2** — `ImportUsersCommand` L117 : remplacer `utf8_encode()` par `mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1')`. Effort XS — correction avant PHP 8.2.

→ **TODO AP.8.3** — `ShiftGenerateCommand::lastCycleDate()` : remplacer 28 jours hardcodés par `$this->params->get('cycle_duration')`. Effort XS.

→ **TODO AP.8.4** — `VerifyCodeChangeCommand` : supprimer la manipulation du `TokenStorage` en CLI ; extraire la logique de visibilité dans `Code::isVisibleTo(User)` ou un service dédié. Effort S.

