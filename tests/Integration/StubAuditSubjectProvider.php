<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration;

use Nowo\SeoKitBundle\Service\Audit\SeoAuditSubjectProviderInterface;

/**
 * One audit subject per locale for the admin kernel test (a key with ":" and "/").
 */
final class StubAuditSubjectProvider implements SeoAuditSubjectProviderInterface
{
    public function subjectsForLocale(string $locale): array
    {
        return [[
            'surface_key'  => 'blog:post/42',
            'locale'       => $locale,
            'path'         => '/blog/hello',
            'title'        => '',
            'description'  => '',
            'robots'       => 'index, follow',
            'published'    => true,
            'has_override' => false,
        ]];
    }
}
