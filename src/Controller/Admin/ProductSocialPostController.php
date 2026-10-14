<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Marketing\ProductFacebookPostGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/product-social-posts', name: 'admin_product_social_posts', requirements: ['_locale' => 'uk|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ProductSocialPostController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly ProductFacebookPostGenerator $generator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = (string) $request->attributes->get('_locale', 'uk');

        return $this->render('admin/product_social_posts/index.html.twig', [
            'page_title' => $this->translator->trans('admin.social_posts.title', [], 'messages'),
            'search_url' => $this->generateUrl('admin_product_social_posts_search', ['_locale' => $locale]),
            'generate_url_template' => str_replace(
                '999999',
                '__ID__',
                $this->generateUrl('admin_product_social_posts_generate', ['_locale' => $locale, 'id' => 999999]),
            ),
            'save_url_template' => str_replace(
                '999999',
                '__ID__',
                $this->generateUrl('admin_product_social_posts_save', ['_locale' => $locale, 'id' => 999999]),
            ),
            'update_url_template' => str_replace(
                ['999999', 'post-placeholder'],
                ['__ID__', '__POST__'],
                $this->generateUrl('admin_product_social_posts_update', [
                    '_locale' => $locale,
                    'id' => 999999,
                    'postId' => 'post-placeholder',
                ]),
            ),
            'delete_url_template' => str_replace(
                ['999999', 'post-placeholder'],
                ['__ID__', '__POST__'],
                $this->generateUrl('admin_product_social_posts_delete', [
                    '_locale' => $locale,
                    'id' => 999999,
                    'postId' => 'post-placeholder',
                ]),
            ),
            'product_url_template' => str_replace(
                '999999',
                '__ID__',
                $this->generateUrl('admin_product_social_posts_product', ['_locale' => $locale, 'id' => 999999]),
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

    #[Route('/product/{id}/generate', name: '_generate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function generate(Request $request, Product $product): JsonResponse
    {
        $locale = (string) $request->attributes->get('_locale', 'uk');
        $text = $this->generator->generate($product, $locale);
        $save = filter_var($request->request->get('save', '1'), \FILTER_VALIDATE_BOOL);
        $post = null;
        if ($save) {
            $post = $product->addSocialPost($text);
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'ok' => true,
            'text' => $text,
            'post' => $post,
            'product' => $this->serializeProduct($product),
            'message' => $this->translator->trans(
                $save ? 'admin.social_posts.generated_saved' : 'admin.social_posts.generated',
                [],
                'messages',
            ),
        ]);
    }

    #[Route('/product/{id}/save', name: '_save', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function save(Request $request, Product $product): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $text = '';
        if (\is_array($payload) && isset($payload['text']) && \is_string($payload['text'])) {
            $text = trim($payload['text']);
        } else {
            $text = trim((string) $request->request->get('text', ''));
        }
        if ($text === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Empty text'], Response::HTTP_BAD_REQUEST);
        }

        $post = $product->addSocialPost($text);
        $this->entityManager->flush();

        return new JsonResponse([
            'ok' => true,
            'post' => $post,
            'product' => $this->serializeProduct($product),
            'message' => $this->translator->trans('admin.social_posts.saved', [], 'messages'),
        ]);
    }

    #[Route('/product/{id}/post/{postId}', name: '_update', methods: ['POST'], requirements: ['id' => '\d+', 'postId' => '[a-zA-Z0-9_-]+'])]
    public function update(Request $request, Product $product, string $postId): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $text = '';
        if (\is_array($payload) && isset($payload['text']) && \is_string($payload['text'])) {
            $text = trim($payload['text']);
        } else {
            $text = trim((string) $request->request->get('text', ''));
        }
        if ($text === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Empty text'], Response::HTTP_BAD_REQUEST);
        }

        if (!$product->updateSocialPost($postId, $text)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $this->entityManager->flush();

        return new JsonResponse([
            'ok' => true,
            'product' => $this->serializeProduct($product),
            'message' => $this->translator->trans('admin.social_posts.updated', [], 'messages'),
        ]);
    }

    #[Route('/product/{id}/post/{postId}/delete', name: '_delete', methods: ['POST', 'DELETE'], requirements: ['id' => '\d+', 'postId' => '[a-zA-Z0-9_-]+'])]
    public function delete(Product $product, string $postId): JsonResponse
    {
        if (!$product->removeSocialPost($postId)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $this->entityManager->flush();

        return new JsonResponse([
            'ok' => true,
            'product' => $this->serializeProduct($product),
            'message' => $this->translator->trans('admin.social_posts.deleted', [], 'messages'),
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeProduct(Product $product): array
    {
        $price = $product->getLowestPrice();

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'slug' => $product->getSlug(),
            'price' => $price,
            'priceLabel' => $price !== null ? number_format((float) $price, 2, '.', ' ').' ₴' : null,
            'inStock' => $product->isInStock(),
            'posts' => $product->getSocialPosts(),
            'postsCount' => \count($product->getSocialPosts()),
        ];
    }
}
