<?php

namespace App\Controller\Admin;

use App\Service\Image\BlurredBackgroundTransformService;
use App\Service\Marketing\MarketingAssetLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/image-transform', name: 'admin_image_transform', requirements: ['_locale' => 'uk|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ImageTransformController extends AbstractController
{
    /** @var array<string, array{w: int, h: int, label: string}> */
    public const ASPECTS = [
        '9:16' => ['w' => 1080, 'h' => 1920, 'label' => '9:16'],
        '4:5' => ['w' => 1080, 'h' => 1350, 'label' => '4:5'],
        '1:1' => ['w' => 1080, 'h' => 1080, 'label' => '1:1'],
        '16:9' => ['w' => 1920, 'h' => 1080, 'label' => '16:9'],
        '1200x630' => ['w' => 1200, 'h' => 630, 'label' => '1200×630 (OG)'],
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly BlurredBackgroundTransformService $transform,
        private readonly MarketingAssetLibrary $library,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = (string) $request->attributes->get('_locale', 'uk');

        return $this->render('admin/image_transform/index.html.twig', [
            'page_title' => $this->translator->trans('admin.image_transform.title', [], 'messages'),
            'aspects' => self::ASPECTS,
            'process_url' => $this->generateUrl('admin_image_transform_process', ['_locale' => $locale]),
            'download_url_template' => str_replace(
                'tf-placeholder.jpg',
                '__FILENAME__',
                $this->generateUrl('admin_image_transform_download', ['_locale' => $locale, 'filename' => 'tf-placeholder.jpg']),
            ),
            'delete_url_template' => str_replace(
                'tf-placeholder.jpg',
                '__FILENAME__',
                $this->generateUrl('admin_image_transform_delete', ['_locale' => $locale, 'filename' => 'tf-placeholder.jpg']),
            ),
            'recent' => $this->listRecent(),
        ]);
    }

    #[Route('/process', name: '_process', methods: ['POST'])]
    public function process(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return new JsonResponse(['ok' => false, 'error' => 'No file'], Response::HTTP_BAD_REQUEST);
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Unsupported type'], Response::HTTP_BAD_REQUEST);
        }

        $aspect = (string) $request->request->get('aspect', '9:16');
        if (!isset(self::ASPECTS[$aspect])) {
            $aspect = '9:16';
        }
        $size = self::ASPECTS[$aspect];
        $blur = max(0, min(40, (int) $request->request->get('blur', 18)));
        $alignX = strtolower(trim((string) $request->request->get('alignX', 'center')));
        if (!\in_array($alignX, ['left', 'center', 'right'], true)) {
            $alignX = 'center';
        }
        $alignY = strtolower(trim((string) $request->request->get('alignY', 'center')));
        if (!\in_array($alignY, ['top', 'center', 'bottom'], true)) {
            $alignY = 'center';
        }
        $rotate = ((int) $request->request->get('rotate', 0)) % 360;
        if ($rotate < 0) {
            $rotate += 360;
        }
        if (!\in_array($rotate, [0, 90, 180, 270], true)) {
            $rotate = 0;
        }
        $saveToLibrary = filter_var($request->request->get('saveToLibrary', '1'), \FILTER_VALIDATE_BOOL);

        $uploadsDir = $this->getUploadsDir();
        $resultsDir = $this->getResultsDir();
        foreach ([$uploadsDir, $resultsDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                return new JsonResponse(['ok' => false, 'error' => 'Cannot create storage'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $stamp = date('Ymd-His').'-'.bin2hex(random_bytes(3));
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
        $sourceName = 'src-'.$stamp.'.'.$ext;
        $resultName = 'tf-'.$stamp.'.jpg';
        $sourcePath = $uploadsDir.'/'.$sourceName;
        $resultPath = $resultsDir.'/'.$resultName;
        $downloadName = $this->buildDownloadName((string) $file->getClientOriginalName(), $aspect);

        $file->move($uploadsDir, $sourceName);

        try {
            @ini_set('memory_limit', '512M');
            @set_time_limit(120);
            $this->transform->transformFile($sourcePath, $resultPath, [
                'width' => $size['w'],
                'height' => $size['h'],
                'blur' => $blur,
                'alignX' => $alignX,
                'alignY' => $alignY,
                'rotate' => $rotate,
            ]);
        } catch (\Throwable $e) {
            @unlink($sourcePath);

            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Processing failed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $libraryItem = null;
        if ($saveToLibrary) {
            try {
                $libraryItem = $this->library->addFromPath(
                    MarketingAssetLibrary::KIND_MEDIA,
                    $resultPath,
                    pathinfo($downloadName, \PATHINFO_FILENAME),
                    'jpg',
                );
            } catch (\Throwable) {
                $libraryItem = null;
            }
        }

        $meta = [
            'filename' => $resultName,
            'downloadName' => $downloadName,
            'aspect' => $aspect,
            'width' => $size['w'],
            'height' => $size['h'],
            'blur' => $blur,
            'alignX' => $alignX,
            'alignY' => $alignY,
            'rotate' => $rotate,
            'savedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'source' => $sourceName,
        ];
        file_put_contents($resultPath.'.json', json_encode($meta, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT));

        return new JsonResponse([
            'ok' => true,
            'message' => $this->translator->trans('admin.image_transform.done', [], 'messages'),
            'filename' => $resultName,
            'url' => '/uploads/image-transform/results/'.$resultName,
            'downloadName' => $downloadName,
            'width' => $size['w'],
            'height' => $size['h'],
            'aspect' => $aspect,
            'library' => $libraryItem,
            'recent' => $this->listRecent(),
        ]);
    }

    #[Route('/download/{filename}', name: '_download', methods: ['GET'], requirements: ['filename' => 'tf-[A-Za-z0-9._-]+\.jpg'])]
    public function download(Request $request, string $filename): Response
    {
        $path = $this->getResultsDir().'/'.$filename;
        $realDir = realpath($this->getResultsDir());
        $realPath = realpath($path);
        if ($realDir === false || $realPath === false || !str_starts_with($realPath, $realDir.\DIRECTORY_SEPARATOR)) {
            throw $this->createNotFoundException();
        }

        $downloadName = $filename;
        $metaPath = $realPath.'.json';
        if (is_file($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true);
            if (\is_array($meta) && isset($meta['downloadName']) && \is_string($meta['downloadName']) && $meta['downloadName'] !== '') {
                $downloadName = $meta['downloadName'];
            }
        }
        $requested = trim((string) $request->query->get('name', ''));
        if ($requested !== '' && preg_match('#^[A-Za-z0-9._\- ]+\.jpe?g$#i', $requested) === 1) {
            $downloadName = $requested;
        }

        $response = new BinaryFileResponse($realPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);

        return $response;
    }

    #[Route('/delete/{filename}', name: '_delete', methods: ['POST', 'DELETE'], requirements: ['filename' => 'tf-[A-Za-z0-9._-]+\.jpg'])]
    public function delete(string $filename): JsonResponse
    {
        $dir = realpath($this->getResultsDir());
        if ($dir === false) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $path = $dir.'/'.$filename;
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, $dir.\DIRECTORY_SEPARATOR)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        @unlink($real);
        if (is_file($real.'.json')) {
            @unlink($real.'.json');
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $this->translator->trans('admin.image_transform.deleted', [], 'messages'),
            'recent' => $this->listRecent(),
        ]);
    }

    /** @return list<array{filename: string, url: string, downloadName: string|null, aspect: string|null, savedAt: string|null}> */
    private function listRecent(): array
    {
        $dir = $this->getResultsDir();
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir.'/tf-*.jpg') ?: [];
        rsort($files, \SORT_STRING);
        $items = [];
        foreach (\array_slice($files, 0, 24) as $path) {
            $filename = basename($path);
            $downloadName = null;
            $aspect = null;
            $savedAt = null;
            $metaPath = $path.'.json';
            if (is_file($metaPath)) {
                $meta = json_decode((string) file_get_contents($metaPath), true);
                if (\is_array($meta)) {
                    $downloadName = isset($meta['downloadName']) && \is_string($meta['downloadName']) ? $meta['downloadName'] : null;
                    $aspect = isset($meta['aspect']) && \is_string($meta['aspect']) ? $meta['aspect'] : null;
                    $savedAt = isset($meta['savedAt']) && \is_string($meta['savedAt']) ? $meta['savedAt'] : null;
                }
            }
            $items[] = [
                'filename' => $filename,
                'url' => '/uploads/image-transform/results/'.$filename,
                'downloadName' => $downloadName,
                'aspect' => $aspect,
                'savedAt' => $savedAt,
            ];
        }

        return $items;
    }

    private function buildDownloadName(string $original, string $aspect): string
    {
        $base = pathinfo($original, \PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $base) ?: 'image';
        $base = trim($base, '._-') ?: 'image';
        $suffix = match ($aspect) {
            '1200x630' => 'og_1200x630',
            '16:9' => '16x9',
            '4:5' => '4x5',
            '1:1' => '1x1',
            default => '9x16',
        };

        return $base.'_'.$suffix.'.jpg';
    }

    private function getUploadsDir(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/image-transform/uploads';
    }

    private function getResultsDir(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/image-transform/results';
    }
}
