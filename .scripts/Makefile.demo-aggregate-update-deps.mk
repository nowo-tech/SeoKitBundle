# Standard update-deps-all for demo/Makefile aggregators with DEMOS variable.
# Requires BUNDLE_ROOT before include (parent of demo/).

ifndef BUNDLES_SCRIPTS
BUNDLES_SCRIPTS := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
endif

.PHONY: update-deps-all

update-deps-all:
ifdef DEMOS
	@for demo in $(DEMOS); do \
		echo ""; \
		echo "=== Demo $$demo ==="; \
		if [ -f $$demo/Makefile ] && $(MAKE) -C $$demo -n update-deps >/dev/null 2>&1; then \
			$(MAKE) -C $$demo update-deps; \
		else \
			echo "⚠️  $$demo: no update-deps target, running script fallback"; \
			bash "$(BUNDLES_SCRIPTS)/update-deps-demos.sh" "$(BUNDLE_ROOT)"; \
			exit 0; \
		fi; \
	done; \
	echo ""; \
	echo "✅ All demo dependencies updated and demos started."
else
	@bash "$(BUNDLES_SCRIPTS)/update-deps-demos.sh" "$(BUNDLE_ROOT)"
endif
