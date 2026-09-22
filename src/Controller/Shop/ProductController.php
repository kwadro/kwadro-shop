<?php

namespace App\Controller\Shop;

use App\Routing\ShopRoutes;
use App\Service\ProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductCatalog $productCatalog,
    ) {
    }

    #[Route(
        '/{_locale}/product/{slug}',
        name: 'shop_product',
        requirements: [
            '_locale' => ShopRoutes::LOCALE_REQUIREMENTS,
            'slug' => '[a-z0-9][a-z0-9\-]*',
        ],
        methods: ['GET'],
    )]
    public function show(string $_locale, string $slug): Response
    {
        $product = $this->productCatalog->findBySlug($slug);
        if ($product === null) {
            throw new NotFoundHttpException();
        }

        return $this->render('shop/product/show.html.twig', [
            'product' => $product,
        ]);
    }
}
