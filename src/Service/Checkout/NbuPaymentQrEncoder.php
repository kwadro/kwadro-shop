<?php

namespace App\Service\Checkout;

final class NbuPaymentQrEncoder
{
    /** Documented start codes for NBU payment QR (Privat24 / Mono / others). */
    private const PREFIX = 'https://bank.gov.ua/qr/';

    /** Format version 002 — current NBU rules (001 also valid; 003 is not). */
    private const VERSION = '002';

    private const SERVICE_TAG = 'BCD';
    private const ENCODING_UTF8 = '1';
    private const FUNCTION_UCT = 'UCT';
    private const MAX_RECIPIENT_LENGTH = 38;
    private const MAX_PURPOSE_LENGTH = 140;
    private const MAX_PAYLOAD_BYTES = 500;

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

        $recipient = $this->truncate($details->recipient, self::MAX_RECIPIENT_LENGTH);
        $purpose = $this->truncate($details->paymentPurpose, self::MAX_PURPOSE_LENGTH);

        // Field order per NBU Rules (version 002): LF separators.
        $payload = implode("\n", [
            self::SERVICE_TAG,
            self::VERSION,
            self::ENCODING_UTF8,
            self::FUNCTION_UCT,
            '', // reserved BIC
            $recipient,
            $this->normalizeIban($details->iban),
            $this->formatAmount($amount),
            trim($details->edrpou),
            '', // Category purpose (optional)
            '', // Reference (optional)
            $purpose,
        ]);

        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            throw new \InvalidArgumentException('NBU QR payload is too long.');
        }

        return self::PREFIX . $this->base64UrlEncode($payload);
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
        }

        if (trim($details->edrpou) === '') {
            $errors[] = 'Recipient code (EDRPOU/IPN) is required';
        } elseif (mb_strlen(trim($details->edrpou)) > 35) {
            $errors[] = 'Recipient code is too long';
        }

        if (trim($details->paymentPurpose) === '') {
            $errors[] = 'Payment purpose is required';
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
        $iban = $this->normalizeIban($iban);
        if (!preg_match('/^UA\d{27}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $expanded = '';
        foreach (str_split($rearranged) as $char) {
            $expanded .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($expanded, 9) as $block) {
            $remainder = (int) (($remainder . $block) % 97);
        }

        return $remainder === 1;
    }

    private function formatAmount(float $amount): string
    {
        if ($amount <= 0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

        return 'UAH' . $formatted;
    }

    private function truncate(string $value, int $maxLength): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
