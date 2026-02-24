# ------------------------------------------------------------------
# Makefile — commandes de développement et de test
# ------------------------------------------------------------------
#
# Fonctionne en deux modes :
#   - Local : les commandes PHP s'exécutent dans Docker Compose
#   - CI    : les commandes PHP s'exécutent directement (CI=true)
#
# Usage :
#   make setup-test    Bootstrap complet (Docker + DB + fixtures)
#   make test          Tous les tests PHPUnit
#   make test-unit     Tests unitaires + intégration
#   make test-func     Tests fonctionnels
#   make lint          Analyse statique PHPStan
#   make test-e2e      Tests Cypress E2E (hors OIDC)
#   make clean         Arrête les conteneurs et supprime les volumes
#
# ------------------------------------------------------------------

# ---- Mode CI vs Local ------------------------------------------------
# Cypress version — doit correspondre à package.json
CYPRESS_VERSION      ?= 13.6.4
CYPRESS_BASE_URL     ?= http://membres.yourcoop.local:8000
CYPRESS_KEYCLOAK_URL ?= http://localhost:8080

ifdef CI
  EXEC        :=
  _DOCKER_DEP :=
  CYPRESS_CMD  = CYPRESS_BASE_URL=$(CYPRESS_BASE_URL) \
                 CYPRESS_KEYCLOAK_URL=$(CYPRESS_KEYCLOAK_URL) \
                 npx cypress run
else
  COMPOSE     := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
  EXEC        := $(COMPOSE) exec -T php
  _DOCKER_DEP := up
  CYPRESS_CMD  = docker run --rm --network host \
                 -v $(CURDIR):/e2e -w /e2e \
                 -e CYPRESS_BASE_URL=$(CYPRESS_BASE_URL) \
                 -e CYPRESS_KEYCLOAK_URL=$(CYPRESS_KEYCLOAK_URL) \
                 cypress/included:$(CYPRESS_VERSION)
endif

.PHONY: help check-docker check-hosts setup-test \
        test test-unit test-func test-coverage lint \
        test-e2e test-e2e-main test-e2e-shift test-e2e-membership test-e2e-oidc \
        npm-install encore-build encore-stubs \
        db-reset db-migrate db-fixtures db-fixtures-load \
        env-ci env-ci-oidc serve \
        up down clean cache-clear

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z0-9_-]+:.*## ' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------
# Prérequis
# ------------------------------------------------------------------

check-docker: ## Vérifie que Docker est accessible
	@command -v docker >/dev/null 2>&1 || \
		{ echo "❌ Docker introuvable. Installe docker.io."; exit 1; }
	@docker info >/dev/null 2>&1 || \
		{ echo "❌ Impossible de contacter le daemon Docker."; \
		  echo "   sudo usermod -aG docker \"\$$USER\" puis ouvre un nouveau terminal."; exit 1; }

check-hosts: ## Vérifie que membres.yourcoop.local est dans /etc/hosts
	@grep -qE '^\s*127\.0\.0\.1\s+.*membres\.yourcoop\.local' /etc/hosts || \
		{ echo "❌ membres.yourcoop.local absent de /etc/hosts"; \
		  echo "   echo '127.0.0.1 membres.yourcoop.local' | sudo tee -a /etc/hosts"; exit 1; }

# ------------------------------------------------------------------
# Fichiers de configuration (local uniquement)
# ------------------------------------------------------------------

compose.yaml:
	@sed -e '/^version:/d' \
	     -e 's|^\(\s*\)- \./mysql:/var/lib/mysql|\1- db_data:/var/lib/mysql|' \
	     docker-compose.symfony_server.yml.dist > compose.yaml
	@printf '\nvolumes:\n  db_data:\n' >> compose.yaml
	@echo "✔ compose.yaml créé (version retirée, volume nommé db_data)"

.env:
	@cp .env.dist .env
	@echo "✔ .env créé depuis .env.dist"

.env.test.local:
	@echo 'DATABASE_URL="mysql://root:secret@database:3306/symfony?serverVersion=5.7&charset=utf8"' > .env.test.local
	@echo "✔ .env.test.local créé"

# ------------------------------------------------------------------
# Docker (local uniquement)
# ------------------------------------------------------------------

up: check-docker compose.yaml .env .env.test.local ## Démarre les conteneurs
	$(COMPOSE) build php
	$(COMPOSE) up -d database php mailcatcher
	@echo "Attente de MariaDB..."
	@for i in $$(seq 1 30); do \
		$(COMPOSE) exec -T database mysqladmin ping -h localhost -u root -psecret --silent >/dev/null 2>&1 && break; \
		sleep 1; \
	done

