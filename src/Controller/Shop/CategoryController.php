<?php

namespace App\Controller\Shop;

use App\Repository\CategoryRepository;
use App\Routing\ShopRoutes;
use App\Service\ProductListingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductListingService $productListing,
    ) {
    }

    #[Route(
        '/{_locale}/category/{slug}',
        name: 'shop_category',
        requirements: [
            '_locale' => ShopRoutes::LOCALE_REQUIREMENTS,
            'slug' => ShopRoutes::SLUG_REQUIREMENTS,
        ],
        methods: ['GET'],
    )]
    public function show(string $_locale, string $slug, Request $request): Response
    {
        $category = $this->categoryRepository->findOneBySlug($slug);
        if ($category === null || $category->isDefault() || !$category->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $children = [];
        foreach ($category->getChildren() as $child) {
            if (!$child->isEnabled()) {
                continue;
            }
            $children[] = [
                'id' => $child->getId(),
                'name' => $child->getName(),
                'slug' => $child->getSlug(),
            ];
        }

        $listing = $this->productListing->list(
            ['category' => $category],
            $request,
            $category->isShowFilters(),
        );

        return $this->render('shop/category/show.html.twig', [
            'category' => $category,
            'children' => $children,
            ...$listing,
        ]);
    }
}
