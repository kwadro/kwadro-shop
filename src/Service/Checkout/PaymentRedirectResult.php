<?php

namespace App\Service\Checkout;

final class PaymentRedirectResult
{
    private function __construct(
        private readonly string $type,
        private readonly ?string $url = null,
        private readonly ?string $action = null,
        private readonly ?string $data = null,
        private readonly ?string $signature = null,
    ) {
    }

    public static function redirect(string $url): self
    {
        return new self('redirect', url: $url);
    }

    public static function liqPayForm(string $action, string $data, string $signature): self
    {
        return new self('liqpay_form', action: $action, data: $data, signature: $signature);
    }

    public static function success(): self
    {
        return new self('success');
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }
}
