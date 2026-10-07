# Environnement de test local (Linux)

Ce guide permet de lancer les suites de tests sans PHP/Composer installés localement, via Docker.
Toutes les commandes sont centralisées dans le `Makefile` à la racine du projet.

Le même Makefile est utilisé par la CI GitHub Actions (`.github/workflows/ci.yaml`).
En local, les commandes PHP passent par Docker Compose ; en CI (`CI=true`), elles
s'exécutent directement.

## 1) Prérequis

### Debian 13 / Ubuntu

```bash
sudo apt update
sudo apt install -y docker.io docker-compose make
```

Pour les tests Cypress E2E, Node.js et npm sont également nécessaires :

```bash
sudo apt install -y nodejs npm
```

### Ajouter l'utilisateur au groupe docker

Ceci est **indispensable** pour éviter les erreurs `permission denied` sur le socket Docker :

```bash
sudo usermod -aG docker "$USER"
```

Puis **ouvrir un nouveau terminal** (ou lancer `newgrp docker` dans le terminal courant).

Vérification :

```bash
docker info >/dev/null && echo "OK"
```

## 2) Bootstrap de l'environnement de test

Depuis la racine du projet :

```bash
make setup-test
```

Cela :
- vérifie l'accès au daemon Docker (message explicite si le groupe manque),
- crée `compose.yaml` depuis le `.dist` (en retirant l'attribut `version` obsolète, bind mount remplacé par un volume nommé),
- crée `.env` et `.env.test.local` si absents,
- **build** l'image PHP puis démarre `database`, `php`, `mailcatcher`,
- lance `composer install`,
- crée des stubs Webpack Encore (`public/build/`),
- recrée le schéma de test et charge les fixtures.

L'image PHP embarque `pcov` (désactivé par défaut) pour `make test-coverage`.
Si ton image date d'avant cet ajout, reconstruis-la : `docker compose build php`.

## 3) Lancer les tests

### PHPUnit

```bash
make test-unit        # Tests unitaires (sans DB)
make test-integration # Tests d'intégration (Kernel + DB)
make test-func        # Tests fonctionnels (HTTP + DB)
make test             # Tous les tests PHPUnit
make test-coverage    # Tous les tests + couverture dans var/coverage/
```

`make test-coverage` produit `var/coverage/html/index.html`, `var/coverage/clover.xml`,
`var/coverage/coverage.txt`, et affiche la couverture par namespace.

### Écrire un test PHPUnit

| Suite         | Dossier              | Classe de base                    | Base de données |
|---------------|----------------------|-----------------------------------|-----------------|
| `unit`        | `tests/Unit`         | `TestCase`                        | aucune (mocks)  |
| `integration` | `tests/Integration`  | `KernelTestCase`                  | réelle          |
| `functional`  | `tests/Functional`   | `FunctionalTestCase` (WebTestCase)| réelle + fixtures |

**Isolation.** Chaque test qui démarre le Kernel tourne dans une transaction annulée
à sa fin (`tests/PHPUnit/DatabaseIsolationExtension.php`, sur
`dama/doctrine-test-bundle`) : ce qu'un test écrit n'est jamais vu par le suivant,
quel que soit l'ordre d'exécution. Conséquences :

- les fixtures se chargent une fois par classe, dans `setUpBeforeClass()`, jamais
  dans un test (le purger fait des `TRUNCATE`, qui valident la transaction) :

  ```php
  public static function setUpBeforeClass(): void
  {
      parent::setUpBeforeClass();         // purge la base
      static::loadFixtures(['period']);    // groupes de fixtures, tous si null
  }
  ```

- un test qui doit exécuter du DDL (`ALTER`, `CREATE`, `TRUNCATE`...) sur la connexion
  par défaut implémente `App\Tests\PHPUnit\SkipDatabaseRollback` et nettoie derrière lui.

**Fixtures déterministes.** `.env.test` fixe `FIXTURES_SEED` : les tirages `rand()` des
fixtures sont identiques d'un chargement à l'autre (PHPUnit comme Cypress). Les dates
restent relatives au jour du chargement. Sans la variable (env `dev`), les fixtures
restent aléatoires.

**Helpers** (`tests/Support`) :

- builders `UserBuilder`, `BeneficiaryBuilder`, `MembershipBuilder`, `ShiftBuilder`,
  avec des valeurs par défaut valides et uniques :

  ```php
  $membership = MembershipBuilder::aMembership()->frozen()->build();
  $shift = ShiftBuilder::aShift()->bookedBy($membership->getMainBeneficiary())->build();
  ```

- `PersistsEntities::persist(...)` enregistre des entités dans la transaction du test ;
- `FunctionalTestCase::createAuthenticatedClient($userOrUsername)` renvoie un client
  déjà connecté, sans passer par le formulaire (`loginAs()` reste disponible pour
  tester le formulaire lui-même).

**Mots de passe.** En env `test`, bcrypt tourne au coût 4
(`config/packages/test/security.yaml`) : au coût par défaut, chaque utilisateur
persisté coûte ~0,5 s.

**Tests de sécurité** (`tests/Functional/Security`, helpers dans `tests/Support/Security`) :

- `AnonymousRouteAccessTest` parcourt toutes les routes : chacune doit renvoyer un
  anonyme vers `/login`, sauf celles de sa liste `PUBLIC_ROUTES`, qui doit refléter
  exactement les règles publiques d'`access_control`. Ajouter une route publique, c'est
  l'ajouter à cette liste avec sa justification ;
- `RoleMatrix::cases()` + le trait `ChecksRoleAccess` : matrice route × rôle × résultat
  attendu (403 sous le rôle minimal, accès au-dessus) à partir de la hiérarchie de
  `security.yaml` ;
- `KnownOpenVulnerability::assertSecureOrKnownOpen()` : un test de faille encore ouverte
  affirme le comportement sûr ; tant que la faille est là il est marqué *incomplete*
  (la CI reste verte), et une fois corrigée il **échoue** pour qu'on retire l'enveloppe
  et qu'il devienne un test de non-régression :

  ```php
  $this->assertSecureOrKnownOpen('C-SEC-1', 'set_email anonyme', function () use ($user) {
      $this->assertSame('ancien@example.test', $user->getEmail());
  });
  ```

### PHPStan

```bash
make lint          # Analyse statique PHPStan
```

### Cypress E2E

Les tests Cypress s'exécutent sur le host (pas dans Docker).
L'application doit tourner (port 8000 via Docker) et npm doit être installé.

```bash
npm ci                    # Installer les dépendances npm (une fois)
make test-e2e             # Login + shift + membership
make test-e2e-main        # Tests login uniquement
make test-e2e-shift       # Tests créneaux
make test-e2e-membership  # Tests adhésion
make test-e2e-oidc        # Tests OIDC (nécessite Keycloak)
```

## 4) Autres commandes utiles

```bash
make help          # Liste toutes les cibles disponibles
make db-reset      # Recrée le schéma en rejouant les migrations (sans fixtures)
make db-migrate    # Exécute les migrations Doctrine
make db-fixtures   # Reset DB + fixtures
make cache-clear   # Vide le cache Symfony (env test)
make down          # Arrête les conteneurs
make clean         # Arrête + supprime volumes + fichiers générés
```

## 5) CI / Makefile partagé

Le workflow `.github/workflows/ci.yaml` utilise les mêmes targets `make`.
La variable `CI=true` (positionnée automatiquement par GitHub Actions) fait que
le Makefile exécute les commandes PHP directement au lieu de passer par Docker.

| Target Makefile                     | Job CI correspondant               |
|-------------------------------------|------------------------------------|
| `make test-unit`                    | `fast-tests` (PHP 7.4 et 8.1)      |
| `make lint`                         | `phpstan`                          |
| `make test-integration`, `test-func`| `symfony-tests` (PHP 7.4)          |
| `make test-coverage`                | `symfony-tests` (PHP 8.1)          |
| `make test-e2e-*`                   | `cypress-tests`                    |

Les jobs PHPUnit tournent en PHP 7.4 et 8.1 (image de production), tous bloquants.
Le job `symfony-tests` en 8.1 publie la couverture : résumé par namespace dans le
récapitulatif du job, rapports complets dans l'artefact `coverage-report`.
Aucun seuil n'est imposé pour l'instant.
