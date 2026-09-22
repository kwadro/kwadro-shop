<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('shop_category_menu', [$this, 'getCategoryMenu']),
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string, children: list<array{id: int, name: string, slug: string}>}>
     */
    public function getCategoryMenu(): array
    {
        return $this->categoryRepository->buildStorefrontMenuTree();
    }
}
