<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Service\Image\BackgroundRemoverService;
use App\Service\Product\ProductImagePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/product/{id}/clean-image', name: 'admin_product_clean_image', requirements: ['_locale' => 'uk|en', 'id' => '\d+'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ProductCleanImageController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly BackgroundRemoverService $backgroundRemover,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/{slot}', name: '_generate', methods: ['POST'], requirements: ['slot' => '[1-5]'])]
    public function generate(Product $product, int $slot): JsonResponse
    {
        $gallery = $product->getGallery();
        $index = $slot - 1;
        if (!isset($gallery[$index]) || !\is_array($gallery[$index])) {
            return new JsonResponse([
                'ok' => false,
                'error' => $this->translator->trans('admin.product.clean_image_no_source', [], 'messages'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $sourceFilename = ProductImagePath::filename((string) ($gallery[$index]['full'] ?? $gallery[$index]['thumb'] ?? ''));
        if ($sourceFilename === '') {
            return new JsonResponse([
                'ok' => false,
                'error' => $this->translator->trans('admin.product.clean_image_no_source', [], 'messages'),
            ], Response::HTTP_BAD_REQUEST);
        }

        ProductImagePath::ensureStored($this->projectDir, $sourceFilename);
        $sourcePath = ProductImagePath::absolutePath($this->projectDir, $sourceFilename);
        if ($sourcePath === '' || !is_file($sourcePath)) {
            return new JsonResponse([
                'ok' => false,
                'error' => $this->translator->trans('admin.product.clean_image_source_missing', [], 'messages'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $outFilename = sprintf('product-%d-clean-%s.png', (int) $product->getId(), bin2hex(random_bytes(4)));
        $outPath = ProductImagePath::absolutePath($this->projectDir, $outFilename);
        $outDir = \dirname($outPath);
        if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            return new JsonResponse([
                'ok' => false,
                'error' => $this->translator->trans('admin.product.clean_image_error', [], 'messages'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            @ini_set('memory_limit', '512M');
            @set_time_limit(120);
            $this->backgroundRemover->removeFromPath($sourcePath, $outPath, [
                'erode' => 1,
                'strength' => 'normal',
                'whiteThreshold' => 215,
                'softThreshold' => 200,
                'edgeDarken' => 55,
            ]);
        } catch (\Throwable $e) {
            @unlink($outPath);

            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage() !== '' ? $e->getMessage() : $this->translator->trans('admin.product.clean_image_error', [], 'messages'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $oldFilename = $product->getCleanImageFilename();
        $product->setCleanImage($outFilename);
        $this->entityManager->flush();

        if ($oldFilename !== null && $oldFilename !== $outFilename) {
            $oldPath = ProductImagePath::absolutePath($this->projectDir, $oldFilename);
            if ($oldPath !== '' && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $this->translator->trans('admin.product.clean_image_done', [], 'messages'),
            'filename' => $outFilename,
            'url' => ProductImagePath::webPath($outFilename).'?t='.time(),
            'relative' => ProductImagePath::relativePath($outFilename),
            'slot' => $slot,
        ]);
    }
}
