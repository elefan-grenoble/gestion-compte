# DB.1 — Validation schéma vs entités

- [x] **DB.1** — Validation schéma vs entités

`docker compose exec -T php php bin/console doctrine:schema:validate`. Divergences → TODO.

**Résultat** : Mapping **OK** — aucune erreur d'annotation. Database **NOT IN SYNC**.

---

### Volume et nature des divergences

- **37 `ALTER TABLE`** sur 37 tables (Doctrine DBAL 2.13.9).
- **0 `CREATE TABLE` / `DROP TABLE`** : toutes les tables sont présentes, pas de table fantôme, pas de table manquante.

**Tables affectées** : `access_token`, `address`, `anonymous_beneficiary`, `auth_code`, `beneficiary`, `client`, `closing_exception`, `code`, `commission`, `dynamic_content`, `email_template`, `event`, `formation`, `fos_user`, `helloasso_payment`, `job`, `membership`, `membership_shift_exemption`, `note`, `opening_hour`, `opening_hour_kind`, `period`, `period_position`, `period_position_free_log`, `process_update`, `proxy`, `refresh_token`, `registration`, `service`, `shift`, `shiftfreelog`, `social_network`, `swipe_card`, `swipe_card_log`, `task`, `time_log`, `view_abstract_registration`.

---

### Pattern unique — `DEFAULT NULL` manquant (36 cas / 🟡 Faible impact)

Toutes les divergences sauf une suivent le même patron :
```sql
ALTER TABLE foo CHANGE col_nullable col_nullable INT DEFAULT NULL;
```
La colonne existe dans la DB, est nullable, mais **sans la clause `DEFAULT NULL` explicite** requise par le mapping Doctrine 2.13.9.

**Cause racine** : La DB a été créée ou les migrations ont été générées avec une version antérieure de Doctrine DBAL qui n'émettait pas `DEFAULT NULL` sur les colonnes nullable. DBAL 2.13.9 compare les définitions colonne à colonne et détecte l'absence comme une divergence.

**Impact fonctionnel : nul.** MariaDB traite une colonne `INT` nullable sans `DEFAULT NULL` explicite identiquement à une colonne avec `DEFAULT NULL` — le comportement à l'INSERT est identique dans les deux cas. Les 36 cas sont donc une divergence **déclarative** (métadonnées), pas comportementale.

---

### Cas particulier — `dynamic_content.type` (🟠 Impact potentiel)

```sql
ALTER TABLE dynamic_content CHANGE type type VARCHAR(64) DEFAULT 'general' NOT NULL;
```

L'annotation Doctrine déclare :
```php
@ORM\Column(name="type", type="string", length=64, options={"default": "general"})
```
La DB manque la clause `DEFAULT 'general'` sur cette colonne `NOT NULL`.

**Impact fonctionnel** : si un INSERT SQL direct (hors Doctrine, ex. script de migration manuelle, fixture externe) omet la colonne `type`, MariaDB retournera une erreur (colonne NOT NULL sans valeur par défaut). Doctrine passe toujours la valeur explicitement — aucun bug runtime constaté à ce jour — mais le schéma DB ne reflète pas l'intention de l'annotation.

---

### Remédiation recommandée

| Action | Commande | Priorité |
|--------|---------|---------|
| Générer la migration | `php bin/console doctrine:migrations:diff` | 🟡 Faible |
| Réviser le diff | Vérifier que seuls des `ALTER TABLE CHANGE … DEFAULT NULL` et `DEFAULT 'general'` apparaissent | — |
| Committer et appliquer | Conventional Commit `fix:` | 🟡 Faible |

**Ne pas utiliser** `doctrine:schema:update --force` : dangereux en production (pas de transaction, pas de versionnement).

Ce correctif est purement déclaratif — aucun risque de perte de données.

---

### Résumé DB.1

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🟡 Faible | 36 colonnes nullable sans `DEFAULT NULL` explicite (divergence déclarative, 0 impact runtime) | XS (1 migration) |
| 🟠 Moyen | `dynamic_content.type` — `DEFAULT 'general'` absent du schéma DB (colonne NOT NULL sans défaut en DB) | XS (inclus dans la même migration) |

→ **TODO SYN.2** — `doctrine:migrations:diff` + révision + merge. Effort XS.

