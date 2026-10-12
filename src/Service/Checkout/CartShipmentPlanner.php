<?php

namespace App\Service\Checkout;

use App\Entity\Product;
use App\Entity\Supplier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Splits the cart into parcels: one parcel per supplier.
 */
final class CartShipmentPlanner
{
    private const MIN_ITEM_WEIGHT = 0.1;
    private const DEFAULT_ITEM_WEIGHT = 1.0;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $cart
     *
     * @return list<array{
     *     key: string,
     *     supplierId: int|null,
     *     supplierName: string,
     *     items: list<array<string, mixed>>,
     *     itemCount: int,
     *     subtotal: float,
     *     weight: float,
     *     supplierSlug: string|null,
     *     senderCityRef: string|null,
     *     senderCityName: string|null,
     *     minOrderAmount: float|null,
     *     minOrderShortfall: float
     * }>
     */
    public function plan(array $cart): array
    {
        $items = $this->extractItems($cart);
        if ($items === []) {
            return [];
        }

        $liveWeights = $this->loadLiveWeights($items);
        $parcels = [];

        foreach ($items as $item) {
            $product = \is_array($item['product'] ?? null) ? $item['product'] : [];
            $supplier = \is_array($product['supplier'] ?? null) ? $product['supplier'] : [];

            $supplierId = $item['supplier_id'] ?? $supplier['id'] ?? null;
            $supplierId = is_numeric($supplierId) && (int) $supplierId > 0 ? (int) $supplierId : null;
            $supplierName = trim((string) ($item['supplier_name'] ?? $supplier['name'] ?? ''));
            $key = $supplierId !== null ? 's' . $supplierId : 'none';

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $productId = (int) ($item['product_id'] ?? $product['id'] ?? 0);
            $unitWeight = $liveWeights[$productId]
                ?? (is_numeric($product['weight'] ?? null) ? (float) $product['weight'] : self::DEFAULT_ITEM_WEIGHT);
            $lineTotal = is_numeric($item['line_total'] ?? null)
                ? (float) $item['line_total']
                : (float) ($product['price'] ?? 0) * $quantity;

            if (!isset($parcels[$key])) {
                $parcels[$key] = [
                    'key' => $key,
                    'supplierId' => $supplierId,
                    'supplierName' => $supplierName,
                    'items' => [],
                    'itemCount' => 0,
                    'subtotal' => 0.0,
                    'weight' => 0.0,
                ];
            } elseif ($parcels[$key]['supplierName'] === '' && $supplierName !== '') {
                $parcels[$key]['supplierName'] = $supplierName;
            }

            $parcels[$key]['items'][] = $item;
            $parcels[$key]['itemCount'] += $quantity;
            $parcels[$key]['subtotal'] += $lineTotal;
            $parcels[$key]['weight'] += max(self::MIN_ITEM_WEIGHT, $unitWeight) * $quantity;
        }

        $suppliers = $this->loadSupplierInfo(array_filter(array_column($parcels, 'supplierId')));

        foreach ($parcels as &$parcel) {
            $parcel['subtotal'] = round($parcel['subtotal'], 2);
            $parcel['weight'] = round(max(self::MIN_ITEM_WEIGHT, $parcel['weight']), 3);
            $supplier = $suppliers[$parcel['supplierId']] ?? null;
            $parcel['supplierSlug'] = $supplier['slug'] ?? null;
            $parcel['senderCityRef'] = $supplier['senderCityRef'] ?? null;
            $parcel['senderCityName'] = $parcel['senderCityRef'] !== null ? ($supplier['senderCityName'] ?? null) : null;
            $minOrderAmount = $supplier['minOrderAmount'] ?? null;
            $parcel['minOrderAmount'] = $minOrderAmount;
            $parcel['minOrderShortfall'] = $minOrderAmount !== null && $parcel['subtotal'] < $minOrderAmount
                ? round($minOrderAmount - $parcel['subtotal'], 2)
                : 0.0;
        }
        unset($parcel);

        return array_values($parcels);
    }

    /**
     * Parcels whose supplier minimum order amount is not reached yet.
     *
     * @param list<array<string, mixed>> $parcels
     *
     * @return list<array<string, mixed>>
     */
    public function findBelowMinimum(array $parcels): array
    {
        return array_values(array_filter(
            $parcels,
            static fn (array $parcel): bool => (float) ($parcel['minOrderShortfall'] ?? 0) > 0,
        ));
    }

    /**
     * @param list<array<string, mixed>> $parcels
     */
    public function totalWeight(array $parcels): float
    {
        $weight = 0.0;
        foreach ($parcels as $parcel) {
            $weight += (float) ($parcel['weight'] ?? 0);
        }

        return round(max(self::MIN_ITEM_WEIGHT, $weight), 3);
    }

    /**
     * @param array<string, mixed> $cart
     *
     * @return list<array<string, mixed>>
     */
    private function extractItems(array $cart): array
    {
        $items = $cart['items'] ?? null;
        if (\is_array($items) && $items !== []) {
            return array_values(array_filter($items, static fn ($item): bool => \is_array($item) && \is_array($item['product'] ?? null)));
        }

        if (\is_array($cart['product'] ?? null)) {
            return [[
                'product_id' => $cart['product_id'] ?? $cart['product']['id'] ?? 0,
                'quantity' => $cart['quantity'] ?? 1,
                'product' => $cart['product'],
            ]];
        }

        return [];
    }

    /**
     * @param array<int|string, int|null> $supplierIds
     *
     * @return array<int, array{slug: string|null, senderCityRef: string|null, senderCityName: string|null, minOrderAmount: float|null}>
     */
    private function loadSupplierInfo(array $supplierIds): array
    {
        $supplierIds = array_values(array_unique(array_map('intval', $supplierIds)));
        if ($supplierIds === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                's.id AS id',
                's.slug AS slug',
                's.np_sender_city_ref AS ref',
                's.np_sender_city_name AS name',
                's.min_order_amount AS minOrderAmount',
            )
            ->from(Supplier::class, 's')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $supplierIds)
            ->getQuery()
            ->getArrayResult();

        $suppliers = [];
        foreach ($rows as $row) {
            $minOrderAmount = is_numeric($row['minOrderAmount'] ?? null) ? (float) $row['minOrderAmount'] : null;
            $suppliers[(int) $row['id']] = [
                'slug' => ($row['slug'] ?? '') !== '' ? (string) $row['slug'] : null,
                'senderCityRef' => ($row['ref'] ?? '') !== '' ? (string) $row['ref'] : null,
                'senderCityName' => $row['name'] ?? null,
                'minOrderAmount' => $minOrderAmount !== null && $minOrderAmount > 0 ? $minOrderAmount : null,
            ];
        }

        return $suppliers;
    }

    /**
     * Current catalog weights win over cart snapshots, which may predate a weight import.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array<int, float>
     */
    private function loadLiveWeights(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['product_id'] ?? $item['product']['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id AS id', 'p.weight AS weight')
            ->from(Product::class, 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', array_values($ids))
            ->getQuery()
            ->getArrayResult();

        $weights = [];
        foreach ($rows as $row) {
            if (is_numeric($row['weight'] ?? null)) {
                $weights[(int) $row['id']] = (float) $row['weight'];
            }
        }

        return $weights;
    }
}
