# Usage

## Twig

```twig
{# base.html.twig #}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{ nowo_seo_head() }}
</head>
```

Inspect resolved metadata in templates:

```twig
{% set seo = nowo_seo_metadata() %}
<h1>{{ seo.title }}</h1>
```

## PHP attribute

```php
use Nowo\SeoKitBundle\Attribute\Seo;

#[Seo(title: 'About us', description: 'Company information')]
final class AboutController
{
    // ...
}
```

## Runtime overrides

Inject `SeoRuntime` in a controller:

```php
public function show(SeoRuntime $seo, string $slug): Response
{
    $seo->set([
        'title' => 'Dynamic '.$slug,
        'description' => 'Loaded from database',
    ]);
    $seo->setVariables(['title' => 'Article title']);

    return $this->render('blog/show.html.twig');
}
```

Runtime is cleared automatically at the end of each request.

## Sitemap and robots

After configuration, verify:

```bash
curl -s https://your-host/sitemap.xml | head
curl -s https://your-host/robots.txt
```

## Translations

UI fallback strings live in `NowoSeoKitBundle` domain YAML files. Override in `translations/NowoSeoKitBundle.en.yaml` in your app.

## Demo

```bash
make -C demo up-symfony8
```

Open the URL printed by the Makefile (default port from `demo/symfony8/.env.example`).
