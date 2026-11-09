<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\Brand\Contract\BrandAsset;
use Nowo\Brand\Contract\BrandAssetOrigin;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\SeoKitBundle\Brand\SeoKitBrand;
use Nowo\SeoKitBundle\Model\SeoMetadata;
use Nowo\SeoKitBundle\Service\SeoDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Nowo\SeoKitBundle\Service\SeoTemplateRenderer;
use Nowo\SeoKitBundle\Tests\Support\BrandFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function array_replace_recursive;
use function dirname;
use function substr_count;

/**
 * Spec 220 S6: the brand name (and, opt-in, the logo) fills what the configuration leaves empty.
 */
final class SeoMetadataResolverBrandTest extends TestCase
{
    public function testWithoutProviderOrWithAFailingOneTheOutputIsUnchanged(): void
    {
        $config   = $this->config(['defaults' => ['json_ld' => ['organization' => ['url' => 'https://example.com']]]]);
        $baseline = $this->resolve($config, null);

        self::assertSame('Home', $baseline->title);
        self::assertSame('', $baseline->openGraph['site_name']);
        self::assertNull($baseline->openGraph['image']);

        self::assertEquals($baseline, $this->resolve($config, new SeoKitBrand(images: true)));
        self::assertEquals($baseline, $this->resolve($config, new SeoKitBrand(BrandFixtures::failingProvider(), images: true)));
        self::assertSame($this->render($baseline), $this->render($this->resolve($config, new SeoKitBrand(BrandFixtures::failingProvider(), images: true))));
    }

    public function testTheBrandNameFillsAnEmptySiteName(): void
    {
        $meta = $this->resolve($this->config(), $this->brand());

        self::assertSame('Home | Acme Portal', $meta->title);
        self::assertSame('Acme Portal', $meta->openGraph['site_name']);
        self::assertStringContainsString('<meta property="og:site_name" content="Acme Portal">', $this->render($meta));
    }

    public function testTheBrandNameFillsAWhitespaceSiteName(): void
    {
        $meta = $this->resolve($this->config(['defaults' => ['site_name' => '  ']]), $this->brand());

        self::assertSame('Acme Portal', $meta->openGraph['site_name']);
    }

    public function testTheBrandNameIsTheTitleOfAPageWithoutTitle(): void
    {
        $config = $this->config(['pages' => ['app_home' => ['title' => null]]]);

        self::assertSame('Acme Portal | Acme Portal', $this->resolve($config, $this->brand())->title);
    }

    public function testAConfiguredSiteNameWins(): void
    {
        $meta = $this->resolve($this->config(['defaults' => ['site_name' => 'Configured']]), $this->brand());

        self::assertSame('Home | Configured', $meta->title);
        self::assertSame('Configured', $meta->openGraph['site_name']);
    }

    public function testAConfiguredOpenGraphSiteNameWins(): void
    {
        $meta = $this->resolve($this->config(['defaults' => ['open_graph' => ['site_name' => 'OG Name']]]), $this->brand());

        self::assertSame('Home | Acme Portal', $meta->title);
        self::assertSame('OG Name', $meta->openGraph['site_name']);
    }

    public function testASiteNameFromADefaultsProviderWins(): void
    {
        $provider = new class implements SeoDefaultsProviderInterface {
            public function getDefaults(): array
            {
                return ['site_name' => 'From Database'];
            }
        };

        $meta = $this->resolve($this->config(), $this->brand(), [$provider]);

        self::assertSame('From Database', $meta->openGraph['site_name']);
    }

    public function testTheDefaultBrandNameIsUsedToo(): void
    {
        $brand = new SeoKitBrand(BrandFixtures::provider(BrandFixtures::brand('Default Co', isDefault: true)));

        self::assertSame('Default Co', $this->resolve($this->config(), $brand)->openGraph['site_name']);
    }

    public function testTheBrandNameFillsTheOrganizationName(): void
    {
        $config = $this->config(['defaults' => ['json_ld' => ['organization' => ['url' => 'https://example.com']]]]);
        $graph  = $this->resolve($config, $this->brand())->jsonLd['graph'];

        self::assertSame(['@type' => 'Organization', 'url' => 'https://example.com', 'name' => 'Acme Portal'], $graph[0]);
    }

    public function testAConfiguredOrganizationNameWinsAndNoOrganizationIsInvented(): void
    {
        $config = $this->config(['defaults' => ['json_ld' => ['organization' => ['name' => 'Acme Ltd']]]]);
        self::assertSame(['@type' => 'Organization', 'name' => 'Acme Ltd'], $this->resolve($config, $this->brand())->jsonLd['graph'][0]);

        $graph = $this->resolve($this->config(), $this->brand())->jsonLd['graph'];
        self::assertCount(1, $graph);
        self::assertSame('WebPage', $graph[0]['@type']);
    }

