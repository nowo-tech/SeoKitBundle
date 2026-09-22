<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Model\PageHeadDefaults;
use Nowo\SeoKitBundle\Model\PageHeadInput;
use Nowo\SeoKitBundle\Model\StructuredData\BreadcrumbListNode;
use Nowo\SeoKitBundle\Model\StructuredData\OrganizationNode;
use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use Nowo\SeoKitBundle\Service\HreflangSetBuilder;
use Nowo\SeoKitBundle\Service\PageHeadDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\PageHeadResolver;
use Nowo\SeoKitBundle\Service\PageHeadSiteGraphProviderInterface;
use Nowo\SeoKitBundle\Service\PageHeadTitleComposerInterface;
use Override;
use PHPUnit\Framework\TestCase;

final class PageHeadResolverTest extends TestCase
{
    public function testTheEditorOverrideWinsOverTheTemplateAndTheSiteDefault(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/productos',
            locale: 'es',
            titleOverride: 'Editor title',
            templateTitle: 'Template title',
        ));

        self::assertSame('Editor title', $meta->title);
    }

    public function testTheTemplateValueWinsWhenThereIsNoOverride(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/productos',
            locale: 'es',
            templateTitle: 'Template title',
        ));

        self::assertSame('Template title · Nowo', $meta->title);
    }

    public function testTheSiteDefaultIsUsedWhenNothingElseSuppliesAValue(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(path: '/productos', locale: 'es'));

        self::assertSame('Site title · Nowo', $meta->title);
        self::assertSame('Site description', $meta->description);
    }

    public function testBlankOverridesDoNotShadowLowerLevels(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/productos',
            locale: 'es',
            titleOverride: '   ',
            templateTitle: 'Template title',
        ));

        self::assertSame('Template title · Nowo', $meta->title);
    }

    public function testTheSitePatternIsNotAppliedToATitleTypedByHand(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/productos',
            locale: 'es',
            titleOverride: 'Exactly the width I want',
        ));

        self::assertSame('Exactly the width I want', $meta->title);
    }

    public function testThePatternAnIntegratorConfiguredIsTheOneApplied(): void
    {
        $resolver = $this->resolver(composer: new PatternTitleComposer('%page% | %site%'));

        $meta = $resolver->resolve(new PageHeadInput(path: '/productos', locale: 'es', templateTitle: 'Products'));

        self::assertSame('Products | Nowo', $meta->title);
    }

    public function testHomeCanUseADedicatedTitle(): void
    {
        $resolver = $this->resolver(composer: new PatternTitleComposer(homeTitle: 'Nowo — insurance software'));

        $meta = $resolver->resolve(new PageHeadInput(path: '/', locale: 'es', templateTitle: 'Home', isHome: true));

        self::assertSame('Nowo — insurance software', $meta->title);
    }

    public function testAFieldNoLevelSuppliesResolvesToEmptyRatherThanToAPlaceholder(): void
    {
        $resolver = $this->resolver(defaults: new PageHeadDefaults(
            siteName: 'Nowo',
            title: 'Site title',
            description: '',
            robots: 'noindex,nofollow',
        ));

        $meta = $resolver->resolve(new PageHeadInput(path: '/productos', locale: 'es'));

        self::assertSame('', $meta->description);
        self::assertSame('', $meta->ogDescription);
    }

    public function testCanonicalIsDerivedFromTheRequestPath(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(path: '/ca/productes', locale: 'ca'));

        self::assertSame('https://nowo.tech/ca/productes', $meta->canonical);
    }

    public function testAStoredCanonicalOverrideIsHonoured(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/productos/caucion',
            locale: 'es',
            canonicalOverride: 'https://nowo.tech/productos',
        ));

        self::assertSame('https://nowo.tech/productos', $meta->canonical);
    }

    public function testRobotsFallsBackToTheSiteDirective(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(path: '/productos', locale: 'es'));

        self::assertSame('noindex,nofollow', $meta->robots);
        self::assertFalse($meta->isIndexable());
    }

    public function testAnIndexablePageIsReportedAsSuch(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/productos',
            locale: 'es',
            robotsOverride: 'index, follow',
        ));

        self::assertTrue($meta->isIndexable());
    }

    public function testOpenGraphFallsBackToTheResolvedTitleAndDescription(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/productos',
            locale: 'es',
            titleOverride: 'Products',
            descriptionOverride: 'Product catalogue',
        ));

        self::assertSame('Products', $meta->ogTitle);
        self::assertSame('Product catalogue', $meta->ogDescription);
        self::assertSame('website', $meta->ogType);
        self::assertSame('Nowo', $meta->ogSiteName);
    }

    public function testOpenGraphLocaleCarriesTheRegionAndListsPublishedSiblings(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/contacto',
            locale: 'es',
            pathsByLocale: ['es' => '/contacto', 'ca' => '/ca/contacte', 'en' => '/en/contact'],
        ));

        self::assertSame('es_ES', $meta->ogLocale);
        self::assertSame(['ca_ES', 'en_GB'], $meta->ogLocaleAlternates);
    }

    public function testTheTwitterCardDropsToSummaryWithoutAnImage(): void
    {
        $resolver = $this->resolver(defaults: new PageHeadDefaults(
            siteName: 'Nowo',
            title: 'Site title',
            description: 'Site description',
            robots: 'noindex,nofollow',
        ));

        $meta = $resolver->resolve(new PageHeadInput(path: '/productos', locale: 'es'));

        self::assertNull($meta->ogImage);
        self::assertNull($meta->ogImageWidth);
        self::assertSame('summary', $meta->twitterCard);
    }

    public function testPageNodesAreAppendedToTheSiteWideGraph(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/productos',
            locale: 'es',
            structuredData: [new BreadcrumbListNode([['name' => 'Home', 'url' => 'https://nowo.tech']])],
        ));

        $types = array_column($meta->structuredData->toArray()['@graph'], '@type');

        self::assertSame(['Organization', 'BreadcrumbList'], $types);
    }

    public function testThePageTypeAndTheTrailJoinTheSiteWideGraph(): void
    {
        $meta = $this->resolver()->resolve(new PageHeadInput(
            path: '/producto/core',
            locale: 'es',
            templateTitle: 'Core product',
            templateDescription: 'From opportunity to bordereau.',
            breadcrumbs: [
                ['name' => 'Home', 'path' => '/'],
                ['name' => 'Core', 'path' => '/producto/core'],
            ],
            schemaType: 'SoftwareApplication',
        ));

        $graph = $meta->structuredData->toArray()['@graph'];
        $types = array_column($graph, '@type');

        self::assertSame(['Organization', 'BreadcrumbList', 'SoftwareApplication'], $types);
        self::assertSame('https://nowo.tech/', $graph[1]['itemListElement'][0]['item']);
        self::assertSame('BusinessApplication', $graph[2]['applicationCategory']);
        self::assertSame('Home', $meta->breadcrumbs[0]['name']);

        $page = $this->resolver()->resolve(new PageHeadInput(
            path: '/contacto',
            locale: 'es',
            templateTitle: 'Contact',
            schemaType: 'WebPage',
        ));

        self::assertContains('WebPage', array_column($page->structuredData->toArray()['@graph'], '@type'));
    }

    private function resolver(
        ?PageHeadDefaults $defaults = null,
        ?PageHeadTitleComposerInterface $composer = null,
    ): PageHeadResolver {
        $urls = new AbsoluteUrlBuilder('https://nowo.tech');
        $defaults ??= new PageHeadDefaults(
            siteName: 'Nowo',
            title: 'Site title',
            description: 'Site description',
            robots: 'noindex,nofollow',
            ogImage: 'https://nowo.tech/brand/og-default.png',
        );

        $defaultsProvider = $this->createStub(PageHeadDefaultsProviderInterface::class);
        $defaultsProvider->method('forLocale')->willReturn($defaults);

        $siteGraph = $this->createStub(PageHeadSiteGraphProviderInterface::class);
        $siteGraph->method('forLocale')->willReturn(
            new StructuredDataGraph(new OrganizationNode('Nowo', 'https://nowo.tech')),
        );

        return new PageHeadResolver(
            $urls,
            new HreflangSetBuilder($urls, ['es', 'ca', 'en', 'pt', 'fr', 'it'], 'es'),
            $defaultsProvider,
            $siteGraph,
            $composer ?? new PatternTitleComposer(),
            ['es' => 'ES', 'ca' => 'ES', 'en' => 'GB', 'pt' => 'PT', 'fr' => 'FR', 'it' => 'IT'],
        );
    }
}

/**
 * Minimal title composer for kit tests (`%page%` / `%site%`, optional home title).
 */
final readonly class PatternTitleComposer implements PageHeadTitleComposerInterface
{
    public function __construct(
        private string $pattern = '%page% · %site%',
        private ?string $homeTitle = null,
    ) {
    }

    #[Override]
    public function compose(PageHeadInput $input, PageHeadDefaults $defaults): string
    {
        $typed = self::clean($input->titleOverride);
        if (null !== $typed) {
            return $typed;
        }

        if ($input->isHome && null !== $this->homeTitle && '' !== $this->homeTitle) {
            return $this->homeTitle;
        }

        $page = self::clean($input->templateTitle) ?? self::clean($defaults->title) ?? '';

        return str_replace(['%page%', '%site%'], [$page, $defaults->siteName], $this->pattern);
    }

    private static function clean(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
