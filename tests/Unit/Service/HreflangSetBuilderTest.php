<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use Nowo\SeoKitBundle\Service\HreflangSetBuilder;
use PHPUnit\Framework\TestCase;

final class HreflangSetBuilderTest extends TestCase
{
    /** @var list<string> */
    private const LOCALES = ['es', 'ca', 'en', 'pt', 'fr', 'it'];

    public function testAlternatesFollowSiteLocaleOrderNotInputOrder(): void
    {
        $set = $this->builder()->build([
            'fr' => '/fr/contact',
            'es' => '/contacto',
            'en' => '/en/contact',
        ]);

        self::assertSame(['es', 'en', 'fr'], array_keys($set->alternates()));
    }

    public function testAlternatesAreAbsoluteUrls(): void
    {
        $set = $this->builder()->build([
            'es' => '/contacto',
            'ca' => '/ca/contacte',
        ]);

        self::assertSame([
            'es' => 'https://nowo.tech/contacto',
            'ca' => 'https://nowo.tech/ca/contacte',
        ], $set->alternates());
    }

    public function testXDefaultPointsAtTheDefaultLocale(): void
    {
        $set = $this->builder()->build([
            'es' => '/contacto',
            'en' => '/en/contact',
        ]);

        self::assertSame('https://nowo.tech/contacto', $set->xDefault());
    }

    public function testUnpublishedLocalesAreAbsentRatherThanPointedAtAFallback(): void
    {
        $set = $this->builder()->build([
            'es' => '/contacto',
            'ca' => '',
        ]);

        self::assertSame(['es'], array_keys($set->alternates()));
    }

    public function testNoXDefaultWhenThePageIsNotPublishedInTheDefaultLocale(): void
    {
        $set = $this->builder()->build([
            'en' => '/en/contact',
            'fr' => '/fr/contact',
        ]);

        self::assertNull($set->xDefault());
        self::assertSame(['en', 'fr'], array_keys($set->alternates()));
    }

    public function testASingleLocalePageIsNotWorthRendering(): void
    {
        $set = $this->builder()->build(['es' => '/contacto']);

        self::assertFalse($set->isEmpty());
        self::assertFalse($set->shouldRender());
    }

    public function testAPageWithoutPublishedLocalesIsEmpty(): void
    {
        $set = $this->builder()->build([]);

        self::assertTrue($set->isEmpty());
        self::assertFalse($set->shouldRender());
        self::assertNull($set->xDefault());
    }

    public function testLocalesOutsideTheSiteSetAreIgnored(): void
    {
        $set = $this->builder()->build([
            'es' => '/contacto',
            'de' => '/de/kontakt',
        ]);

        self::assertSame(['es'], array_keys($set->alternates()));
    }

    private function builder(): HreflangSetBuilder
    {
        return new HreflangSetBuilder(
            new AbsoluteUrlBuilder('https://nowo.tech'),
            self::LOCALES,
            'es',
        );
    }
}
