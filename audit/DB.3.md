# DB.3 — Qualité des migrations

- [x] **DB.3** — Qualité des migrations

Migrations avec `down()` vides, opérations irréversibles sans warning → TODO.

### Résumé DB.3

**Méthode** : grep systématique sur les 99 fichiers (`TRUNCATE`, `DROP COLUMN`, `DELETE FROM` en `down()`, `$this->container`, garde plateforme) + lecture ciblée des cas flaggés.

**Findings** :

**1. `TRUNCATE` en `up()` + `down()` vide** (`Version20190430204903_swipe_card`)
`up()` exécute `TRUNCATE swipe_card` (purge complète), `down()` est vide. C'est une purge de données ponctuelle déguisée en migration — irréversible par conception. Non bloquant (la table était supposément vide à l'époque), mais trompeur pour qui lirait le log de migration.

**2. `ContainerAwareInterface` + `$this->container`** (2 migrations — bloquant SF5+)
```
Version20190218130524_job_id_not_null.php    ← $container->get('doctrine.orm.entity_manager')->getConnection()
Version20190402014558_add_role_to_never_logged_user.php ← $em->getRepository(User::class)->...->flush()
```
Ces migrations implémentent `ContainerAwareInterface`. Dans `doctrine/migrations` 3.x (requis par Symfony 5+), la propriété `$container` n'est plus injectée — ces migrations **crashent** à l'exécution. Bloquant pour SF-PREP.

**3. `DROP COLUMN` en `down()` — perte de données sur rollback** (2 migrations)
```
Version20200301100000.php         ← ALTER TABLE shift DROP COLUMN fixe
Version20210224215308.php         ← ALTER TABLE swipe_card_log DROP COLUMN counter
```
Un rollback (`doctrine:migrations:migrate prev`) détruit irrémédiablement les données de ces colonnes.

**4. `DELETE FROM` en `down()` — suppression de données métier** (4 migrations)
```
Version20190111150938.php
Version20191021000000_home_dynamic_content.php
Version20230519151558_dynamic_content_pre_membership_email.php
Version20230520171433_dynamic_content_home_bottom.php
```
Ces migrations seedent des `dynamic_content` en `up()` et font `DELETE FROM` en `down()`. Un rollback supprime les données seedées (et potentiellement les données saisies ensuite par les admins).

**5. Migrations sans garde de plateforme MySQL** (6 migrations)
`Version20190214200309`, `Version20190402014558`, `Version20191218002203`, `Version20200708035603`, `Version20190218130524`, `Version20190324212024` — manquent du `$this->abortIf(platform !== 'mysql')`. Risque faible (la DB est MariaDB partout), mais incohérent avec les 93 autres.

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🔴 Élevé | 2 migrations `ContainerAwareInterface` — cassent avec doctrine/migrations 3.x requis par SF5+ | S |
| 🟠 Moyen | TRUNCATE en `up()` + `down()` vide (purge ponctuelle non documentée) | XS |
| 🟠 Moyen | `DROP COLUMN` en `down()` sur 2 migrations — rollback = perte de données colonne | XS (doc) |
| 🟠 Moyen | `DELETE FROM` en `down()` sur 4 migrations — rollback = suppression de données seedées | XS (doc) |
| 🟡 Faible | 6 migrations sans garde de plateforme MySQL (`abortIf`) | XS |

<a id="todo-MIG-1"></a>
→ **TODO MIG.1** — Réécrire les 2 migrations `ContainerAwareInterface` en SQL natif pur (`$this->addSql()`), sans passer par l'EntityManager. Bloquant pour la migration SF5+. Effort S.

<a id="todo-MIG-2"></a>
→ **TODO MIG.2** — Ajouter un commentaire d'en-tête dans `Version20190430204903_swipe_card` documentant la nature de la purge et l'impossibilité de rollback. Effort XS.

<a id="todo-MIG-3"></a>
→ **TODO MIG.3** — Marquer les 6 migrations à `down()` destructif (`DROP COLUMN` / `DELETE FROM`) avec un commentaire explicite `// WARNING: down() is destructive — data cannot be recovered`. Effort XS.

---

