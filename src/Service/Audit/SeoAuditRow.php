<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service\Audit;

use function in_array;

/**
 * One surface in one locale, as the audit sees it.
 *
 * Not readonly: duplication can only be known once every row exists.
 */
final class SeoAuditRow
{
    /**
     * @param list<string> $problems
     */
    public function __construct(
        public readonly string $surfaceKey,
        public readonly string $locale,
        public readonly string $path,
        public readonly string $title,
        public readonly string $description,
        public readonly string $robots,
        public readonly bool $published,
        public readonly bool $hasOverride,
        private array $problems = [],
    ) {
    }

    public function addProblem(string $problem): void
    {
        if (!in_array($problem, $this->problems, true)) {
            $this->problems[] = $problem;
        }
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    public function isClean(): bool
    {
        return $this->problems === [];
    }

    public function titleLength(): int
    {
        return mb_strlen($this->title);
    }

    public function descriptionLength(): int
    {
        return mb_strlen($this->description);
    }
}