    public function testImagesStayOffByDefault(): void
    {
        $config = $this->config(['defaults' => ['json_ld' => ['organization' => ['name' => 'Acme Ltd']]]]);
        $meta   = $this->resolve($config, $this->brand(images: false));

        self::assertNull($meta->openGraph['image']);
        self::assertNull($meta->twitter['image']);
        self::assertArrayNotHasKey('logo', $meta->jsonLd['graph'][0]);
    }

    public function testTheLogoIsTheFallbackOgImageWithAnAbsoluteUrlAndItsSize(): void
    {
        $meta = $this->resolve($this->config(), $this->brand(images: true));

        self::assertSame('https://example.com/_settings-file/brand/logo_light/0123456789ab.png', $meta->openGraph['image']);
        self::assertSame(1200, $meta->openGraph['image_width']);
        self::assertSame(400, $meta->openGraph['image_height']);
        self::assertSame($meta->openGraph['image'], $meta->twitter['image']);

        $html = $this->render($meta);
        self::assertStringContainsString('<meta property="og:image" content="https://example.com/_settings-file/brand/logo_light/0123456789ab.png">', $html);
        self::assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
    }

    public function testWithoutBaseUrlTheRequestOriginIsUsed(): void
    {
        $config = $this->config();
        unset($config['base_url']);

        $meta = $this->resolve($config, $this->brand(images: true), [], Request::create('http://localhost:8000/'));

        self::assertSame('http://localhost:8000/_settings-file/brand/logo_light/0123456789ab.png', $meta->openGraph['image']);
    }

