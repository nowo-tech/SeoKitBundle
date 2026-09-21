<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Model;

use Nowo\SeoKitBundle\Model\SeoMetadata;
use PHPUnit\Framework\TestCase;

final class SeoMetadataTest extends TestCase
{
    public function testToArrayContainsAllFields(): void
    {
        $metadata = new SeoMetadata(
            title: 'Home | Demo',
            description: 'Welcome',
            robots: 'index,follow',
            canonical: 'https://example.com/',
            alternates: [
                ['locale' => 'es', 'url' => 'https://example.com/es', 'hreflang' => 'es'],
            ],
            openGraph: [
                'enabled'           => true,
                'type'              => 'website',
                'title'             => 'Home | Demo',
                'description'       => 'Welcome',
                'image'             => null,
                'image_width'       => null,
                'image_height'      => null,
                'image_alt'         => null,
                'url'               => 'https://example.com/',
                'site_name'         => 'Demo',
                'locale'            => 'en',
                'locale_alternates' => [],
            ],
            twitter: [
                'enabled'     => true,
                'card'        => 'summary_large_image',
                'title'       => 'Home | Demo',
                'description' => 'Welcome',
                'image'       => null,
                'site'        => null,
                'creator'     => null,
            ],
            jsonLd: ['enabled' => true, 'graph' => [['@type' => 'WebPage']], 'document' => null, 'json' => null],
            keywords: 'demo, seo',
            author: 'Nowo',
            verification: ['google' => 'g-token', 'bing' => 'b-token'],
            extra: ['foo' => 'bar'],
            source: 'pages',
        );

        $array = $metadata->toArray();

        $this->assertSame('Home | Demo', $array['title']);
        $this->assertSame('Welcome', $array['description']);
        $this->assertSame('index,follow', $array['robots']);
        $this->assertSame('https://example.com/', $array['canonical']);
        $this->assertCount(1, $array['alternates']);
        $this->assertTrue($array['open_graph']['enabled']);
        $this->assertSame('summary_large_image', $array['twitter']['card']);
        $this->assertSame('demo, seo', $array['keywords']);
        $this->assertSame('Nowo', $array['author']);
        $this->assertSame(['google' => 'g-token', 'bing' => 'b-token'], $array['verification']);
        $this->assertSame('pages', $array['source']);
        $this->assertSame(['foo' => 'bar'], $array['extra']);
    }
}
