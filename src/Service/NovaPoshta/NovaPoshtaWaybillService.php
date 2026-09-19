<?php

namespace App\Service\NovaPoshta;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ShopDeliveryMethod;
use App\Repository\ProductRepository;
use App\Service\NovaPoshtaClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NovaPoshtaWaybillService
{
    private const DEFAULT_DIMENSION_CM = 10.0;

    private readonly NovaPoshtaClient $waybillClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly LoggerInterface $logger,
        ?string $apiKey,
        ?string $testApiKey,
        bool $testMode,
        private readonly ?string $senderRef,
        private readonly ?string $senderContactRef,
        private readonly ?string $senderAddressRef,
        private readonly ?string $senderCityRef,
        private readonly ?string $senderPhone,
        private readonly float $defaultPackageHeight = self::DEFAULT_DIMENSION_CM,
        private readonly float $defaultPackageWidth = self::DEFAULT_DIMENSION_CM,
        private readonly float $defaultPackageLength = self::DEFAULT_DIMENSION_CM,
    ) {
        $effectiveApiKey = $this->resolveWaybillApiKey($apiKey, $testApiKey, $testMode);
        $this->waybillClient = new NovaPoshtaClient($httpClient, $effectiveApiKey);
    }

    public function isConfigured(): bool
    {
        return $this->waybillClient->isConfigured()
            && $this->senderRef !== null && $this->senderRef !== ''
            && $this->senderContactRef !== null && $this->senderContactRef !== ''
            && $this->senderAddressRef !== null && $this->senderAddressRef !== ''
            && $this->senderCityRef !== null && $this->senderCityRef !== ''
            && $this->senderPhone !== null && $this->senderPhone !== '';
    }

    public function createForPaidOrder(Order $order, string $paymentMethod): bool
    {
        if (!in_array($paymentMethod, ['monobank', 'on_delivery'], true)) {
            return false;
        }

        if ($order->hasNovaPoshtaWaybill()) {
            return true;
        }

        $deliveryMethod = (string) ($order->getDeliveryData()['deliveryMethod'] ?? '');
        if (!in_array($deliveryMethod, [ShopDeliveryMethod::NovaPoshtaBranch, ShopDeliveryMethod::NovaPoshtaPostomat], true)) {
            return false;
        }

        if (!$this->isConfigured()) {
            $this->logger->warning('Nova Poshta waybill skipped: sender configuration is incomplete.', [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
            ]);

            return false;
        }

        try {
            $result = $this->createWaybill($order, $deliveryMethod);
        } catch (\Throwable $exception) {
            $this->logger->error('Nova Poshta waybill creation failed.', [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        $order
            ->setNpWaybillRef((string) ($result['Ref'] ?? ''))
            ->setNpWaybillNumber((string) ($result['IntDocNumber'] ?? ''))
            ->setNpWaybillData($result);

        $this->entityManager->flush();

        $this->logger->info('Nova Poshta waybill created.', [
            'order_id' => $order->getId(),
            'order_number' => $order->getOrderNumber(),
            'waybill_number' => $order->getNpWaybillNumber(),
        ]);

        return true;
    }

    /** @return array<string, mixed> */
    private function createWaybill(Order $order, string $deliveryMethod): array
    {
        $deliveryData = $order->getDeliveryData();

        $cityRecipientRef = trim((string) ($deliveryData['npCityRef'] ?? ''));
        $warehouseRef = trim((string) ($deliveryData['npWarehouseRef'] ?? ''));
        if ($cityRecipientRef === '' || $warehouseRef === '') {
            throw new \InvalidArgumentException('Order delivery data is missing Nova Poshta city or warehouse reference.');
        }

        $customerName = $order->getCustomerName();
        $customerPhone = $this->normalizePhone($order->getCustomerPhone());
        if ($customerName === '' || $customerPhone === '') {
            throw new \InvalidArgumentException('Order contact data is missing customer name or phone.');
        }

        [$firstName, $lastName, $middleName] = $this->splitCustomerName($customerName);
        $recipient = $this->waybillClient->createRecipient(
            $firstName,
            $lastName,
            $middleName,
            $customerPhone,
            $order->getCustomerEmail(),
        );

        $cargo = $this->buildCargoPayload($order, $deliveryMethod);
        $properties = [
            'PayerType' => 'Sender',
            'PaymentMethod' => 'NonCash',
            'DateTime' => (new \DateTimeImmutable())->format('d.m.Y'),
            'CargoType' => 'Parcel',
            'Weight' => number_format($cargo['weight'], 2, '.', ''),
            'ServiceType' => 'WarehouseWarehouse',
            'SeatsAmount' => (string) $cargo['seatsAmount'],
            'Description' => sprintf('Замовлення %s', $order->getOrderNumber()),
            'Cost' => (string) max(300, (int) round($order->getAmount())),
            'CitySender' => $this->senderCityRef,
            'Sender' => $this->senderRef,
            'SenderAddress' => $this->senderAddressRef,
            'ContactSender' => $this->senderContactRef,
            'SendersPhone' => $this->normalizePhone($this->senderPhone ?? ''),
            'CityRecipient' => $cityRecipientRef,
            'Recipient' => $recipient['recipientRef'],
            'RecipientAddress' => $warehouseRef,
            'ContactRecipient' => $recipient['contactRef'],
            'RecipientsPhone' => $customerPhone,
            'InfoRegClientBarcodes' => $order->getOrderNumber(),
            'OptionsSeat' => $cargo['optionsSeat'],
        ];

        return $this->waybillClient->saveInternetDocument($properties);
    }

    /**
     * @return array{weight: float, seatsAmount: int, optionsSeat: list<array<string, string>>}
     */
    private function buildCargoPayload(Order $order, string $deliveryMethod): array
    {
        $seats = [];
        foreach ($order->getItems() as $item) {
            $dimensions = $this->resolveItemDimensions($item);
            for ($i = 0; $i < $item->getQuantity(); ++$i) {
                $seats[] = $this->buildSeat($dimensions, $dimensions['weight']);
            }
        }

        if ($seats === []) {
            $seats[] = $this->buildSeat([
                'height' => $this->defaultPackageHeight,
                'width' => $this->defaultPackageWidth,
                'length' => $this->defaultPackageLength,
                'weight' => 1.0,
            ], 1.0);
        }

        if ($deliveryMethod === ShopDeliveryMethod::NovaPoshtaPostomat) {
            $seats = [$this->mergeSeats($seats)];
        }

        $totalWeight = 0.0;
        foreach ($seats as $seat) {
            $totalWeight += (float) $seat['weight'];
        }

        return [
            'weight' => max(0.1, $totalWeight),
            'seatsAmount' => count($seats),
            'optionsSeat' => $seats,
        ];
    }

    /** @return array{height: float, width: float, length: float, weight: float} */
    private function resolveItemDimensions(OrderItem $item): array
    {
        $snapshot = $item->getProductSnapshot();

        $height = $this->readDimension($snapshot, 'packageHeight');
        $width = $this->readDimension($snapshot, 'packageWidth');
        $length = $this->readDimension($snapshot, 'packageLength');
        $weight = isset($snapshot['weight']) && is_numeric($snapshot['weight'])
            ? max(0.1, (float) $snapshot['weight'])
            : 1.0;

        if ($height === null || $width === null || $length === null) {
            $product = $this->productRepository->find($item->getProductId());
            if ($product !== null) {
                $height ??= $product->getPackageHeight();
                $width ??= $product->getPackageWidth();
                $length ??= $product->getPackageLength();
                $weight = max(0.1, $product->getWeight());
            }
        }

        return [
            'height' => $height ?? $this->defaultPackageHeight,
            'width' => $width ?? $this->defaultPackageWidth,
            'length' => $length ?? $this->defaultPackageLength,
            'weight' => $weight,
        ];
    }

    private function readDimension(array $snapshot, string $key): ?float
    {
        if (!isset($snapshot[$key]) || !is_numeric($snapshot[$key])) {
            return null;
        }

        return max(1.0, (float) $snapshot[$key]);
    }

    /** @param array{height: float, width: float, length: float, weight: float} $dimensions */
    /** @return array<string, string> */
    private function buildSeat(array $dimensions, float $weight): array
    {
        $height = max(1.0, $dimensions['height']);
        $width = max(1.0, $dimensions['width']);
        $length = max(1.0, $dimensions['length']);
        $volume = ($height * $width * $length) / 1_000_000;

        return [
            'volumetricVolume' => number_format(max(0.0004, $volume), 4, '.', ''),
            'volumetricWidth' => number_format($width, 0, '.', ''),
            'volumetricLength' => number_format($length, 0, '.', ''),
            'volumetricHeight' => number_format($height, 0, '.', ''),
            'weight' => number_format(max(0.1, $weight), 2, '.', ''),
        ];
    }

    /**
     * @param list<array<string, string>> $seats
     *
     * @return array<string, string>
     */
    private function mergeSeats(array $seats): array
    {
        $maxWidth = 1.0;
        $maxLength = 1.0;
        $maxHeight = 1.0;
        $totalWeight = 0.0;

        foreach ($seats as $seat) {
            $maxWidth = max($maxWidth, (float) $seat['volumetricWidth']);
            $maxLength = max($maxLength, (float) $seat['volumetricLength']);
            $maxHeight = max($maxHeight, (float) $seat['volumetricHeight']);
            $totalWeight += (float) $seat['weight'];
        }

        return $this->buildSeat([
            'height' => min(30.0, $maxHeight),
            'width' => min(40.0, $maxWidth),
            'length' => min(60.0, $maxLength),
            'weight' => min(20.0, $totalWeight),
        ], min(20.0, $totalWeight));
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function splitCustomerName(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return ['Клієнт', 'Інтернет-магазин', ''];
        }

        if (\count($parts) === 1) {
            return [$parts[0], 'Клієнт', ''];
        }

        if (\count($parts) === 2) {
            return [$parts[1], $parts[0], ''];
        }

        return [
            $parts[1],
            $parts[0],
            implode(' ', \array_slice($parts, 2)),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            throw new \InvalidArgumentException('Customer phone is empty.');
        }

        if (str_starts_with($digits, '380')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '38' . $digits;
        }

        return '380' . $digits;
    }

    private function resolveWaybillApiKey(?string $apiKey, ?string $testApiKey, bool $testMode): ?string
    {
        if ($testMode && $testApiKey !== null && $testApiKey !== '') {
            return $testApiKey;
        }

        if ($testMode) {
            $this->logger->notice('Nova Poshta test mode is enabled but NOVA_POSHTA_TEST_API_KEY is empty; falling back to NOVA_POSHTA_API_KEY.');
        }

        return $apiKey;
    }
}
