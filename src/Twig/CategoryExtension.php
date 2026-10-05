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
            new TwigFunction('shop_category_nav_current', [$this, 'getCategoryNavCurrent']),
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string, children: list<array{id: int, name: string, slug: string}>}>
     */
    public function getCategoryMenu(): array
    {
        return $this->categoryRepository->buildStorefrontMenuTree();
    }

    /**
     * @return array{label: string|null, parent: string|null, is_blog: bool}
     */
    public function getCategoryNavCurrent(?string $route, ?string $slug): array
    {
        if ($route !== null && str_starts_with($route, 'shop_blog')) {
            return ['label' => null, 'parent' => null, 'is_blog' => true];
        }

        if ($route !== 'shop_category' || $slug === null || $slug === '') {
            return ['label' => null, 'parent' => null, 'is_blog' => false];
        }

        foreach ($this->getCategoryMenu() as $item) {
            if ($item['slug'] === $slug) {
                return ['label' => $item['name'], 'parent' => null, 'is_blog' => false];
            }

            foreach ($item['children'] as $child) {
                if ($child['slug'] === $slug) {
                    return ['label' => $child['name'], 'parent' => $item['name'], 'is_blog' => false];
                }
            }
        }

        return ['label' => null, 'parent' => null, 'is_blog' => false];
    }
}
