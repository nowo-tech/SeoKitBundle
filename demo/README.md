# Seo Kit Bundle — Demos

## Symfony 8 (FrankenPHP)

| Command | Description |
|---------|-------------|
| `make up-symfony8` | Start demo (default port **8050**) |
| `make down-symfony8` | Stop containers |
| `make shell-symfony8` | Shell in PHP container |
| `make update-bundle-symfony8` | Sync bundle autoload + clear cache |
| `make release-check` | Healthcheck: `/`, `/en`, sitemap, robots |

Demo sources: [`symfony8/`](symfony8/).

SEO Admin (demo CRUD for `pages` / `slug_routes` / `slugs`): [http://localhost:8050/admin/seo](http://localhost:8050/admin/seo).

Documentation: [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md).