# Standard update-deps targets for bundle root Makefiles.
# Requires: COMPOSE, SERVICE_PHP, ensure-up
# Include from bundle Makefile:
#   BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
#   include $(BUNDLE_ROOT)/.scripts/Makefile.update-deps.mk

ifndef BUNDLES_SCRIPTS
BUNDLES_SCRIPTS := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
endif

ifndef BUNDLE_ROOT
BUNDLE_ROOT := $(abspath $(BUNDLES_SCRIPTS)/..)
endif

.PHONY: update-deps update-deps-demos

update-deps: ensure-up
	@echo "=== $(notdir $(BUNDLE_ROOT)): composer update (bundle) ==="
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer update --no-interaction
	@$(MAKE) update-deps-demos

update-deps-demos:
	@bash "$(BUNDLES_SCRIPTS)/update-deps-demos.sh" "$(BUNDLE_ROOT)"
