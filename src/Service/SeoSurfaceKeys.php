<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use function strlen;

/**
 * Conventional surface keys so hosts and the pencil API share one namespace.
 */
final class SeoSurfaceKeys
{
    public const PREFIX_PAGE = 'page:';
    public const PREFIX_BLOG = 'blog:';

    public static function page(string $pageKey): string
    {
        return self::PREFIX_PAGE . trim($pageKey);
    }

    public static function blog(int|string $articleId): string
    {
        return self::PREFIX_BLOG . $articleId;
    }

    public static function isPage(string $surfaceKey): bool
    {
        return str_starts_with($surfaceKey, self::PREFIX_PAGE);
    }

    public static function pageKey(string $surfaceKey): ?string
    {
        if (!self::isPage($surfaceKey)) {
            return null;
        }

        $key = substr($surfaceKey, strlen(self::PREFIX_PAGE));

        return $key === '' ? null : $key;
    }
}
