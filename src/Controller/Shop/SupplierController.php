<?php

namespace App\Controller\Shop;

use App\Repository\SupplierRepository;
use App\Routing\ShopRoutes;
use App\Service\ProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SupplierController extends AbstractController
{
    public function __construct(
        private readonly SupplierRepository $supplierRepository,
        private readonly ProductCatalog $productCatalog,
    ) {
    }

    #[Route(
        '/{_locale}/supplier/{slug}',
        name: 'shop_supplier',
        requirements: [
            '_locale' => ShopRoutes::LOCALE_REQUIREMENTS,
            'slug' => ShopRoutes::SLUG_REQUIREMENTS,
        ],
        methods: ['GET'],
    )]
    public function show(string $_locale, string $slug): Response
    {
        $supplier = $this->supplierRepository->findOneBySlug($slug);
        if ($supplier === null) {
            throw new NotFoundHttpException();
        }

        return $this->render('shop/supplier/show.html.twig', [
            'supplier' => $supplier,
            'products' => $this->productCatalog->findBySupplier($supplier),
        ]);
    }
}
