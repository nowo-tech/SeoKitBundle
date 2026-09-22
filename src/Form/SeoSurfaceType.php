<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Form;

use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function is_string;

/**
 * Per-surface SEO overrides (nullable = inherit).
 *
 * Not final so hosts can subclass for a custom {@see getBlockPrefix()} / translation catalogue
 * (e.g. CMS `page_seo` forms). Prefer this type directly when the kit prefix is fine.
 */
class SeoSurfaceType extends AbstractSeoFormType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly OriginUrlGuard $originUrlGuard,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('metaTitle', [
                'required'    => false,
                'constraints' => [new Length(max: 255)],
            ]);
            $this->addTextareaField('metaDescription', [
                'required'    => false,
                'constraints' => [new Length(max: 320)],
            ]);
            $this->addChoiceField('metaRobots', [
                'required'    => false,
                'placeholder' => 'nowo_seo_kit.surface.robots.inherit',
                'choices'     => [
                    'nowo_seo_kit.robots.index_follow'     => 'index, follow',
                    'nowo_seo_kit.robots.index_nofollow'   => 'index, nofollow',
                    'nowo_seo_kit.robots.noindex_follow'   => 'noindex, follow',
                    'nowo_seo_kit.robots.noindex_nofollow' => 'noindex, nofollow',
                ],
            ]);
            $this->addTextField('canonicalOverride', [
                'required'    => false,
                'constraints' => [
                    new Length(max: 500),
                    new Callback($this->validateCanonical(...)),
                ],
            ]);
            $this->addTextField('openGraphImage', [
                'required'    => false,
                'constraints' => [
                    new Length(max: 500),
                    new Callback($this->validateOpenGraphImage(...)),
                ],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => SeoSurface::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'nowo_seo_surface';
    }

    private function validateCanonical(mixed $value, ExecutionContextInterface $context): void
    {
        if (!$this->originUrlGuard->isSafeCanonical(is_string($value) ? $value : null)) {
            $context->buildViolation('nowo_seo_kit.surface.canonical.outside_site')
                ->setTranslationDomain('NowoSeoKitBundle')
                ->addViolation();
        }
    }

    private function validateOpenGraphImage(mixed $value, ExecutionContextInterface $context): void
    {
        if (!$this->originUrlGuard->isSafeOpenGraphImage(is_string($value) ? $value : null)) {
            $context->buildViolation('nowo_seo_kit.surface.open_graph_image.invalid')
                ->setTranslationDomain('NowoSeoKitBundle')
                ->addViolation();
        }
    }
}
