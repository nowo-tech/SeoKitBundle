<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Optional per-locale text fields of a demo SEO entry (title, path, slug, …).
 */
final class LocaleFieldsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, array{label: string, placeholder?: string}> $fields */
        $fields = $options['fields'];
        foreach ($fields as $name => $field) {
            $attr = isset($field['placeholder']) ? ['placeholder' => $field['placeholder']] : [];
            $builder->add($name, TextType::class, ['label' => $field['label'], 'required' => false, 'attr' => $attr]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_protection' => false]);
        $resolver->setRequired('fields');
        $resolver->setAllowedTypes('fields', 'array');
    }
}
