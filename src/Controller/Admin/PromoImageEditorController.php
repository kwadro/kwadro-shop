<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/{_locale}/promo-image-editor', name: 'admin_promo_image_editor', requirements: ['_locale' => 'uk|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class PromoImageEditorController extends AbstractController
{
    private const CANVAS_SIZE = 526;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $defaults = $this->getDefaultContent();

        return $this->render('admin/promo_image_editor/index.html.twig', [
            'page_title' => $this->translator->trans('admin.promo_image_editor.title', [], 'messages'),
            'canvas_size' => self::CANVAS_SIZE,
            'defaults' => $defaults,
            'saved_images' => $this->listSavedImages(),
            'save_url' => $this->generateUrl('admin_promo_image_editor_save', ['_locale' => 'uk']),
            'upload_url' => $this->generateUrl('admin_promo_image_editor_upload', ['_locale' => 'uk']),
            'load_url_template' => str_replace(
                'promo-placeholder.jpg',
                '__FILENAME__',
                $this->generateUrl('admin_promo_image_editor_load', ['_locale' => 'uk', 'filename' => 'promo-placeholder.jpg']),
            ),
            'delete_url_template' => str_replace(
                'promo-placeholder.jpg',
                '__FILENAME__',
                $this->generateUrl('admin_promo_image_editor_delete', ['_locale' => 'uk', 'filename' => 'promo-placeholder.jpg']),
            ),
        ]);
    }

    #[Route('/load/{filename}', name: '_load', methods: ['GET'], requirements: ['filename' => 'promo-[A-Za-z0-9._-]+\.(jpg|jpeg|png)'])]
    public function load(string $filename): JsonResponse
    {
        $path = $this->getSavedDir().'/'.$filename;
        $metaPath = $path.'.json';
        if (!is_file($path)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $content = null;
        if (is_file($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true);
            if (\is_array($meta) && isset($meta['content']) && \is_array($meta['content'])) {
                $content = $meta['content'];
            }
        }

        return new JsonResponse([
            'ok' => true,
            'filename' => $filename,
            'url' => '/uploads/promo/saved/'.$filename,
            'content' => $this->mergeContent($content),
        ]);
    }

    #[Route('/delete/{filename}', name: '_delete', methods: ['POST', 'DELETE'], requirements: ['filename' => 'promo-[A-Za-z0-9._-]+\.(jpg|jpeg|png)'])]
    public function delete(string $filename): JsonResponse
    {
        $dir = realpath($this->getSavedDir());
        if ($dir === false) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $path = $dir.'/'.$filename;
        $metaPath = $path.'.json';
        $realPath = realpath($path);
        if ($realPath === false || !str_starts_with($realPath, $dir.DIRECTORY_SEPARATOR)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        @unlink($realPath);
        if (is_file($metaPath)) {
            @unlink($metaPath);
        }

        return new JsonResponse([
            'ok' => true,
            'filename' => $filename,
            'message' => $this->translator->trans('admin.promo_image_editor.deleted', [], 'messages'),
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
            return new JsonResponse(['ok' => false, 'error' => 'Cannot create directory'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $overwrite = isset($payload['overwrite']) && \is_string($payload['overwrite'])
            ? basename($payload['overwrite'])
            : '';
        $canOverwrite = $overwrite !== ''
            && (bool) preg_match('/^promo-[A-Za-z0-9._-]+\.(jpg|jpeg|png)$/', $overwrite)
            && is_file($dir.'/'.$overwrite);

        $filename = $canOverwrite ? $overwrite : sprintf('promo-%s.%s', date('Ymd-His'), $ext);
        $path = $dir.'/'.$filename;
        if (file_put_contents($path, $binary) === false) {
            return new JsonResponse(['ok' => false, 'error' => 'Write failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $meta = [
            'filename' => $filename,
            'savedAt' => date('c'),
            'content' => $this->mergeContent(\is_array($payload['content'] ?? null) ? $payload['content'] : null),
        ];
        file_put_contents($dir.'/'.$filename.'.json', json_encode($meta, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT));

        return new JsonResponse([
            'ok' => true,
            'url' => '/uploads/promo/saved/'.$filename,
            'filename' => $filename,
            'overwritten' => $canOverwrite,
            'message' => $this->translator->trans(
                $canOverwrite ? 'admin.promo_image_editor.updated' : 'admin.promo_image_editor.saved',
                [],
                'messages',
            ),
        ]);
    }

    #[Route('/upload', name: '_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $kind = (string) $request->request->get('kind', 'bg');
        if ($file === null || !$file->isValid()) {
            return new JsonResponse(['ok' => false, 'error' => 'No file'], Response::HTTP_BAD_REQUEST);
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid mime'], Response::HTTP_BAD_REQUEST);
        }

        $dir = $this->getProjectDir().'/public/uploads/promo/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return new JsonResponse(['ok' => false, 'error' => 'Cannot create directory'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $filename = sprintf('%s-%s.%s', $kind === 'product' ? 'product' : 'bg', date('Ymd-His'), $ext);
        $file->move($dir, $filename);
        $absolute = $dir.'/'.$filename;

        if ($kind === 'product') {
            $transparent = $this->stripWhiteBackground($absolute);
            if ($transparent !== null) {
                $filename = $transparent;
                $absolute = $dir.'/'.$filename;
            }
        }

        return new JsonResponse([
            'ok' => true,
            'url' => '/uploads/promo/uploads/'.$filename,
        ]);
    }

    /**
     * Convert near-white studio backdrop to transparency and save as PNG.
     */
    private function stripWhiteBackground(string $absolutePath): ?string
    {
        if (!\function_exists('imagecreatetruecolor') || !is_file($absolutePath)) {
            return null;
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            return null;
        }

        $src = match ($info[2]) {
            \IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            \IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            \IMAGETYPE_WEBP => \function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            default => false,
        };
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
        imagealphablending($dst, true);

        $limit = 235;
        for ($y = 0; $y < $h; ++$y) {
            for ($x = 0; $x < $w; ++$x) {
                $rgba = imagecolorat($src, $x, $y);
                $a = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $chroma = $max - $min;

                if ($min >= $limit && $chroma <= 18) {
                    continue;
                }

                $alpha = $a;
                if ($min >= $limit - 20 && $chroma <= 12) {
                    $alpha = max($alpha, (int) round((1 - (($limit - $min) / 20)) * 127));
                }

                $color = imagecolorallocatealpha($dst, $r, $g, $b, $alpha);
                imagesetpixel($dst, $x, $y, $color);
            }
        }

        imagedestroy($src);

        $filename = sprintf('product-%s.png', date('Ymd-His'));
        $out = \dirname($absolutePath).'/'.$filename;
        imagepng($dst, $out, 6);
        imagedestroy($dst);

        if (is_file($absolutePath) && realpath($absolutePath) !== realpath($out)) {
            @unlink($absolutePath);
        }

        return is_file($out) ? $filename : null;
    }

    /** @return array<string, mixed> */
    private function getDefaultContent(): array
    {
        return [
            'canvasSize' => self::CANVAS_SIZE,
            'backgroundUrl' => '/uploads/promo/default-bg.jpg',
            'productUrl' => '/uploads/promo/products/111142.png',
            'brandName' => 'KVADRO',
            'brandTagline' => 'МАГАЗИН ТОВАРІВ ДЛЯ ДОМУ',
            'title' => 'ТВ антена DVB-T2',
            'model' => 'Delta 3000',
            'slogan' => 'Більше улюблених каналів!',
            'features' => [
                'Вбудований підсилювач',
                'Прийом DVB-T / DVB-T2',
                'Просте встановлення',
                'Компактний розмір',
                'Кабель 1,5 м',
            ],
            'discount' => '-17%',
            'price' => '250 ₴',
            'oldPrice' => '300 ₴',
            'stockText' => 'В наявності',
            'footerLeft' => 'Цифрове телебачення у вашому домі!',
            'footerRight' => 'Замовляйте на сайті kvadro.if.ua',
            'showDiscount' => true,
            'showOldPrice' => true,
            'showStock' => true,
            'featuresOffsetY' => 0,
        ];
    }

    /** @return list<array{filename: string, url: string, savedAt: string|null, hasContent: bool}> */
    private function listSavedImages(): array
    {
        $dir = $this->getSavedDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/promo-*.{jpg,jpeg,png}', \GLOB_BRACE) ?: [];
        rsort($files, \SORT_STRING);

        $items = [];
        foreach (\array_slice($files, 0, 24) as $path) {
            $filename = basename($path);
            $metaPath = $path.'.json';
            $savedAt = null;
            $hasContent = false;
            if (is_file($metaPath)) {
                $meta = json_decode((string) file_get_contents($metaPath), true);
                if (\is_array($meta)) {
                    $savedAt = isset($meta['savedAt']) && \is_string($meta['savedAt']) ? $meta['savedAt'] : null;
                    $hasContent = isset($meta['content']) && \is_array($meta['content']) && $meta['content'] !== [];
                }
            }
            $items[] = [
                'filename' => $filename,
                'url' => '/uploads/promo/saved/'.$filename,
                'savedAt' => $savedAt,
                'hasContent' => $hasContent,
            ];
        }

        return $items;
    }

    /** @param array<string, mixed>|null $content */
    private function mergeContent(?array $content): array
    {
        $merged = $this->getDefaultContent();
        if ($content === null) {
            return $merged;
        }

        foreach ($merged as $key => $defaultValue) {
            if (!\array_key_exists($key, $content)) {
                continue;
            }
            $value = $content[$key];
            if ($key === 'features' && \is_array($value)) {
                $merged[$key] = array_values(array_map(
                    static fn ($item): string => \is_scalar($item) ? trim((string) $item) : '',
                    $value,
                ));
                continue;
            }
            if (\is_bool($defaultValue) && \is_bool($value)) {
                $merged[$key] = $value;
                continue;
            }
            if (\is_int($defaultValue) && (\is_int($value) || (\is_string($value) && is_numeric($value)))) {
                $merged[$key] = max(0, min(120, (int) $value));
                continue;
            }
            if (\is_string($defaultValue) && (\is_string($value) || \is_numeric($value))) {
                $merged[$key] = (string) $value;
            }
        }

        return $merged;
    }

    private function getSavedDir(): string
    {
        return $this->getProjectDir().'/public/uploads/promo/saved';
    }

    private function getProjectDir(): string
    {
        return $this->getParameter('kernel.project_dir');
    }
}
