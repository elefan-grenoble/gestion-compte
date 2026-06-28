# AP.3 — Requêtes hors Repository

- [x] **AP.3** — Requêtes hors Repository

`grep -rn "createQuery\|createNativeQuery\|getConnection\|createQueryBuilder" src/` hors `src/Repository/`. SQL/DQL dans controllers ou services → TODO.

**Périmètre analysé** : 55 occurrences dans 28 fichiers hors `src/Repository/`. Après filtrage des patterns légitimes (voir ci-dessous), 4 catégories d'antipatterns identifiées.

---

### Patterns légitimes (non listés)

| Catégorie | Fichiers | Motif |
|-----------|---------|-------|
| `src/Form/*.php` — callbacks `query_builder` | 5 fichiers, 10 occurrences | Pattern Symfony standard pour les champs `EntityType` : la closure reçoit le repository en paramètre et retourne un `QueryBuilder`. Pas un antipattern. |
| `src/Migrations/*.php` | 2 fichiers | Les migrations ont un accès direct à la connexion par conception. Attendu et correct. |
| `src/DataFixtures/Purger/CustomPurger.php:30` | 1 fichier | Purger de fixtures dev/test. Accès connexion acceptable. |

---

<a id="AP.3-1"></a>
### 1. SQL brut avec concaténation de table dans un controller (🔴)

`RegistrationsController.php:118-159` — deux requêtes SQL brutes (`$connection->prepare()`) avec le nom de table concaténé dans la chaîne SQL :
```php
$table_name = $em->getClassMetadata('App:AbstractRegistration')->getTableName();
$connection->prepare("SELECT ... FROM ".$table_name." WHERE date >= :from ...");
```
Les paramètres utilisateurs (`:from`, `:to`) sont bien paramétrés — pas d'injection SQL via les inputs. Cependant :
- Le nom de table est concaténé (non paramétrable, même si issu de Doctrine metadata)
- Ce sont des requêtes d'agrégation pivot complexes (`SUM(IF(mode='1',...))` × 6 modes par date) qui appartiennent au Repository
- La logique est dupliquée entre les deux requêtes (même filtre `date >= :from`, même structure pivot)

**Migration cible** : `AbstractRegistrationRepository::getSumsByDateRange(DateTimeInterface $from, ?DateTimeInterface $to)` et `getGrandTotalByDateRange()`. Supprime le raw SQL du controller et découple de la structure MariaDB.

→ **TODO SYN.2** — effort M (logique SQL complexe, 2 méthodes Repository, tests unitaires recommandés)

---

<a id="AP.3-2"></a>
### 2. `$em->createQueryBuilder()` sans passer par le Repository (🟠)

Ces occurrences construisent des requêtes depuis l'EntityManager directement, contournant entièrement la couche Repository :

| Fichier | Ligne | Entité | Type de requête |
|---------|-------|--------|----------------|
| `RegistrationsController.php` | 91 | `AbstractRegistration` | COUNT avec filtre date |
| `EventController.php` | 299 | `Beneficiary` | Recherche fulltext firstname + joins membership/registration |
| `HelloassoController.php` | 39 | `HelloassoPayment` | COUNT simple (pagination) |
| `BeneficiaryInitializationSubscriber.php` | 70 | `User` | Recherche par préfixe username pour unicité |

Le cas `BeneficiaryInitializationSubscriber` est particulièrement problématique : un event listener qui construit directement une requête DQL bypasse les deux couches (controller ET repository). La requête devrait être dans `UserRepository::findByUsernamePrefix(string $prefix): array`.

→ **TODO SYN.2** — effort S (4 méthodes Repository à créer, chaque cas est simple)

---

<a id="AP.3-3"></a>
### 3. Logique de filtrage complexe construite dans les controllers (🟠)

Ces controllers appellent `$em->getRepository(X)->createQueryBuilder()` puis enchaînent des clauses WHERE dynamiques directement dans l'action. La construction de requête est une responsabilité du Repository, pas du controller.

| Controller | Entité | Complexité |
|-----------|--------|-----------|
| `AdminShiftFreeLogController.php:107` | `ShiftFreeLog` | Tri + 2 filtres conditionnels (date création, date shift via LEFT JOIN) |
| `AdminMembershipShiftExemptionController.php:87` | `MembershipShiftExemption` | Filtres multi-colonnes |
| `AdminEventController.php:113` | `Event` | Filtres + tri dynamiques |
| `AdminEventController.php:385` | `Beneficiary` | Joins membership/registration, filtres, tri |
| `AdminPeriodPositionFreeLogController.php:86` | `PeriodPositionFreeLog` | Filtres conditionnels |

