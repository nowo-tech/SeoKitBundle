<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Form;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function in_array;
use function is_string;
use function strlen;

/**
 * Editable site-wide SEO settings (identity, robots, organisation, per-locale wording).
 *
 * Localised fields are unmapped (`defaultTitle_es`); SUBMIT writes them into translations.
 *
 * @extends AbstractType<SeoSiteSettings>
 */
final class SeoSiteSettingsType extends AbstractType
{
    /** @var list<string> */
    private const LOCALISED_FIELDS = ['defaultTitle', 'homeTitle', 'defaultDescription', 'organisationDescription'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $locales */
        $locales = $options['enabled_locales'];

        $builder
            ->add('siteName', TextType::class, [
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ])
            ->add('titleTemplate', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 120),
                    new Callback($this->validateTitleTemplate(...)),
                ],
            ])
            ->add('indexable', CheckboxType::class, ['required' => false])
            ->add('defaultRobots', ChoiceType::class, [
                'choices' => [
                    'nowo_seo_kit.robots.index_follow'     => 'index, follow',
                    'nowo_seo_kit.robots.index_nofollow'   => 'index, nofollow',
                    'nowo_seo_kit.robots.noindex_follow'   => 'noindex, follow',
                    'nowo_seo_kit.robots.noindex_nofollow' => 'noindex, nofollow',
                ],
            ])
            ->add('defaultOpenGraphImage', TextType::class, [
                'required'    => false,
                'constraints' => [new Length(max: 500)],
            ])
            ->add('twitterSite', TextType::class, [
                'required'    => false,
                'constraints' => [new Length(max: 64)],
            ])
            ->add('organisationLegalName', TextType::class, ['required' => false, 'constraints' => [new Length(max: 180)]])
            ->add('organisationLogo', TextType::class, ['required' => false, 'constraints' => [new Length(max: 500)]])
            ->add('contactEmail', EmailType::class, ['required' => false, 'constraints' => [new Email(), new Length(max: 180)]])
            ->add('contactPhone', TextType::class, ['required' => false, 'constraints' => [new Length(max: 40)]])
            ->add('streetAddress', TextType::class, ['required' => false, 'constraints' => [new Length(max: 180)]])
            ->add('postalCode', TextType::class, ['required' => false, 'constraints' => [new Length(max: 20)]])
            ->add('addressLocality', TextType::class, ['required' => false, 'constraints' => [new Length(max: 120)]])
            ->add('addressRegion', TextType::class, ['required' => false, 'constraints' => [new Length(max: 120)]])
            ->add('addressCountry', TextType::class, ['required' => false, 'constraints' => [new Length(min: 2, max: 2)]])
            ->add('socialProfilesText', TextareaType::class, [
                'mapped'      => false,
                'required'    => false,
                'constraints' => [new Callback($this->validateSocialProfiles(...))],
            ])
            ->add('googleSiteVerification', TextType::class, ['required' => false, 'constraints' => [new Length(max: 120)]])
            ->add('bingSiteVerification', TextType::class, ['required' => false, 'constraints' => [new Length(max: 120)]]);

        foreach ($locales as $locale) {
            foreach (self::LOCALISED_FIELDS as $field) {
                $isLong = in_array($field, ['defaultDescription', 'organisationDescription'], true);
                $type   = $isLong ? TextareaType::class : TextType::class;
                $builder->add($field . '_' . $locale, $type, [
                    'mapped'      => false,
                    'required'    => false,
                    'constraints' => [new Length(max: $isLong ? 320 : 255)],
                ]);
            }
        }

        $builder->addEventListener(FormEvents::POST_SET_DATA, $this->fillFromEntity(...));
        $builder->addEventListener(FormEvents::SUBMIT, $this->writeBackToEntity(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => SeoSiteSettings::class,
            'translation_domain' => 'NowoSeoKitBundle',
            'enabled_locales'    => ['en'],
            'default_locale'     => 'en',
        ]);
        $resolver->setAllowedTypes('enabled_locales', 'string[]');
        $resolver->setAllowedTypes('default_locale', 'string');
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['enabled_locales']  = $options['enabled_locales'];
        $view->vars['default_locale']   = $options['default_locale'];
        $view->vars['localised_fields'] = self::LOCALISED_FIELDS;
    }

    public function getBlockPrefix(): string
    {
        return 'nowo_seo_site';
    }

    private function fillFromEntity(FormEvent $event): void
    {
        $settings = $event->getData();
        $form     = $event->getForm();
        if (!$settings instanceof SeoSiteSettings) {
            return;
        }

        $form->get('socialProfilesText')->setData(implode("\n", $settings->getSocialProfiles()));

        foreach ($settings->getTranslations() as $translation) {
            $locale = $translation->getLocale();
            $values = [
                'defaultTitle'            => $translation->getDefaultTitle(),
                'homeTitle'               => $translation->getHomeTitle(),
                'defaultDescription'      => $translation->getDefaultDescription(),
                'organisationDescription' => $translation->getOrganisationDescription(),
            ];
            foreach ($values as $field => $value) {
                if ($form->has($field . '_' . $locale)) {
                    $form->get($field . '_' . $locale)->setData($value);
                }
            }
        }
    }

    private function writeBackToEntity(FormEvent $event): void
    {
        $settings = $event->getData();
        $form     = $event->getForm();
        if (!$settings instanceof SeoSiteSettings) {
            return;
        }

        $raw = (string) $form->get('socialProfilesText')->getData();
        $settings->setSocialProfiles(array_map(trim(...), preg_split('/\R/', $raw) ?: []));

        foreach ($form->all() as $name => $child) {
            foreach (self::LOCALISED_FIELDS as $field) {
                if (!str_starts_with($name, $field . '_')) {
                    continue;
                }
                $locale      = substr($name, strlen($field) + 1);
                $value       = $child->getData();
                $translation = $settings->translationFor($locale);
                $text        = is_string($value) ? $value : null;
                match ($field) {
                    'defaultTitle'            => $translation->setDefaultTitle($text),
                    'homeTitle'               => $translation->setHomeTitle($text),
                    'defaultDescription'      => $translation->setDefaultDescription($text),
                    'organisationDescription' => $translation->setOrganisationDescription($text),
                };
            }
        }
    }

    private function validateTitleTemplate(mixed $value, ExecutionContextInterface $context): void
    {
        if (is_string($value) && !str_contains($value, SeoSiteSettings::TITLE_PLACEHOLDER)) {
            $context->buildViolation('nowo_seo_kit.title_template.missing_placeholder')
                ->setTranslationDomain('NowoSeoKitBundle')
                ->setParameter('%placeholder%', SeoSiteSettings::TITLE_PLACEHOLDER)
                ->addViolation();
        }
    }

    private function validateSocialProfiles(mixed $value, ExecutionContextInterface $context): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }

        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $url = trim($line);
            if ($url === '') {
                continue;
            }
            if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
                $context->buildViolation('nowo_seo_kit.social_profiles.invalid')
                    ->setTranslationDomain('NowoSeoKitBundle')
                    ->setParameter('%url%', $url)
                    ->addViolation();
            }
        }
    }
}
