<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Ddd\Domain;

/**
 * Plain domain object of the DDD test host: no attributes, mapped in XML on the host's own manager (not final:
 * Doctrine proxies it).
 */
class HostArticle
{
    private ?int $id = null; // @phpstan-ignore property.unusedType (Doctrine generated id)

    public function __construct(private readonly string $slug)
    {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }
}
