# Export anonymisé de la base

Produire un dump SQL partageable avec un développeur, sans données
personnelles dedans.

La base source n'est **jamais modifiée**. Un dump brut est restauré dans
une base jetable, anonymisé là, re-dumpé, et le fichier de destination
n'est créé qu'une fois les contrôles passés.

## Utilisation

```bash
export DATABASE_URL='mysql://user:pass@127.0.0.1:3306/gestion_compte'

# depuis la base que DATABASE_URL désigne
make db-export-anon

# depuis un dump SQL brut déjà en votre possession
make db-export-anon ANON_INPUT=prod-dump.sql ANON_OUTPUT=partageable.sql
```

Vérifier un dump dont vous n'êtes pas sûr, sans rien anonymiser :

```bash
make db-verify-anon ANON_DUMP=un-dump-quelconque.sql
```

Le script accepte aussi des valeurs que vous savez réelles, pour vérifier
nommément qu'elles ont disparu :

```bash
./bin/export-anonymized-db.sh --output out.sql \
  --canary 'Dupont' --canary 'jean.dupont@gmail.com'
```

Et l'inverse, pour du contenu volontairement conservé :

```bash
./bin/export-anonymized-db.sh --output out.sql --allow 'contact@elefan.org'
```

## Les trois contrôles

Ils sont **bloquants**. Aucun n'a d'option pour passer outre : la
correction attendue est de mettre le manifeste à jour, pas de désarmer le
contrôle.

**1. Couverture du schéma** — avant toute écriture. Si la base contient
une table ou une colonne que `config/anonymization.yaml` ne classe pas,
`app:anonymize` s'arrête. C'est le mécanisme qui rend le dispositif
durable : une migration qui ajoute une colonne bloque l'export tant que
quelqu'un n'a pas décidé si elle contient des données personnelles.

**2. Scan du dump produit** — avant livraison. Le fichier est parcouru à
la recherche d'adresses e-mail hors `example.invalid`, de numéros de
téléphone français, de toute valeur passée en `--canary`, et de tout
hachage bcrypt qui ne valide pas le mot de passe de l'export. À la
moindre trouvaille le fichier est écrasé puis supprimé : il n'est pas
livré.

Ce contrôle refuse aussi un dump qui ne porte **aucune** marque
d'anonymisation — le cas le plus probable étant de s'être trompé de
fichier.

**3. Restauration** — le dump est restauré dans une seconde base jetable.
Un artefact corrompu est ainsi détecté ici plutôt que par son
destinataire. Désactivable avec `--no-restore-check`.

## Le manifeste

`config/anonymization.yaml` est la source de vérité. **Toute** table et
**toute** colonne y figurent. Il n'y a volontairement ni règle par
défaut, ni joker : la garantie repose sur le fait que chaque colonne a
été regardée au moins une fois par un humain.

Trois stratégies par table :

| Stratégie   | Effet |
|-------------|-------|
| `truncate`  | toutes les lignes supprimées ; les colonnes n'ont pas à être classées, puisqu'il ne reste rien |
| `anonymize` | lignes conservées ; **chaque** colonne porte une règle |
| `view`      | vue SQL ; son contenu découle des tables de base, l'anonymiseur n'y touche pas |

Les règles de colonne sont listées en tête du manifeste et implémentées
dans `src/Anonymization/RuleRegistry.php`. `keep` — conserver tel quel —
est une réponse parfaitement valable ; c'est le silence qui ne l'est pas.

### Ajouter une colonne

Si vous ajoutez une colonne par migration, l'export s'arrêtera avec le
nom exact de la colonne non classée. Ajoutez-lui une règle dans le
manifeste et relancez. Le test `SchemaCoverageTest` fait remonter la même
information dès la CI, au moment de la pull request, plutôt qu'au moment
de l'export.

### Cohérence des identités

Les valeurs de remplacement sont déterministes, tirées d'une graine. Par
défaut la graine est l'`id` de la ligne ; `beneficiary` utilise `user_id`,
ce qui suffit à ce qu'un utilisateur et son bénéficiaire portent la même
identité fictive sans avoir besoin d'une jointure.

## Ce que l'export contient encore

Le public visé est le **développement interne**. Les identifiants métier
dont on a besoin pour reproduire un bug sont conservés délibérément :
numéros d'adhérent, dates, montants, structure des créneaux, et le
contenu éditorial déjà public sur le site.

Élargir le public — un partage hors de l'équipe — veut dire relire chaque
`keep` du manifeste, pas seulement en ajouter des règles. Un numéro
d'adhérent conservé se recoupe avec les registres papier de la
coopérative.

## Comptes de l'export

Tous les comptes partagent le même mot de passe, **`Password123`** par
défaut. C'est volontaire : l'export est fait pour qu'on s'y connecte.

Il se choisit à l'exécution :

```bash
make db-export-anon                                    # Password123
./bin/export-anonymized-db.sh --output out.sql --password 'AutreChose'
```

Le script transmet la valeur **aux deux étapes** — anonymisation et
vérification. Si vous les lancez séparément, elles doivent recevoir le
même `--password`, sinon la vérification rejette un export pourtant sain.

La vérification ne compare pas à un hash connu : bcrypt tire un sel
différent à chaque appel, donc le hash produit par un export n'est pas
prévisible. Elle utilise `password_verify` — tout hash bcrypt du dump qui
ne valide pas le mot de passe attendu est, par construction, le
justificatif réel de quelqu'un.

Les jetons de réinitialisation, les sels, les secrets OAuth et les codes
de badge sont supprimés ou régénérés.

Les adresses e-mail sont réécrites sur `@example.invalid`, un domaine que
la RFC 6761 garantit non résolvable — une instance de développement
branchée par erreur sur un vrai SMTP ne peut donc atteindre personne.

## Limites connues

- Le scan par motifs reconnaît les adresses e-mail, les hachages bcrypt
  et les numéros de téléphone français. Un nom de famille isolé dans un
  champ de texte libre laissé en `keep` n'est pas détectable
  automatiquement — d'où les `--canary` quand vous connaissez des valeurs
  réelles.
- `mysql`/`mariadb` et `mysqldump`/`mariadb-dump` doivent être dans le
  `PATH` de la machine qui lance l'export.
- Le moteur est spécifique à MySQL/MariaDB.
