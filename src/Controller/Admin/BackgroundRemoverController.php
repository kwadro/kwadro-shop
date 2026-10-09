<?php

namespace App\Controller\Admin;

use App\Service\Image\BackgroundRemoverService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/background-remover', name: 'admin_background_remover', requirements: ['_locale' => 'uk|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class BackgroundRemoverController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly BackgroundRemoverService $backgroundRemover,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = (string) $request->attributes->get('_locale', 'uk');

        return $this->render('admin/background_remover/index.html.twig', [
            'page_title' => $this->translator->trans('admin.background_remover.title', [], 'messages'),
            'process_url' => $this->generateUrl('admin_background_remover_process', ['_locale' => $locale]),
            'download_url_template' => str_replace(
                'cut-placeholder.png',
                '__FILENAME__',
                $this->generateUrl('admin_background_remover_download', ['_locale' => $locale, 'filename' => 'cut-placeholder.png']),
            ),
            'delete_url_template' => str_replace(
                'cut-placeholder.png',
                '__FILENAME__',
                $this->generateUrl('admin_background_remover_delete', ['_locale' => $locale, 'filename' => 'cut-placeholder.png']),
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

        $erode = max(0, min(4, (int) $request->request->get('erode', 1)));
        $strength = (string) $request->request->get('strength', 'normal');
        $mode = (string) $request->request->get('mode', BackgroundRemoverService::MODE_FULL);
        if ($mode !== BackgroundRemoverService::MODE_CONTOUR) {
            $mode = BackgroundRemoverService::MODE_FULL;
        }
        [$whiteThreshold, $softThreshold, $edgeDarken] = match ($strength) {
            'soft' => [230, 210, 65],
            'strong' => [200, 185, 50],
            default => [215, 200, 55],
        };

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
        $resultName = 'cut-'.$stamp.'.png';
        $sourcePath = $uploadsDir.'/'.$sourceName;
        $resultPath = $resultsDir.'/'.$resultName;
        $downloadName = $this->buildDownloadName((string) $file->getClientOriginalName());

        $file->move($uploadsDir, $sourceName);

        try {
            @ini_set('memory_limit', '512M');
            @set_time_limit(120);
            $this->backgroundRemover->removeFromPath($sourcePath, $resultPath, [
                'erode' => $erode,
                'whiteThreshold' => $whiteThreshold,
                'softThreshold' => $softThreshold,
                'edgeDarken' => $edgeDarken,
                'mode' => $mode,
            ]);
        } catch (\Throwable $e) {
            @unlink($sourcePath);

            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Processing failed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $size = @getimagesize($resultPath) ?: [0, 0];

        return new JsonResponse([
            'ok' => true,
            'message' => $this->translator->trans('admin.background_remover.done', [], 'messages'),
            'filename' => $resultName,
            'downloadName' => $downloadName,
            'url' => '/uploads/background-remover/results/'.$resultName,
            'sourceUrl' => '/uploads/background-remover/uploads/'.$sourceName,
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'recent' => $this->listRecent(),
        ]);
    }

    #[Route('/download/{filename}', name: '_download', methods: ['GET'], requirements: ['filename' => 'cut-[A-Za-z0-9._-]+\.png'])]
    public function download(Request $request, string $filename): Response
    {
        $path = $this->getResultsDir().'/'.$filename;
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $as = trim((string) $request->query->get('as', ''));
        $downloadName = $as !== '' ? $this->sanitizeDownloadName($as) : $filename;
        $targetHeight = (int) $request->query->get('height', 0);

        $info = @getimagesize($path) ?: [0, 0];
        $srcH = (int) ($info[1] ?? 0);
        $needsResize = $targetHeight >= 16 && $srcH > 0 && $targetHeight !== $srcH;

        if (!$needsResize) {
            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $downloadName);
            $response->headers->set('Content-Type', 'image/png');

            return $response;
        }

        @ini_set('memory_limit', '512M');
        $img = $this->backgroundRemover->loadImage($path);
        $img = $this->backgroundRemover->resizeToHeight($img, $targetHeight);
        ob_start();
        imagepng($img, null, 6);
        $png = ob_get_clean();
        imagedestroy($img);
        if ($png === false || $png === '') {
            throw $this->createNotFoundException();
        }

        $response = new Response($png);
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $downloadName,
        ));

        return $response;
    }

    #[Route('/delete/{filename}', name: '_delete', methods: ['POST'], requirements: ['filename' => 'cut-[A-Za-z0-9._-]+\.png'])]
    public function delete(string $filename): JsonResponse
    {
        $path = $this->getResultsDir().'/'.$filename;
        if (is_file($path)) {
            @unlink($path);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $this->translator->trans('admin.background_remover.deleted', [], 'messages'),
            'recent' => $this->listRecent(),
        ]);
    }

    /** @return list<array{filename: string, url: string, savedAt: string|null}> */
    private function listRecent(): array
    {
        $dir = $this->getResultsDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/cut-*.png') ?: [];
        rsort($files, \SORT_STRING);
        $items = [];
        foreach (\array_slice($files, 0, 24) as $path) {
            $filename = basename($path);
            $items[] = [
                'filename' => $filename,
                'url' => '/uploads/background-remover/results/'.$filename,
                'savedAt' => date(\DateTimeInterface::ATOM, (int) filemtime($path)),
            ];
        }

        return $items;
    }

    private function getUploadsDir(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/background-remover/uploads';
    }

    private function getResultsDir(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/background-remover/results';
    }

    private function buildDownloadName(string $originalClientName): string
    {
        $base = pathinfo($originalClientName, \PATHINFO_FILENAME);
        $base = $this->sanitizeDownloadBasename($base);
        if ($base === '') {
            $base = 'image';
        }

        return $base.'_cut.png';
    }

    private function sanitizeDownloadName(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $name);
        $name = trim($name);
        if ($name === '') {
            return 'image_cut.png';
        }
        if (!str_ends_with(strtolower($name), '.png')) {
            $name .= '.png';
        }
        $base = pathinfo($name, \PATHINFO_FILENAME);
        $base = $this->sanitizeDownloadBasename($base);
        if ($base === '') {
            $base = 'image_cut';
        }

        return $base.'.png';
    }

    private function sanitizeDownloadBasename(string $base): string
    {
        $base = trim($base);
        $base = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $base) ?? '';
        $base = preg_replace('/_+/', '_', $base) ?? '';
        $base = trim($base, '._-');

        return mb_substr($base, 0, 120);
    }
}
