<?php

namespace App\Service\Checkout;

use App\Entity\Order;
use App\Entity\Site;
use App\Repository\BankAccountRepository;
use App\Repository\LocaleRepository;

final class ShipmentIbanDetailsProvider
{
    public function __construct(
        private readonly BankAccountRepository $bankAccountRepository,
        private readonly LocaleRepository $localeRepository,
    ) {
    }

    public function createForOrder(Order $order, Site $site, string $locale): ShipmentIbanDetails
    {
        $localeEntity = $this->localeRepository->findOneBy(['code' => $locale])
            ?? $this->localeRepository->findOneBy(['is_default' => 'Yes'])
            ?? $this->localeRepository->findOneBy([], ['id' => 'ASC']);

        $orderNumber = $order->getOrderNumber();
        $paymentPurpose = $locale === 'en'
            ? sprintf('Shipping prepayment for order %s', $orderNumber)
            : sprintf('Оплата завдатку за замовлення %s', $orderNumber);

        $account = $localeEntity !== null
            ? $this->bankAccountRepository->findPreferredForSiteAndLocale($site, $localeEntity)
            : null;

        if ($account === null) {
            return new ShipmentIbanDetails(
                iban: '',
                recipient: '',
                bankName: '',
                edrpou: '',
                paymentPurpose: $paymentPurpose,
            );
        }

        return new ShipmentIbanDetails(
            iban: $account->getIban(),
            recipient: $account->getRecipient(),
            bankName: $account->getBankName(),
            edrpou: $account->getEdrpou(),
            paymentPurpose: $paymentPurpose,
        );
    }
}
