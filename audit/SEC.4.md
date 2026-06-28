# SEC.4 — Requêtes non paramétrées

- [x] **SEC.4** — Requêtes non paramétrées

`grep -rn "\"SELECT\|'SELECT\|createNativeQuery" src/Repository/`. Concaténation de variables dans du SQL → TODO critique.

**Périmètre analysé** : tous les fichiers `src/` (Repository, Controller, Service, Command, EventListener, DataFixtures, Migrations) — pas uniquement `src/Repository/`.

**Résultat global** : aucune injection SQL exploitable détectée. La codebase utilise massivement le QueryBuilder Doctrine (paramétré par construction). 3 usages non idiomatiques de `expr()->literal()` identifiés ; 2 requêtes natives avec concaténation de constante.

---

<a id="SEC.4-1"></a>
### 1. `expr()->literal()` avec input utilisateur — 3 occurrences (🟡)

`$qb->expr()->literal()` inline la valeur échappée directement dans la chaîne DQL au lieu d'utiliser un paramètre lié (`setParameter()`). Doctrine appelle `PDO::quote()` en interne, ce qui fournit un échappement correct pour la version actuelle — **pas d'injection exploitable**. Mais ce pattern :
- bypass le mécanisme de prepared statement (protection liée à l'implémentation du driver, pas au protocole)
- est fragile face aux changements de charset ou de driver
- est explicitement déconseillé par la documentation Doctrine au profit de `setParameter()`

| Fichier | Ligne | Source de la valeur | Input HTTP ? |
|---------|-------|---------------------|--------------|
| `src/Repository/BeneficiaryRepository.php` | 92 | `$firstname` passé par `BeneficiaryController:239` depuis `$form->get('firstname')->getData()` | **Oui** (POST form) |
| `src/Controller/EventController.php` | 304 | `$firstname` depuis `$search_form->get('firstname')->getData()` | **Oui** (POST form) |
| `src/EventListener/BeneficiaryInitializationSubscriber.php` | 72 | `$username` dérivé de `User::makeUsername($firstname, $lastname)` | Non (donnée interne Doctrine) |

Dans les deux premiers cas, la valeur vient d'un `TextType` Symfony validé (`isSubmitted() && isValid()`), ce qui n'empêche pas la valeur de contenir des caractères SQL. L'échappement de `PDO::quote()` les neutralise, mais le pattern reste fragile.

**Fix recommandé** (exemple pour `BeneficiaryRepository.php:92`) :
```php
// Avant (non idiomatique) :
->where($qb->expr()->like('b.firstname', $qb->expr()->literal('%' . $firstname . '%')))

// Après (paramétré) :
->where($qb->expr()->like('b.firstname', ':firstname'))
->setParameter('firstname', '%' . $firstname . '%')
```

→ **TODO SYN.2** — effort XS × 3 (remplacer `expr()->literal()` par `setParameter()` dans les 3 occurrences)

---

<a id="SEC.4-2"></a>
### 2. SQL natif avec `$table_name` concaténée — `RegistrationsController` (🔵 Info)

`RegistrationsController` (lignes 119–130 et 144–153) construit deux requêtes DBAL avec `$connection->prepare()` en concaténant `$table_name` :

```php
$table_name = $em->getClassMetadata('App:AbstractRegistration')->getTableName();
$statement = $connection->prepare("SELECT ... FROM " . $table_name . " WHERE date >= :from ...");
$statement->bindValue('from', $from->format('Y-m-d'));
```

- `$table_name` vient de `getClassMetadata()` — **valeur statique de métadonnées Doctrine**, non contrôlable par l'utilisateur. **Pas d'injection.**
- Les paramètres utilisateur (`$from`, `$to`) sont liés correctement via `bindValue()`.
- Le recours au SQL natif (DBAL brut) à la place du DQL est dû à l'usage de `IF()` et `date_format()` MySQL spécifiques — justification valide.

→ **Pas de TODO sécurité.** Annotation maintenance possible si la codebase migre vers une base non-MySQL.

---

<a id="SEC.4-3"></a>
### 3. Autres cas examinés — OK

| Fichier | Ligne | Constat |
|---------|-------|---------|
| `DataFixtures/Purger/CustomPurger.php` | 36 | `sprintf('TRUNCATE TABLE %s', $tableName)` — table name issu du schéma DB (non user input) ; code fixtures uniquement |
| `Migrations/Version20190218130524_job_id_not_null.php` | 26 | `exec('INSERT INTO job ...')` — valeurs littérales hardcodées |
| `Command/FixShiftMissingPositionCommand.php` | 108 | `createQuery("UPDATE ... WHERE s.id in (:ids)")` — DQL paramétré avec `:ids` |
| Tous les autres Repository | — | QueryBuilder avec `setParameter()` / `bindValue()` — safe |

---

### Résumé SEC.4

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🟡 Mineur | 3 × `expr()->literal()` avec input utilisateur — non idiomatique, échappement présent mais fragile | XS × 3 |
| 🔵 Info | SQL natif dans `RegistrationsController` — `$table_name` Doctrine (non user-input), params bindés | — |
| ✅ OK | Ensemble du codebase — QueryBuilder paramétré, aucune concaténation user-input dans SQL | — |

