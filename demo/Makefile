.PHONY: help up-symfony8 down-symfony8 install-symfony8 shell-symfony8 release-check update-bundle-symfony8

help:
	@echo "Seo Kit Bundle — Demos"
	@echo ""
	@echo "Symfony 8 (port 8050):"
	@echo "  up-symfony8 down-symfony8 install-symfony8 shell-symfony8"
	@echo ""
	@echo "  release-check          Lint + verify demo health (HTTP 200 public/admin pages, sitemap, robots)"

up-symfony8:
	@$(MAKE) -C symfony8 up

down-symfony8:
	@$(MAKE) -C symfony8 down

install-symfony8:
	@$(MAKE) -C symfony8 install

shell-symfony8:
	@$(MAKE) -C symfony8 shell

update-bundle-symfony8:
	@$(MAKE) -C symfony8 update-bundle

release-check:
	@$(MAKE) up-symfony8
	@$(MAKE) update-bundle-symfony8
	@$(MAKE) -C symfony8 test
	@$(MAKE) -C symfony8 verify

# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
DEMOS := symfony8
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(BUNDLE_ROOT)/.scripts/Makefile.demo-aggregate-update-deps.mk
