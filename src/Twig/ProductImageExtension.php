<?php

namespace App\Twig;

use App\Service\Product\ProductImagePath;
use App\Service\Product\ProductOgImagePath;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ProductImageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('product_image', [$this, 'resolve']),
            new TwigFilter('product_og_image', [$this, 'resolveOg']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_image_url', [$this, 'resolve']),
            new TwigFunction('product_og_image_url', [$this, 'resolveOg']),
            new TwigFunction('product_uploads_base', static fn (): string => ProductImagePath::WEB_BASE),
            new TwigFunction('product_og_uploads_base', static fn (): string => ProductOgImagePath::WEB_BASE),
        ];
    }

    public function resolve(?string $value, ?string $supplierCode = null): string
    {
        return ProductImagePath::webPath($value, $supplierCode);
    }

    public function resolveOg(?string $value): string
    {
        return ProductOgImagePath::webPath($value);
    }
}
