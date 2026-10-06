<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\ConfigPageHeadDefaultsProvider;
use PHPUnit\Framework\TestCase;

final class ConfigPageHeadDefaultsProviderTest extends TestCase
{
    public function testEmptyConfigUsesSiteFallbacks(): void
    {
        $defaults = (new ConfigPageHeadDefaultsProvider([]))->forLocale('es');

        self::assertSame('', $defaults->siteName);
        self::assertSame('', $defaults->title);
        self::assertSame('', $defaults->description);
        self::assertSame('index, follow', $defaults->robots);
        self::assertNull($defaults->ogImage);
        self::assertNull($defaults->ogImageWidth);
        self::assertNull($defaults->ogImageHeight);
    }

    public function testMapsYamlDefaultsAndOpenGraphImage(): void
    {
        $defaults = (new ConfigPageHeadDefaultsProvider([
            'defaults' => [
                'site_name'   => 'Nowo',
                'title'       => 'Home',
                'description' => 'SEO kit',
                'robots'      => 'noindex, follow',
                'open_graph'  => [
                    'image'        => 'https://example.test/og.png',
                    'image_width'  => 800,
                    'image_height' => 400,
                ],
            ],
        ]))->forLocale('en');

        self::assertSame('Nowo', $defaults->siteName);
        self::assertSame('Home', $defaults->title);
        self::assertSame('SEO kit', $defaults->description);
        self::assertSame('noindex, follow', $defaults->robots);
        self::assertSame('https://example.test/og.png', $defaults->ogImage);
        self::assertSame(800, $defaults->ogImageWidth);
        self::assertSame(400, $defaults->ogImageHeight);
    }

    public function testTitleFallsBackToSiteNameWhenBlank(): void
    {
        $defaults = (new ConfigPageHeadDefaultsProvider([
            'defaults' => [
                'site_name' => 'Nowo',
                'title'     => '',
            ],
        ]))->forLocale('en');

        self::assertSame('Nowo', $defaults->title);
    }

    public function testIgnoresNonArrayAndNonStringLeaves(): void
    {
        $defaults = (new ConfigPageHeadDefaultsProvider([
            'defaults' => [
                'site_name'   => 1,
                'title'       => 1,
                'description' => 1,
                'robots'      => '',
                'open_graph'  => 'nope',
            ],
        ]))->forLocale('en');

        self::assertSame('', $defaults->siteName);
        self::assertSame('', $defaults->title);
        self::assertSame('', $defaults->description);
        self::assertSame('index, follow', $defaults->robots);
        self::assertNull($defaults->ogImage);
    }

    public function testOgImageUsesDefaultDimensionsWhenWidthIsNotInt(): void
    {
        $defaults = (new ConfigPageHeadDefaultsProvider([
            'defaults' => [
                'open_graph' => [
                    'image'       => 'https://example.test/og.png',
                    'image_width' => '800',
                ],
            ],
        ]))->forLocale('en');

        self::assertSame(1200, $defaults->ogImageWidth);
        self::assertSame(630, $defaults->ogImageHeight);
    }
}
