<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\PrivateEntityManager;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Host SQL filter of the test kernel (tenant-style parameter); adds no constraint, only its state is asserted.
 */
final class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        return '';
    }
}
