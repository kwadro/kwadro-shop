<?php

namespace App\Controller\Shop;

use App\Repository\SupplierRepository;
use App\Routing\ShopRoutes;
use App\Service\ProductListingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class SupplierController extends AbstractController
{
    public function __construct(
        private readonly SupplierRepository $supplierRepository,
        private readonly ProductListingService $productListing,
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
    public function show(string $_locale, string $slug, Request $request): Response
    {
        $supplier = $this->supplierRepository->findOneBySlug($slug);
        if ($supplier === null) {
            throw new NotFoundHttpException();
        }

        $listing = $this->productListing->list(
            ['supplier' => $supplier],
            $request,
            true,
        );

        return $this->render('shop/supplier/show.html.twig', [
            'supplier' => $supplier,
            ...$listing,
        ]);
    }
}
