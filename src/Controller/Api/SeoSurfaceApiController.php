<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Controller\Api;

use Nowo\SeoKitBundle\Controller\Admin\SeoKitAdminAccessTrait;
use Nowo\SeoKitBundle\Controller\Admin\SeoSurfaceController;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function array_map;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function strtolower;

/**
 * JSON API for editor pencils: load/save one surface override without a full admin page.
 *
 * Every action requires the admin access checker (REQ-UI-002); unknown locales and over-long surface keys answer 404.
 * Writes require a JSON body and (unless `admin.api_csrf_protection` is false) a CSRF token in the
 * `X-CSRF-Token` header; `GET` returns that token as `csrf_token`. CSRF is fail-closed: without a token manager every
 * `POST` is refused (REQ-SEC-005).
 */
final class SeoSurfaceApiController extends AbstractController
{
    use SeoKitAdminAccessTrait;

    public const CSRF_TOKEN_ID = 'nowo_seo_kit_surface_api';
    public const CSRF_HEADER   = 'X-CSRF-Token';

    /**
     * @param list<string> $locales enabled locales (`nowo_seo_kit.locales`)
     */
    public function __construct(
        private readonly SeoSurfaceRepository $surfaces,
        SeoKitAccessCheckerInterface $accessChecker,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private readonly bool $csrfProtection = true,
        private readonly array $locales = ['en'],
    ) {
        $this->accessChecker = $accessChecker;
    }

    #[Route('/_nowo/seo/surfaces/{surfaceKey}/{locale}', name: 'nowo_seo_kit_api_surface_get', requirements: ['surfaceKey' => '.+', 'locale' => '[a-z]{2}'], methods: ['GET'])]
    public function get(string $surfaceKey, string $locale): JsonResponse
    {
        $this->denyUnlessAdmin();
        $surfaceKey = $this->surfaceKey($surfaceKey, $locale);
        $surface    = $this->surfaces->findOneByKeyAndLocale($surfaceKey, $locale);

        return $this->json([
            'surface_key'        => $surfaceKey,
            'locale'             => $locale,
            'meta_title'         => $surface?->getMetaTitle(),
            'meta_description'   => $surface?->getMetaDescription(),
            'meta_robots'        => $surface?->getMetaRobots(),
            'canonical_override' => $surface?->getCanonicalOverride(),
            'open_graph_image'   => $surface?->getOpenGraphImage(),
            'csrf_token'         => $this->csrfProtection ? $this->csrfTokenManager?->getToken(self::CSRF_TOKEN_ID)->getValue() : null,
        ]);
    }

    #[Route('/_nowo/seo/surfaces/{surfaceKey}/{locale}', name: 'nowo_seo_kit_api_surface_post', requirements: ['surfaceKey' => '.+', 'locale' => '[a-z]{2}'], methods: ['POST'])]
    public function post(string $surfaceKey, string $locale, Request $request): JsonResponse
    {
        $this->denyUnlessAdmin();
        $surfaceKey = $this->surfaceKey($surfaceKey, $locale);

        // JSON only: a cross-site HTML form cannot send application/json without a CORS preflight.
        if ($request->getContentTypeFormat() !== 'json') {
            return $this->json(['ok' => false, 'errors' => ['Content-Type must be application/json.']], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        // Explicit CSRF token in a header on top of the JSON requirement (REQ-SEC-005); the inner form has none.
        if ($this->csrfProtection && !$this->isCsrfHeaderValid($request)) {
            return $this->json(['ok' => false, 'errors' => ['Missing or invalid CSRF token (' . self::CSRF_HEADER . ' header).']], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['ok' => false, 'errors' => ['Request body must be a JSON object.']], Response::HTTP_BAD_REQUEST);
        }

        $surface = $this->surfaces->findOneByKeyAndLocale($surfaceKey, $locale) ?? (new SeoSurface())
            ->setSurfaceKey($surfaceKey)
            ->setLocale($locale);

        $form = $this->createForm(SeoSurfaceType::class, $surface, ['csrf_protection' => false]);

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
                // @igor-ignore - HTTP handler delegates to services; no controller worker state.
                $this->surfaces->remove($surface);
            }

            return $this->json(['ok' => true, 'cleared' => true]);
        }

        $this->surfaces->save($surface);

        return $this->json(['ok' => true, 'cleared' => false, 'id' => $surface->getId()]);
    }

    /**
     * Decoded surface key; 404 for a locale that is not enabled or a key longer than the column.
     */
    private function surfaceKey(string $surfaceKey, string $locale): string
    {
        $surfaceKey = rawurldecode($surfaceKey);
        if (!in_array(strtolower($locale), array_map(strtolower(...), $this->locales), true)) {
            throw $this->createNotFoundException('Locale is not enabled.');
        }
        if (mb_strlen($surfaceKey) > SeoSurfaceController::MAX_SURFACE_KEY_LENGTH) {
            throw $this->createNotFoundException('Surface key is too long.');
        }

        return $surfaceKey;
    }

    private function isCsrfHeaderValid(Request $request): bool
    {
        if (!$this->csrfTokenManager instanceof CsrfTokenManagerInterface) {
            return false;
        }

        $token = $request->headers->get(self::CSRF_HEADER);

        return is_string($token) && $token !== ''
            && $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token));
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
