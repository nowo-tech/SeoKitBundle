<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Model\StructuredData;

use Nowo\SeoKitBundle\Model\StructuredData\BlogPostingNode;
use Nowo\SeoKitBundle\Model\StructuredData\FaqPageNode;
use Nowo\SeoKitBundle\Model\StructuredData\LocalBusinessNode;
use Nowo\SeoKitBundle\Model\StructuredData\PersonNode;
use PHPUnit\Framework\TestCase;

final class LocalBusinessAndContentNodesTest extends TestCase
{
    public function testLocalBusinessPrunesEmptyAndKeepsAddress(): void
    {
        $node = new LocalBusinessNode(
            name: 'Clinic',
            url: 'https://example.com',
            telephone: '+34000',
            address: [
                'street'     => '1 Main',
                'postalCode' => '14800',
                'locality'   => 'Priego',
                'region'     => 'Andalusia',
                'country'    => 'ES',
            ],
            type: ['LocalBusiness', 'MedicalBusiness'],
            email: '',
            sameAs: [],
        );
        $data = $node->toArray();

        self::assertSame(['LocalBusiness', 'MedicalBusiness'], $data['@type']);
        self::assertSame('https://example.com#localbusiness', $data['@id']);
        self::assertArrayNotHasKey('email', $data);
        self::assertArrayNotHasKey('sameAs', $data);
        self::assertSame('PostalAddress', $data['address']['@type']);
        self::assertSame('Priego', $data['areaServed']['name']);
    }

    public function testPersonAndFaqAndBlogPosting(): void
    {
        $person = (new PersonNode(
            name: 'Jane Doe',
            url: 'https://example.com/about',
            worksForId: 'https://example.com#localbusiness',
            jobTitle: 'Podiatrist',
            type: ['Person', 'Physician'],
            medicalSpecialty: 'Podiatry',
        ))->toArray();
        self::assertSame(['Person', 'Physician'], $person['@type']);
        self::assertSame('https://example.com#localbusiness', $person['worksFor']['@id']);

        $faq = (new FaqPageNode('https://example.com/faq', [
            ['name' => 'Q?', 'acceptedAnswer' => 'A.'],
            ['name' => ' ', 'acceptedAnswer' => 'skip'],
        ]))->toArray();
        self::assertSame('FAQPage', $faq['@type']);
        self::assertCount(1, $faq['mainEntity']);
        self::assertSame('Question', $faq['mainEntity'][0]['@type']);

        $post = (new BlogPostingNode(
            headline: 'Gait',
            url: 'https://example.com/blog/gait',
            inLanguage: 'en',
            authorName: 'Jane',
            authorId: 'https://example.com/about#person',
            publisherId: 'https://example.com#localbusiness',
        ))->toArray();
        self::assertSame('BlogPosting', $post['@type']);
        self::assertSame('Jane', $post['author']['name']);
        self::assertSame(['@id' => 'https://example.com#localbusiness'], $post['publisher']);
    }
}
