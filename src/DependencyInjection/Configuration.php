<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection;

use Nowo\SeoKitBundle\Enum\SitemapChangeFreq;
use Nowo\SeoKitBundle\Enum\TwitterCard;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function in_array;
use function is_string;
use function preg_match;
use function sprintf;
use function trim;

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

    /** Security attributes that grant anonymous users: refused in security.access_roles. */
    public const PUBLIC_ATTRIBUTES = ['PUBLIC_ACCESS', 'IS_AUTHENTICATED_ANONYMOUSLY'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->booleanNode('indexable')
                    ->info('Master switch: when false, defaults robots to noindex, robots.txt disallows /, and sitemap is empty/404.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('x_robots_tag')
                    ->info('Send the page\'s resolved robots value as an X-Robots-Tag response header (every response: HTML, JSON, files…). A response that already carries the header keeps it.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                    ->end()
                ->end()
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
        $this->addPageHeadNode($root);
        $this->addPersistenceNode($root);
        $this->addDoctrineNode($root);
        $this->addWebUiNode($root);
        $this->addSecurityNode($root);
        $this->addLegacyAdminNode($root);

        return $treeBuilder;
    }

    private function addPageHeadNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('page_head')
            ->info('Layered PageHead resolution for CMS hosts.')
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('open_graph_regions')
                    ->useAttributeAsKey('locale')
                    ->scalarPrototype()->end()
                    ->defaultValue([
                        'es' => 'ES', 'ca' => 'ES', 'en' => 'GB',
                        'pt' => 'PT', 'fr' => 'FR', 'it' => 'IT',
                    ])
                ->end()
            ->end()
            ->end()
        ->end();
    }

    private function addDoctrineNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('doctrine')
            ->info('Doctrine options for the bundle-owned entities (persistence.enabled).')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('table_prefix')
                    ->info('Prepended to every SeoKit table and named unique constraint (REQ-ORM-002), e.g. "app_" yields app_nowo_seo_surface. Empty = unchanged.')
                    ->defaultValue('')
                    ->validate()
                        ->ifTrue(static fn (mixed $v): bool => !is_string($v) || preg_match('/^[A-Za-z0-9_]*$/', $v) !== 1)
                        ->thenInvalid('nowo_seo_kit.doctrine.table_prefix may only contain letters, digits and underscores; got %s.')
                    ->end()
                ->end()
            ->end()
            ->end()
        ->end();
    }

    private function addPersistenceNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('persistence')
            ->info('Optional Doctrine-backed site settings + surface overrides (requires doctrine/orm).')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('When true, register SeoSiteSettings / SeoSurface entities, config provider, and defaults SPI.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('fallback_robots')->defaultValue('index, follow')->end()
                ->scalarNode('fallback_contact_email')->defaultValue('')->end()
                ->scalarNode('fallback_site_name')->defaultValue('')->end()
                ->booleanNode('register_audit_command')
                    ->info('Register nowo:seo:audit (always available when persistence is enabled; uses tagged audit subjects).')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    private function addWebUiNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('web_ui')
            ->info('Optional admin UI + JSON API for site settings and surface overrides (requires persistence.enabled). UiKit page contract (REQ-UI-001 / REQ-UI-003).')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')
                    ->info('Register the admin pages and the JSON API (routes from the nowo_seo_kit_admin loader).')
                    ->defaultFalse()
                ->end()
                ->booleanNode('settings')
                    ->info('Register the site settings page.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('surfaces')
                    ->info('Register the surfaces list + edit pages.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('path_prefix')
                    ->info('URL prefix of the admin pages: <prefix>/settings, <prefix>/surfaces. Null (default) keeps the 1.x paths /settings/seo and /admin/seo/surfaces. The JSON API stays under /_nowo/seo/surfaces.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => $value !== null && (!is_string($value) || preg_match('#^(/(?!\.{1,2}(?:/|$))[A-Za-z0-9._~-]+)+$#', $value) !== 1))
                        ->thenInvalid('nowo_seo_kit.web_ui.path_prefix must be null or start with "/" and contain URL path segments only (no "." / ".." segment), got %s.')
                    ->end()
                ->end()
                ->scalarNode('layout_template')
                    ->info('Layout the page scaffolds extend. Null (default) = the UiKit admin layout (nowo_ui_kit.layouts.admin). A host layout must render the UiKit canonical blocks (nowo_ui_content, …).')
                    ->defaultNull()
                ->end()
                ->arrayNode('templates')
                    ->info('Per-page template overrides: a host template that extends the bundle page and overrides a data block (see docs/TEMPLATES.md).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('settings')->defaultValue(SeoKitPages::TEMPLATES['settings'])->cannotBeEmpty()->end()
                        ->scalarNode('surfaces_index')->defaultValue(SeoKitPages::TEMPLATES['surfaces_index'])->cannotBeEmpty()->end()
                        ->scalarNode('surfaces_form')->defaultValue(SeoKitPages::TEMPLATES['surfaces_form'])->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('form_themes')
                    ->info('Form themes applied to the admin forms (default: the UiKit form layout; the CSS stack comes from nowo_ui_kit.css_framework).')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([SeoKitPages::DEFAULT_FORM_THEME])
                ->end()
                ->booleanNode('api_enabled')
                    ->info('Register the JSON pencil API under /_nowo/seo/surfaces/{key}/{locale}.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('api_csrf_protection')
                    ->info('Require a CSRF token (X-CSRF-Token header, from the GET response csrf_token) on JSON API writes. Disable only behind a Bearer/API-token firewall without session cookies.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    private function addSecurityNode(ArrayNodeDefinition $root): void
    {
        $root->children()
            ->arrayNode('security')
            ->info('Access to the admin UI and the JSON API (REQ-UI-002). Production: keep allow_unauthenticated false and add access_control for the admin paths.')
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('access_roles')
                    ->info('The user needs at least one of these roles (default checker). An empty list is refused unless access_checker is set.')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $value): bool => !is_string($value) || trim($value) === '' || in_array($value, self::PUBLIC_ATTRIBUTES, true))
                            ->thenInvalid('nowo_seo_kit.security.access_roles entries must be non-empty literal role names (no %%env()%% placeholder, not PUBLIC_ACCESS / IS_AUTHENTICATED_ANONYMOUSLY; use allow_unauthenticated for a demo), got %s.')
                        ->end()
                    ->end()
                    ->defaultValue(['ROLE_ADMIN'])
                ->end()
                ->scalarNode('access_checker')
                    ->info('Service id implementing Nowo\\SeoKitBundle\\Security\\SeoKitAccessCheckerInterface. Null (default) = role checker over access_roles.')
                    ->defaultNull()
                ->end()
                ->booleanNode('allow_unauthenticated')
                    ->info('DEV / DEMO only: the admin UI and the JSON API become public (no SecurityBundle needed). Never in production.')
                    ->defaultFalse()
                ->end()
            ->end();
    }

    /**
     * 1.x `admin` node (deprecated since 1.19, removed in 2.0): every key maps onto `web_ui.*` / `security.*` in
     * {@see SeoKitExtension::resolveWebUi()}; a legacy key that is set wins over the new default.
     */
    private function addLegacyAdminNode(ArrayNodeDefinition $root): void
    {
        $message = 'The "%%path%%.%%node%%" option is deprecated; use "%s" instead (see docs/UPGRADING.md).';
        $map     = [
            'enabled'                 => 'nowo_seo_kit.web_ui.enabled',
            'settings'                => 'nowo_seo_kit.web_ui.settings',
            'surfaces'                => 'nowo_seo_kit.web_ui.surfaces',
            'role'                    => 'nowo_seo_kit.security.access_roles',
            'settings_template'       => 'nowo_seo_kit.web_ui.templates.settings',
            'surfaces_index_template' => 'nowo_seo_kit.web_ui.templates.surfaces_index',
            'surfaces_form_template'  => 'nowo_seo_kit.web_ui.templates.surfaces_form',
            'api_enabled'             => 'nowo_seo_kit.web_ui.api_enabled',
            'api_csrf_protection'     => 'nowo_seo_kit.web_ui.api_csrf_protection',
        ];

        $children = $root->children()
            ->arrayNode('admin')
            ->info('Deprecated since 1.19: use web_ui.* and security.* (mapped for 1.x, removed in 2.0).')
            ->addDefaultsIfNotSet()
            ->children();

        foreach ($map as $key => $replacement) {
            $node = in_array($key, ['enabled', 'settings', 'surfaces', 'api_enabled', 'api_csrf_protection'], true)
                ? $children->booleanNode($key)
                : $children->scalarNode($key);
            $node->defaultNull()->setDeprecated('nowo-tech/seo-kit-bundle', '1.19', sprintf($message, $replacement))->end();
        }
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
            ->booleanNode('x_default_hreflang')->defaultTrue()->end()
            ->arrayNode('verification')
                ->info('Webmaster verification tokens emitted as meta tags.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('google')->defaultNull()->end()
                    ->scalarNode('bing')->defaultNull()->end()
                ->end()
            ->end();
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
                ->values(SitemapChangeFreq::values())
                ->defaultValue(SitemapChangeFreq::Weekly->value)
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
                ->values(SitemapChangeFreq::values())
                ->defaultValue(SitemapChangeFreq::Weekly->value)
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
                ->arrayNode('groups')
                    ->info('Extra robots.txt groups after the default User-agent block. Prefer nowo-tech/generative-seo-kit-bundle (GeoRobotsGroupsProviderInterface) for AI crawler policy; do not duplicate the same User-agent here.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('user_agent')->isRequired()->cannotBeEmpty()->end()
                            ->arrayNode('allow')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('disallow')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
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
                ->booleanNode('head_includes_title')
                    ->info('When false, the head partial omits <title> so the host layout can own the title block.')
                    ->defaultTrue()
                ->end()
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
                ->integerNode('image_width')->min(1)->defaultNull()->end()
                ->integerNode('image_height')->min(1)->defaultNull()->end()
                ->scalarNode('image_alt')->defaultNull()->end()
                ->scalarNode('site_name')->defaultNull()->end()
                ->arrayNode('locale_alternates')
                    ->info('Additional og:locale:alternate values (xx_XX).')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
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
                    ->values(TwitterCard::values())
                    ->defaultValue(TwitterCard::SummaryLargeImage->value)
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
