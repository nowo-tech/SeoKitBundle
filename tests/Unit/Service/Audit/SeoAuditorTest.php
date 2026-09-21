<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service\Audit;

use Nowo\SeoKitBundle\Model\SeoSiteConfig;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditor;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditRow;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditRules;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditSubjectProviderInterface;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use PHPUnit\Framework\TestCase;

final class SeoAuditorTest extends TestCase
{
    public function testBuildsRowsFromSubjectsAndAnnotatesDuplicates(): void
    {
        $subjects = $this->createMock(SeoAuditSubjectProviderInterface::class);
        $subjects->method('subjectsForLocale')->with('en')->willReturn([
            [
                'surface_key'  => 'a',
                'locale'       => 'en',
                'path'         => '/',
                'title'        => 'Same',
                'description'  => 'desc-a-enough-chars-to-pass-min-length-check-here',
                'robots'       => 'index, follow',
                'published'    => true,
                'has_override' => false,
            ],
            [
                'surface_key'  => 'b',
                'locale'       => 'en',
                'path'         => '/b',
                'title'        => 'Same',
                'description'  => 'desc-b-enough-chars-to-pass-min-length-check-here',
                'robots'       => 'index, follow',
                'published'    => true,
                'has_override' => true,
            ],
        ]);

        $auditor = new SeoAuditor(new SeoAuditRules(), [$subjects]);
        $rows    = $auditor->forLocale('en');

        self::assertCount(2, $rows);
        self::assertContains('duplicate_title', $rows[0]->problems());
        self::assertTrue($rows[1]->hasOverride);
    }

    public function testUsesConfigProviderIndexabilityWhenPresent(): void
    {
        $config = new SeoSiteConfig(
            siteName: 'Nowo',
            titleTemplate: '%page%',
            indexable: false,
            robots: 'noindex, nofollow',
            openGraphImage: null,
            twitterSite: null,
            organisationLegalName: null,
            organisationLogo: null,
            contactEmail: null,
            contactPhone: null,
            streetAddress: null,
            postalCode: null,
            addressLocality: null,
            addressRegion: null,
            addressCountry: null,
            socialProfiles: [],
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [],
        );

        $configProvider = $this->createMock(SeoSiteConfigProviderInterface::class);
        $configProvider->method('get')->willReturn($config);

        $subjects = $this->createMock(SeoAuditSubjectProviderInterface::class);
        $subjects->method('subjectsForLocale')->willReturn([
            [
                'surface_key'  => 'home',
                'locale'       => 'en',
                'path'         => '/',
                'title'        => 'Home title long enough',
                'description'  => str_repeat('d', 80),
                'robots'       => 'index, follow',
                'published'    => true,
                'has_override' => false,
            ],
        ]);

        $rows = (new SeoAuditor(new SeoAuditRules(), [$subjects], $configProvider, true))->forLocale('en');

        // Site not indexable → published_but_noindex is not raised even when robots say index.
        self::assertSame([], $rows[0]->problems());
    }

    public function testSeoAuditRowHelpers(): void
    {
        $row = new SeoAuditRow('home', 'en', '/', 'Title', 'Desc', 'index', true, false, ['missing_title']);
        $row->addProblem('missing_title');
        $row->addProblem('long_title');

        self::assertFalse($row->isClean());
        self::assertSame(['missing_title', 'long_title'], $row->problems());
        self::assertSame(5, $row->titleLength());
        self::assertSame(4, $row->descriptionLength());
    }
}
