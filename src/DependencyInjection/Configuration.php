<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Hierarchical SEO configuration.
 *
 * Resolution order (later wins):
 * 1. `defaults` — global minimal defaults
 * 2. `slug_routes.<route>` — general rules for slug-based routes
 * 3. `pages.<route>` — static page / route SEO (locale overrides nested)
 * 4. `slugs.<route>.<slug>` — specific slug override
 * 5. Runtime (`SeoRuntime`) / PHP attribute `#[Seo]`
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_seo_kit';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('default_locale')->defaultValue('en')->end()
                ->arrayNode('locales')
                    ->scalarPrototype()->end()
                    ->defaultValue(['en', 'es', 'fr', 'de', 'it', 'pt', 'nl'])
                ->end()
                ->scalarNode('base_url')
                    ->info('Absolute origin for canonicals/sitemap when Request host is unavailable (e.g. CLI).')
                    ->defaultNull()
                ->end()
            ->end();

        $this->addDefaultsNode($root);
        $this->addPagesNode($root);
        $this->addSlugRoutesNode($root);
        $this->addSlugsNode($root);
        $this->addSitemapNode($root);
        $this->addRobotsNode($root);
        $this->addTemplatesNode($root);

        return $treeBuilder;
    }

    private function addDefaultsNode(ArrayNodeDefinition $root): void
    {
        $children = $root->children()->arrayNode('defaults')->addDefaultsIfNotSet()->children();
        $this->addSeoFieldNodes($children);
        $children
            ->scalarNode('site_name')->defaultValue('')->end()
            ->scalarNode('title_separator')->defaultValue(' | ')->end()
            ->scalarNode('title_template')
                ->info('Placeholders: {title}, {site_name}, {separator}')
                ->defaultValue('{title}{separator}{site_name}')
            ->end()
            ->booleanNode('canonical_enabled')->defaultTrue()->end()
            ->booleanNode('hreflang_enabled')->defaultTrue()->end()
            ->booleanNode('x_default_hreflang')->defaultTrue()->end();
        $this->addOpenGraphNode($children);
        $this->addTwitterNode($children);
        $this->addJsonLdNode($children);
    }

    private function addPagesNode(ArrayNodeDefinition $root): void
    {
        $children = $root->children()
            ->arrayNode('pages')
            ->info('SEO for static routes. Key = Symfony route name. Optional locale-specific paths for i18n URLs.')
            ->useAttributeAsKey('route')
            ->arrayPrototype()
            ->children();

        $this->addSeoFieldNodes($children);
        $children
            ->scalarNode('path')
                ->info('Optional public path for the default locale (hreflang / sitemap).')
                ->defaultNull()
            ->end()
            ->scalarNode('controller')
                ->info('Optional controller for SeoStaticRouteLoader (type: nowo_seo_kit).')
                ->defaultNull()
            ->end()
            ->booleanNode('in_sitemap')->defaultTrue()->end()
            ->floatNode('sitemap_priority')->min(0)->max(1)->defaultValue(0.8)->end()
            ->enumNode('sitemap_changefreq')
                ->values(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])
                ->defaultValue('weekly')
            ->end()
            ->arrayNode('locales')
                ->useAttributeAsKey('locale')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('title')->defaultNull()->end()
                        ->scalarNode('description')->defaultNull()->end()
                        ->scalarNode('robots')->defaultNull()->end()
                        ->scalarNode('canonical')->defaultNull()->end()
                        ->scalarNode('keywords')->defaultNull()->end()
                        ->scalarNode('path')->defaultNull()->end()
                        ->scalarNode('og_image')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
        $this->addOpenGraphNode($children);
        $this->addTwitterNode($children);
    }

    private function addSlugRoutesNode(ArrayNodeDefinition $root): void
    {
        $children = $root->children()
            ->arrayNode('slug_routes')
            ->info('General SEO rules for slug-based routes. Key = Symfony route name.')
            ->useAttributeAsKey('route')
            ->arrayPrototype()
            ->children();

        $children
            ->scalarNode('slug_parameter')->defaultValue('slug')->end()
            ->scalarNode('title_template')
                ->info('Placeholders from request attributes / runtime variables, e.g. {title}, {slug}, {site_name}.')
                ->defaultNull()
            ->end()
            ->scalarNode('description_template')->defaultNull()->end()
            ->scalarNode('path_pattern')
                ->info('Path pattern with {slug} (and optional {locale}), e.g. /blog/{slug}.')
                ->defaultNull()
            ->end()
            ->booleanNode('in_sitemap')->defaultTrue()->end()
            ->floatNode('sitemap_priority')->min(0)->max(1)->defaultValue(0.6)->end()
            ->enumNode('sitemap_changefreq')
                ->values(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])
                ->defaultValue('weekly')
            ->end()
            ->arrayNode('locales')
                ->useAttributeAsKey('locale')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('path_pattern')->defaultNull()->end()
                        ->scalarNode('title_template')->defaultNull()->end()
                        ->scalarNode('description_template')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
        $this->addSeoFieldNodes($children);
        $this->addOpenGraphNode($children);
        $this->addTwitterNode($children);
    }

    private function addSlugsNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('slugs')
            ->info('Specific slug overrides. Outer key = route name, inner key = slug value.')
            ->useAttributeAsKey('route')
            ->arrayPrototype()
                // Keep URL slugs with hyphens (hello-world); Symfony normalizes "-" to "_" by default.
                ->normalizeKeys(false)
                ->useAttributeAsKey('slug')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('title')->defaultNull()->end()
                        ->scalarNode('description')->defaultNull()->end()
                        ->scalarNode('robots')->defaultNull()->end()
                        ->scalarNode('canonical')->defaultNull()->end()
                        ->scalarNode('keywords')->defaultNull()->end()
                        ->booleanNode('noindex')->defaultFalse()->end()
                        ->booleanNode('in_sitemap')->defaultTrue()->end()
                        ->scalarNode('og_image')->defaultNull()->end()
                        ->arrayNode('locales')
                            ->useAttributeAsKey('locale')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('title')->defaultNull()->end()
                                    ->scalarNode('description')->defaultNull()->end()
                                    ->scalarNode('path')->defaultNull()->end()
                                    ->scalarNode('slug')
                                        ->info('Translated slug value for this locale (hreflang / path building).')
                                        ->defaultNull()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addSitemapNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('sitemap')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('path')->defaultValue('/sitemap.xml')->end()
                ->booleanNode('include_static_pages')->defaultTrue()->end()
                ->booleanNode('include_configured_slugs')->defaultTrue()->end()
            ->end();
    }

    private function addRobotsNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('robots')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('path')->defaultValue('/robots.txt')->end()
                ->scalarNode('user_agent')->defaultValue('*')->end()
                ->arrayNode('allow')
                    ->scalarPrototype()->end()
                    ->defaultValue(['/'])
                ->end()
                ->arrayNode('disallow')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('sitemap_link')->defaultTrue()->end()
            ->end();
    }

    private function addTemplatesNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('templates')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('head')->defaultValue('@NowoSeoKitBundle/seo/head.html.twig')->end()
            ->end();
    }

    private function addSeoFieldNodes(NodeBuilder $children): void
    {
        $children
            ->scalarNode('title')->defaultNull()->end()
            ->scalarNode('description')->defaultNull()->end()
            ->scalarNode('robots')->defaultNull()->end()
            ->scalarNode('canonical')->defaultNull()->end()
            ->scalarNode('keywords')->defaultNull()->end()
            ->scalarNode('author')->defaultNull()->end();
    }

    private function addOpenGraphNode(NodeBuilder $children): void
    {
        $children
            ->arrayNode('open_graph')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('type')->defaultValue('website')->end()
                ->scalarNode('image')->defaultNull()->end()
                ->scalarNode('site_name')->defaultNull()->end()
            ->end();
    }

    private function addTwitterNode(NodeBuilder $children): void
    {
        $children
            ->arrayNode('twitter')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->enumNode('card')
                    ->values(['summary', 'summary_large_image', 'app', 'player'])
                    ->defaultValue('summary_large_image')
                ->end()
                ->scalarNode('site')->defaultNull()->end()
                ->scalarNode('creator')->defaultNull()->end()
                ->scalarNode('image')->defaultNull()->end()
            ->end();
    }

    private function addJsonLdNode(NodeBuilder $children): void
    {
        $children
            ->arrayNode('json_ld')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->variableNode('organization')
                    ->info('Optional Organization schema.org object fields (name, url, logo, ...).')
                    ->defaultValue([])
                ->end()
                ->arrayNode('extra')
                    ->info('Additional JSON-LD objects (list of associative arrays).')
                    ->prototype('variable')->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }
}
