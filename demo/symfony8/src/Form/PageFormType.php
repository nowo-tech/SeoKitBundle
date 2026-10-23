<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo admin form for a `pages.<route>` SEO entry.
 */
final class PageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('route', TextType::class, [
                'label'    => 'Route name',
                'disabled' => !$options['is_new'],
                'attr'     => ['placeholder' => 'app_home'],
            ])
            ->add('title', TextType::class, ['label' => 'Title', 'required' => false])
            ->add('path', TextType::class, ['label' => 'Default locale path', 'required' => false, 'attr' => ['placeholder' => '/']])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('sitemap_priority', NumberType::class, [
                'label' => 'Sitemap priority',
                'scale' => 1,
                'html5' => true,
                'attr'  => ['min' => 0, 'max' => 1, 'step' => 0.1],
            ])
            ->add('in_sitemap', CheckboxType::class, ['label' => 'In sitemap', 'required' => false])
            ->add('locales', LocalesType::class, [
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
