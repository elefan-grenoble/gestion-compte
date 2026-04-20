# ------------------------------------------------------------------
# Makefile — commandes de développement et de test
# ------------------------------------------------------------------
#
# Usage :
#   make setup-test   Bootstrap complet (Docker + DB + fixtures)
#   make test          Tous les tests PHPUnit
#   make test-unit     Tests unitaires + intégration (sans DB)
#   make test-func     Tests fonctionnels (avec DB)
#   make clean         Arrête les conteneurs et supprime les volumes
#
# ------------------------------------------------------------------

# Détection automatique de docker compose vs docker-compose
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
EXEC    := $(COMPOSE) exec -T php

.PHONY: help check-docker setup-test test test-unit test-func test-coverage \
        db-reset db-fixtures encore-stubs up down clean cache-clear

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------
# Prérequis
# ------------------------------------------------------------------

check-docker: ## Vérifie que Docker est accessible
	@command -v docker >/dev/null 2>&1 || \
		{ echo "❌ Docker introuvable. Installe docker.io."; exit 1; }
	@docker info >/dev/null 2>&1 || \
		{ echo "❌ Impossible de contacter le daemon Docker."; \
		  echo "   sudo usermod -aG docker \"\$$USER\" puis ouvre un nouveau terminal."; exit 1; }

# ------------------------------------------------------------------
# Fichiers de configuration
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
# Docker
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

vendor: up
	$(EXEC) composer install --no-interaction --prefer-dist

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

db-fixtures: db-reset ## Reset DB + charge les fixtures
	$(EXEC) php bin/console --env=test doctrine:fixtures:load --no-interaction

# ------------------------------------------------------------------
# Setup complet
# ------------------------------------------------------------------

setup-test: vendor encore-stubs db-fixtures cache-clear ## Bootstrap complet de l'environnement de test
	@echo ""
	@echo "✅ Environnement de test prêt."
	@echo "  make test          Tous les tests"
	@echo "  make test-unit     Unit + intégration"
	@echo "  make test-func     Fonctionnels"

# ------------------------------------------------------------------
# Tests
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
# Utilitaires
# ------------------------------------------------------------------

cache-clear: ## Vide le cache Symfony (env test)
	$(EXEC) php bin/console cache:clear --env=test
