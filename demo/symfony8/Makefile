# Makefile for Symfony 8 Demo (Seo Kit Bundle)

COMPOSE = /usr/bin/docker compose
SERVICE_PHP = php

.PHONY: help up down build install shell update-bundle ensure-up ensure-env

help:
	@echo "Symfony 8 Demo - Seo Kit Bundle"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down           Stop containers"
	@echo "  make install        Install dependencies"
	@echo "  make shell          Open shell in PHP container"
	@echo "  make update-bundle  Sync bundle autoload and clear cache"

up: ensure-env
	@$(COMPOSE) up -d
	@echo "Waiting for container..."
	@sleep 5
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	@$(COMPOSE) down

build:
	@$(COMPOSE) build --no-cache

ensure-env:
	@test -f .env || (cp .env.example .env && echo "Created .env from .env.example")

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		$(MAKE) up; \
	fi

install: ensure-up
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction

shell: ensure-up
	@$(COMPOSE) exec $(SERVICE_PHP) sh

update-bundle: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) composer dump-autoload --optimize
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear --env=dev --no-warmup || true

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
include $(BUNDLE_ROOT)/.scripts/Makefile.demo-update-deps.mk
