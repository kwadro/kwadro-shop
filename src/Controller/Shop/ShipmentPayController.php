<?php

namespace App\Controller\Shop;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\ShopPaymentMethod;
use App\Entity\Site;
use App\Repository\OrderRepository;
use App\Repository\SiteRepository;
use App\Routing\ShopRoutes;
use App\Service\Checkout\NbuPaymentQrEncoder;
use App\Service\Checkout\QrCodeGenerator;
use App\Service\Checkout\ShipmentDepositPaymentService;
use App\Service\Checkout\ShipmentIbanDetails;
use App\Service\Checkout\ShipmentIbanDetailsProvider;
use App\Service\Checkout\ShipmentPayTokenService;
use App\Service\Checkout\ShipmentPayUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ShipmentPayController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly SiteRepository $siteRepository,
        private readonly ShipmentPayTokenService $tokenService,
        private readonly ShipmentPayUrlGenerator $shipmentPayUrlGenerator,
        private readonly ShipmentDepositPaymentService $depositPaymentService,
        private readonly QrCodeGenerator $qrCodeGenerator,
        private readonly NbuPaymentQrEncoder $nbuPaymentQrEncoder,
        private readonly ShipmentIbanDetailsProvider $ibanDetailsProvider,
    ) {
    }

    #[Route(
        '/{_locale}/order/{orderNumber}/shipment-pay/iban',
        name: 'shop_order_shipment_pay_iban',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS, 'orderNumber' => '[A-Za-z0-9\-]+'],
        methods: ['GET'],
    )]
    public function iban(string $_locale, string $orderNumber, Request $request): Response
    {
        [$order, $codPayment, $site] = $this->resolveAccess($orderNumber, $request);
        $checkout = $this->depositPaymentService->resolveCheckout($order, $site, $_locale);
        $ibanDetails = $this->ibanDetailsProvider->createForOrder($order, $site, $_locale);
        $token = $this->tokenService->generate($order, $codPayment);
        $qrPayload = $this->resolveIbanQrPayload($ibanDetails, $checkout->amount);

        return $this->render('shop/checkout/shipment_pay_iban.html.twig', [
            'order' => $order,
            'checkout' => $checkout,
            'iban' => $ibanDetails,
            'qr_url' => $qrPayload !== null
                ? $this->generateUrl('shop_order_shipment_pay_iban_qr', [
                    '_locale' => $_locale,
                    'orderNumber' => $orderNumber,
                    'token' => $token,
                ])
                : null,
            'card_pay_url' => $this->generateUrl('shop_order_shipment_pay', [
                '_locale' => $_locale,
                'orderNumber' => $orderNumber,
                'token' => $token,
            ]),
        ]);
    }

    #[Route(
        '/{_locale}/order/{orderNumber}/shipment-pay/iban/qr.png',
        name: 'shop_order_shipment_pay_iban_qr',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS, 'orderNumber' => '[A-Za-z0-9\-]+'],
        methods: ['GET'],
    )]
    public function ibanQr(string $_locale, string $orderNumber, Request $request): Response
    {
        [$order, , $site] = $this->resolveAccess($orderNumber, $request);
        $checkout = $this->depositPaymentService->resolveCheckout($order, $site, $_locale);
        $ibanDetails = $this->ibanDetailsProvider->createForOrder($order, $site, $_locale);
        $qrPayload = $this->resolveIbanQrPayload($ibanDetails, $checkout->amount);

        if ($qrPayload === null) {
            throw new NotFoundHttpException();
        }

        $png = $this->qrCodeGenerator->generatePng($qrPayload);

        return new Response($png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    #[Route(
        '/{_locale}/order/{orderNumber}/shipment-pay',
        name: 'shop_order_shipment_pay',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS, 'orderNumber' => '[A-Za-z0-9\-]+'],
        methods: ['GET'],
    )]
    public function pay(string $_locale, string $orderNumber, Request $request): Response
    {
        [$order, $codPayment, $site] = $this->resolveAccess($orderNumber, $request);
        $checkout = $this->depositPaymentService->resolveCheckout($order, $site, $_locale);
        $qrPayload = $this->depositPaymentService->resolveQrPayload($checkout);
        $liqpayWidget = $this->depositPaymentService->resolveLiqPayWidget($checkout);

        $token = $this->tokenService->generate($order, $codPayment);

        return $this->render('shop/checkout/shipment_pay.html.twig', [
            'order' => $order,
            'checkout' => $checkout,
            'cod_payment' => $codPayment,
            'token' => $token,
            'iban_pay_url' => $this->generateUrl('shop_order_shipment_pay_iban', [
                '_locale' => $_locale,
                'orderNumber' => $orderNumber,
                'token' => $token,
            ]),
            'qr_url' => $checkout->gatewayMethod === ShopPaymentMethod::Monobank && $qrPayload !== null
                ? $this->generateUrl('shop_order_shipment_pay_qr', [
                    '_locale' => $_locale,
                    'orderNumber' => $orderNumber,
                    'token' => $token,
                ])
                : null,
            'qr_download_url' => $checkout->gatewayMethod === ShopPaymentMethod::Monobank && $qrPayload !== null
                ? $this->generateUrl('shop_order_shipment_pay_qr', [
                    '_locale' => $_locale,
                    'orderNumber' => $orderNumber,
                    'token' => $token,
                    'download' => 1,
                ])
                : null,
            'liqpay_widget' => $liqpayWidget,
            'pay_url' => $checkout->payUrl,
            'gateway_method' => $checkout->gatewayMethod,
        ]);
    }

    #[Route(
        '/{_locale}/order/{orderNumber}/shipment-pay/qr.png',
        name: 'shop_order_shipment_pay_qr',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS, 'orderNumber' => '[A-Za-z0-9\-]+'],
        methods: ['GET'],
    )]
    public function qr(string $_locale, string $orderNumber, Request $request): Response
    {
        [$order, $codPayment, $site] = $this->resolveAccess($orderNumber, $request);
        $checkout = $this->depositPaymentService->resolveCheckout($order, $site, $_locale);
        $qrPayload = $this->depositPaymentService->resolveQrPayload($checkout);

        if ($qrPayload === null) {
            throw new NotFoundHttpException();
        }

        $png = $this->qrCodeGenerator->generatePng($qrPayload);
        $response = new Response($png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);

        if ($request->query->getBoolean('download')) {
            $filename = sprintf('shipment-pay-%s.png', $order->getOrderNumber());
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        }

        return $response;
    }

    /** @return array{0: Order, 1: Payment, 2: Site} */
    private function resolveAccess(string $orderNumber, Request $request): array
    {
        $order = $this->orderRepository->findOneByOrderNumber($orderNumber);
        if ($order === null) {
            throw new NotFoundHttpException();
        }

        $codPayment = $this->shipmentPayUrlGenerator->resolveCodPayment($order);
        if ($codPayment === null) {
            throw new NotFoundHttpException();
        }

        $token = (string) $request->query->get('token', '');
        if (!$this->tokenService->matches($order, $codPayment, $token)) {
            throw new AccessDeniedHttpException();
        }

        $site = $this->siteRepository->findOneBy(['domain' => $request->getHost()])
            ?? $this->shipmentPayUrlGenerator->resolveSite();
        if ($site === null) {
            throw new NotFoundHttpException();
        }

        return [$order, $codPayment, $site];
    }

    private function resolveIbanQrPayload(ShipmentIbanDetails $ibanDetails, float $amount): ?string
    {
        if (!$this->nbuPaymentQrEncoder->canEncode($ibanDetails)) {
            return null;
        }

        try {
            return $this->nbuPaymentQrEncoder->encodeUrl($ibanDetails, $amount);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
