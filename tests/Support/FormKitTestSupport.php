<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

use function dirname;

/**
 * Builds FormKit merger / types for SEO admin unit tests.
 */
final class FormKitTestSupport
{
    public static function merger(string $defaultProfile = 'seo_kit'): FormOptionsMerger
    {
        $seoKit = [
            'translation_domain'              => 'NowoSeoKitBundle',
            'convention_requires_translation' => true,
            // Same as the profile the extension prepends: no framework classes (REQ-UI-003).
            'defaults' => [
                'attr'     => [],
                'row_attr' => [],
            ],
            'field_types' => [],
        ];

        return new FormOptionsMerger(
            [
                'seo_kit' => $seoKit,
                'default' => $seoKit,
            ],
            $defaultProfile,
            new ConstraintDefinitionFactory(),
            null,
            null,
            self::translator(),
        );
    }

    /**
     * English bundle catalogue: the convention help / placeholder keys apply only when translated, as in the seeded
     * profile (convention_requires_translation).
     */
    public static function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 2) . '/src/Resources/translations/NowoSeoKitBundle.en.yaml', 'en', 'NowoSeoKitBundle');

        return $translator;
    }

    public static function typeMap(): FormTypeMap
    {
        return new FormTypeMap([]);
    }

    public static function siteSettingsType(): SeoSiteSettingsType
    {
        return new SeoSiteSettingsType(self::merger(), self::typeMap());
    }

    public static function surfaceType(?OriginUrlGuard $guard = null): SeoSurfaceType
    {
        return new SeoSurfaceType(self::merger(), self::typeMap(), $guard ?? new OriginUrlGuard('https://nowo.tech'));
    }
}
