<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo admin form for a `slug_routes.<route>` SEO entry (path pattern + title templates).
 */
final class SlugRouteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('route', TextType::class, [
                'label'    => 'Route name',
                'disabled' => !$options['is_new'],
                'attr'     => ['placeholder' => 'app_blog_show'],
            ])
            ->add('slug_parameter', TextType::class, ['label' => 'Slug parameter', 'required' => false])
            ->add('sitemap_priority', NumberType::class, [
                'label' => 'Sitemap priority',
                'scale' => 1,
                'html5' => true,
                'attr'  => ['min' => 0, 'max' => 1, 'step' => 0.1],
            ])
            ->add('path_pattern', TextType::class, ['label' => 'Default path pattern', 'required' => false, 'attr' => ['placeholder' => '/en/blog/{slug}']])
            ->add('title_template', TextType::class, ['label' => 'Title template', 'required' => false, 'attr' => ['placeholder' => '{title} — Blog']])
            ->add('description_template', TextType::class, ['label' => 'Description template', 'required' => false, 'attr' => ['placeholder' => 'Article: {slug}']])
            ->add('in_sitemap', CheckboxType::class, ['label' => 'In sitemap', 'required' => false])
            ->add('locales', LocalesType::class, [
                'locales' => $options['locales'],
                'fields'  => [
                    'path_pattern'   => ['label' => 'Path pattern'],
                    'title_template' => ['label' => 'Title template'],
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
