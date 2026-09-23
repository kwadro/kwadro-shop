<?php

namespace App\Service\Checkout;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\Site;
use App\Repository\SiteRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class ShipmentPayUrlGenerator
{
    public function __construct(
        private readonly ShipmentPayTokenService $tokenService,
        private readonly SiteRepository $siteRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RouterInterface $router,
        private readonly string $appDomain,
    ) {
    }

    public function generateForOrder(Order $order, ?Payment $payment = null): string
    {
        if (!$this->canGenerateShipmentPayUrl($order)) {
            return '';
        }

        $locale = $order->getLocale() !== '' ? $order->getLocale() : 'uk';
        $this->applySiteRoutingContext();

        return $this->urlGenerator->generate('shop_order_shipment_pay', [
            '_locale' => $locale,
            'orderNumber' => $order->getOrderNumber(),
            'token' => $this->tokenService->generateForOrder($order),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function generateIbanUrlForOrder(Order $order, ?Payment $payment = null): string
    {
        if (!$this->canGenerateShipmentPayUrl($order)) {
            return '';
        }

        $locale = $order->getLocale() !== '' ? $order->getLocale() : 'uk';
        $this->applySiteRoutingContext();

        return $this->urlGenerator->generate('shop_order_shipment_pay', [
            '_locale' => $locale,
            'orderNumber' => $order->getOrderNumber(),
            'token' => $this->tokenService->generateForOrder($order),
        ], UrlGeneratorInterface::ABSOLUTE_URL) . '#iban';
    }

    private function canGenerateShipmentPayUrl(Order $order): bool
    {
        if ($order->getStatus() !== OrderStatus::AwaitingDepositForShipment) {
            return false;
        }

        $site = $order->getSite() ?? $this->resolveSite();

        return ($site?->getCodPrepaymentAmount() ?? 0.0) > 0;
    }

    public function resolveSite(?Site $site = null): ?Site
    {
        if ($site instanceof Site) {
            return $site;
        }

        $domain = trim($this->appDomain);
        if ($domain !== '') {
            $resolved = $this->siteRepository->findOneBy(['domain' => $domain]);
            if ($resolved instanceof Site) {
                return $resolved;
            }
        }

        return $this->siteRepository->findOneBy([], ['id' => 'ASC']);
    }

    private function applySiteRoutingContext(): void
    {
        $site = $this->resolveSite();
        $domain = trim((string) ($site?->getDomain() ?? $this->appDomain));
        if ($domain === '') {
            return;
        }

        $this->router->getContext()->setHost($domain);
        $this->router->getContext()->setScheme('https');
    }
}
