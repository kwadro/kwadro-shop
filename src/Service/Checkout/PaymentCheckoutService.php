<?php

namespace App\Service\Checkout;

use App\Entity\Payment;
use App\Entity\ShopPaymentMethod;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PaymentCheckoutService
{
    private const LIQPAY_CHECKOUT_URL = 'https://www.liqpay.ua/api/3/checkout';
    private const MONOBANK_INVOICE_URL = 'https://api.monobank.ua/api/merchant/invoice/create';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly OrderCheckoutService $orderCheckoutService,
        private readonly ?string $liqpayPublicKey,
        private readonly ?string $liqpayPrivateKey,
        private readonly ?string $monobankToken,
    ) {
    }

    public function isPrivatBankConfigured(): bool
    {
        return $this->liqpayPublicKey !== null && $this->liqpayPublicKey !== ''
            && $this->liqpayPrivateKey !== null && $this->liqpayPrivateKey !== '';
    }

    public function isMonobankConfigured(): bool
    {
        return $this->monobankToken !== null && $this->monobankToken !== '';
    }

    public function isAnyGatewayConfigured(): bool
    {
        return $this->isPrivatBankConfigured() || $this->isMonobankConfigured();
    }

    /**
     * @param array<string, mixed> $cart
     * @param array<string, mixed> $checkoutData
     */
    public function createPayment(
        string $method,
        array $cart,
        array $checkoutData,
        string $locale,
        ?User $customer = null,
        ?string $visitorId = null,
        ?string $domain = null,
    ): PaymentRedirectResult {

        $order = $this->orderCheckoutService->resolveOrCreateOrderForCheckout(
            $cart,
            $checkoutData,
            $customer,
            $visitorId,
            $locale,
            $domain,
        );

        if ($method === ShopPaymentMethod::OnDelivery) {
            $this->orderCheckoutService->completeOnDeliveryCheckout($order, $order->getSite());

            return PaymentRedirectResult::success();
        }

        $paymentAmount = $method === ShopPaymentMethod::Privatbank
            ? max(0.0, round($this->orderCheckoutService->resolveCartSubtotal($cart), 2))
            : $order->getAmount();

        $payment = $this->orderCheckoutService->createPendingPayment($order, $method, $paymentAmount, $order->getSite());

        $gatewayReference = (string) $payment->getGatewayReference();
        $amount = $paymentAmount;
        $description = $this->buildPaymentDescription($cart);

        $result = match ($method) {
            'privatbank' => $this->createPrivatBankPayment($gatewayReference, $amount, $description, $locale, $payment),
            'monobank' => $this->createMonobankPayment($gatewayReference, $amount, $description, $locale, $payment),
            default => throw new \InvalidArgumentException(sprintf('Unsupported payment method "%s".', $method)),
        };

        if ($result->getType() === 'redirect') {
            $this->orderCheckoutService->updatePaymentGatewayData($payment, $result->getUrl(), null);
        } else {
            $this->orderCheckoutService->updatePaymentGatewayData($payment, null, [
                'type' => 'liqpay_form',
                'action' => $result->getAction(),
            ]);
        }

        return $result;
    }

    private function createPrivatBankPayment(
        string $gatewayReference,
        float $amount,
        string $description,
        string $locale,
        Payment $payment,
    ): PaymentRedirectResult {
        return $this->createPrivatBankInvoice($payment, $amount, $description, $locale);
    }

    public function createMonobankInvoice(
        Payment $payment,
        float $amount,
        string $description,
        string $locale,
        ?string $redirectRoute = null,
        array $redirectParams = [],
    ): PaymentRedirectResult {
        if (!$this->isMonobankConfigured()) {
            throw new \RuntimeException('Monobank is not configured.');
        }

        $gatewayReference = (string) $payment->getGatewayReference();
        $redirectRoute ??= 'shop_checkout_success';
        $redirectParams = $redirectParams !== [] ? $redirectParams : ['_locale' => $locale];

        $payload = [
            'amount' => (int) round($amount * 100),
            'ccy' => 980,
            'merchantPaymInfo' => [
                'reference' => $gatewayReference,
                'destination' => $description,
            ],
            'redirectUrl' => $this->urlGenerator->generate($redirectRoute, $redirectParams, UrlGeneratorInterface::ABSOLUTE_URL),
            'webHookUrl' => $this->urlGenerator->generate('shop_monobank_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $response = $this->httpClient->request('POST', self::MONOBANK_INVOICE_URL, [
            'headers' => [
                'X-Token' => $this->monobankToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout'  => 20,
        ])->toArray(false);

        $invoiceId = $response['invoiceId'] ?? null;
        if (\is_string($invoiceId) && $invoiceId !== '') {
            $payment->setMonobankInvoiceId($invoiceId);
        }

        $this->orderCheckoutService->updatePaymentGatewayData($payment, null, $response);

        $pageUrl = $response['pageUrl'] ?? null;
        if (!\is_string($pageUrl) || $pageUrl === '') {
            $error = $response['errText'] ?? $response['errorDescription'] ?? 'Monobank invoice creation failed';
            throw new \RuntimeException((string) $error);
        }

        $this->orderCheckoutService->updatePaymentGatewayData($payment, $pageUrl, $response);

        return PaymentRedirectResult::redirect($pageUrl);
    }

    public function createPrivatBankInvoice(
        Payment $payment,
        float $amount,
        string $description,
        string $locale,
        ?string $redirectRoute = null,
        array $redirectParams = [],
    ): PaymentRedirectResult {
        if (!$this->isPrivatBankConfigured()) {
            throw new \RuntimeException('PrivatBank (LiqPay) is not configured.');
        }

        $gatewayReference = (string) $payment->getGatewayReference();
        $redirectRoute ??= 'shop_checkout_success';
        $redirectParams = $redirectParams !== [] ? $redirectParams : ['_locale' => $locale];

        $params = [
            'version' => 3,
            'public_key' => $this->liqpayPublicKey,
            'action' => 'pay',
            'amount' => round($amount, 2),
            'currency' => 'UAH',
            'description' => $description,
            'order_id' => $gatewayReference,
            'language' => $locale === 'en' ? 'en' : 'uk',
            'result_url' => $this->urlGenerator->generate($redirectRoute, $redirectParams, UrlGeneratorInterface::ABSOLUTE_URL),
            'server_url' => $this->urlGenerator->generate('shop_checkout_liqpay_callback', ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $data = base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE));
        $signature = base64_encode(sha1($this->liqpayPrivateKey . $data . $this->liqpayPrivateKey, true));

        $this->orderCheckoutService->updatePaymentGatewayData($payment, self::LIQPAY_CHECKOUT_URL, [
            'type' => 'liqpay_form',
            'action' => self::LIQPAY_CHECKOUT_URL,
            'data' => $data,
            'signature' => $signature,
        ]);

        return PaymentRedirectResult::liqPayForm(self::LIQPAY_CHECKOUT_URL, $data, $signature);
    }

    private function createMonobankPayment(
        string $gatewayReference,
        float $amount,
        string $description,
        string $locale,
        Payment $payment,
    ): PaymentRedirectResult {
        return $this->createMonobankInvoice($payment, $amount, $description, $locale);
    }

    /** @param array<string, mixed> $cart */
    private function buildPaymentDescription(array $cart): string
    {
        $items = $cart['items'] ?? null;
        if (!\is_array($items) || $items === []) {
            return sprintf(
                '%s x%d',
                (string) ($cart['product']['name'] ?? 'Order'),
                (int) ($cart['quantity'] ?? 1),
            );
        }

        $first = $items[0];
        $product = \is_array($first['product'] ?? null) ? $first['product'] : [];
        $name = (string) ($product['name'] ?? 'Order');
        $count = \count($items);

        if ($count === 1) {
            return sprintf('%s x%d', $name, (int) ($first['quantity'] ?? 1));
        }

        return sprintf('%s +%d', $name, $count - 1);
    }
}
