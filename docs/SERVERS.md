# Server cookbook — SEO routes

SeoKitBundle serves `/sitemap.xml`, `/robots.txt`, and application pages through Symfony. No special static files are required on disk.

## FrankenPHP

Recommended for demos and many production setups.

```caddyfile
:80 {
    root * /app/public
    php_server
    encode gzip
}
```

- Import bundle routes so Symfony handles `/sitemap.xml` and `/robots.txt`.
- Set `nowo_seo_kit.base_url` when behind TLS terminators so canonicals use `https://`.

**Workers**: In production you may enable FrankenPHP workers; ensure `APP_ENV=prod` uses the production `Caddyfile`. Clear opcode cache after deploy.

## Nginx + php-fpm

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/app/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

Do **not** add separate `location = /sitemap.xml` static files unless you intentionally bypass Symfony.

### Trusted proxies

When Symfony sits behind a load balancer, configure `trusted_proxies` / `X-Forwarded-*` so `Request::getSchemeAndHttpHost()` matches public URLs, or set `base_url` explicitly.

## php-fpm only (Apache / other front controllers)

Ensure all requests reach `public/index.php`. SEO metadata uses the current Request; canonical and hreflang depend on correct host/scheme.

## Caching

| Endpoint | Suggestion |
| --- | --- |
| `/sitemap.xml` | Short cache at CDN (e.g. 1h) or Symfony HTTP cache |
| `/robots.txt` | Low cache or none during staging changes |
| HTML pages | Application cache; SEO head reflects resolved metadata per request |

## Multilingual routes

Configure locale prefixes in `pages` / `slug_routes` / `slugs` so public paths match your routing (`/_locale` prefix or dedicated paths per locale). See [CONFIGURATION.md](CONFIGURATION.md).

## Checklist

- [ ] `{{ nowo_seo_head() }}` in base layout
- [ ] `config/routes/nowo_seo_kit.yaml` imported
- [ ] `base_url` set in production if needed
- [ ] `curl /sitemap.xml` and `curl /robots.txt` return 200