down: ## Arrête les conteneurs
	$(COMPOSE) down

clean: ## Arrête les conteneurs et supprime volumes + fichiers générés
	$(COMPOSE) down -v 2>/dev/null || true
	rm -f compose.yaml .env .env.test.local
	rm -rf public/build
	@echo "✔ Nettoyé"

# ------------------------------------------------------------------
# Dépendances & assets
# ------------------------------------------------------------------

vendor: $(_DOCKER_DEP)
	$(EXEC) composer install --no-interaction --prefer-dist

npm-install: ## Installe les paquets NPM
	npm ci

encore-build: ## Build les assets front-end (production)
	./node_modules/.bin/encore production --progress

encore-stubs: ## Crée des stubs Webpack Encore (évite les 500 en test)
	@mkdir -p public/build
	@test -f public/build/entrypoints.json || \
		echo '{"entrypoints":{"app":{"js":[],"css":[]}}}' > public/build/entrypoints.json
	@test -f public/build/manifest.json || \
		echo '{}' > public/build/manifest.json

# ------------------------------------------------------------------
# Base de données
# ------------------------------------------------------------------

db-reset: vendor ## Drop + recreate le schéma de test
	$(EXEC) php bin/console --env=test doctrine:database:drop --force --if-exists
	$(EXEC) php bin/console --env=test doctrine:database:create
	$(EXEC) php bin/console --env=test doctrine:schema:create

db-migrate: ## Exécute les migrations Doctrine (env test)
	$(EXEC) php bin/console --env=test doctrine:migrations:migrate --no-interaction

db-fixtures: db-reset ## Reset DB + charge les fixtures
	$(EXEC) php bin/console --env=test doctrine:fixtures:load --no-interaction

db-fixtures-load: ## Charge les fixtures (sans reset DB)
	$(EXEC) php bin/console --env=test doctrine:fixtures:load --no-interaction

# ------------------------------------------------------------------
# Setup complet (local)
# ------------------------------------------------------------------

setup-test: check-hosts vendor encore-stubs db-fixtures cache-clear ## Bootstrap complet de l'environnement de test
	@echo ""
	@echo "✅ Environnement de test prêt."
	@echo "  make test          Tous les tests"
	@echo "  make test-unit     Unit + intégration"
	@echo "  make test-func     Fonctionnels"
	@echo "  make lint          PHPStan"
	@echo "  make test-e2e      Cypress E2E"

# ------------------------------------------------------------------
# Tests PHPUnit
# ------------------------------------------------------------------

test: ## Tous les tests PHPUnit
	$(EXEC) composer test

test-unit: ## Tests unitaires + intégration (sans DB)
	$(EXEC) composer test-unit

test-func: ## Tests fonctionnels (avec DB)
	$(EXEC) composer test-functional

test-coverage: ## Tests avec rapport de couverture HTML
	$(EXEC) composer test-coverage

# ------------------------------------------------------------------
# Analyse statique
# ------------------------------------------------------------------

lint: ## Analyse PHPStan
	$(EXEC) php bin/console cache:warmup --env=dev
	$(EXEC) php vendor/bin/phpstan analyse src

# ------------------------------------------------------------------
# Tests Cypress E2E
# ------------------------------------------------------------------

test-e2e: test-e2e-main test-e2e-shift test-e2e-membership ## Tous les tests Cypress (hors OIDC)

test-e2e-main: ## Cypress — tests login
	$(CYPRESS_CMD) --spec 'cypress/e2e/login/**/*'

test-e2e-shift: ## Cypress — tests créneaux
	$(CYPRESS_CMD) --spec 'cypress/e2e/shift/**/*'

test-e2e-membership: ## Cypress — tests adhésion
	$(CYPRESS_CMD) --spec 'cypress/e2e/membership/**/*'

test-e2e-oidc: ## Cypress — tests OIDC / Keycloak
	$(CYPRESS_CMD) --spec 'cypress/e2e/keycloak/**/*'

# ------------------------------------------------------------------
# Helpers CI
# ------------------------------------------------------------------

env-ci: ## Configure l'environnement CI
	cp .env.test .env

env-ci-oidc: ## Configure l'environnement CI pour OIDC
	cp .env.oidc.test .env.test

serve: ## Démarre le serveur Symfony (CI / host)
	symfony server:start --no-tls -d --port=8000

# ------------------------------------------------------------------
# Utilitaires
# ------------------------------------------------------------------

cache-clear: ## Vide le cache Symfony (env test)
	$(EXEC) php bin/console cache:clear --env=test
