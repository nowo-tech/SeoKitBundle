<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service\Audit;

use function count;

/**
 * Pure rules for page/surface SEO health checks (no CMS coupling).
 */
final class SeoAuditRules
{
    public const TITLE_MAX       = 60;
    public const DESCRIPTION_MIN = 70;
    public const DESCRIPTION_MAX = 160;

    /**
     * @return list<string>
     */
    public function problemsOf(
        string $title,
        string $description,
        string $robots,
        bool $published,
        bool $siteIsIndexable,
    ): array {
        $problems = [];

        if ($title === '') {
            $problems[] = 'missing_title';
        } elseif (mb_strlen($title) > self::TITLE_MAX) {
            $problems[] = 'long_title';
        }

        if ($description === '') {
            $problems[] = 'missing_description';
        } elseif (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $problems[] = 'long_description';
        } elseif (mb_strlen($description) < self::DESCRIPTION_MIN) {
            $problems[] = 'short_description';
        }

        if ($siteIsIndexable && $published && str_contains(strtolower($robots), 'noindex')) {
            $problems[] = 'published_but_noindex';
        }

        return $problems;
    }

    /**
     * Annotates duplicate titles/descriptions across a set of rows.
     *
     * @param list<SeoAuditRow> $rows
     *
     * @return list<SeoAuditRow>
     */
    public function annotateDuplicates(array $rows): array
    {
        $titles       = [];
        $descriptions = [];

        foreach ($rows as $row) {
            $titles[$row->title][]             = $row->surfaceKey;
            $descriptions[$row->description][] = $row->surfaceKey;
        }

        foreach ($rows as $row) {
            if (count($titles[$row->title] ?? []) > 1) {
                $row->addProblem('duplicate_title');
            }

            if ($row->description !== '' && count($descriptions[$row->description] ?? []) > 1) {
                $row->addProblem('duplicate_description');
            }
        }

        return $rows;
    }
}
