# FrankenPHP demo

**REQ-DEMO-001:** FrankenPHP demos must install **Nowo Twig Inspector** and **Nowo Hot Reload** together (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`). Caddyfile: Mercure + `hot_reload` (and `worker { file …; watch }` in worker mode). Do not enable Hot Reload in production.

The Symfony 8 demo runs as a **single FrankenPHP container** (no separate Nginx).

## Table of contents

- [Quick start](#quick-start)
- [Development vs production Caddyfile](#development-vs-production-caddyfile)
- [Bundle path repository](#bundle-path-repository)
- [Verify SEO endpoints](#verify-seo-endpoints)
- [SEO Admin (demo CRUD)](#seo-admin-demo-crud)
- [Worker mode note](#worker-mode-note)

## Quick start

```bash
make -C demo up-symfony8
```

Default URL: `http://localhost:8050` (see `demo/symfony8/.env.example`).

## Development vs production Caddyfile

| Mode | Caddyfile | PHP mode |
| --- | --- | --- |
| `APP_ENV=dev` (default) | `Caddyfile.dev` | `php_server` — no workers, file changes visible immediately |
| `APP_ENV=prod` | `Caddyfile` | Can use `php_server { worker … }` for FrankenPHP workers |

The Docker entrypoint selects the Caddyfile from `FRANKENPHP_MODE` (`classic`|`worker`).

## Bundle path repository

The demo mounts the bundle at `/var/seo-kit-bundle` and uses a Composer path repository so local `src/` changes are symlinked.

## Verify SEO endpoints

```bash
curl -s http://localhost:8050/ | grep -i '<title>'
curl -s http://localhost:8050/en | grep -i '<title>'
curl -s http://localhost:8050/sitemap.xml | head
curl -s http://localhost:8050/robots.txt
```

## SEO Admin (demo CRUD)

Browse [http://localhost:8050/admin/seo](http://localhost:8050/admin/seo) to list/edit `pages`, `slug_routes`, and `slugs` (SEO URL definitions) stored in `config/packages/nowo_seo_kit.yaml`. Disallowed in `robots.txt` under `/admin`.

## Worker mode note

FrankenPHP workers keep PHP state across requests. `SeoRuntime` is cleared per request via `SeoRuntimeClearSubscriber`; still prefer `APP_ENV=dev` without workers during local development.

See also [SERVERS.md](SERVERS.md).
