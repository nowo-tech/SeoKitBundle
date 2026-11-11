<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\PrivateEntityManager;

use Nowo\SeoKitBundle\Model\SeoSurfaceData;
use Nowo\SeoKitBundle\Service\SeoSurfaceStoreInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host controller of the test kernel: optionally saves a surface title, then returns the title it reads.
 */
final readonly class SurfaceTitleController
{
    public function __construct(private SeoSurfaceStoreInterface $store)
    {
    }

    public function __invoke(Request $request): Response
    {
        $key   = $request->query->getString('key', 'page:home');
        $write = $request->query->getString('write');
        if ($write !== '') {
            $this->store->save(new SeoSurfaceData($key, 'en', $write));
        }

        return new Response((string) $this->store->findData($key, 'en')?->metaTitle);
    }
}
