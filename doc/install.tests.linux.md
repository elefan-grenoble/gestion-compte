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

## 3) Lancer les tests

### PHPUnit

```bash
make test-unit     # Tests unitaires + intégration (sans DB)
make test-func     # Tests fonctionnels (avec DB)
make test          # Tous les tests PHPUnit
make test-coverage # Avec rapport de couverture HTML
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

| Target Makefile    | Job CI correspondant |
|--------------------|----------------------|
| `make test-unit`   | `fast-tests`         |
| `make lint`        | `phpstan`            |
| `make test-func`   | `symfony-tests`      |
| `make test-e2e-*`  | `cypress-tests`      |
