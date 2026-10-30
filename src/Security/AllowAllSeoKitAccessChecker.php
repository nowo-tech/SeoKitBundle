<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Security;

/**
 * Permissive checker selected only by `nowo_seo_kit.security.allow_unauthenticated: true` (DEV / DEMO only: the admin
 * UI and the JSON API become public).
 */
final class AllowAllSeoKitAccessChecker implements SeoKitAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }
}
