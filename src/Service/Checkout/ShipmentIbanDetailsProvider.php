<?php

namespace App\Service\Checkout;

use App\Entity\BankAccount;
use App\Entity\Order;
use App\Entity\Site;
use App\Repository\BankAccountRepository;
use App\Repository\LocaleRepository;

final class ShipmentIbanDetailsProvider
{
    /** Universal Bank (Monobank) */
    private const MFO_MONOBANK = '322001';
    /** PrivatBank */
    private const MFO_PRIVATBANK = '305299';

    public function __construct(
        private readonly BankAccountRepository $bankAccountRepository,
        private readonly LocaleRepository $localeRepository,
    ) {
    }

    public function createForOrder(Order $order, Site $site, string $locale): ShipmentIbanDetails
    {
        $accounts = $this->createAllForOrder($order, $site, $locale);

        foreach ($accounts as $account) {
            if ($account->isConfigured()) {
                return $account;
            }
        }

        return new ShipmentIbanDetails(
            iban: '',
            recipient: '',
            bankName: '',
            edrpou: '',
            paymentPurpose: $this->buildPurpose($order, $locale),
        );
    }

    /**
     * Preferred order: Monobank IBAN, PrivatBank IBAN (unique by bank key).
     *
     * @return list<ShipmentIbanDetails>
     */
    public function createAllForOrder(Order $order, Site $site, string $locale): array
    {
        $localeEntity = $this->localeRepository->findOneBy(['code' => $locale])
            ?? $this->localeRepository->findOneBy(['is_default' => 'Yes'])
            ?? $this->localeRepository->findOneBy([], ['id' => 'ASC']);

        $paymentPurpose = $this->buildPurpose($order, $locale);

        if ($localeEntity === null) {
            return [];
        }

        $accounts = $this->bankAccountRepository->findAllForSiteAndLocale($site, $localeEntity);
        $byKey = [];

        foreach ($accounts as $account) {
            $bankKey = $this->resolveBankKey($account);
            if ($bankKey === null || isset($byKey[$bankKey])) {
                continue;
            }

            $byKey[$bankKey] = new ShipmentIbanDetails(
                iban: $account->getIban(),
                recipient: $account->getRecipient(),
                bankName: $account->getBankName(),
                edrpou: $account->getEdrpou(),
                paymentPurpose: $paymentPurpose,
                bankKey: $bankKey,
                title: $account->getTitle() !== '' ? $account->getTitle() : $account->getBankName(),
            );
        }

        $ordered = [];
        foreach ([ShipmentIbanDetails::BANK_MONOBANK, ShipmentIbanDetails::BANK_PRIVATBANK] as $key) {
            if (isset($byKey[$key])) {
                $ordered[] = $byKey[$key];
            }
        }

        return $ordered;
    }

    public function createForOrderAndBank(Order $order, Site $site, string $locale, string $bankKey): ?ShipmentIbanDetails
    {
        foreach ($this->createAllForOrder($order, $site, $locale) as $details) {
            if ($details->bankKey === $bankKey) {
                return $details;
            }
        }

        return null;
    }

    private function buildPurpose(Order $order, string $locale): string
    {
        $orderNumber = $order->getOrderNumber();

        return $locale === 'en'
            ? sprintf('Shipping prepayment for order %s', $orderNumber)
            : sprintf('Оплата завдатку за замовлення %s', $orderNumber);
    }

    private function resolveBankKey(BankAccount $account): ?string
    {
        $iban = preg_replace('/\s+/', '', $account->getIban()) ?? '';
        if (strlen($iban) >= 10 && str_starts_with($iban, 'UA')) {
            $mfo = substr($iban, 4, 6);
            if ($mfo === self::MFO_MONOBANK) {
                return ShipmentIbanDetails::BANK_MONOBANK;
            }
            if ($mfo === self::MFO_PRIVATBANK) {
                return ShipmentIbanDetails::BANK_PRIVATBANK;
            }
        }

        $haystack = mb_strtolower($account->getBankName() . ' ' . $account->getTitle());
        if (str_contains($haystack, 'приват') || str_contains($haystack, 'privat')) {
            return ShipmentIbanDetails::BANK_PRIVATBANK;
        }
        if (str_contains($haystack, 'моно')
            || str_contains($haystack, 'mono')
            || str_contains($haystack, 'універсал')
            || str_contains($haystack, 'универсал')
            || str_contains($haystack, 'universal')) {
            return ShipmentIbanDetails::BANK_MONOBANK;
        }

        return null;
    }
}
