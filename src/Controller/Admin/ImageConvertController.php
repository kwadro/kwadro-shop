<?php

namespace App\Controller\Admin;


use App\Service\ProductImport\ImageConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/image-convert', name: 'admin_image_convert', requirements: ['_locale' => 'ua|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ImageConvertController extends AbstractController
{


    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ImageConverter $imageConverter,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = (string) $request->attributes->get('_locale', 'ua');

        return $this->render('admin/image_convert/index.html.twig', [
            'page_title' => $this->translator->trans('admin.image_convert.title', [], 'messages'),
            'process_url' => $this->generateUrl('admin_image_convert_process', ['_locale' => $locale]),
            'download_url_template' => str_replace(
                'tf-placeholder.webp',
                '__FILENAME__',
                $this->generateUrl('admin_image_convert_download', ['_locale' => $locale, 'filename' => 'tf-placeholder.webp']),
            )
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
        if (!\in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Unsupported type'], Response::HTTP_BAD_REQUEST);
        }
        $quality = max(0, min(100, (int) $request->request->get('quality', 80)));
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
            default => 'jpg',
        };
        $sourceName = 'src-'.$stamp.'.'.$ext;
        $resultName = 'tf-'.$stamp.'.webp';
        $sourcePath = $uploadsDir.'/'.$sourceName;
        $resultPath = $resultsDir.'/'.$resultName;
        $downloadName = $this->buildDownloadName((string) $file->getClientOriginalName());

        $file->move($uploadsDir, $sourceName);

        try {
            @ini_set('memory_limit', '512M');
            @set_time_limit(120);
            $savedPercent = $this->imageConverter->convertFile($sourcePath,$resultPath,$quality);
            $size = number_format(filesize($resultPath) / (1024 * 1024),3) . ' Mb';;
        } catch (\Throwable $e) {
            @unlink($sourcePath);

            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Processing failed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'ok' => true,
            'message' => $this->translator->trans('admin.image_convert.done', [], 'messages'),
            'filename' => $resultName,
            'url' => '/uploads/image-convert/results/'.$resultName,
            'downloadName' => $downloadName,
            'savedPercent' => $savedPercent . ' %',
            'filesize' => $size
        ]);
    }

    #[Route('/download/{filename}', name: '_download', methods: ['GET'], requirements: ['filename' => 'tf-[A-Za-z0-9._-]+\.webp'])]
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

    private function buildDownloadName(string $original): string
    {
        $base = pathinfo($original, \PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $base) ?: 'image';
        $base = trim($base, '._-') ?: 'image';
        $suffix = 'converted';

        return $base.'_'.$suffix.'.webp';
    }

    private function getUploadsDir(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/image-convert/uploads';
    }

    private function getResultsDir(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/image-convert/results';
    }
}
