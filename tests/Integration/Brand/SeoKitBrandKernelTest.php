<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Brand;

use Nowo\SeoKitBundle\Brand\SeoKitBrand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

use function assert;

/**
 * Spec 220 S6 through a real kernel: the host's BrandProviderInterface service reaches the rendered head.
 */
final class SeoKitBrandKernelTest extends TestCase
{
    protected function setUp(): void
    {
        (new Filesystem())->remove(SeoKitBrandTestKernel::baseDir());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(SeoKitBrandTestKernel::baseDir());
    }

    public function testTheBrandNameReachesTheHead(): void
    {
        $html = $this->head(new SeoKitBrandTestKernel(true));

        self::assertStringContainsString('<title>Home | Acme Portal</title>', $html);
        self::assertStringContainsString('<meta property="og:site_name" content="Acme Portal">', $html);
        self::assertStringNotContainsString('og:image', $html, 'Images are opt-in.');
    }

    public function testImagesOptIn(): void
    {
        $html = $this->head(new SeoKitBrandTestKernel(true, [
            'brand'    => ['images' => true],
            'defaults' => ['json_ld' => ['organization' => ['url' => 'https://example.test']]],
        ]));

        self::assertStringContainsString('<meta property="og:image" content="https://example.test/_settings-file/brand/logo_light/0123456789ab.png">', $html);
        self::assertStringContainsString('"logo":"https://example.test/_settings-file/brand/mark/abcdef012345.png"', $html);
        self::assertStringContainsString('"name":"Acme Portal"', $html);
    }

    public function testWithoutProviderOrWithBrandOffTheHeadIsTheConfiguration(): void
    {
        $none = $this->head(new SeoKitBrandTestKernel(false));
        $off  = $this->head(new SeoKitBrandTestKernel(true, ['brand' => 'off']));

        self::assertStringContainsString('<title>Home</title>', $none);
        self::assertStringNotContainsString('og:site_name', $none);
        self::assertSame($none, $off);
    }

    public function testWithBrandOffNoConsumerIsRegistered(): void
    {
        $kernel = new SeoKitBrandTestKernel(true, ['brand' => 'off']);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        assert($container instanceof ContainerInterface);

        self::assertFalse($container->has(SeoKitBrand::class));
        $kernel->shutdown();
    }

    private function head(SeoKitBrandTestKernel $kernel): string
    {
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        assert($container instanceof ContainerInterface);

        $request = Request::create('https://example.test/');
        $request->attributes->set('_route', 'app_home');
        $request->setLocale('en');
        $stack = $container->get('request_stack');
        assert($stack instanceof RequestStack);
        $stack->push($request);

        $twig = $container->get('twig');
        assert($twig instanceof Environment);
        $html = $twig->createTemplate('{{ nowo_seo_head() }}')->render();

        $stack->pop();
        $kernel->shutdown();

        return $html;
    }
}
