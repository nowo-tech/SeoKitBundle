<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use LogicException;
use Nowo\SeoKitBundle\Model\PageHeadDefaults;
use Nowo\SeoKitBundle\Model\PageHeadInput;
use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use Nowo\SeoKitBundle\Service\HreflangSetBuilder;
use Nowo\SeoKitBundle\Service\PageHeadContext;
use Nowo\SeoKitBundle\Service\PageHeadDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\PageHeadResolver;
use Nowo\SeoKitBundle\Service\PageHeadSiteGraphProviderInterface;
use PHPUnit\Framework\TestCase;

final class PageHeadContextTest extends TestCase
{
    public function testItResolvesTheDescribedPage(): void
    {
        $context = $this->context();

        $context->describe(new PageHeadInput(path: '/contacto', locale: 'es', templateTitle: 'Contacto'));

        self::assertTrue($context->isDescribed());
        self::assertSame('Contacto', $context->pageHead()->title);
        self::assertSame('https://nowo.tech/contacto', $context->pageHead()->canonical);
    }

    public function testItResolvesOnlyOnce(): void
    {
        $context = $this->context();
        $context->describe(new PageHeadInput(path: '/', locale: 'es'));

        self::assertSame($context->pageHead(), $context->pageHead());
    }

    public function testDescribingAgainReplacesThePreviousMetadata(): void
    {
        $context = $this->context();

        $context->describe(new PageHeadInput(path: '/', locale: 'es', templateTitle: 'Home'));
        self::assertSame('Home', $context->pageHead()->title);

        $context->describe(new PageHeadInput(path: '/contacto', locale: 'es', templateTitle: 'Contacto'));
        self::assertSame('Contacto', $context->pageHead()->title);
    }

    public function testRenderingAPageNobodyDescribedFailsLoudly(): void
    {
        $this->expectException(LogicException::class);

        $this->context()->pageHead();
    }

    public function testResetForgetsTheRequest(): void
    {
        $context = $this->context();
        $context->describe(new PageHeadInput(path: '/contacto', locale: 'es'));

        $context->reset();

        self::assertFalse($context->isDescribed());
        $this->expectException(LogicException::class);
        $context->pageHead();
    }

    private function context(): PageHeadContext
    {
        $urls = new AbsoluteUrlBuilder('https://nowo.tech');

        $defaults = self::createStub(PageHeadDefaultsProviderInterface::class);
        $defaults->method('forLocale')->willReturn(new PageHeadDefaults(
            siteName: 'Nowo',
            title: 'Nowo',
            description: 'Platform description.',
            robots: 'index,follow',
        ));

        $structuredData = self::createStub(PageHeadSiteGraphProviderInterface::class);
        $structuredData->method('forLocale')->willReturn(new StructuredDataGraph());

        return new PageHeadContext(new PageHeadResolver(
            $urls,
            new HreflangSetBuilder($urls, ['es', 'en'], 'es'),
            $defaults,
            $structuredData,
            null,
            ['es' => 'ES', 'en' => 'GB'],
        ));
    }
}
