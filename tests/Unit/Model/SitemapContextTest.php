<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Model;

use InvalidArgumentException;
use Nowo\SeoKitBundle\Model\SitemapContext;
use Nowo\SeoKitBundle\Service\SitemapContextFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SitemapContextTest extends TestCase
{
    public function testNormalisesOriginLocaleAndBasePath(): void
    {
        $context = new SitemapContext(' https://www.example.test:8443/ ', ' ES ', 'app/');

        self::assertSame('https://www.example.test:8443', $context->origin);
        self::assertSame('es', $context->locale);
        self::assertSame('/app', $context->basePath);
        self::assertSame('https://www.example.test:8443/app', $context->baseUrl());
    }

    public function testEmptyLocaleAndBasePathMeanNone(): void
    {
        $context = new SitemapContext('http://localhost', '', '/');

        self::assertNull($context->locale);
        self::assertSame('', $context->basePath);
        self::assertSame('http://localhost', $context->baseUrl());
    }

    public function testWithLocaleKeepsOriginAndBasePath(): void
    {
        $context = new SitemapContext('https://example.test', 'en', '/app');
        $es      = $context->withLocale('es');

        self::assertNotSame($context, $es);
        self::assertSame('en', $context->locale);
        self::assertSame('es', $es->locale);
        self::assertSame('https://example.test/app', $es->baseUrl());
        self::assertNull($context->withLocale(null)->locale);
    }

    #[DataProvider('invalidOrigins')]
    public function testRejectsAnythingButAnOrigin(string $origin): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SitemapContext($origin);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidOrigins(): iterable
    {
        yield 'empty' => [''];
        yield 'no scheme' => ['example.test'];
        yield 'path' => ['https://example.test/blog'];
        yield 'query' => ['https://example.test?x=1'];
        yield 'fragment' => ['https://example.test#top'];
        yield 'malformed' => ['http://:80'];
    }

    public function testFactoryReadsTheRequest(): void
    {
        $request = new Request(server: [
            'HTTP_HOST'       => 'shop.example.test:8443',
            'HTTPS'           => 'on',
            'SERVER_PORT'     => 8443,
            'SCRIPT_FILENAME' => '/srv/public/app/index.php',
            'SCRIPT_NAME'     => '/app/index.php',
            'PHP_SELF'        => '/app/index.php',
            'REQUEST_URI'     => '/app/es/catalog',
        ]);
        $request->setLocale('es');

        $context = SitemapContextFactory::fromRequest($request);

        self::assertSame('https://shop.example.test:8443', $context->origin);
        self::assertSame('es', $context->locale);
        self::assertSame('/app', $context->basePath);
    }
}
