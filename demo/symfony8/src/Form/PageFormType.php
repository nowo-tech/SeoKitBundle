<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`). Demo admin form for a `pages.<route>` SEO entry.
 */
#[FormKitConfig('demo')]
final class PageFormType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'route', TextType::class, [
            'label'    => 'Route name',
            'disabled' => !$options['is_new'],
            'attr'     => ['placeholder' => 'app_home'],
        ]);
        $this->addWithDefaults($builder, 'title', TextType::class, ['label' => 'Title', 'required' => false]);
        $this->addWithDefaults($builder, 'path', TextType::class, ['label' => 'Default locale path', 'required' => false, 'attr' => ['placeholder' => '/']]);
        $this->addWithDefaults($builder, 'description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 2]]);
        $this->addWithDefaults($builder, 'sitemap_priority', NumberType::class, [
            'label' => 'Sitemap priority',
            'scale' => 1,
            'html5' => true,
            'attr'  => ['min' => 0, 'max' => 1, 'step' => 0.1],
        ]);
        $this->addWithDefaults($builder, 'in_sitemap', CheckboxType::class, ['label' => 'In sitemap', 'required' => false]);
        $this->addWithDefaults($builder, 'locales', LocalesType::class, [
            'locales' => $options['locales'],
            'fields'  => [
                'title'       => ['label' => 'Title'],
                'path'        => ['label' => 'Path'],
                'description' => ['label' => 'Description'],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['is_new' => true]);
        $resolver->setRequired('locales');
        $resolver->setAllowedTypes('locales', 'array');
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
