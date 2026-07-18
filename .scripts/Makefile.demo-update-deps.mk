# Standard update-deps target for a single demo Makefile (demo/symfonyX/).
# Uses a one-off container so composer update does not depend on FrankenPHP staying up.
# Then starts the persistent demo container and warms Symfony cache.
# Requires: COMPOSE, SERVICE_PHP (defined in the demo Makefile before include).
# Include at end of demo Makefile:
#   include $(BUNDLE_ROOT)/.scripts/Makefile.demo-update-deps.mk

.PHONY: update-deps

update-deps:
	@echo "=== $(notdir $(CURDIR)): composer update ==="
	@if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi
	$(COMPOSE) run --rm --no-deps \
		-e COMPOSER_MEMORY_LIMIT=-1 \
		--entrypoint "" \
		$(SERVICE_PHP) \
		composer update --no-interaction --no-scripts
	@echo "=== $(notdir $(CURDIR)): starting demo ==="
	@PORT_VALUE=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT_VALUE" ] && PORT_VALUE=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	PORT=$$PORT_VALUE $(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console importmap:install --no-interaction 2>/dev/null || true
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear --no-interaction 2>/dev/null || true
	@$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console assets:install public --symlink --no-interaction 2>/dev/null || true
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
