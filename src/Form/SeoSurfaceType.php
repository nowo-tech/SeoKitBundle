<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Form;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function is_string;

/**
 * Per-surface SEO overrides (nullable = inherit).
 *
 * @extends AbstractType<SeoSurface>
 */
final class SeoSurfaceType extends AbstractType
{
    public function __construct(
        private readonly OriginUrlGuard $originUrlGuard,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metaTitle', TextType::class, [
                'required'    => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('metaDescription', TextareaType::class, [
                'required'    => false,
                'constraints' => [new Length(max: 320)],
            ])
            ->add('metaRobots', ChoiceType::class, [
                'required'    => false,
                'placeholder' => 'nowo_seo_kit.surface.robots.inherit',
                'choices'     => [
                    'nowo_seo_kit.robots.index_follow'     => 'index, follow',
                    'nowo_seo_kit.robots.index_nofollow'   => 'index, nofollow',
                    'nowo_seo_kit.robots.noindex_follow'   => 'noindex, follow',
                    'nowo_seo_kit.robots.noindex_nofollow' => 'noindex, nofollow',
                ],
            ])
            ->add('canonicalOverride', TextType::class, [
                'required'    => false,
                'constraints' => [
                    new Length(max: 500),
                    new Callback($this->validateCanonical(...)),
                ],
            ])
            ->add('openGraphImage', TextType::class, [
                'required'    => false,
                'constraints' => [
                    new Length(max: 500),
                    new Callback($this->validateOpenGraphImage(...)),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => SeoSurface::class,
            'translation_domain' => 'NowoSeoKitBundle',
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
