<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Form;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Override;
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
 */
final class SeoSiteSettingsType extends AbstractSeoFormType
{
    /** @var list<string> */
    private const LOCALISED_FIELDS = ['defaultTitle', 'homeTitle', 'defaultDescription', 'organisationDescription'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<string> $locales */
        $locales = $options['enabled_locales'];

        $this->withBuilder($builder, function () use ($locales): void {
            $this->addTextField('siteName', [
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ]);
            $this->addTextField('titleTemplate', [
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 120),
                    new Callback($this->validateTitleTemplate(...)),
                ],
            ]);
            $this->addCheckboxField('indexable', ['required' => false]);
            $this->addChoiceField('defaultRobots', [
                'choices' => [
                    'nowo_seo_kit.robots.index_follow'     => 'index, follow',
                    'nowo_seo_kit.robots.index_nofollow'   => 'index, nofollow',
                    'nowo_seo_kit.robots.noindex_follow'   => 'noindex, follow',
                    'nowo_seo_kit.robots.noindex_nofollow' => 'noindex, nofollow',
                ],
            ]);
            $this->addTextField('defaultOpenGraphImage', [
                'required'    => false,
                'constraints' => [new Length(max: 500)],
            ]);
            $this->addTextField('twitterSite', [
                'required'    => false,
                'constraints' => [new Length(max: 64)],
            ]);
            $this->addTextField('organisationLegalName', ['required' => false, 'constraints' => [new Length(max: 180)]]);
            $this->addTextField('organisationLogo', ['required' => false, 'constraints' => [new Length(max: 500)]]);
            $this->addEmailField('contactEmail', ['required' => false, 'constraints' => [new Email(), new Length(max: 180)]]);
            $this->addTextField('contactPhone', ['required' => false, 'constraints' => [new Length(max: 40)]]);
            $this->addTextField('streetAddress', ['required' => false, 'constraints' => [new Length(max: 180)]]);
            $this->addTextField('postalCode', ['required' => false, 'constraints' => [new Length(max: 20)]]);
            $this->addTextField('addressLocality', ['required' => false, 'constraints' => [new Length(max: 120)]]);
            $this->addTextField('addressRegion', ['required' => false, 'constraints' => [new Length(max: 120)]]);
            $this->addTextField('addressCountry', ['required' => false, 'constraints' => [new Length(min: 2, max: 2)]]);
            $this->addTextareaField('socialProfilesText', [
                'mapped'      => false,
                'required'    => false,
                'constraints' => [new Callback($this->validateSocialProfiles(...))],
            ]);
            $this->addTextField('googleSiteVerification', ['required' => false, 'constraints' => [new Length(max: 120)]]);
            $this->addTextField('bingSiteVerification', ['required' => false, 'constraints' => [new Length(max: 120)]]);

            foreach ($locales as $locale) {
                foreach (self::LOCALISED_FIELDS as $field) {
                    $isLong = in_array($field, ['defaultDescription', 'organisationDescription'], true);
                    if ($isLong) {
                        $this->addTextareaField($field . '_' . $locale, [
                            'mapped'      => false,
                            'required'    => false,
                            'constraints' => [new Length(max: 320)],
                        ]);
                    } else {
                        $this->addTextField($field . '_' . $locale, [
                            'mapped'      => false,
                            'required'    => false,
                            'constraints' => [new Length(max: 255)],
                        ]);
                    }
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, $this->fillFromEntity(...));
        $builder->addEventListener(FormEvents::SUBMIT, $this->writeBackToEntity(...));
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class'      => SeoSiteSettings::class,
            'enabled_locales' => ['en'],
            'default_locale'  => 'en',
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
