<?php

namespace App\Twig;

use App\Service\PromoStripProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PromoStripExtension extends AbstractExtension
{
    public function __construct(
        private readonly PromoStripProvider $promoStripProvider,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('shop_promo_strip', [$this, 'getPromoStrip']),
        ];
    }

    /**
     * @return array|null
     */
    public function getPromoStrip(): ?array
    {
        return $this->promoStripProvider->getForCurrentRequest();
    }
}
