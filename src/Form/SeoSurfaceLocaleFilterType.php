<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_combine;
use function array_map;
use function strtoupper;

/**
 * GET locale switcher of the surfaces list (`?locale=…`, no CSRF) — REQ-TWIG-005. Rendered in the filters region of
 * the UiKit list scaffold (apply button; no inline handler, REQ-UI-003).
 *
 * @extends AbstractType<array{locale?: string|null}>
 */
final class SeoSurfaceLocaleFilterType extends AbstractType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $locales */
        $locales = $options['locales'];

        $builder->add('locale', ChoiceType::class, [
            'label'                     => 'nowo_seo_kit.admin.surfaces.locale',
            'choices'                   => array_combine(array_map(strtoupper(...), $locales), $locales),
            'choice_translation_domain' => false,
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'GET',
            'csrf_protection'    => false,
            'translation_domain' => 'NowoSeoKitBundle',
            'locales'            => [],
        ]);
        $resolver->setAllowedTypes('locales', 'string[]');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
