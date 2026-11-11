<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Worker;

/**
 * Messenger message of the boundary test: render one tenant's e-mail head (every 7th handler fails afterwards).
 */
final readonly class TenantPageMessage
{
    public function __construct(
        public string $tenant,
        public int $index,
        public bool $fail,
    ) {
    }
}
