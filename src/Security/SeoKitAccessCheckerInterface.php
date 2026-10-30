<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Security;

/**
 * Decides whether the current request may use the SeoKit admin UI and its JSON API (REQ-UI-002).
 *
 * Select a host implementation with `nowo_seo_kit.security.access_checker` (service id); the default is
 * {@see ConfigurableSeoKitAccessChecker} driven by `security.access_roles`. Implementations must be stateless between
 * requests (FrankenPHP worker mode): read the user from request-scoped services.
 */
interface SeoKitAccessCheckerInterface
{
    public function canAccess(): bool;
}
