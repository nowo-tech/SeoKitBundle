<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The bundle's /settings/seo page needs Doctrine persistence and a ROLE_ADMIN user; this demo has neither, so the
 * page is previewed with an in-memory SeoSiteSettings (submits validate but are not saved).
 */
final class SiteSettingsPreviewController extends AbstractController
{
    #[Route(path: '/admin/seo/settings-preview', name: 'admin_seo_settings_preview', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $settings = new SeoSiteSettings();
        $settings->setSiteName('Nowo SEO Kit demo');
        $settings->translationFor('en')->setDefaultTitle('SEO Kit demo')->setHomeTitle('Welcome');
        $settings->translationFor('es')->setDefaultTitle('Demo de SEO Kit')->setHomeTitle('Bienvenida');
        $settings->translationFor('fr')->setDefaultTitle('Démo SEO Kit');

        $form = $this->createForm(SeoSiteSettingsType::class, $settings, [
            'enabled_locales' => ['en', 'es', 'fr'],
            'default_locale'  => 'en',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'nowo_seo_kit.flash.settings_saved');

            return $this->redirectToRoute('admin_seo_settings_preview');
        }

        return $this->render('@NowoSeoKitBundle/admin/settings.html.twig', [
            'form'            => $form,
            'settings'        => $settings,
            'enabled_locales' => ['en', 'es', 'fr'],
            'default_locale'  => 'en',
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
