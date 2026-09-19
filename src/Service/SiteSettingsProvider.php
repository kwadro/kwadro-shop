<?php

namespace App\Service;

use App\Entity\ShopDeliveryMethod;
use App\Entity\ShopPaymentMethod;
use App\Entity\Site;
use App\Repository\FooterSettingRepository;
use App\Repository\HeaderSettingRepository;
use App\Repository\LocaleRepository;
use App\Repository\MegaMenuSettingRepository;
use App\Repository\SeoSettingRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;

class SiteSettingsProvider
{
    public function __construct(
        private SiteRepository $siteRepo,
        private SeoSettingRepository $seoRepo,
        private HeaderSettingRepository $headerRepo,
        private FooterSettingRepository $footerRepo,
        private MegaMenuSettingRepository $menuRepo,
        private LocaleRepository $localeRepo,
        private EntityManagerInterface $em
    ) {
    }

    public function getSettings(string $domain, string $locale): array
    {
        $site = $this->siteRepo->findOneBy(['domain' => $domain]);
        $localeObject = $this->localeRepo->findOneBy(['code' => $locale]);

        if (!$site || !$localeObject) {
            return [];
        }
        $seoSetting = $this->seoRepo->findOneBySiteAndLocale($site->getId(), $localeObject->getId()) ?? [];
        $headerSetting = $this->headerRepo->findOneBySiteAndLocale($site->getId(), $localeObject->getId()) ?? [];
        $footerSetting = $this->footerRepo->findOneBySiteAndLocale($site->getId(), $localeObject->getId()) ?? [];
        $menuSettingRes = $this->menuRepo->findBySiteAndLocale($site->getId(), $localeObject->getId()) ?? [];
        $menuSetting = [];
        $menuPages = [];
        $menuUrlKey = '';
        if (!empty($menuSettingRes)) {
        foreach ($menuSettingRes[0]->getTranslations() as $menuSettingItem) {
            if ($menuSettingItem->getLocale()?->getId() !== $localeObject->getId()) {
                continue;
            }
            $menuType = $menuSettingItem->getMegamenutype()?->getName();
            $content = $menuSettingItem->getContent();
            if ($menuType === 'Link') {
                $menuPages[$menuSettingItem->getUrl()] = [
                    'content' => $menuSettingItem->getContent()
                ];
            }
            if ($menuType === 'Form' && $menuSettingItem->getUrl() !== 'holiday_table') {
                $content = explode('"', $content)[1];
                $entityClass = 'App\\Form\\' . ucfirst($content) . 'FormType';
                $menuPages[$menuSettingItem->getUrl()] = [
                    'content' => $this->getFormContent($entityClass),
                    'isForm' => true,
                ];
            }
            if ($menuType === 'Collection') {
                $menuUrlKey = $menuSettingItem->getUrl();
                $content = explode('"', $content)[1];
                $entityClass = 'App\\Entity\\' . $content;
                $repo = $this->em->getRepository($entityClass);
                $defaultCategory = $repo->findDefaultItem();
                $content = [];
                foreach ($defaultCategory->getChildren() as $child) {
                    $childrenCategories = [];
                    if ($child->getChildren()->count() > 0) {
                        foreach ($child->getChildren() as $child2) {
                            $children2Categories = [];
                            if ($child2->getChildren()->count() > 0) {
                                foreach ($child2->getChildren() as $child3) {
                                    $children2Categories[] = [
                                        'name' => $child3->getName(),
                                        'slug' => $child3->getSlug()
                                    ];
                                }
                            }
                            $childrenCategories[] = [
                                'name' => $child2->getName(),
                                'slug' => $child2->getSlug(),
                                'children' => $children2Categories
                            ];
                        }
                    }
                    $content[] = [
                        'name' => $child->getName(),
                        'slug' => $child->getSlug(),
                        'children' => $childrenCategories
                    ];
                }
            }
            $menuSetting[] = [
                'id' => $menuSettingItem->getId(),
                'content' => $content,
                'name' => $menuSettingItem->getName(),
                'megamenutype' => $menuType,
                'position' => $menuSettingItem->getPosition(),
                'url' => $menuSettingItem->getUrl()
            ];
        }
        }
        return [
            'seo' => $seoSetting ? [
                'id' => $seoSetting->getTranslations()[0]->getId(),
                'meta_title' => $seoSetting->getTranslations()[0]->getMetaTitle(),
                'meta_description' => $seoSetting->getTranslations()[0]->getMetaDescription(),
                'meta_keywords' => $seoSetting->getTranslations()[0]->getMetaKeywords(),
                'author' => $seoSetting->getTranslations()[0]->getAuthor(),
                'og_title' => $seoSetting->getTranslations()[0]->getOgTitle(),
                'og_description' => $seoSetting->getTranslations()[0]->getOgDescription(),
                'og_image' => $seoSetting->getTranslations()[0]->getOgImage(),
                'og_type' => $seoSetting->getTranslations()[0]->getOgType(),
                'gtm_code' => $seoSetting->getTranslations()[0]->getGtmCode(),
            ] : [],
            'header' => $headerSetting ? [
                'id' => $headerSetting->getId(),
                'favicon' => $headerSetting->getFavicon(),
                'logo' => $headerSetting->getLogo(),
                'title' => $headerSetting->getTranslations()[0]->getTitle(),
                'work_hours' => $this->formatWorkHours($headerSetting->getTranslations()[0]->getWorkHours()),
                'phones' => $this->formatPhonesDisplay($headerSetting->getPhones()),
                'phone_list' => $this->parsePhoneList($headerSetting->getPhones()),
                'phone_links' => $this->buildPhoneLinks($headerSetting->getPhones()),
                'courier_delivery_cost' => $this->resolveCourierDeliveryCost($headerSetting->getCourierDeliveryCost()),
            ] : [],
            'footer' => $footerSetting ? [
                'id' => $footerSetting->getId(),
                'content' => $footerSetting->getTranslations()[0]->getContent()
            ] : [],
            'menu_pages' => $menuPages ?: [],
            'menu_url_key' => $menuUrlKey,
            'menu' => $menuSetting ?: [],
        ];
    }

