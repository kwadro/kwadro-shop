<?php

namespace App\Controller\Admin;

use App\Service\Marketing\MarketingAssetLibrary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractMarketingLibraryController extends AbstractController
{
    public function __construct(
        protected readonly TranslatorInterface $translator,
        protected readonly MarketingAssetLibrary $library,
    ) {
    }

    abstract protected function getKind(): string;

    abstract protected function getRoutePrefix(): string;

    abstract protected function getTranslationPrefix(): string;

    abstract protected function getTemplate(): string;

    protected function renderIndex(Request $request): Response
    {
        $locale = (string) $request->attributes->get('_locale', 'uk');
        $prefix = $this->getRoutePrefix();

        return $this->render($this->getTemplate(), [
            'page_title' => $this->translator->trans($this->getTranslationPrefix().'.title', [], 'messages'),
            'kind' => $this->getKind(),
            'items' => $this->library->list($this->getKind()),
            'upload_url' => $this->generateUrl($prefix.'_upload', ['_locale' => $locale]),
            'rename_url_template' => str_replace(
                'media-placeholder.jpg',
                '__FILENAME__',
                $this->generateUrl($prefix.'_rename', ['_locale' => $locale, 'filename' => 'media-placeholder.jpg']),
            ),
            'delete_url_template' => str_replace(
                'media-placeholder.jpg',
                '__FILENAME__',
                $this->generateUrl($prefix.'_delete', ['_locale' => $locale, 'filename' => 'media-placeholder.jpg']),
            ),
            'list_url' => $this->generateUrl($prefix.'_list', ['_locale' => $locale]),
            'i18n_prefix' => $this->getTranslationPrefix(),
        ]);
    }

    protected function listJson(): JsonResponse
    {
        return new JsonResponse([
            'ok' => true,
            'items' => $this->library->list($this->getKind()),
        ]);
    }

    protected function uploadJson(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return new JsonResponse(['ok' => false, 'error' => 'No file'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $item = $this->library->addFromUpload(
                $this->getKind(),
                $file,
                trim((string) $request->request->get('name', '')) ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Upload failed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'ok' => true,
            'item' => $item,
            'items' => $this->library->list($this->getKind()),
            'message' => $this->translator->trans($this->getTranslationPrefix().'.uploaded', [], 'messages'),
        ]);
    }

    protected function renameJson(Request $request, string $filename): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $name = '';
        if (\is_array($payload) && isset($payload['name']) && \is_string($payload['name'])) {
            $name = trim($payload['name']);
        } else {
            $name = trim((string) $request->request->get('name', ''));
        }

        if ($name === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Empty name'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->library->rename($this->getKind(), $filename, $name)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'ok' => true,
            'items' => $this->library->list($this->getKind()),
            'message' => $this->translator->trans($this->getTranslationPrefix().'.renamed', [], 'messages'),
        ]);
    }

    protected function deleteJson(string $filename): JsonResponse
    {
        if (!$this->library->delete($this->getKind(), $filename)) {
            return new JsonResponse(['ok' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'ok' => true,
            'items' => $this->library->list($this->getKind()),
            'message' => $this->translator->trans($this->getTranslationPrefix().'.deleted', [], 'messages'),
        ]);
    }
}
