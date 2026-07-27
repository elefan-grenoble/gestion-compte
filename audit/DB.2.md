# DB.2 — État des migrations

- [x] **DB.2** — État des migrations

`docker compose exec -T php php bin/console doctrine:migrations:status`. Migrations en attente ou orphelines → TODO.

### Résumé DB.2

**Commande** : `doctrine:migrations:status` + `doctrine:migrations:list` + lecture Makefile + lecture `dploy.sh`.

**État constaté** :
- Table `migration_versions` **absente** de la base Docker : Doctrine considère les 99 migrations comme *non exécutées*.
- La base Docker a été créée via `doctrine:schema:create` (cible `db-reset` du Makefile), qui court-circuite complètement le système de migrations.
- Les 99 migrations couvrent 5 ans d'historique (2018-11 → 2023-12).
- Aucune migration orpheline (version en DB sans fichier sur disque) — car la table de suivi n'existe pas.

**Flux de bootstrap actuel** :
```
make setup-test
  └─► db-fixtures
        └─► db-reset
              ├─ doctrine:database:drop --force --if-exists
              ├─ doctrine:database:create
              └─ doctrine:schema:create          ← bypasse les migrations
```
La cible `db-migrate` (`doctrine:migrations:migrate`) existe mais n'est jamais appelée par `setup-test` ni par aucune autre cible.

**Production** : `dploy.sh` ne lance pas les migrations — le bloc est commenté depuis l'origine avec un `#todo` explicite (ligne 4 et 74 du script).

**Risque** : si quelqu'un lançait `doctrine:migrations:migrate` sur la base courante, 98 des 99 migrations n'ont pas de garde `skipIf`/`IF NOT EXISTS` — elles échoueraient avec "table already exists". Seule `Version20181103153303_initial` contient un `$this->skipIf($schema->hasTable('fos_user'), …)`.

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🔴 Élevé | Système de migrations incohérent : schema:create en test, migrations commentées en prod — les migrations ne sont jamais appliquées nulle part | M |
| 🟠 Moyen | `db-migrate` est une cible isolée non appelée par `setup-test` ; `migration_versions` absente → état de suivi inexistant | XS |
| 🟠 Moyen | 98 migrations sans garde d'idempotence — `migrate` sur schéma existant → crash garanti | S |

→ **TODO SYN.3** — Après `schema:create` en test, synchroniser le tracking Doctrine : `doctrine:migrations:version --add --all --no-interaction`. À ajouter dans la cible `db-reset` du Makefile juste après `schema:create`. Effort XS.

→ **TODO SYN.4** — Rétablir les migrations en production : décommenter et compléter le bloc migration dans `dploy.sh` (ligne 74), en lançant `doctrine:migrations:version --add --all` une seule fois sur les instances existantes pour synchroniser l'état, puis `doctrine:migrations:migrate --no-interaction` à chaque déploiement. Effort S (coordination avec les deux instances, Elefan + Scopeli).

