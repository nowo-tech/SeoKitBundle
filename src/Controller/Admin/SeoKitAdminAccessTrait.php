<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Controller\Admin;

use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Single access gate of every SeoKit admin and API action (REQ-UI-002): the configured
 * {@see SeoKitAccessCheckerInterface} decides; a refusal is an {@see AccessDeniedException} (anonymous users go to
 * the firewall entry point, authenticated users get 403).
 */
trait SeoKitAdminAccessTrait
{
    private readonly SeoKitAccessCheckerInterface $accessChecker;

    private function denyUnlessAdmin(): void
    {
        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('NowoSeoKitBundle admin access denied.');
        }
    }
}
