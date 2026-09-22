<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Controller\Api;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;

/**
 * JSON API for editor pencils: load/save one surface override without a full admin page.
 */
final class SeoSurfaceApiController extends AbstractController
{
    public function __construct(
        private readonly SeoSurfaceRepository $surfaces,
        private readonly string $role = 'ROLE_ADMIN',
    ) {
    }

    #[Route('/_nowo/seo/surfaces/{surfaceKey}/{locale}', name: 'nowo_seo_kit_api_surface_get', requirements: ['surfaceKey' => '.+', 'locale' => '[a-z]{2}'], methods: ['GET'])]
    public function get(string $surfaceKey, string $locale): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->role);
        $surfaceKey = rawurldecode($surfaceKey);
        $surface    = $this->surfaces->findOneByKeyAndLocale($surfaceKey, $locale);

        return $this->json([
            'surface_key'        => $surfaceKey,
            'locale'             => $locale,
            'meta_title'         => $surface?->getMetaTitle(),
            'meta_description'   => $surface?->getMetaDescription(),
            'meta_robots'        => $surface?->getMetaRobots(),
            'canonical_override' => $surface?->getCanonicalOverride(),
            'open_graph_image'   => $surface?->getOpenGraphImage(),
        ]);
    }

    #[Route('/_nowo/seo/surfaces/{surfaceKey}/{locale}', name: 'nowo_seo_kit_api_surface_post', requirements: ['surfaceKey' => '.+', 'locale' => '[a-z]{2}'], methods: ['POST'])]
    public function post(string $surfaceKey, string $locale, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->role);
        $surfaceKey = rawurldecode($surfaceKey);
        $surface    = $this->surfaces->findOneByKeyAndLocale($surfaceKey, $locale) ?? (new SeoSurface())
            ->setSurfaceKey($surfaceKey)
            ->setLocale($locale);

        $form    = $this->createForm(SeoSurfaceType::class, $surface);
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        $form->submit([
            'metaTitle'         => $payload['metaTitle'] ?? $payload['meta_title'] ?? null,
            'metaDescription'   => $payload['metaDescription'] ?? $payload['meta_description'] ?? null,
            'metaRobots'        => $payload['metaRobots'] ?? $payload['meta_robots'] ?? null,
            'canonicalOverride' => $payload['canonicalOverride'] ?? $payload['canonical_override'] ?? null,
            'openGraphImage'    => $payload['openGraphImage'] ?? $payload['open_graph_image'] ?? null,
        ]);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json(['ok' => false, 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->isEmpty($surface)) {
            if ($surface->getId() !== null) {
                $this->surfaces->remove($surface);
            }

            return $this->json(['ok' => true, 'cleared' => true]);
        }

        $this->surfaces->save($surface);

        return $this->json(['ok' => true, 'cleared' => false, 'id' => $surface->getId()]);
    }

    private function isEmpty(SeoSurface $seo): bool
    {
        return $seo->getMetaTitle() === null
            && $seo->getMetaDescription() === null
            && $seo->getMetaRobots() === null
            && $seo->getCanonicalOverride() === null
            && $seo->getOpenGraphImage() === null
            && $seo->getStructuredDataExtra() === null;
    }
}