Ces 5 controllers correspondent tous à des écrans d'administration avec filtres dynamiques. Le pattern récurrent est : construire le `QueryBuilder` dans le controller, puis l'alimenter dans un `Paginator` (antipattern AP.2 finding 6). Une méthode Repository `findFiltered(array $filters): QueryBuilder` par entité résoudrait les deux problèmes.

Note : `AdminEventController.php:55` est dans un callback `query_builder` d'un formulaire inline — pattern légitime, non listé dans les antipatterns.

→ **TODO SYN.2** — effort S (5 méthodes Repository, migration mécanique)

---

<a id="AP.3-4"></a>
### 4. DQL brut dans une commande (🟡)

`FixShiftMissingPositionCommand.php:108` :
```php
$this->em->createQuery("UPDATE App:Shift s SET s.position = :position WHERE s.id in (:ids)")
```
Seul cas de DQL littéral (non via QueryBuilder) hors Repository. Le contexte justifie partiellement l'approche — il s'agit d'un UPDATE bulk conditionnel, difficilement exprimable avec les méthodes Repository existantes. Mais la requête devrait être dans `ShiftRepository::setPositionForIds(PeriodPosition $position, array $shifts): void`.

→ **TODO SYN.2** — effort XS (1 méthode Repository)

---

<a id="AP.3-5"></a>
### 5. Commandes construisant des QueryBuilders inline (🟡)

13 occurrences dans 8 commandes, toutes via `$this->em->getRepository(X)->createQueryBuilder()`. Ces requêtes pourraient être des méthodes Repository nommées :

| Commande | Entité requêtée | Logique inline |
|---------|----------------|----------------|
| `ShiftReminderCommand` | `Shift` | Filtrage sur date + statut |
| `RandomSortMembersCommand` | `Beneficiary` | Filtres sur adhésion active |
| `SendMassMailCommand` | `Membership` | Filtres complexes multi-critères |
| `FixShiftMissingPositionCommand` (×2) | `PeriodPosition`, `Shift` | Filtres période + position null |
| `ShiftGenerateCommand` | `Period` | Filtres date + statut |
| `FixBeneficiariesWithoutAddressCommand` | `Beneficiary` | Filtre adresse null |
| `UpdateIgloohomeCodeCommand` | `Code` | Filtres type + statut |
| `AnonymizeDataCommand` (×4) | plusieurs | Filtres anonymisation |
| `VerifyCodeChangeCommand` | `Code` | Filtre vérification |

Nuance multi-instance : certaines de ces requêtes sont spécifiques à des commandes de maintenance ou migration, avec une logique qui ne sera probablement jamais réutilisée. Le ratio coût/bénéfice d'extraire vers le Repository est faible pour ces cas.

→ **TODO SYN.2** — effort S global (prioriser les requêtes réutilisables, ignorer les commandes de migration one-shot)

---

<a id="AP.3-6"></a>
### 6. Services avec QueryBuilder via Repository (🟡)

| Service | Occurrences | Note |
|---------|-------------|------|
| `EventService.php:25,37` | 2 | Service "métier" qui construit ses propres requêtes via `$this->em->getRepository(Proxy::class)->createQueryBuilder()`. Ces 2 méthodes sont candidates à `ProxyRepository`. |
| `SearchUserFormHelper.php:386` | 1 | Helper de formulaire de recherche qui construit la query de base — c'est un service de présentation qui pilote la couche données. La méthode `initSearchQuery()` retourne un `QueryBuilder` pour que les filtres dynamiques puissent être chaînés. Pattern hybride discutable, mais moins urgent que les controllers. |

→ **TODO SYN.2** — effort XS (EventService : 2 méthodes à déplacer dans ProxyRepository)

---

### Résumé

| Gravité | Finding | Fichiers | Effort |
|---------|---------|---------|--------|
| 🔴 | SQL brut + concaténation table dans controller | 1 (RegistrationsController) | M |
| 🟠 | `$em->createQueryBuilder()` sans Repository | 4 | S |
| 🟠 | Filtrage complexe inline dans controllers | 5 | S |
| 🟡 | DQL brut dans une commande | 1 (FixShiftMissingPositionCommand) | XS |
| 🟡 | QueryBuilder inline dans commandes | 8 (13 occurrences) | S |
| 🟡 | QueryBuilder dans services | 2 (EventService) | XS |

