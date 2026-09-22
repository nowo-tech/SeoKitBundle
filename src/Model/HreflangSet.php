<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model;

/**
 * The `hreflang` alternates of one page: absolute URL per published locale + optional `x-default`.
 */
final readonly class HreflangSet
{
    /**
     * @param array<string, string> $alternates locale => absolute URL
     */
    public function __construct(
        private array $alternates,
        private ?string $xDefault,
    ) {
    }

    /** @return array<string, string> */
    public function alternates(): array
    {
        return $this->alternates;
    }

    public function xDefault(): ?string
    {
        return $this->xDefault;
    }

    public function isEmpty(): bool
    {
        return [] === $this->alternates;
    }

    public function shouldRender(): bool
    {
        return \count($this->alternates) > 1;
    }
}
