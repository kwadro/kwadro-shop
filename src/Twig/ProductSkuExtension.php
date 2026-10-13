<?php

namespace App\Twig;

use App\Service\Product\SkuDisplayFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ProductSkuExtension extends AbstractExtension
{
    public function __construct(
        private readonly SkuDisplayFormatter $formatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('display_sku', $this->formatter->format(...)),
        ];
    }
}