    /** @return list<string> */
    private function parsePhoneList(?string $phones): array
    {
        if ($phones === null || trim($phones) === '') {
            return ['066-913-30-97', '067-343-70-40'];
        }

        $parts = preg_split('/[\r\n]+|·/u', $phones) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $part): string => trim($part),
            $parts,
        ), static fn (string $part): bool => $part !== ''));
    }

    private function formatPhonesDisplay(?string $phones): string
    {
        $list = $this->parsePhoneList($phones);

        return $list !== [] ? implode(' · ', $list) : '066-913-30-97 · 067-343-70-40';
    }

    /**
     * @return list<array{display: string, tel: string}>
     */
    private function buildPhoneLinks(?string $phones): array
    {
        return array_map(
            fn (string $display): array => [
                'display' => $display,
                'tel' => $this->formatPhoneTelHref($display),
            ],
            $this->parsePhoneList($phones),
        );
    }

    private function formatPhoneTelHref(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return $phone;
        }

        if (str_starts_with($digits, '380')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+38'.$digits;
        }

        return '+'.$digits;
    }

    private function formatWorkHours(?string $workHours): string
    {
        $workHours = trim((string) $workHours);
        return $workHours !== '' ? $workHours : '7 днів на тиждень <br/> з <strong>9:00</strong> до <strong>19:00</strong>';
    }

    public function getCourierDeliveryCost(string $domain, string $locale, float $default = 100.0): float
    {
        $site = $this->siteRepo->findOneBy(['domain' => $domain]);
        $localeObject = $this->localeRepo->findOneBy(['code' => $locale]);
        if (!$site || !$localeObject) {
            return $default;
        }

        $headerSetting = $this->headerRepo->findOneBySiteAndLocale($site, $localeObject);
        if ($headerSetting === null) {
            return $default;
        }

        return $this->resolveCourierDeliveryCost($headerSetting->getCourierDeliveryCost(), $default);
    }

    /** @return list<string> */
    public function getActivePaymentMethods(string $domain): array
    {
        return $this->resolveSite($domain)?->getActivePaymentMethods() ?? ShopPaymentMethod::all();
    }

    /** @return list<string> */
    public function getActiveDeliveryMethods(string $domain): array
    {
        return $this->resolveSite($domain)?->getActiveDeliveryMethods() ?? ShopDeliveryMethod::all();
    }

    /** @return array{standard_percent: float, novapay_percent: float, prepayment_amount: float, prepayment_notice: string, notice: string} */
    public function getCodCommissionSettings(string $domain, string $locale): array
    {
        $defaults = $this->getDefaultCodCommissionSettings($locale);
        $site = $this->siteRepo->findOneBy(['domain' => $domain]);
        if ($site === null) {
            return $defaults;
        }

        $notice = $locale === 'en'
            ? trim((string) ($site->getCodCommissionNoticeEn() ?? ''))
            : trim((string) ($site->getCodCommissionNoticeUk() ?? ''));

        if ($notice === '') {
            $fallbackNotice = $locale === 'en'
                ? trim((string) ($site->getCodCommissionNoticeUk() ?? ''))
                : trim((string) ($site->getCodCommissionNoticeEn() ?? ''));
            $notice = $fallbackNotice !== '' ? $fallbackNotice : $defaults['notice'];
        }

        return [
            'standard_percent' => max(0.0, min(100.0, round($site->getCodStandardPercent(), 2))),
            'novapay_percent' => max(0.0, min(100.0, round($site->getCodNovapayPercent(), 2))),
            'prepayment_amount' => max(0.0, round($site->getCodPrepaymentAmount(), 2)),
            'prepayment_notice' => $defaults['prepayment_notice'],
            'notice' => $notice,
        ];
    }

    /** @return array{standard_percent: float, novapay_percent: float, prepayment_amount: float, prepayment_notice: string, notice: string} */
    private function getDefaultCodCommissionSettings(string $locale): array
    {
        if ($locale === 'en') {
            return [
                'standard_percent' => 2.0,
                'novapay_percent' => 1.0,
                'prepayment_amount' => 100.0,
                'prepayment_notice' => 'Prepayment for shipping: %prepayment_amount%. (Payment details are in the email after order confirmation)',
                'notice' => 'Standard method (cash/terminal): %standard_percent%% of the order total + 20 UAH = %standard_amount%.' . "\n"
                    . 'NovaPay: %novapay_percent%% of the order total + 10 UAH = %novapay_amount%. '
                    . '<a href="https://novapay.ua/pisljaplata/" target="_blank" rel="noopener noreferrer">Details</a>.',
            ];
        }

        return [
            'standard_percent' => 2.0,
            'novapay_percent' => 1.0,
            'prepayment_amount' => 100.0,
            'prepayment_notice' => 'Аванс для відправки товару %prepayment_amount%. (Реквізити для оплати в листі після підтвердження замовлення)',
            'notice' => 'Стандартний спосіб (готівка/термінал): %standard_percent%% від суми + 20 грн = %standard_amount%.' . "\n"
                . 'NovaPay: %novapay_percent%% від суми + 10 грн = %novapay_amount%. '
                . '<a href="https://novapay.ua/pisljaplata/" target="_blank" rel="noopener noreferrer">Деталі</a>.',
        ];
    }

    private function resolveSite(string $domain): ?Site
    {
        return $this->siteRepo->findOneBy(['domain' => $domain]);
    }

    private function resolveCourierDeliveryCost(?float $cost, float $default = 100.0): float
    {
        if ($cost === null || $cost <= 0) {
            return $default;
        }

        return round($cost, 2);
    }

    private function getFormContent(string $entityClass)
    {
        return '<div class="container">
    <section class="d-flex flex-wrap justify-content-left py-3 mb-4 border-bottom">
        <h1 class="w-100 mb-4">{{ \'Contact\'|trans }}</h1>
    </section>
    <section class="d-flex flex-wrap justify-content-left py-3 mb-4 border-bottom">

        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    {% for message in app.flashes(\'success\') %}
                        <div class="alert alert-success">
                            {{ message }}
                        </div>
                    {% endfor %}

                    {{ form_start(' . $entityClass . ') }}
                    {{ form_row(' . $entityClass . '.name , {\'attr\': {\'class\': \'mb-1 form-control name\'}}) }}
                    {{ form_row(' . $entityClass . '.email, {\'attr\': {\'class\': \'mb-1 form-control email\'}}) }}
                    {{ form_row(' . $entityClass . '.message,{\'attr\': {\'class\': \'mb-1 form-control message\'}}) }}
                    <button type="submit" class="btn btn-primary">Send Message</button>
                    {{ form_end(' . $entityClass . ') }}
                </div>
            </div>
        </div>
    </section>
</div>';
    }

}
