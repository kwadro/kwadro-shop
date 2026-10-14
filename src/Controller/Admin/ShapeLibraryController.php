<?php

namespace App\Controller\Admin;

use App\Service\Marketing\MarketingAssetLibrary;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/{_locale}/shape-library', name: 'admin_shape_library', requirements: ['_locale' => 'uk|en'])]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ShapeLibraryController extends AbstractMarketingLibraryController
{
    protected function getKind(): string
    {
        return MarketingAssetLibrary::KIND_SHAPE;
    }

    protected function getRoutePrefix(): string
    {
        return 'admin_shape_library';
    }

    protected function getTranslationPrefix(): string
    {
        return 'admin.shape_library';
    }

    protected function getTemplate(): string
    {
        return 'admin/marketing_library/index.html.twig';
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderIndex($request);
    }

    #[Route('/list', name: '_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->listJson();
    }

    #[Route('/upload', name: '_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        return $this->uploadJson($request);
    }

    #[Route('/rename/{filename}', name: '_rename', methods: ['POST'], requirements: ['filename' => '.+'])]
    public function rename(Request $request, string $filename): JsonResponse
    {
        return $this->renameJson($request, $filename);
    }

    #[Route('/delete/{filename}', name: '_delete', methods: ['POST', 'DELETE'], requirements: ['filename' => '.+'])]
    public function delete(string $filename): JsonResponse
    {
        return $this->deleteJson($filename);
    }
}
