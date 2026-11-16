<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One {@see LocaleFieldsType} fieldset per configured locale, keyed by locale code.
 */
#[FormKitConfig('demo')]
final class LocalesType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $locales */
        $locales = $options['locales'];
        foreach ($locales as $locale) {
            $this->addWithDefaults($builder, $locale, LocaleFieldsType::class, [
                'label'  => 'Locale ' . $locale,
                'fields' => $options['fields'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_protection' => false, 'label' => false]);
        $resolver->setRequired(['locales', 'fields']);
        $resolver->setAllowedTypes('locales', 'array');
        $resolver->setAllowedTypes('fields', 'array');
    }
}
