<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Support;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Form\SeoSurfaceLocaleFilterType;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditRow;
use Nowo\SeoKitBundle\Ui\SeoKitAreaNav;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;

use function http_build_query;

/**
 * Template contexts of the admin pages, built like the admin controllers build them (REQ-UI-003 contract test and
 * page tests).
 */
final class AdminPageFixtures
{
    public const LOCALES = ['en', 'es', 'fr'];

    public static function pages(): SeoKitPages
    {
        return new SeoKitPages(areaNav: new SeoKitAreaNav(self::urls()));
    }

    /**
     * @param array<string, string>|null $submit submitted payload (validation errors on the page)
     *
     * @return array<string, mixed>
     */
    public static function settings(bool $indexable = true, string $defaultLocale = 'en', ?array $submit = null): array
    {
        $settings = (new SeoSiteSettings())->setSiteName('Nowo')->setIndexable($indexable);
        $settings->translationFor('en')->setDefaultTitle('Nowo — digital products');
        $form = self::forms()->create(SeoSiteSettingsType::class, $settings, [
            'action'          => '/settings/seo',
            'enabled_locales' => self::LOCALES,
            'default_locale'  => $defaultLocale,
        ]);
        if ($submit !== null) {
            $form->submit($submit);
        }
        $form = $form->createView();

        return [
            'page'            => self::pages()->settingsPage($form, self::urls()),
            'form'            => $form,
            'settings'        => $settings,
            'enabled_locales' => self::LOCALES,
            'default_locale'  => $defaultLocale,
        ];
    }

    /**
     * @return list<SeoAuditRow>
     */
    public static function rows(): array
    {
        return [
            new SeoAuditRow('page:home', 'en', '/', 'Home | Nowo', 'Digital products and services built with Symfony for growing teams.', 'index, follow', true, true),
            new SeoAuditRow('blog:post:42', 'en', '/blog/symfony-seo', '', '', 'noindex, follow', true, false, ['missing_title', 'missing_description', 'published_but_noindex']),
            new SeoAuditRow('page:about', 'en', '/about', 'About us | Nowo', 'Short.', 'index, follow', true, false, ['short_description', 'duplicate_title']),
        ];
    }

    /**
     * @param list<SeoAuditRow>|null $rows
     *
     * @return array<string, mixed>
     */
    public static function surfacesIndex(?array $rows = null, bool $siteIndexable = true): array
    {
        $rows ??= self::rows();
        $filters = self::forms()->createNamed('', SeoSurfaceLocaleFilterType::class, ['locale' => 'en'], [
            'action'  => '/admin/seo/surfaces',
            'locales' => self::LOCALES,
        ])->createView();

        return [
            'page'           => self::pages()->surfacesPage($rows, 'en', $filters, self::urls()),
            'locale'         => 'en',
            'locales'        => self::LOCALES,
            'rows'           => $rows,
            'site_indexable' => $siteIndexable,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function surfaceForm(): array
    {
        $surface = (new SeoSurface())->setSurfaceKey('page:home')->setLocale('en')->setMetaTitle('Home | Nowo');
        $form    = self::forms()->create(SeoSurfaceType::class, $surface, ['action' => '/admin/seo/surfaces/en/edit/page%3Ahome'])->createView();

        return [
            'page'        => self::pages()->surfaceFormPage('page:home', 'en', $form, self::urls()),
            'form'        => $form,
            'surface'     => $surface,
            'surface_key' => 'page:home',
            'locale'      => 'en',
            'locales'     => self::LOCALES,
        ];
    }

    /**
     * Router stand-in for the admin routes (1.x paths) and their query parameters.
     */
    public static function urls(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                if ($name === 'nowo_seo_kit_admin_surfaces_edit') {
                    return '/admin/seo/surfaces/' . $parameters['locale'] . '/edit/' . $parameters['surfaceKey'];
                }
                $path = $name === 'nowo_seo_kit_admin_settings' ? '/settings/seo' : '/admin/seo/surfaces';

                return $parameters === [] ? $path : $path . '?' . http_build_query($parameters);
            }

            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }
        };
    }

    public static function forms(): FormFactoryInterface
    {
        $csrf = new class implements CsrfTokenManagerInterface {
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'token-' . $tokenId);
            }

            public function refreshToken(string $tokenId): CsrfToken
            {
                return $this->getToken($tokenId);
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            public function isTokenValid(CsrfToken $token): bool
            {
                return true;
            }
        };

        return Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension($csrf))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(FormKitTestSupport::siteSettingsType())
            ->addType(FormKitTestSupport::surfaceType())
            ->addType(new SeoSurfaceLocaleFilterType())
            ->getFormFactory();
    }
}
