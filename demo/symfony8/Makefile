# Makefile for Symfony 8 Demo (Seo Kit Bundle)

# Prefer Compose V2; absolute docker path avoids shadowing by local docker/ when PATH has "." (REQ-MAKE-010).
DOCKER_BIN := $(shell command -v docker 2>/dev/null)
ifeq ($(DOCKER_BIN),)
COMPOSE ?= docker-compose
else
COMPOSE ?= $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
endif
SERVICE_PHP = php

.PHONY: help up down restart build install shell update-bundle ensure-up ensure-env test verify

help:
	@echo "Symfony 8 Demo - Seo Kit Bundle"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down           Stop containers"
	@echo "  make restart        Recreate containers (reloads .env, e.g. FRANKENPHP_MODE)"
	@echo "  make install        Install dependencies"
	@echo "  make shell          Open shell in PHP container"
	@echo "  make update-bundle  Sync bundle autoload and clear cache"
	@echo "  make test           Lint demo container, Twig templates and YAML config"
	@echo "  make verify         HTTP 200 healthcheck (home, /en, sitemap, robots, admin)"

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

restart:
	@$(COMPOSE) down
	@$(MAKE) up

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

test: ensure-up
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console lint:container
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console lint:twig templates
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console lint:yaml config

# REQ-MAKE-003 / REQ-TEST-011 — waits for the worker, then asserts HTTP 200 on public and admin pages.
verify: ensure-up
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=8050; \
	code=000; i=0; \
	while [ $$i -lt 30 ]; do \
		code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT/" || true); \
		[ "$$code" = "200" ] && break; \
		i=$$((i+1)); sleep 2; \
	done; \
	[ "$$code" = "200" ] || { echo "Demo did not answer HTTP 200 on / (last: $$code)"; exit 1; }; \
	for url in /en /sitemap.xml /robots.txt /admin/seo /admin/seo/pages /admin/seo/pages/new /admin/seo/slug-routes /admin/seo/slugs /admin/seo/slugs/new; do \
		code=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT$$url" || true); \
		[ "$$code" = "200" ] || { echo "FAIL $$url -> $$code"; exit 1; }; \
		echo "OK $$url"; \
	done; \
	curl -sf "http://127.0.0.1:$$PORT/sitemap.xml" | grep -q '<urlset' || { echo "sitemap.xml has no <urlset>"; exit 1; }; \
	curl -sf "http://127.0.0.1:$$PORT/robots.txt" | grep -q 'User-agent' || { echo "robots.txt has no User-agent"; exit 1; }; \
	echo "Symfony 8 SEO demo verify OK (port $$PORT)"

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
include $(BUNDLE_ROOT)/.scripts/Makefile.demo-update-deps.mk
