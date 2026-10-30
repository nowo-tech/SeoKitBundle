<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Default access checker: grants when the user has at least one of `nowo_seo_kit.security.access_roles`.
 *
 * Fails closed: an empty role list grants nobody (the configuration already refuses an empty list without a custom
 * checker or `allow_unauthenticated`).
 */
final readonly class ConfigurableSeoKitAccessChecker implements SeoKitAccessCheckerInterface
{
    /**
     * @param list<string> $accessRoles
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $accessRoles = ['ROLE_ADMIN'],
    ) {
    }

    public function canAccess(): bool
    {
        foreach ($this->accessRoles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
