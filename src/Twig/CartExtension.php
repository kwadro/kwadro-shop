<?php

namespace App\Twig;

use App\Service\Cart\CartStorageService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CartExtension extends AbstractExtension
{
    public function __construct(
        private readonly CartStorageService $cartStorage,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('shop_cart', [$this, 'getCartSummary']),
        ];
    }

    /** @return array{hasItems: bool, itemCount: int, items: list<array<string, mixed>>, total: float} */
    public function getCartSummary(): array
    {
        return $this->cartStorage->getSummary();
    }
}
