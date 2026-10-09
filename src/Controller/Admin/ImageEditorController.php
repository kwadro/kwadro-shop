<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/image-editor', name: 'admin_image_editor', requirements: ['_locale' => 'uk|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ImageEditorController extends AbstractController
{
    /** @var array<string, array{w: int, h: int, label: string}> */
    public const ASPECTS = [
        '9:16' => ['w' => 1080, 'h' => 1920, 'label' => '9:16'],
        '16:9' => ['w' => 1920, 'h' => 1080, 'label' => '16:9'],
        '4:3' => ['w' => 1600, 'h' => 1200, 'label' => '4:3'],
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = (string) $request->attributes->get('_locale', 'uk');

        return $this->render('admin/image_editor/index.html.twig', [
            'page_title' => $this->translator->trans('admin.image_editor.title', [], 'messages'),
            'aspects' => self::ASPECTS,
            'defaults' => $this->getDefaults(),
            'saved_images' => $this->listSavedImages(),
            'save_url' => $this->generateUrl('admin_image_editor_save', ['_locale' => $locale]),
            'upload_url' => $this->generateUrl('admin_image_editor_upload', ['_locale' => $locale]),
            'load_url_template' => str_replace(
                'img-placeholder.jpg',
                '__FILENAME__',
                $this->generateUrl('admin_image_editor_load', ['_locale' => $locale, 'filename' => 'img-placeholder.jpg']),
            ),
            'delete_url_template' => str_replace(
                'img-placeholder.jpg',
                '__FILENAME__',
                $this->generateUrl('admin_image_editor_delete', ['_locale' => $locale, 'filename' => 'img-placeholder.jpg']),
            ),
        ]);
    }

    #[Route('/upload', name: '_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return new JsonResponse(['ok' => false, 'error' => 'No file'], Response::HTTP_BAD_REQUEST);
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Unsupported type'], Response::HTTP_BAD_REQUEST);
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $dir = $this->getUploadsDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return new JsonResponse(['ok' => false, 'error' => 'Cannot create upload dir'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $kind = trim((string) $request->request->get('kind', 'bg'));
        $prefix = $kind === 'overlay' ? 'overlay' : 'bg';
        $filename = $prefix.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.'.$ext;
        $file->move($dir, $filename);

        return new JsonResponse([
            'ok' => true,
            'filename' => $filename,
            'url' => '/uploads/image-editor/uploads/'.$filename,
            'kind' => $prefix,
        ]);
    }

    #[Route('/save', name: '_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !isset($payload['image']) || !\is_string($payload['image'])) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('#^data:image/(png|jpeg|jpg);base64,#i', $payload['image'], $m)) {
            return new JsonResponse(['ok' => false, 'error' => 'Unsupported image format'], Response::HTTP_BAD_REQUEST);
        }

        $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $payload['image']) ?? '', true);
        if ($binary === false || $binary === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Decode failed'], Response::HTTP_BAD_REQUEST);
        }

        $ext = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
        $dir = $this->getSavedDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return new JsonResponse(['ok' => false, 'error' => 'Cannot create save dir'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $requested = isset($payload['filename']) && \is_string($payload['filename']) ? trim($payload['filename']) : '';
        if ($requested !== '' && preg_match('#^img-[A-Za-z0-9._-]+\.(jpg|jpeg|png)$#', $requested) === 1) {
            $filename = preg_replace('#\.(jpg|jpeg|png)$#i', '.'.$ext, $requested) ?? ('img-'.date('Ymd-His').'.'.$ext);
        } else {
            $filename = 'img-'.date('Ymd-His').'.'.$ext;
        }

        $path = $dir.'/'.$filename;
        if (file_put_contents($path, $binary) === false) {
            return new JsonResponse(['ok' => false, 'error' => 'Write failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $content = isset($payload['content']) && \is_array($payload['content']) ? $payload['content'] : null;
        $meta = [
            'filename' => $filename,
            'savedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'content' => $content,
        ];
        file_put_contents($path.'.json', json_encode($meta, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT));

        return new JsonResponse([
            'ok' => true,
            'filename' => $filename,
            'url' => '/uploads/image-editor/saved/'.$filename,
            'message' => $this->translator->trans('admin.image_editor.saved', [], 'messages'),
            'saved_images' => $this->listSavedImages(),
        ]);
    }

    #[Route('/load/{filename}', name: '_load', methods: ['GET'], requirements: ['filename' => 'img-[A-Za-z0-9._-]+\.(jpg|jpeg|png)'])]
    public function load(string $filename): JsonResponse
    {
        $path = $this->getSavedDir().'/'.$filename;
        if (!is_file($path)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $content = null;
        $metaPath = $path.'.json';
        if (is_file($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true);
            if (\is_array($meta) && isset($meta['content']) && \is_array($meta['content'])) {
                $content = $meta['content'];
            }
        }

        return new JsonResponse([
            'ok' => true,
            'filename' => $filename,
            'url' => '/uploads/image-editor/saved/'.$filename,
            'content' => $content,
        ]);
    }

    #[Route('/delete/{filename}', name: '_delete', methods: ['POST', 'DELETE'], requirements: ['filename' => 'img-[A-Za-z0-9._-]+\.(jpg|jpeg|png)'])]
    public function delete(string $filename): JsonResponse
    {
        $dir = realpath($this->getSavedDir());
        if ($dir === false) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $path = $dir.'/'.$filename;
        $realPath = realpath($path);
        if ($realPath === false || !str_starts_with($realPath, $dir.DIRECTORY_SEPARATOR)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        @unlink($realPath);
        $metaPath = $realPath.'.json';
        if (is_file($metaPath)) {
            @unlink($metaPath);
        }

        return new JsonResponse([
            'ok' => true,
            'filename' => $filename,
            'message' => $this->translator->trans('admin.image_editor.deleted', [], 'messages'),
            'saved_images' => $this->listSavedImages(),
        ]);
    }

    /** @return array<string, mixed> */
    private function getDefaults(): array
    {
        return [
            'aspect' => '9:16',
            'width' => self::ASPECTS['9:16']['w'],
            'height' => self::ASPECTS['9:16']['h'],
            'bgType' => 'color',
            'bgColor' => '#1e293b',
            'bgImageUrl' => '',
            'crop' => null,
            'texts' => [],
            'overlays' => [],
            'lines' => [],
            'frames' => [],
            'selectedTextId' => null,
            'selectedOverlayId' => null,
            'selectedLineId' => null,
            'selectedFrameId' => null,
            'fontWeight' => '700',
        ];
    }

    /** @return list<array{filename: string, url: string, savedAt: string|null}> */
    private function listSavedImages(): array
    {
        $dir = $this->getSavedDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/img-*.{jpg,jpeg,png}', \GLOB_BRACE) ?: [];
        rsort($files, \SORT_STRING);
        $items = [];
        foreach (\array_slice($files, 0, 36) as $path) {
            $filename = basename($path);
            $savedAt = null;
            $metaPath = $path.'.json';
            if (is_file($metaPath)) {
                $meta = json_decode((string) file_get_contents($metaPath), true);
                if (\is_array($meta) && isset($meta['savedAt']) && \is_string($meta['savedAt'])) {
                    $savedAt = $meta['savedAt'];
                }
            }
            $items[] = [
                'filename' => $filename,
                'url' => '/uploads/image-editor/saved/'.$filename,
                'savedAt' => $savedAt,
            ];
        }

        return $items;
    }

    private function getSavedDir(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/image-editor/saved';
    }

    private function getUploadsDir(): string
    {
        return $this->getParameter('kernel.project_dir').'/public/uploads/image-editor/uploads';
    }
}