    public function testAnHttpsAssetUrlIsKeptAsIs(): void
    {
        $logo  = BrandFixtures::asset(url: 'https://cdn.example.net/acme/logo.webp', mimeType: 'image/webp');
        $brand = new SeoKitBrand(BrandFixtures::provider(BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => $logo])), images: true);

        self::assertSame('https://cdn.example.net/acme/logo.webp', $this->resolve($this->config(), $brand)->openGraph['image']);
    }

    public function testAConfiguredOgImageOrSizeWins(): void
    {
        $config = $this->config(['defaults' => ['open_graph' => ['image' => '/og.png', 'image_width' => 800, 'image_height' => 420]]]);
        $meta   = $this->resolve($config, $this->brand(images: true));
        self::assertSame('https://example.com/og.png', $meta->openGraph['image']);
        self::assertSame(800, $meta->openGraph['image_width']);

        $page = $this->config(['pages' => ['app_home' => ['og_image' => '/page.png']]]);
        $meta = $this->resolve($page, $this->brand(images: true));
        self::assertSame('https://example.com/page.png', $meta->openGraph['image']);
        self::assertNull($meta->openGraph['image_width'], 'No brand size on an image that is not the brand logo.');

        $sized = $this->config(['defaults' => ['open_graph' => ['image_width' => 640]]]);
        self::assertSame(640, $this->resolve($sized, $this->brand(images: true))->openGraph['image_width']);
    }

    public function testASvgLogoIsNeverTheOgImage(): void
    {
        $svg   = BrandFixtures::asset(BrandAssetSlot::LOGO_LIGHT, '/brand/logo-light.svg', 'image/svg+xml', null, null, BrandAssetOrigin::ConfigDefault);
        $brand = new SeoKitBrand(BrandFixtures::provider(BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => $svg])), images: true);
        $meta  = $this->resolve($this->config(['defaults' => ['json_ld' => ['organization' => ['name' => 'Acme']]]]), $brand);

        self::assertNull($meta->openGraph['image']);
        self::assertArrayNotHasKey('logo', $meta->jsonLd['graph'][0]);
        self::assertStringNotContainsString('.svg', $this->render($meta));
    }

    public function testABadBaseUrlYieldsNoBrandImage(): void
    {
        $meta = $this->resolve($this->config(['base_url' => 'https://example.com/sub']), $this->brand(images: true));

        self::assertNull($meta->openGraph['image']);
    }

    public function testTheMarkIsTheOrganizationLogo(): void
    {
        $config = $this->config(['defaults' => ['json_ld' => ['organization' => ['name' => 'Acme Ltd']]]]);
        $org    = $this->resolve($config, $this->brand(images: true))->jsonLd['graph'][0];

        self::assertSame('https://example.com/_settings-file/brand/mark/abcdef012345.png', $org['logo']);

        $configured = $this->config(['defaults' => ['json_ld' => ['organization' => ['name' => 'Acme Ltd', 'logo' => 'https://example.com/own.png']]]]);
        self::assertSame('https://example.com/own.png', $this->resolve($configured, $this->brand(images: true))->jsonLd['graph'][0]['logo']);
    }

    public function testNoBrandLogoMeansNoOrganizationLogo(): void
    {
        $brand  = new SeoKitBrand(BrandFixtures::provider(BrandFixtures::brand()), images: true);
        $config = $this->config(['defaults' => ['json_ld' => ['organization' => ['name' => 'Acme Ltd']]]]);

        self::assertArrayNotHasKey('logo', $this->resolve($config, $brand)->jsonLd['graph'][0]);
    }

    public function testAHostileBrandNameIsEncodedInTheHeadAndTheJsonLd(): void
    {
        $brand  = new SeoKitBrand(BrandFixtures::provider(BrandFixtures::brand('Acme </script><!-- & "Co" \'x\'')));
        $config = $this->config(['defaults' => ['json_ld' => ['organization' => ['url' => 'https://example.com']]]]);
        $html   = $this->render($this->resolve($config, $brand));

        self::assertSame(1, substr_count($html, '</script>'), 'The name cannot close the JSON-LD script element.');
        self::assertStringNotContainsString('<!--', $html);
        self::assertStringContainsString('Acme \\u003C/script\\u003E\\u003C!-- \\u0026 \\u0022Co\\u0022 \\u0027x\\u0027', $html);
        self::assertStringContainsString('<meta property="og:site_name" content="Acme &lt;/script&gt;&lt;!-- &amp; &quot;Co&quot; &#039;x&#039;">', $html);
    }

    public function testTheProviderIsAskedOncePerResolve(): void
    {
        $provider = BrandFixtures::provider(BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => BrandFixtures::asset()]));
        $config   = $this->config(['defaults' => ['json_ld' => ['organization' => ['url' => 'https://example.com']]]]);
        $this->resolve($config, new SeoKitBrand($provider, images: true));

        self::assertSame(1, $provider->calls);
    }

    private function brand(bool $images = false): SeoKitBrand
    {
        $assets = [BrandAssetSlot::LOGO_LIGHT => BrandFixtures::asset(), BrandAssetSlot::MARK => BrandFixtures::mark()];

        return new SeoKitBrand($this->provider($assets), images: $images);
    }

    /**
     * @param array<string, BrandAsset> $assets
     */
    private function provider(array $assets): BrandProviderInterface
    {
        return BrandFixtures::provider(BrandFixtures::brand(assets: $assets));
    }

    /**
     * @param array<string, mixed> $config
     * @param iterable<SeoDefaultsProviderInterface> $defaultsProviders
     */
    private function resolve(array $config, ?SeoKitBrand $brand, iterable $defaultsProviders = [], ?Request $request = null): SeoMetadata
    {
        $request ??= Request::create('https://example.com/');
        $request->attributes->set('_route', 'app_home');
        $request->setLocale('en');
        $stack = new RequestStack();
        $stack->push($request);

        return (new SeoMetadataResolver(
            $config,
            $stack,
            new SeoRuntime(),
            new SeoTemplateRenderer(),
            new SeoPathBuilder($config),
            $this->createMock(UrlGeneratorInterface::class),
            $defaultsProviders,
            $brand,
        ))->resolve();
    }

    private function render(SeoMetadata $meta): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/src/Resources/views'));

        return $twig->render('seo/head.html.twig', ['seo_include_title' => true, 'seo' => $meta]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return array_replace_recursive([
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'base_url'       => 'https://example.com',
            'defaults'       => [
                'site_name'          => '',
                'title_separator'    => ' | ',
                'title_template'     => '{title}{separator}{site_name}',
                'canonical_enabled'  => true,
                'hreflang_enabled'   => false,
                'x_default_hreflang' => false,
                'open_graph'         => ['enabled' => true, 'type' => 'website', 'image' => null, 'image_width' => null, 'image_height' => null, 'site_name' => null],
                'twitter'            => ['enabled' => true, 'card' => 'summary_large_image', 'site' => null, 'creator' => null, 'image' => null],
                'json_ld'            => ['enabled' => true, 'organization' => [], 'extra' => []],
            ],
            'pages' => [
                'app_home' => ['title' => 'Home', 'path' => '/'],
            ],
            'slug_routes' => [],
            'slugs'       => [],
        ], $overrides);
    }
}
