<?php

namespace App\Service\Mail;

use App\Entity\Site;
use App\Repository\BankAccountRepository;
use App\Repository\EmailParameterRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;

final class EmailBrandingContextProvider
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly EmailParameterRepository $parameterRepository,
        private readonly BankAccountRepository $bankAccountRepository,
    ) {
    }

    /** @return array<string, string> */
    public function create(?Site $site = null, string $locale = 'uk'): array
    {
        $site ??= $this->siteRepository->findOneBy([], ['id' => 'ASC']);
        if ($site === null) {
            return $this->fallbackContext();
        }

        $localeEntity = $this->localeRepository->findOneBy(['code' => $locale])
            ?? $this->localeRepository->findOneBy(['is_default' => 'Yes'])
            ?? $this->localeRepository->findOneBy([], ['id' => 'ASC']);

        if ($localeEntity === null) {
            return $this->fallbackContext();
        }

        $parameters = $this->parameterRepository->findValueMapBySiteAndLocale($site, $localeEntity);

        $bankAccount = $this->bankAccountRepository->findPreferredForSiteAndLocale($site, $localeEntity);
        if ($bankAccount !== null) {
            $parameters = array_merge($parameters, $bankAccount->toEmailParameterMap());
        }

        if ($parameters === []) {
            return $this->fallbackContext();
        }

        return $this->finalizeContext($parameters);
    }

    /** @param array<string, string> $parameters */
    private function finalizeContext(array $parameters): array
    {
        $parameters['current_year'] = (string) (new \DateTimeImmutable())->format('Y');


        return $parameters;
    }

    /** @return array<string, string> */
    private function fallbackContext(): array
    {
        return $this->finalizeContext([
            'shop_url' => 'https://kwadro.com.ua',
            'shop_logo_url' => 'https://kwadro.com.ua/uploads/images/favicon.png',
            'shop_title' => 'Kvadro',
            'support_phone' => '066-913-30-97',
            'support_email' => 'info@kwadro.com.ua',
        ]);
    }
}
