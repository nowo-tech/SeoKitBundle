# FrankenPHP demo

The Symfony 8 demo runs as a **single FrankenPHP container** (no separate Nginx).

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

The Docker entrypoint copies `Caddyfile.dev` when `APP_ENV=dev`.

## Bundle path repository

The demo mounts the bundle at `/var/seo-kit-bundle` and uses a Composer path repository so local `src/` changes are symlinked.

## Verify SEO endpoints

```bash
curl -s http://localhost:8050/en/ | grep -i '<title>'
curl -s http://localhost:8050/sitemap.xml | head
curl -s http://localhost:8050/robots.txt
```

## Worker mode note

FrankenPHP workers keep PHP state across requests. `SeoRuntime` is cleared per request via `SeoRuntimeClearSubscriber`; still prefer `APP_ENV=dev` without workers during local development.

See also [SERVERS.md](SERVERS.md).
