# Upgrading

## To 1.0.0

First public release. No prior versions.

### Install

```bash
composer require nowo-tech/seo-kit-bundle:^1.0
```

### Configuration

Ensure `config/packages/nowo_seo_kit.yaml` exists (Flex recipe or copy from `.symfony/recipe/`). Import routes via `config/routes/nowo_seo_kit.yaml`.

### Twig

Add `{{ nowo_seo_head() }}` to your base layout if not already present.

### Breaking changes

None (initial release).
