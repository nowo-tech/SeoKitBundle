<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;

/**
 * Builds FormKit merger / types for SEO admin unit tests.
 */
final class FormKitTestSupport
{
    public static function merger(string $defaultProfile = 'seo_kit'): FormOptionsMerger
    {
        $seoKit = [
            'translation_domain' => 'NowoSeoKitBundle',
            'defaults'           => [
                'attr'     => ['class' => 'form-control'],
                'row_attr' => ['class' => 'mb-3'],
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
        );
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
