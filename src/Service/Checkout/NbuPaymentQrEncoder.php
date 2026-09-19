<?php

namespace App\Service\Checkout;

final class NbuPaymentQrEncoder
{
    private const PREFIX = 'https://qr.bank.gov.ua/';
    private const SERVICE_TAG = 'BCD';
    private const VERSION = '003';
    private const ENCODING_UTF8 = '1';
    private const FUNCTION_UCT = 'UCT';
    private const MAX_URL_LENGTH = 331;

    public function canEncode(ShipmentIbanDetails $details): bool
    {
        return $this->validate($details) === [];
    }

    public function encodeUrl(ShipmentIbanDetails $details, float $amount): string
    {
        $errors = $this->validate($details, $amount);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        $payload = implode("\n", [
            self::SERVICE_TAG,
            self::VERSION,
            self::ENCODING_UTF8,
            self::FUNCTION_UCT,
            '',
            $details->recipient,
            $this->normalizeIban($details->iban),
            $this->formatAmount($amount),
            $details->edrpou,
            '',
            '',
            $details->paymentPurpose,
            '',
            '',
            '',
            '',
            '',
        ]);

        $url = self::PREFIX . $this->base64UrlEncode($payload);
        if (strlen($url) > self::MAX_URL_LENGTH) {
            throw new \InvalidArgumentException('NBU QR payload is too long.');
        }

        return $url;
    }

    /** @return list<string> */
    private function validate(ShipmentIbanDetails $details, ?float $amount = null): array
    {
        $errors = [];

        if (!$details->isConfigured()) {
            $errors[] = 'IBAN is required';
        } elseif (!$this->isValidIban($details->iban)) {
            $errors[] = 'Invalid Ukrainian IBAN format';
        }

        if (trim($details->recipient) === '') {
            $errors[] = 'Recipient is required';
        } elseif (mb_strlen($details->recipient) > 140) {
            $errors[] = 'Recipient name is too long';
        }

        if (trim($details->edrpou) === '') {
            $errors[] = 'Recipient code (EDRPOU/IPN) is required';
        } elseif (mb_strlen($details->edrpou) > 35) {
            $errors[] = 'Recipient code is too long';
        }

        if (trim($details->paymentPurpose) === '') {
            $errors[] = 'Payment purpose is required';
        } elseif (mb_strlen($details->paymentPurpose) > 420) {
            $errors[] = 'Payment purpose is too long';
        }

        if ($amount !== null && ($amount < 0 || $amount > 999999999.99)) {
            $errors[] = 'Amount must be between 0 and 999999999.99';
        }

        return $errors;
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
    }

    private function isValidIban(string $iban): bool
    {
        return (bool) preg_match('/^UA\d{27}$/', $this->normalizeIban($iban));
    }

    private function formatAmount(float $amount): string
    {
        if ($amount <= 0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

        return 'UAH' . $formatted;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
