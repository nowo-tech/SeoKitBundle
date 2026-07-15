# Security

## Reporting vulnerabilities

Report security issues privately to **hectorfranco@nowo.tech**. Do not open public issues for sensitive reports.

See [.github/SECURITY.md](../.github/SECURITY.md) for supported versions.

## Integrator guidance

- Set `base_url` in production when generating canonicals/sitemap behind proxies or in CLI.
- Use `noindex` on slugs or runtime overrides for private or draft content.
- Keep `robots.disallow` aligned with admin and API paths.
- Do not expose internal hostnames in `base_url` or canonical overrides.

## Dependencies

Run `composer audit` in consuming applications and keep Symfony/Twig updated per your support policy.
