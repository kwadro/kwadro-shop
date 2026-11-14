<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Applies the logged-in customer's user-group discount to catalog price arrays.
 * When a discount applies, `price` becomes the member price and `rrp` keeps the list (РРЦ) price.
 */
final class CustomerPriceApplier
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function discountPercentForCurrentUser(): float
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0.0;
        }

        $group = $user->getUserGroup();
        if ($group === null) {
            return 0.0;
        }

        return $group->getDiscountPercent();
    }

    /** @param array $item Catalog row with at least `price` */
    public function applyToCatalogItem(array $item): array
    {
        $percent = $this->discountPercentForCurrentUser();
        if ($percent <= 0) {
            return $item;
        }

        if (isset($item['price']) && is_numeric($item['price'])) {
            $listPrice = (float) $item['price'];
            if ($listPrice > 0) {
                $item['rrp'] = $listPrice;
                $item['price'] = $this->discountAmount($listPrice, $percent);
                $item['member_discount_percent'] = $percent;
            }
        }

        if (isset($item['offers']) && \is_array($item['offers'])) {
            $item['offers'] = array_map(
                function ($offer) use ($percent) {
                    if (!\is_array($offer) || !isset($offer['price']) || !is_numeric($offer['price'])) {
                        return $offer;
                    }

                    $listPrice = (float) $offer['price'];
                    if ($listPrice <= 0) {
                        return $offer;
                    }

                    $offer['rrp'] = $listPrice;
                    $offer['price'] = $this->discountAmount($listPrice, $percent);
                    $offer['member_discount_percent'] = $percent;

                    return $offer;
                },
                $item['offers'],
            );
        }

        return $item;
    }

    /** @param array $items */
    public function applyToCatalogItems(array $items): array
    {
        return array_map(
            fn (array $item): array => $this->applyToCatalogItem($item),
            $items,
        );
    }

    public function memberPriceFromList(float $listPrice): float
    {
        $percent = $this->discountPercentForCurrentUser();
        if ($percent <= 0 || $listPrice <= 0) {
            return round(max(0.0, $listPrice), 2);
        }

        return $this->discountAmount($listPrice, $percent);
    }

    private function discountAmount(float $listPrice, float $percent): float
    {
        return round(max(0.0, $listPrice * (1 - ($percent / 100))), 2);
    }
}
