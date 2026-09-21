<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service\Audit;

use Nowo\SeoKitBundle\Service\Audit\SeoAuditRow;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditRules;
use PHPUnit\Framework\TestCase;

final class SeoAuditRulesTest extends TestCase
{
    public function testFlagsMissingAndLengthProblems(): void
    {
        $rules = new SeoAuditRules();

        self::assertSame(
            ['missing_title', 'missing_description'],
            $rules->problemsOf('', '', 'index, follow', true, true),
        );

        self::assertContains(
            'long_title',
            $rules->problemsOf(str_repeat('a', 61), str_repeat('b', 80), 'index, follow', true, true),
        );
    }

    public function testAnnotatesDuplicateTitles(): void
    {
        $rules = new SeoAuditRules();
        $rows  = [
            new SeoAuditRow('a', 'en', '/', 'Same', 'desc-a-enough-chars-to-pass-min-length-check-here', 'index', true, false),
            new SeoAuditRow('b', 'en', '/b', 'Same', 'desc-b-enough-chars-to-pass-min-length-check-here', 'index', true, false),
        ];

        $rules->annotateDuplicates($rows);

        self::assertContains('duplicate_title', $rows[0]->problems());
        self::assertContains('duplicate_title', $rows[1]->problems());
    }

    public function testFlagsLongDescriptionPublishedNoindexAndDuplicateDescriptions(): void
    {
        $rules = new SeoAuditRules();

        self::assertContains(
            'long_description',
            $rules->problemsOf('Title', str_repeat('d', 161), 'index, follow', true, true),
        );
        self::assertContains(
            'published_but_noindex',
            $rules->problemsOf('Title', str_repeat('d', 80), 'noindex, follow', true, true),
        );

        $dupDesc = str_repeat('d', 80);
        $rows    = [
            new SeoAuditRow('a', 'en', '/', 'One', $dupDesc, 'index', true, false),
            new SeoAuditRow('b', 'en', '/b', 'Two', $dupDesc, 'index', true, false),
        ];
        $rules->annotateDuplicates($rows);

        self::assertContains('duplicate_description', $rows[0]->problems());
        self::assertContains('duplicate_description', $rows[1]->problems());
    }
}
