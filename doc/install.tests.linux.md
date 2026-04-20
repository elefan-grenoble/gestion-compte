# Environnement de test local (Linux)

Ce guide permet de lancer les suites de tests sans PHP/Composer installés localement, via Docker.
Toutes les commandes sont centralisées dans le `Makefile` à la racine du projet.

## 1) Prérequis

### Debian 13 / Ubuntu

```bash
sudo apt update
sudo apt install -y docker.io docker-compose make
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

```bash
make test-unit     # Tests unitaires + intégration (sans DB)
make test-func     # Tests fonctionnels (avec DB)
make test          # Tous les tests
make test-coverage # Avec rapport de couverture HTML
```

## 4) Autres commandes utiles

```bash
make help          # Liste toutes les cibles disponibles
make db-reset      # Recrée le schéma (sans fixtures)
make db-fixtures   # Reset DB + fixtures
make cache-clear   # Vide le cache Symfony (env test)
make down          # Arrête les conteneurs
make clean         # Arrête + supprime volumes + fichiers générés
```
