<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * SEO admin forms — FormKit profile {@code seo_kit}.
 */
#[FormKitConfig('seo_kit')]
abstract class AbstractSeoFormType extends FormKitAbstractType
{
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'translation_domain' => 'NowoSeoKitBundle',
        ]);
    }
}
