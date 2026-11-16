<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/product-rating', name: 'admin_product_rating', requirements: ['_locale' => 'ua|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ProductRatingController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = (string) $request->attributes->get('_locale', 'ua');

        return $this->render('admin/product_rating/index.html.twig', [
            'page_title' => $this->translator->trans('admin.product_rating.title', [], 'messages'),
            'search_url' => $this->generateUrl('admin_product_rating_search', ['_locale' => $locale]),
            'product_url_template' => str_replace(
                '999999',
                '__ID__',
                $this->generateUrl('admin_product_rating_product', ['_locale' => $locale, 'id' => 999999]),
            ),
            'save_url_template' => str_replace(
                '999999',
                '__ID__',
                $this->generateUrl('admin_product_rating_save', ['_locale' => $locale, 'id' => 999999]),
            ),
        ]);
    }

    #[Route('/search', name: '_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return new JsonResponse(['ok' => true, 'items' => []]);
        }

        $products = $this->productRepository->searchForAdmin($q, 20);
        $items = [];
        foreach ($products as $product) {
            $items[] = $this->serializeProduct($product);
        }

        return new JsonResponse(['ok' => true, 'items' => $items]);
    }

    #[Route('/product/{id}', name: '_product', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function product(Product $product): JsonResponse
    {
        return new JsonResponse([
            'ok' => true,
            'product' => $this->serializeProduct($product),
        ]);
    }

    #[Route('/product/{id}/save', name: '_save', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function save(Request $request, Product $product): JsonResponse
    {
        $raw = $request->request->get('rating');
        if ($raw === null && $request->getContent() !== '') {
            try {
                $payload = $request->toArray();
                $raw = $payload['rating'] ?? null;
            } catch (\Throwable) {
                $raw = null;
            }
        }
        if (!is_numeric($raw)) {
            return new JsonResponse([
                'ok' => false,
                'message' => $this->translator->trans('admin.product_rating.invalid', [], 'messages'),
            ], 400);
        }

        $product->setRating((int) $raw);
        $this->entityManager->flush();

        return new JsonResponse([
            'ok' => true,
            'product' => $this->serializeProduct($product),
            'message' => $this->translator->trans('admin.product_rating.saved', [], 'messages'),
        ]);
    }

    /**
     * @return array{id: int, name: string, sku: string, rating: int, enabled: bool}
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => (int) $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'rating' => $product->getRating(),
            'enabled' => $product->isEnabled(),
        ];
    }
}
