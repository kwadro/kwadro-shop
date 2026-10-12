<?php

namespace App\Twig;

use App\Service\Product\ProductDescriptionFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ProductDescriptionExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductDescriptionFormatter $formatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_description', $this->formatter->format(...), ['is_safe' => ['html']]),
        ];
    }
}
