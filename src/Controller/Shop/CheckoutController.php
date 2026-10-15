<?php

namespace App\Controller\Shop;

use App\Dto\CheckoutContactData;
use App\Dto\CheckoutDeliveryData;
use App\Dto\CheckoutPaymentData;
use App\Form\Type\CheckoutContactFormType;
use App\Form\Type\CheckoutDeliveryFormType;
use App\Form\Type\CheckoutPaymentFormType;
use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\Payment;
use App\Entity\PaymentStatus;
use App\Entity\ShopDeliveryMethod;
use App\Entity\ShopPaymentMethod;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Routing\ShopRoutes;
use App\Service\Cart\CartStorageService;
use App\Service\Cart\CartSummaryFormatter;
use App\Service\Cart\VisitorIdResolver;
use App\Service\Checkout\CartShipmentPlanner;
use App\Service\Checkout\CheckoutPaymentMethodResolver;
use App\Service\Checkout\LiqPay\LiqPayCallbackHandler;
use App\Service\Checkout\Monobank\MonobankInvoiceStatusClient;
use App\Service\Checkout\Monobank\MonobankWebhookHandler;
use App\Service\Checkout\OrderCheckoutService;
use App\Service\Checkout\PaymentCheckoutService;
use App\Service\GeoIp\UserCityService;
use App\Service\NovaPoshtaClient;
use App\Service\NovaPoshtaDeliveryQuoteService;
use App\Service\ProductCatalog;
use App\Service\SiteSettingsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class CheckoutController extends AbstractController
{
    private const DEFAULT_CITY_NAME = 'Івано Франківськ';

    public function __construct(
        private readonly ProductCatalog $productCatalog,
        private readonly NovaPoshtaClient $novaPoshtaClient,
        private readonly PaymentCheckoutService $paymentCheckoutService,
        private readonly CartStorageService $cartStorage,
        private readonly OrderCheckoutService $orderCheckoutService,
        private readonly PaymentRepository $paymentRepository,
        private readonly LiqPayCallbackHandler $liqPayCallbackHandler,
        private readonly MonobankInvoiceStatusClient $monobankInvoiceStatusClient,
        private readonly UserCityService $userCityService,
        private readonly NovaPoshtaDeliveryQuoteService $deliveryQuoteService,
        private readonly SiteSettingsProvider $siteSettingsProvider,
        private readonly CheckoutPaymentMethodResolver $paymentMethodResolver,
        private readonly CartShipmentPlanner $shipmentPlanner,
        private readonly CartSummaryFormatter $cartSummaryFormatter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        '/{_locale}/checkout/start',
        name: 'shop_checkout_start',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['POST'],
    )]
    public function start(string $_locale, Request $request): Response
    {
        if ($blockedResponse = $this->renderOutsideUkraineCheckoutBlocked($_locale)) {
            return $blockedResponse;
        }

        if (!$this->isCsrfTokenValid('checkout_start', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $productId = (int) $request->request->get('product_id');
        $quantity = max(1, (int) $request->request->get('quantity', 1));

        $product = $this->productCatalog->findById($productId);
        if (!$product || empty($product['inStock']) || empty($product['hasPrice'])) {
            throw new NotFoundHttpException();
        }

        $offerId = (int) $request->request->get('offer_id');
        if ($offerId <= 0) {
            $offerId = (int) ($product['selectedOfferId'] ?? 0);
        }
        if ($offerId > 0) {
            $product = $this->productCatalog->applyOffer($product, $offerId);
        }

        $quantity = min($quantity, max(1, (int) ($product['stockQty'] ?? 1)));

        $this->cartStorage->setCartData([
            'product_id' => $productId,
            'quantity' => $quantity,
            'product' => $product,
        ]);

        return $this->redirectToRoute('shop_checkout', ['_locale' => $_locale]);
    }

    #[Route(
        '/{_locale}/checkout',
        name: 'shop_checkout',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET', 'POST'],
    )]
    public function checkout(string $_locale, Request $request): Response
    {
        if ($blockedResponse = $this->renderOutsideUkraineCheckoutBlocked($_locale)) {
            return $blockedResponse;
        }

        $cart = $this->cartStorage->getCartData();

        if (!\is_array($cart) || empty($cart['items'])) {
            return $this->redirectToRoute('shop_home', ['_locale' => $_locale]);
        }

        $accountEmail = $this->getAccountEmail();
        $savedContact = $this->cartStorage->getContactData();
        if (\is_array($savedContact) && $accountEmail !== null) {
            $savedContact['customerEmail'] = $accountEmail;
        }
        $savedDelivery = $this->cartStorage->getDeliveryData();
        $editContact = $request->query->getBoolean('edit_contact');
        $editDelivery = $request->query->getBoolean('edit_delivery');

        $contactComplete = \is_array($savedContact)
            && ($savedContact['customerName'] ?? '') !== ''
            && (($savedContact['customerEmail'] ?? '') !== '' || $accountEmail !== null);

        $showContactForm = !$contactComplete || $editContact;
        $showDeliveryForm = $contactComplete && (!\is_array($savedDelivery) || $editDelivery);
        $showPayment = $contactComplete
            && \is_array($savedDelivery)
            && !$editContact
            && !$editDelivery;

        $siteEnabledPaymentMethods = $this->resolveEnabledPaymentMethods($request);
        $enabledDeliveryMethods = $this->resolveEnabledDeliveryMethods($request);

        $contactData = $this->hydrateContactData($savedContact, $accountEmail);
        $deliveryData = $this->hydrateDeliveryData($savedDelivery, $request);
        $deliveryData->deliveryMethod = $this->normalizeDeliveryMethod(
            (string) ($deliveryData->deliveryMethod ?? ShopDeliveryMethod::NovaPoshtaBranch),
            $enabledDeliveryMethods,
            $this->isCourierAllowed($request, $deliveryData->npCityName),
            !\is_array($savedDelivery),
        );
        $courierDeliveryAvailable = $this->isCourierAllowed($request, $deliveryData->npCityName)
            && in_array(ShopDeliveryMethod::Courier, $enabledDeliveryMethods, true);
        $deliveryMethodForPayment = $this->resolveDeliveryMethodForPayment(
            $savedDelivery,
            $deliveryData,
            $showPayment,
        );
        $availablePaymentMethods = $this->paymentMethodResolver->filterByDeliveryMethod(
            $siteEnabledPaymentMethods,
            $deliveryMethodForPayment,
        );
        $paymentData = $this->hydratePaymentData($availablePaymentMethods, $savedContact);

        $contactForm = $this->createForm(CheckoutContactFormType::class, $contactData, [
            'show_email_field' => $accountEmail === null,
        ]);
        $deliveryForm = $this->createForm(CheckoutDeliveryFormType::class, $deliveryData, [
            'enabled_delivery_methods' => $enabledDeliveryMethods,
        ]);
        $paymentForm = $this->createForm(CheckoutPaymentFormType::class, $paymentData, [
            'enabled_payment_methods' => $siteEnabledPaymentMethods,
            'allowed_payment_methods' => $availablePaymentMethods,
        ]);

        if ($request->isMethod('POST') && $request->request->has('checkout_contact_form')) {
            $contactForm->handleRequest($request);
        } elseif ($request->isMethod('GET')) {
            $contactForm->handleRequest($request);
        }

        if ($request->isMethod('POST') && $request->request->has('checkout_delivery_form')) {
            $deliveryForm->handleRequest($request);
        } elseif ($request->isMethod('GET')) {
            $deliveryForm->handleRequest($request);
        }

        if ($request->isMethod('POST') && $request->request->has('checkout_payment_form')) {
            $paymentForm->handleRequest($request);
        } elseif ($request->isMethod('GET')) {
            $paymentForm->handleRequest($request);
        }

        if ($contactForm->isSubmitted() && $contactForm->isValid()) {
            $this->cartStorage->setContactData($this->contactToArray($contactData, $accountEmail, $savedContact));
            $this->addFlash('success', 'shop.checkout.contact_saved');

            return $this->redirectToRoute('shop_checkout', [
                '_locale' => $_locale,
                '_fragment' => 'checkout-delivery-step',
            ]);
        }

        $deliverySaveBlocked = false;

        if ($deliveryForm->isSubmitted() && $deliveryForm->isValid()) {
            if (!\is_array($this->cartStorage->getContactData())) {
                $this->addFlash('error', 'shop.checkout.delivery_contact_required');

                return $this->redirectToRoute('shop_checkout', ['_locale' => $_locale]);
            }

            if (($deliveryData->deliveryMethod ?? '') === 'courier' && !$this->isCourierAllowed($request, $deliveryData->npCityName)) {
                $deliveryForm->get('deliveryMethod')->addError(new FormError('shop.checkout.courier_not_available'));
                $deliverySaveBlocked = true;
            } else {
                $this->applyDeliveryCost($deliveryData, $cart, $request);
                $this->cartStorage->setDeliveryData($this->deliveryToArray($deliveryData));
                $this->addFlash('success', 'shop.checkout.delivery_saved');

                return $this->redirectToRoute('shop_checkout', [
                    '_locale' => $_locale,
                    '_fragment' => 'checkout-payment-step',
                ]);
            }
        }

        if ($contactForm->isSubmitted() && !$contactForm->isValid()) {
            $showContactForm = true;
            $showDeliveryForm = false;
            $showPayment = false;
        }

        if (($deliveryForm->isSubmitted() && !$deliveryForm->isValid()) || $deliverySaveBlocked) {
            $showDeliveryForm = true;
            $showPayment = false;
        }

        if ($paymentForm->isSubmitted()) {
            $checkoutData = $this->resolveCheckoutData($accountEmail);
            if (!\is_array($checkoutData)) {
                $this->addFlash('error', 'shop.checkout.payment_delivery_required');

                return $this->redirectToRoute('shop_checkout', ['_locale' => $_locale]);
            }

            if ($this->shipmentPlanner->findBelowMinimum($this->shipmentPlanner->plan($cart)) !== []) {
                $this->addFlash('error', 'shop.checkout.min_order_blocked');

                return $this->redirectToRoute('shop_checkout', ['_locale' => $_locale]);
            }

            if ($paymentForm->isValid()) {
                $deliveryMethod = (string) ($checkoutData['deliveryMethod'] ?? '');
                $availablePaymentMethods = $this->paymentMethodResolver->filterByDeliveryMethod(
                    $siteEnabledPaymentMethods,
                    $deliveryMethod,
                );

                if (!in_array((string) $paymentData->paymentMethod, $availablePaymentMethods, true)) {
                    $paymentForm->get('paymentMethod')->addError(new FormError('shop.checkout.payment_method_unavailable'));
                } else {
                    $this->persistDoNotCallPreference($paymentData, $accountEmail);

                    try {
                        $result = $this->paymentCheckoutService->createPayment(
                            (string) $paymentData->paymentMethod,
                            $cart,
                            $checkoutData,
                            $_locale,
                            $this->getUser() instanceof User ? $this->getUser() : null,
                            (string) $request->cookies->get(VisitorIdResolver::COOKIE_NAME, ''),
                            $request->getHost(),
                        );

                        if ($result->getType() === 'success') {
                            return $this->redirectToRoute('shop_checkout_success', ['_locale' => $_locale]);
                        }

                        if ($result->getType() === 'redirect' && $result->getUrl()) {
                            return $this->redirect($result->getUrl());
                        }

                        if ($result->getType() === 'liqpay_form') {
                            return $this->render('shop/checkout/payment_redirect.html.twig', [
                                'action' => $result->getAction(),
                                'data' => $result->getData(),
                                'signature' => $result->getSignature(),
                            ]);
                        }
                    } catch (\Throwable $exception) {
                        $this->addFlash('error', 'shop.checkout.payment_failed');
                        $this->addFlash('info', $exception->getMessage());
                    }

                    return $this->redirectToRoute('shop_checkout', ['_locale' => $_locale]);
                }
            }
        }

        $shipmentParcels = $this->shipmentPlanner->plan($cart);
        $parcelCount = max(1, \count($shipmentParcels));
        $checkoutTotals = $this->resolveCheckoutTotals($cart, \is_array($savedDelivery) ? $savedDelivery : null, $request, $parcelCount);
        $savedDeliveryParcels = $parcelCount > 1 && \is_array($savedDelivery)
            ? $this->quoteParcels(
                (string) ($savedDelivery['deliveryMethod'] ?? ''),
                (string) ($savedDelivery['npCityRef'] ?? ''),
                $shipmentParcels,
                $request,
            )['parcels']
            : [];
        $courierParcelCost = $this->resolveCourierDeliveryCost($request);
        $preferredCity = $this->userCityService->resolveData($request);
        $defaultCity = $this->novaPoshtaClient->isConfigured()
            ? ($preferredCity['ref'] !== '' ? $preferredCity : $this->novaPoshtaClient->findCityByName(self::DEFAULT_CITY_NAME))
            : null;

        return $this->render('shop/checkout/index.html.twig', [
            'cart' => $cart,
            'subtotal' => $checkoutTotals['subtotal'],
            'deliveryCost' => $checkoutTotals['deliveryCost'],
            'total' => $checkoutTotals['total'],
            'contactForm' => $contactForm,
            'deliveryForm' => $deliveryForm,
            'paymentForm' => $paymentForm,
            'savedContact' => $savedContact,
            'savedDelivery' => $savedDelivery,
            'showContactForm' => $showContactForm,
            'showDeliveryForm' => $showDeliveryForm,
            'showPayment' => $showPayment,
            'contactComplete' => $contactComplete,
            'showContactEmailField' => $accountEmail === null,
            'paymentEnabled' => $siteEnabledPaymentMethods !== [],
            'siteEnabledPaymentMethods' => $siteEnabledPaymentMethods,
            'enabledPaymentMethods' => $availablePaymentMethods,
            'enabledDeliveryMethods' => $enabledDeliveryMethods,
            'deliveryMethodForPayment' => $deliveryMethodForPayment,
            'onDeliveryConfigured' => in_array(ShopPaymentMethod::OnDelivery, $siteEnabledPaymentMethods, true),
            'onDeliveryEnabled' => in_array(ShopPaymentMethod::OnDelivery, $availablePaymentMethods, true),
            'privatbankEnabled' => in_array(ShopPaymentMethod::Privatbank, $siteEnabledPaymentMethods, true),
            'monobankEnabled' => in_array(ShopPaymentMethod::Monobank, $siteEnabledPaymentMethods, true),
            'onDeliveryDeliveryMethods' => $this->paymentMethodResolver->deliveryMethodsAllowingOnDelivery(),
            'novaPoshtaEnabled' => $this->novaPoshtaClient->isConfigured(),
            'defaultCity' => $defaultCity,
            'preferredCity' => $preferredCity,
            'courierDeliveryAvailable' => $courierDeliveryAvailable,
            'headerCourierAvailable' => $this->userCityService->isCourierDeliveryAvailable($request),
            'courierCityName' => $this->userCityService->getDefaultCity(),
            'npCitiesUrl' => $this->generateUrl('shop_checkout_np_cities', ['_locale' => $_locale]),
            'npWarehousesUrl' => $this->generateUrl('shop_checkout_np_warehouses', ['_locale' => $_locale]),
            'npDeliveryPriceUrl' => $this->generateUrl('shop_checkout_np_delivery_price', ['_locale' => $_locale]),
            'deliveryQuoteEnabled' => $this->deliveryQuoteService->isAvailable(),
            'courierDeliveryCost' => round($courierParcelCost * $parcelCount, 2),
            'courierParcelCost' => $courierParcelCost,
            'shipmentParcels' => $shipmentParcels,
            'parcelCount' => $parcelCount,
            'savedDeliveryParcels' => $savedDeliveryParcels,
            'belowMinimumParcels' => $this->shipmentPlanner->findBelowMinimum($shipmentParcels),
            'codCommissionSettings' => $this->siteSettingsProvider->getCodCommissionSettings(
                $request->getHost(),
                $_locale,
            ),
        ]);
    }

    #[Route(
        '/{_locale}/checkout/success',
        name: 'shop_checkout_success',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET', 'POST'],
    )]
    public function success(string $_locale, Request $request): Response
    {
        $this->applyLiqPayBrowserReturn($request);

        $orderEntity = $this->orderCheckoutService->resolveOrderFromCartSession();
        if ($orderEntity !== null) {
            $this->syncPendingGatewayPaymentOnReturn($orderEntity);
        }

        $orderEntity = $this->orderCheckoutService->completeOrderFromCartSession();

        if ($orderEntity === null) {
            $visitorId = (string) $request->cookies->get(VisitorIdResolver::COOKIE_NAME, '');
            $orderEntity = $this->orderCheckoutService->resolveRecentPaidOrder(
                $this->getUser() instanceof User ? $this->getUser() : null,
                $visitorId,
            );
            if ($orderEntity !== null) {
                $this->cartStorage->deactivateActiveCart();
            }
        }
        $order = null;

        if ($orderEntity !== null) {
            $order = $this->buildOrderSuccessViewData($orderEntity);
        } else {
            $sessionOrder = $this->cartStorage->getOrderData();
            if (\is_array($sessionOrder)) {
                $order = $this->buildSessionOrderSuccessViewData($sessionOrder);
            }
        }

        $variant = \is_array($order) ? (string) ($order['success_variant'] ?? 'paid') : 'paid';
        if (in_array($variant, ['payment_pending', 'payment_failed'], true)) {
            $orderId = \is_array($order) ? (string) ($order['id'] ?? '') : '';
            $params = ['%order_id%' => $orderId !== '' ? $orderId : '—'];
            if ($variant === 'payment_failed') {
                $this->addFlash('error', $this->translator->trans('shop.checkout.success_failed_main', $params));
                $this->addFlash('error', $this->translator->trans('shop.checkout.success_failed_hint'));
            } else {
                $this->addFlash('warning', $this->translator->trans('shop.checkout.success_pending_main', $params));
                $this->addFlash('warning', $this->translator->trans('shop.checkout.success_pending_hint'));
            }

            return $this->redirectToRoute('shop_checkout', ['_locale' => $_locale]);
        }

        return $this->render('shop/checkout/success.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route(
        '/{_locale}/checkout/callback/liqpay',
        name: 'shop_checkout_liqpay_callback',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['POST'],
    )]
    public function liqpayCallback(Request $request): Response
    {
        $result = $this->liqPayCallbackHandler->handleEncodedCallback(
            (string) $request->request->get('data', ''),
            (string) $request->request->get('signature', ''),
        );

        return match ($result) {
            'invalid_signature' => new Response('Invalid signature', Response::HTTP_FORBIDDEN),
            'invalid_payload' => new Response('Invalid payload', Response::HTTP_BAD_REQUEST),
            default => new Response('OK', Response::HTTP_OK),
        };
    }

    #[Route(
        '/{_locale}/checkout/callback/monobank',
        name: 'shop_checkout_monobank_callback',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['POST'],
    )]
    public function monobankCallback(Request $request, MonobankWebhookHandler $webhookHandler): Response
    {
        $rawBody = $request->getContent();
        $result = $webhookHandler->handle($rawBody, $request->headers->get('X-Sign'));

        return match ($result) {
            'invalid_signature' => new Response('Invalid signature', Response::HTTP_FORBIDDEN),
            'invalid_payload' => new Response('Invalid payload', Response::HTTP_BAD_REQUEST),
            default => new Response('OK', Response::HTTP_OK),
        };
    }

    #[Route(
        '/{_locale}/checkout/np/cities',
        name: 'shop_checkout_np_cities',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET'],
    )]
    public function novaPoshtaCities(Request $request): JsonResponse
    {
        if ($blockedResponse = $this->createOutsideUkraineCheckoutBlockedJson()) {
            return $blockedResponse;
        }

        if (!$this->cartStorage->getCartData()) {
            return new JsonResponse(['error' => 'Cart not found'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->novaPoshtaClient->isConfigured()) {
            return new JsonResponse(['items' => [], 'error' => 'Nova Poshta API key is not configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $items = $this->novaPoshtaClient->searchCities((string) $request->query->get('q', ''));
        } catch (\Throwable $exception) {
            return new JsonResponse(['items' => [], 'error' => $exception->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['items' => $items]);
    }

    #[Route(
        '/{_locale}/checkout/np/warehouses',
        name: 'shop_checkout_np_warehouses',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET'],
    )]
    public function novaPoshtaWarehouses(Request $request): JsonResponse
    {
        if ($blockedResponse = $this->createOutsideUkraineCheckoutBlockedJson()) {
            return $blockedResponse;
        }

        if (!$this->cartStorage->getCartData()) {
            return new JsonResponse(['error' => 'Cart not found'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->novaPoshtaClient->isConfigured()) {
            return new JsonResponse(['items' => [], 'error' => 'Nova Poshta API key is not configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $cityRef = (string) $request->query->get('cityRef', '');
        $type = (string) $request->query->get('type', 'branch');
        $category = $type === 'postomat' ? 'postomat' : 'branch';

        try {
            $items = $this->novaPoshtaClient->getWarehouses($cityRef, $category);
            $warehouseCity = $this->novaPoshtaClient->findCityByRef($cityRef);
        } catch (\Throwable $exception) {
            return new JsonResponse(['items' => [], 'error' => $exception->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'items' => $items,
            'warehouseCityName' => $warehouseCity['name'] ?? '',
        ]);
    }

    #[Route(
        '/{_locale}/checkout/np/delivery-price',
        name: 'shop_checkout_np_delivery_price',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET'],
    )]
    public function novaPoshtaDeliveryPrice(Request $request): JsonResponse
    {
        if ($blockedResponse = $this->createOutsideUkraineCheckoutBlockedJson()) {
            return $blockedResponse;
        }

        $cart = $this->cartStorage->getCartData();
        if (!\is_array($cart)) {
            return new JsonResponse(['error' => 'Cart not found'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->deliveryQuoteService->isAvailable()) {
            return new JsonResponse(['error' => 'Delivery quote unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $cityRef = trim((string) $request->query->get('cityRef', ''));
        $deliveryMethod = trim((string) $request->query->get('method', 'np_branch'));
        if ($cityRef === '' || !in_array($deliveryMethod, ['np_branch', 'np_postomat'], true)) {
            return new JsonResponse(['error' => 'Invalid delivery quote request'], Response::HTTP_BAD_REQUEST);
        }

        $quote = $this->quoteParcels($deliveryMethod, $cityRef, $this->shipmentPlanner->plan($cart), $request);
        $cost = $quote['total'];

        if ($cost === null) {
            return new JsonResponse(['error' => 'Unable to calculate delivery price'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'cost' => $cost,
            'subtotal' => $this->getCartSubtotal($cart),
            'total' => round($this->getCartSubtotal($cart) + $cost, 2),
            'parcelCount' => max(1, \count($quote['parcels'])),
            'parcels' => array_map(static fn (array $parcel): array => [
                'key' => $parcel['key'],
                'cost' => $parcel['cost'],
            ], $quote['parcels']),
        ]);
    }

    #[Route(
        '/{_locale}/checkout/cart/update',
        name: 'shop_checkout_cart_update',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['POST'],
    )]
    public function updateCartItem(string $_locale, Request $request): JsonResponse
    {
        if ($errorResponse = $this->validateCartRequest($request)) {
            return $errorResponse;
        }

        $cartItemId = (int) $request->request->get('cart_item_id');
        $quantity = (int) $request->request->get('quantity', 1);
        if ($cartItemId <= 0 || $this->cartStorage->updateItemQuantity($cartItemId, $quantity) === null) {
            return new JsonResponse(['success' => false, 'error' => 'Item not found in cart.'], Response::HTTP_NOT_FOUND);
        }

        return $this->buildCartRefreshResponse($_locale, $request);
    }

    #[Route(
        '/{_locale}/checkout/cart/remove',
        name: 'shop_checkout_cart_remove',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['POST'],
    )]
    public function removeCartItems(string $_locale, Request $request): JsonResponse
    {
        if ($errorResponse = $this->validateCartRequest($request)) {
            return $errorResponse;
        }

        $ids = array_values(array_filter(
            array_map('intval', (array) $request->request->all('cart_item_ids')),
            static fn (int $id): bool => $id > 0,
        ));
        if ($ids === [] || $this->cartStorage->removeItemsKeepingCheckout($ids) === 0) {
            return new JsonResponse(['success' => false, 'error' => 'Item not found in cart.'], Response::HTTP_NOT_FOUND);
        }

        return $this->buildCartRefreshResponse($_locale, $request);
    }

    private function validateCartRequest(Request $request): ?JsonResponse
    {
        if ($blockedResponse = $this->createOutsideUkraineCheckoutBlockedJson()) {
            return $blockedResponse;
        }

        if (!$this->isCsrfTokenValid('checkout_cart', (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * Re-renders the order summary and delivery parcels after the cart changed on the checkout page.
     */
    private function buildCartRefreshResponse(string $_locale, Request $request): JsonResponse
    {
        $cart = $this->cartStorage->getCartData();
        $cartSummary = $this->cartSummaryFormatter->format($this->cartStorage->getSummary());

        if (!\is_array($cart) || empty($cart['items'])) {
            return new JsonResponse([
                'success' => true,
                'redirect' => $this->generateUrl('shop_home', ['_locale' => $_locale]),
                'cart' => $cartSummary,
            ]);
        }

        $savedDelivery = $this->cartStorage->getDeliveryData();
        if (\is_array($savedDelivery)) {
            $deliveryData = $this->hydrateDeliveryData($savedDelivery, $request);
            $this->applyDeliveryCost($deliveryData, $cart, $request);
            $this->cartStorage->setDeliveryData($this->deliveryToArray($deliveryData));
            $savedDelivery = $this->cartStorage->getDeliveryData();
        }

        $shipmentParcels = $this->shipmentPlanner->plan($cart);
        $parcelCount = max(1, \count($shipmentParcels));
        $subtotal = $this->getCartSubtotal($cart);
        $belowMinimum = $this->shipmentPlanner->findBelowMinimum($shipmentParcels);
        $courierParcelCost = $this->resolveCourierDeliveryCost($request);

        $savedDeliveryHtml = null;
        if (\is_array($savedDelivery)) {
            $savedDeliveryParcels = $parcelCount > 1
                ? $this->quoteParcels(
                    (string) ($savedDelivery['deliveryMethod'] ?? ''),
                    (string) ($savedDelivery['npCityRef'] ?? ''),
                    $shipmentParcels,
                    $request,
                )['parcels']
                : [];
            $savedDeliveryHtml = $this->renderView('shop/checkout/_saved_delivery.html.twig', [
                'savedDelivery' => $savedDelivery,
                'parcelCount' => $parcelCount,
                'savedDeliveryParcels' => $savedDeliveryParcels,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'summaryHtml' => $this->renderView('shop/checkout/_summary.html.twig', [
                'shipmentParcels' => $shipmentParcels,
                'parcelCount' => $parcelCount,
                'subtotal' => $subtotal,
                'belowMinimumParcels' => $belowMinimum,
            ]),
            'savedDeliveryHtml' => $savedDeliveryHtml,
            'deliveryParcelsHtml' => $parcelCount > 1
                ? $this->renderView('shop/checkout/_parcels.html.twig', [
                    'id' => 'checkout-delivery-parcels',
                    'parcels' => $shipmentParcels,
                    'parcelCount' => $parcelCount,
                ])
                : '',
            'subtotal' => $subtotal,
            'parcelCount' => $parcelCount,
            'courierDeliveryCost' => round($courierParcelCost * $parcelCount, 2),
            'minOrderBlocked' => $belowMinimum !== [],
            'cart' => $cartSummary,
        ]);
    }

    /** @param array<string, mixed>|null $saved */
    private function hydrateContactData(?array $saved, ?string $accountEmail = null): CheckoutContactData
    {
        $data = new CheckoutContactData();

        if (\is_array($saved)) {
            $data->customerName = (string) ($saved['customerName'] ?? '');
            $data->customerPhone = (string) ($saved['customerPhone'] ?? '');
            $data->customerEmail = (string) ($saved['customerEmail'] ?? '');
        }

        if ($accountEmail !== null) {
            $data->customerEmail = $accountEmail;
        }

        return $data;
    }

    /** @param array<string, mixed>|null $saved */
    private function hydrateDeliveryData(?array $saved, Request $request): CheckoutDeliveryData
    {
        $data = new CheckoutDeliveryData();

        if (\is_array($saved)) {
            $data->deliveryMethod = (string) ($saved['deliveryMethod'] ?? 'np_branch');
            $isNovaPoshta = in_array($data->deliveryMethod, ['np_branch', 'np_postomat'], true);
            $data->courierAddress = $isNovaPoshta ? null : ($saved['courierAddress'] ?? null);
            $data->npCityRef = $isNovaPoshta ? ($saved['npCityRef'] ?? null) : null;
            $data->npCityName = $isNovaPoshta ? ($saved['npCityName'] ?? null) : null;
            $data->npWarehouseRef = $isNovaPoshta ? ($saved['npWarehouseRef'] ?? null) : null;
            $data->npWarehouseName = $isNovaPoshta ? ($saved['npWarehouseName'] ?? null) : null;
            $data->deliveryCost = isset($saved['deliveryCost']) && is_numeric($saved['deliveryCost'])
                ? (float) $saved['deliveryCost']
                : null;

            return $data;
        }

        $userCity = $this->userCityService->resolveData($request);
        if ($userCity['ref'] !== '' && ($userCity['hasLocalWarehouses'] ?? false)) {
            $data->npCityRef = $userCity['ref'];
            $data->npCityName = $userCity['name'];
        }

        return $data;
    }

    private function isCourierAllowed(Request $request, ?string $npCityName): bool
    {
        if ($this->userCityService->isCourierDeliveryAvailable($request)) {
            return true;
        }

        if ($npCityName !== null && $npCityName !== '' && $this->userCityService->isCourierCityName($npCityName)) {
            return true;
        }

        return false;
    }

    /** @param list<string> $enabledPaymentMethods */
    /** @param array<string, mixed>|null $savedContact */
    private function hydratePaymentData(array $enabledPaymentMethods, ?array $savedContact): CheckoutPaymentData
    {
        $data = new CheckoutPaymentData();
        $order = $this->cartStorage->getOrderData();

        if (\is_array($order) && isset($order['payment_method'])) {
            $data->paymentMethod = (string) $order['payment_method'];
        }

        if ($enabledPaymentMethods !== [] && !in_array($data->paymentMethod, $enabledPaymentMethods, true)) {
            $data->paymentMethod = $enabledPaymentMethods[0];
        }

        if (\is_array($savedContact)) {
            $data->doNotCall = filter_var($savedContact['doNotCall'] ?? false, FILTER_VALIDATE_BOOL);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    /** @param array<string, mixed>|null $existingContact */
    private function contactToArray(CheckoutContactData $data, ?string $accountEmail = null, ?array $existingContact = null): array
    {
        return [
            'customerName' => (string) ($data->customerName ?? ''),
            'customerPhone' => (string) ($data->customerPhone ?? ''),
            'customerEmail' => $accountEmail ?? (string) ($data->customerEmail ?? ''),
            'doNotCall' => filter_var($existingContact['doNotCall'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    private function persistDoNotCallPreference(CheckoutPaymentData $paymentData, ?string $accountEmail): void
    {
        $contact = $this->cartStorage->getContactData();
        if (!\is_array($contact)) {
            return;
        }

        $contact['doNotCall'] = (bool) $paymentData->doNotCall;
        if ($accountEmail !== null) {
            $contact['customerEmail'] = $accountEmail;
        }

        $this->cartStorage->setContactData($contact);
    }

    /** @return array<string, mixed> */
    private function buildOrderSuccessViewData(Order $orderEntity): array
    {
        $payment = $this->paymentRepository->findLatestByOrder($orderEntity);
        $sessionOrder = $this->cartStorage->getOrderData();
        $sessionPaymentMethod = \is_array($sessionOrder)
            ? (string) ($sessionOrder['payment_method'] ?? '')
            : '';
        $paymentMethod = (string) ($payment?->getMethod() ?? $sessionPaymentMethod);
        if ($paymentMethod === '' && in_array($orderEntity->getStatus(), [OrderStatus::AwaitingDepositForShipment, OrderStatus::DepositPaid, OrderStatus::InProcess], true)) {
            $paymentMethod = ShopPaymentMethod::OnDelivery;
        }

        return [
            'id' => $orderEntity->getOrderNumber(),
            'amount' => $orderEntity->getAmount(),
            'status' => $orderEntity->getStatus()->value,
            'success_variant' => $this->resolveSuccessVariant($orderEntity->getStatus(), $payment),
            'do_not_call' => $orderEntity->isDoNotCall(),
            'payment_method' => $paymentMethod,
        ];
    }

    /** @param array<string, mixed> $sessionOrder */
    /** @return array<string, mixed> */
    private function buildSessionOrderSuccessViewData(array $sessionOrder): array
    {
        $paymentMethod = (string) ($sessionOrder['payment_method'] ?? '');
        $status = (string) ($sessionOrder['status'] ?? '');
        $contact = $this->cartStorage->getContactData();
        $orderStatus = OrderStatus::tryFrom($status);
        $isAwaitingDeposit = $orderStatus === OrderStatus::AwaitingDepositForShipment;
        $isPaid = in_array($orderStatus, [OrderStatus::Paid, OrderStatus::DepositPaid], true);
        $isGateway = in_array($paymentMethod, [ShopPaymentMethod::Privatbank, ShopPaymentMethod::Monobank], true);

        return [
            'id' => (string) ($sessionOrder['order_number'] ?? $sessionOrder['id'] ?? ''),
            'amount' => (float) ($sessionOrder['amount'] ?? 0),
            'status' => $status,
            'success_variant' => $isAwaitingDeposit
                ? 'awaiting_deposit'
                : ($isPaid ? 'paid' : ($isGateway ? 'payment_pending' : 'paid')),
            'do_not_call' => filter_var($contact['doNotCall'] ?? false, FILTER_VALIDATE_BOOL),
            'payment_method' => $paymentMethod,
        ];
    }

    private function resolveSuccessVariant(OrderStatus $status, ?Payment $payment): string
    {
        if ($status === OrderStatus::AwaitingDepositForShipment) {
            return 'awaiting_deposit';
        }

        if (in_array($status, [OrderStatus::Paid, OrderStatus::DepositPaid], true)) {
            return 'paid';
        }

        if (
            $payment !== null
            && in_array($payment->getMethod(), [ShopPaymentMethod::Privatbank, ShopPaymentMethod::Monobank], true)
            && $payment->getStatus() !== PaymentStatus::Success
        ) {
            return $payment->getStatus() === PaymentStatus::Failed ? 'payment_failed' : 'payment_pending';
        }

        if ($status === OrderStatus::InProcess) {
            return 'payment_pending';
        }

        return 'paid';
    }

    private function applyLiqPayBrowserReturn(Request $request): void
    {
        $encodedData = (string) ($request->request->get('data') ?? $request->query->get('data') ?? '');
        $signature = (string) ($request->request->get('signature') ?? $request->query->get('signature') ?? '');
        if ($encodedData === '' || $signature === '') {
            return;
        }

        $this->liqPayCallbackHandler->handleEncodedCallback($encodedData, $signature);
    }

    private function syncPendingGatewayPaymentOnReturn(Order $order): void
    {
        $payment = $this->paymentRepository->findLatestPendingGatewayByOrder($order);
        if ($payment === null) {
            return;
        }

        if ($payment->getMethod() === ShopPaymentMethod::Privatbank) {
            $this->liqPayCallbackHandler->syncPendingPayment($payment);

            return;
        }

        if ($payment->getMethod() !== ShopPaymentMethod::Monobank || !$this->monobankInvoiceStatusClient->isConfigured()) {
            return;
        }

        $invoiceId = $payment->getMonobankInvoiceId();
        if ($invoiceId === null || $invoiceId === '') {
            $gatewayResponse = $payment->getGatewayResponse();
            $invoiceId = \is_array($gatewayResponse) ? (string) ($gatewayResponse['invoiceId'] ?? '') : '';
        }
        if ($invoiceId === '') {
            return;
        }

        $payload = $this->monobankInvoiceStatusClient->fetchStatus($invoiceId);
        if ($payload === null) {
            return;
        }

        $status = (string) ($payload['status'] ?? '');
        if ($status === 'success') {
            if ($payment->getMonobankInvoiceId() === null) {
                $payment->setMonobankInvoiceId($invoiceId);
            }
            $this->orderCheckoutService->markPaymentSuccessful($payment, $payload);
        } elseif (in_array($status, ['failure', 'expired', 'reversed'], true)) {
            $this->orderCheckoutService->markPaymentFailed($payment, $payload);
        }
    }

    private function getAccountEmail(): ?string
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getEmail() : null;
    }

    /** @return array<string, mixed>|null */
    private function resolveCheckoutData(?string $accountEmail = null): ?array
    {
        $checkoutData = $this->cartStorage->getCheckoutData();
        if (!\is_array($checkoutData)) {
            return null;
        }

        if ($accountEmail !== null) {
            $checkoutData['customerEmail'] = $accountEmail;
        }

        if (($checkoutData['customerEmail'] ?? '') === '') {
            return null;
        }

        return $checkoutData;
    }

    /** @return array<string, mixed> */
    private function deliveryToArray(CheckoutDeliveryData $data): array
    {
        $method = (string) ($data->deliveryMethod ?? 'courier');
        $isNovaPoshta = in_array($method, ['np_branch', 'np_postomat'], true);

        return [
            'deliveryMethod' => $method,
            'courierAddress' => $isNovaPoshta ? null : $data->courierAddress,
            'npCityRef' => $isNovaPoshta ? $data->npCityRef : null,
            'npCityName' => $isNovaPoshta ? $data->npCityName : null,
            'npWarehouseRef' => $isNovaPoshta ? $data->npWarehouseRef : null,
            'npWarehouseName' => $isNovaPoshta ? $data->npWarehouseName : null,
            'deliveryCost' => is_numeric($data->deliveryCost ?? null) ? round((float) $data->deliveryCost, 2) : null,
        ];
    }

    /** @param array<string, mixed> $cart */
    private function applyDeliveryCost(CheckoutDeliveryData $data, array $cart, Request $request): void
    {
        $method = (string) ($data->deliveryMethod ?? '');
        $parcels = $this->shipmentPlanner->plan($cart);

        if ($method === 'courier') {
            $data->deliveryCost = round($this->resolveCourierDeliveryCost($request) * max(1, \count($parcels)), 2);

            return;
        }

        if (!in_array($method, ['np_branch', 'np_postomat'], true) || ($data->npCityRef ?? '') === '') {
            $data->deliveryCost = 0.0;

            return;
        }

        $quoted = $this->quoteParcels($method, (string) $data->npCityRef, $parcels, $request)['total'];
        $data->deliveryCost = $quoted ?? (is_numeric($data->deliveryCost ?? null) ? (float) $data->deliveryCost : 0.0);
    }

    /**
     * Prices every parcel separately; the total is null when any parcel could not be quoted.
     *
     * @param list<array<string, mixed>> $parcels
     *
     * @return array{total: float|null, parcels: list<array{key: string, supplierName: string, senderCityName: string|null, itemCount: int, weight: float, subtotal: float, cost: float|null}>}
     */
    private function quoteParcels(string $method, string $cityRef, array $parcels, Request $request): array
    {
        $isNovaPoshta = in_array($method, ['np_branch', 'np_postomat'], true);
        $courierCost = $method === 'courier' ? $this->resolveCourierDeliveryCost($request) : 0.0;
        $quoted = [];
        $total = 0.0;
        $complete = true;

        foreach ($parcels as $parcel) {
            $cost = 0.0;
            if ($method === 'courier') {
                $cost = $courierCost;
            } elseif ($isNovaPoshta) {
                try {
                    $cost = $cityRef !== ''
                        ? $this->deliveryQuoteService->quoteForDeliveryMethod(
                            $method,
                            $cityRef,
                            (float) $parcel['subtotal'],
                            (float) $parcel['weight'],
                            $parcel['senderCityRef'] ?? null,
                        )
                        : null;
                } catch (\Throwable) {
                    $cost = null;
                }
            }

            if ($cost === null) {
                $complete = false;
            } else {
                $total += $cost;
            }

            $quoted[] = [
                'key' => (string) $parcel['key'],
                'supplierName' => (string) $parcel['supplierName'],
                'senderCityName' => $parcel['senderCityName'] ?? null,
                'itemCount' => (int) $parcel['itemCount'],
                'weight' => (float) $parcel['weight'],
                'subtotal' => (float) $parcel['subtotal'],
                'cost' => $cost === null ? null : round($cost, 2),
            ];
        }

        return [
            'total' => $complete && $quoted !== [] ? round($total, 2) : null,
            'parcels' => $quoted,
        ];
    }

    /** @param array<string, mixed> $cart */
    /** @param array<string, mixed>|null $savedDelivery */
    /** @return array{subtotal: float, deliveryCost: float, total: float} */
    private function resolveCheckoutTotals(array $cart, ?array $savedDelivery, Request $request, int $parcelCount = 1): array
    {
        $subtotal = $this->getCartSubtotal($cart);
        $deliveryCost = 0.0;

        if (\is_array($savedDelivery)) {
            if (($savedDelivery['deliveryMethod'] ?? '') === 'courier') {
                $deliveryCost = round($this->resolveCourierDeliveryCost($request) * max(1, $parcelCount), 2);
            } elseif (in_array($savedDelivery['deliveryMethod'] ?? '', ['np_branch', 'np_postomat'], true)) {
                $deliveryCost = max(0.0, (float) ($savedDelivery['deliveryCost'] ?? 0));
            }
        }

        return [
            'subtotal' => $subtotal,
            'deliveryCost' => $deliveryCost,
            'total' => $subtotal,
        ];
    }

    private function resolveCourierDeliveryCost(Request $request): float
    {
        return $this->siteSettingsProvider->getCourierDeliveryCost(
            $request->getHost(),
            $request->getLocale(),
        );
    }

    /** @param array<string, mixed> $cart */
    private function getCartSubtotal(array $cart): float
    {
        if (isset($cart['subtotal']) && is_numeric($cart['subtotal'])) {
            return round((float) $cart['subtotal'], 2);
        }

        $items = $cart['items'] ?? [];
        if (\is_array($items) && $items !== []) {
            $total = 0.0;
            foreach ($items as $cartItem) {
                if (!\is_array($cartItem)) {
                    continue;
                }

                $product = $cartItem['product'] ?? null;
                if (!\is_array($product)) {
                    continue;
                }

                $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
                $total += (float) ($product['price'] ?? 0) * $quantity;
            }

            return round($total, 2);
        }

        return round((float) ($cart['product']['price'] ?? 0) * (int) ($cart['quantity'] ?? 1), 2);
    }

    /** @param array<string, mixed>|null $savedDelivery */
    private function resolveDeliveryMethodForPayment(
        ?array $savedDelivery,
        CheckoutDeliveryData $deliveryData,
        bool $showPayment,
    ): string {
        if ($showPayment && \is_array($savedDelivery)) {
            return (string) ($savedDelivery['deliveryMethod'] ?? ShopDeliveryMethod::NovaPoshtaBranch);
        }

        return (string) ($deliveryData->deliveryMethod ?? ShopDeliveryMethod::NovaPoshtaBranch);
    }

    /** @return list<string> */
    private function resolveEnabledPaymentMethods(Request $request): array
    {
        $active = $this->siteSettingsProvider->getActivePaymentMethods($request->getHost());
        $enabled = [];

        if (in_array(ShopPaymentMethod::OnDelivery, $active, true)) {
            $enabled[] = ShopPaymentMethod::OnDelivery;
        }
        if (in_array(ShopPaymentMethod::Privatbank, $active, true) && $this->paymentCheckoutService->isPrivatBankConfigured()) {
            $enabled[] = ShopPaymentMethod::Privatbank;
        }
        if (in_array(ShopPaymentMethod::Monobank, $active, true) && $this->paymentCheckoutService->isMonobankConfigured()) {
            $enabled[] = ShopPaymentMethod::Monobank;
        }

        return $enabled;
    }

    /** @return list<string> */
    private function resolveEnabledDeliveryMethods(Request $request): array
    {
        return $this->siteSettingsProvider->getActiveDeliveryMethods($request->getHost());
    }

    /**
     * @param list<string> $enabledDeliveryMethods
     */
    private function normalizeDeliveryMethod(
        string $method,
        array $enabledDeliveryMethods,
        bool $courierAllowed,
        bool $preferCourierWhenAvailable,
    ): string {
        if ($enabledDeliveryMethods === []) {
            return ShopDeliveryMethod::NovaPoshtaBranch;
        }

        if (!in_array($method, $enabledDeliveryMethods, true)) {
            $method = $enabledDeliveryMethods[0];
        }

        if ($method === ShopDeliveryMethod::Courier && !$courierAllowed) {
            foreach ($enabledDeliveryMethods as $enabledMethod) {
                if ($enabledMethod !== ShopDeliveryMethod::Courier) {
                    return $enabledMethod;
                }
            }
        }

        if (
            $preferCourierWhenAvailable
            && $courierAllowed
            && in_array(ShopDeliveryMethod::Courier, $enabledDeliveryMethods, true)
            && in_array($method, [ShopDeliveryMethod::NovaPoshtaBranch, ShopDeliveryMethod::NovaPoshtaPostomat], true)
        ) {
            return ShopDeliveryMethod::Courier;
        }

        return $method;
    }

    private function renderOutsideUkraineCheckoutBlocked(string $_locale): ?Response
    {
        if ($this->userCityService->isUkraineVisitor()) {
            return null;
        }

        return $this->render('shop/checkout/blocked.html.twig', [
            '_locale' => $_locale,
        ]);
    }

    private function createOutsideUkraineCheckoutBlockedJson(): ?JsonResponse
    {
        if ($this->userCityService->isUkraineVisitor()) {
            return null;
        }

        return new JsonResponse([
            'error' => 'shop.checkout.outside_ukraine_message',
        ], Response::HTTP_FORBIDDEN);
    }
}
