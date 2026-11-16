<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_combine;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`). Demo admin form for a `slugs.<route>.<slug>` SEO entry.
 */
#[FormKitConfig('demo')]
final class SlugFormType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $slugRoutes */
        $slugRoutes = $options['slug_routes'];
        if ($options['is_new'] && $slugRoutes !== []) {
            $this->addWithDefaults($builder, 'route', ChoiceType::class, ['label' => 'Route', 'choices' => array_combine($slugRoutes, $slugRoutes)]);
        } else {
            $this->addWithDefaults($builder, 'route', TextType::class, ['label' => 'Route', 'disabled' => !$options['is_new']]);
        }

        $this->addWithDefaults($builder, 'slug', TextType::class, [
            'label'    => 'Canonical slug',
            'disabled' => !$options['is_new'],
            'attr'     => ['placeholder' => 'hello-world'],
        ]);
        $this->addWithDefaults($builder, 'title', TextType::class, ['label' => 'Title', 'required' => false]);
        $this->addWithDefaults($builder, 'description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 2]]);
        $this->addWithDefaults($builder, 'in_sitemap', CheckboxType::class, ['label' => 'In sitemap', 'required' => false]);
        $this->addWithDefaults($builder, 'noindex', CheckboxType::class, ['label' => 'noindex', 'required' => false]);
        $this->addWithDefaults($builder, 'locales', LocalesType::class, [
            'locales' => $options['locales'],
            'fields'  => [
                'title' => ['label' => 'Title'],
                'slug'  => ['label' => 'Translated slug', 'placeholder' => 'hola-mundo'],
                'path'  => ['label' => 'Optional full path override'],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['is_new' => true, 'slug_routes' => []]);
        $resolver->setRequired('locales');
        $resolver->setAllowedTypes('locales', 'array');
        $resolver->setAllowedTypes('slug_routes', 'array');
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
