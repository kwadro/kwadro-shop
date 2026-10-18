<?php

namespace App\Twig;

use App\Service\Menu\ShopMegaMenuBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryExtension extends AbstractExtension
{
    public function __construct(
        private readonly ShopMegaMenuBuilder $megaMenuBuilder,
        private readonly RequestStack $requestStack,
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
        $request = $this->requestStack->getCurrentRequest();
        $host = $request?->getHost() ?? '';
        $locale = $request?->getLocale() ?? 'uk';

        return $this->megaMenuBuilder->buildForHost($host, $locale);
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

                foreach ($child['children'] ?? [] as $grandchild) {
                    if ($grandchild['slug'] === $slug) {
                        return ['label' => $grandchild['name'], 'parent' => $child['name'], 'is_blog' => false];
                    }
                }
            }
        }

        return ['label' => null, 'parent' => null, 'is_blog' => false];
    }
}
