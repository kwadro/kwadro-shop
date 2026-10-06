<?php

namespace App\Twig;

use App\Service\Product\ProductImagePath;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ProductImageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('product_image', [$this, 'resolve']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_image_url', [$this, 'resolve']),
            new TwigFunction('product_uploads_base', static fn (): string => ProductImagePath::WEB_BASE),
        ];
    }

    public function resolve(?string $value): string
    {
        return ProductImagePath::webPath($value);
    }
}
