<?php

namespace App\Service\ProductImport;

use App\Entity\Category;
use App\Entity\ProductImport;
use App\Entity\ProductImportMode;
use App\Entity\ProductImportRun;
use App\Entity\ProductImportRunStatus;
use App\Entity\Supplier;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SupplierRepository;
use App\Service\Product\ProductImagePath;
use Doctrine\ORM\EntityManagerInterface;
use SimpleXMLElement;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class LugiProductImportService
{
    /** Smaller batches keep attribute values + EM identity map under control. */
    const BASH_SIZE = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        private readonly SupplierRepository $supplierRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly CategoryService $categoryService,
        private readonly ProductService $productService,
        private readonly AttributeImportService $attributeImportService,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly ?DebugDataHolder $debugDataHolder = null,
    ) {
    }

    public function run(ProductImport $import, bool $dryRun = false, bool $loadImages = false): ProductImportRun
    {
        @ini_set('memory_limit', '512M');

        $run = (new ProductImportRun())
            ->setProductImport($import)
            ->setStatus(ProductImportRunStatus::Running)
            ->setStartedAt(new \DateTimeImmutable());

        if (!$dryRun) {
            $this->em->persist($run);
            $this->em->flush();
        }
        try {
            $feedUrl = $import->getFilePath();
            $localPath = $this->downloadFeed($feedUrl);

            $reader = new \XMLReader();
            $reader->open($localPath);

            $baseExternalId = $import->getDefaultCategoryExternalId();
            if ($baseExternalId === null) {
                throw new \RuntimeException('Base category (external_id) is not set in admin.');
            }

            $baseCategory = $this->categoryRepository->findOneBy(['external_id' => $baseExternalId]);
            if ($baseCategory === null) {
                throw new \RuntimeException(
                    sprintf(
                        'Base category with external_id=%d not found.',
                        $baseExternalId,
                    )
                );
            }
            $baseCategoryId = (int) $baseCategory->getId();
            $supplier = $this->resolveSupplier();
            $supplierId = (int) $supplier->getId();
            $importId = (int) $import->getId();
            $runId = $run->getId();
            $lastRunDate = $import->getLastRunAt();

            // load file date
            $catalogDate = null;
            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'yml_catalog') {
                    $catalogDate = $reader->getAttribute('date');
                    break;
                }
            }
            $reader->close();

            if ($catalogDate === $lastRunDate) {
                throw new \RuntimeException('Import this price  is  run  as another process.');
            }

            if (3===4 && $loadImages && !$dryRun) {
                $this->downloadOfferImages($localPath);
            }
           

            // load category map
            $reader = new \XMLReader();
            $reader->open($localPath);
            $position = 1;
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'category') {
                    continue;
                }
                $node = simplexml_load_string($reader->readOuterXml());
                if ($node === false) {
                    continue;
                }
                $this->categoryService->update(
                    $node,
                    $dryRun,
                    $baseCategoryId,
                    true,
                    $position,
                );
                ++$position;
            }

            // Build attribute groups from distinct <param> sets per feed category.
            $attributeStats = $this->attributeImportService->syncGroupsFromFeed($localPath, $dryRun);
            if (!$dryRun) {
                // Groups/attrs flushed — re-attach category/supplier for product loop.
                [$baseCategory, $supplier] = $this->reloadManagedRefs($baseCategoryId, $supplierId);
            }

            // load product map
            $reader = new \XMLReader();
            $reader->open($localPath);
            $categoryPositions = [];
            $seenSkus = [];
            $result = [
                'created' => 0,
                'updated' => 0,
                'disabled' => 0,
            ];
            $disabledSkuMap = array_fill_keys(array_map('mb_strtolower', $import->getDisabledSkus()), true);
            $count = 0;
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'offer') {
                    continue;
                }
                $node = simplexml_load_string($reader->readOuterXml());
                if ($node === false) {
                    continue;
                }
                $productData = $this->productService->loadProductDataByNode($node);
                if (!$productData) {
                    continue;
                }
                $categoryId = $productData['category_id'];
                if (!isset($categoryPositions[$categoryId])) {
                    $categoryPositions[$categoryId] = 0;
                }
                ++$categoryPositions[$categoryId];
                $productData['position'] = $categoryPositions[$categoryId];
                $productData['status'] = true;
                $sku = mb_strtolower($productData['sku']);
                $productData['force_disabled'] = isset($disabledSkuMap[$sku]);
                $seenSkus[$sku] = true;
                $result = $this->productService->update(
                    $this->em,
                    $productData,
                    $supplier,
                    $baseCategory,
                    $result,
                    $dryRun
                );
                ++$count;
                if (!$dryRun && $count >= self::BASH_SIZE) {
                    $this->em->flush();
                    $this->em->clear();
                    $this->releaseImportMemory();
                    $count = 0;
                    // clear() detaches category/supplier/attrs — re-load managed instances for the next batch.
                    [$baseCategory, $supplier] = $this->reloadManagedRefs($baseCategoryId, $supplierId);
                    $this->attributeImportService->rebuildAttributeIndex();
                }
            }
            $deleted = 0;
            if ($import->getMode() === ProductImportMode::DeleteAddUpdate) {
                // Supplier may be detached after the last batch clear.
                if (!$this->em->contains($supplier)) {
                    $supplier = $this->supplierRepository->find($supplierId)
                        ?? throw new \RuntimeException('Supplier lost after EntityManager clear.');
                }
                $deleted = $this->productService->disableMissingProducts($supplier, $seenSkus, $dryRun);
            }

            $summary = sprintf(
                'Created: %d, updated: %d, disabled: %d, deleted/deactivated: %d, attr groups: %d, attributes: %d (mode: %s)',
                $result['created'],
                $result['updated'],
                $result['disabled'],
                $deleted,
                $attributeStats['groups'],
                $attributeStats['attributes'],
                $import->getMode()->label(),
            );

            [$import, $run] = $this->reloadImportRun($import, $run, $importId, $runId);

            $run
                ->setCreatedCount($result['created'])
                ->setUpdatedCount($result['updated'])
                ->setDisabledCount($result['disabled'])
                ->setDeletedCount($deleted)
                ->setResult($summary)
                ->setStatus(ProductImportRunStatus::Success)
                ->setFinishedAt(new \DateTimeImmutable());

            $import
                ->setLastRunAt($run->getFinishedAt())
                ->setLastResult($summary);

            if (!$dryRun && $this->em->isOpen()) {
                $this->em->flush();
            }

        } catch (Throwable $e) {
            if ($this->em->isOpen()) {
                [$import, $run] = $this->reloadImportRun(
                    $import,
                    $run,
                    isset($importId) ? $importId : (int) ($import->getId() ?? 0),
                    isset($runId) ? $runId : $run->getId(),
                );
            }

            $run
                ->setStatus(ProductImportRunStatus::Failed)
                ->setErrorMessage($e->getMessage())
                ->setResult('Failed: ' . $e->getMessage())
                ->setFinishedAt(new \DateTimeImmutable());

            $import
                ->setLastRunAt($run->getFinishedAt())
                ->setLastResult($run->getResult());

            // After a DB exception Doctrine closes the EM — only flush if still open.
            if (!$dryRun && $this->em->isOpen()) {
                $this->em->flush();
            }
        }

        return $run;
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function downloadFeed(string $feedUrl): string
    {
        $feedUrl = trim($feedUrl);
        if ($feedUrl === '' || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException(sprintf('Invalid feed URL: "%s"', $feedUrl));
        }
        $dir = $this->projectDir . '/var/import';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create import directory: %s', $dir));
        }
        $path = sprintf('%s/lugi-%s.yml', $dir, (new \DateTimeImmutable())->format('Ymd-His'));
        $response = $this->httpClient->request('GET', $feedUrl, [
            'timeout' => 120,
            'max_duration' => 300,
            'headers' => [
                'Accept' => 'application/xml, text/xml, */*',
                'User-Agent' => 'KvadroProductImport/1.0',
            ],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf(
                    'Feed download failed with HTTP %d: %s',
                    $response->getStatusCode(),
                    $feedUrl,
                )
            );
        }
        // Stream to disk — do not load the whole body into a PHP string.
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot write feed file: %s', $path));
        }
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }
        if (!is_file($path) || filesize($path) === 0) {
            @unlink($path);
            throw new \RuntimeException('Downloaded feed is empty.');
        }
        return $path;
    }

    /**
     * Download every <picture> for each offer into:
     * public/uploads/products/lugi/{first}/{second}/{vendorCode}.jpg
     * public/uploads/products/lugi/{first}/{second}/{vendorCode}_1.jpg
     * …
     * Example: HP-42-91 → …/lugi/H/P/HP-42-91.jpg
     */
    private function downloadOfferImages(string $localPath): void
    {
        $reader = new \XMLReader();
        if (!$reader->open($localPath)) {
            throw new \RuntimeException(sprintf('Cannot open feed for images: %s', $localPath));
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'offer') {
                    continue;
                }

                $node = simplexml_load_string($reader->readOuterXml());
                if ($node === false) {
                    continue;
                }

                $this->downloadOfferPictures($node);
            }
        } finally {
            $reader->close();
        }
    }

    private function downloadOfferPictures(SimpleXMLElement $node): void
    {
        $vendorCode = $this->sanitizeImageBasename((string) ($node->vendorCode ?: $node['id'] ?? ''));
        if ($vendorCode === '') {
            return;
        }

        $pictureUrls = [];
        if (isset($node->picture)) {
            foreach ($node->picture as $picture) {
                $url = trim(html_entity_decode((string) $picture, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $pictureUrls[] = $url;
            }
        }

        foreach ($pictureUrls as $index => $url) {
            $extension = $this->guessImageExtension($url);
            $basename = $index === 0
                ? $vendorCode
                : sprintf('%s_%d', $vendorCode, $index);
            $filename = sprintf('%s.%s', $basename, $extension);
            $targetPath = ProductImagePath::absolutePath($this->projectDir, $filename, 'lugi');
            if ($targetPath === '') {
                continue;
            }

            // Skip when this picture (or a legacy/alternate copy) is already on disk.
            if ($this->isOfferImageAlreadyDownloaded($basename, $targetPath)) {
                continue;
            }

            $targetDir = \dirname($targetPath);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                continue;
            }

            try {
                $this->downloadBinaryToFile($url, $targetPath);
            } catch (Throwable) {
                // Keep downloading remaining pictures for this and other offers.
                continue;
            }
        }
    }

    /**
     * True when the sharded target (or an existing copy we can reuse) is already present.
     */
    private function isOfferImageAlreadyDownloaded(string $basename, string $targetPath): bool
    {
        if ($this->isNonEmptyFile($targetPath)) {
            return true;
        }

        $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $publicRoot = rtrim($this->projectDir, '/').'/public'.ProductImagePath::WEB_BASE;
        $supplier = 'lugi';

        foreach ($extensions as $ext) {
            $filename = sprintf('%s.%s', $basename, $ext);
            $candidates = [
                ProductImagePath::absolutePath($this->projectDir, $filename, $supplier),
                // Legacy flat layout: …/uploads/products/lugi/{file}
                $publicRoot.'/'.$supplier.'/'.$filename,
                // Legacy sharded without supplier: …/uploads/products/{a}/{b}/{file}
                $publicRoot.'/'.ProductImagePath::relativePath($filename, null),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate === '' || $candidate === $targetPath || !$this->isNonEmptyFile($candidate)) {
                    continue;
                }

                $targetDir = \dirname($targetPath);
                if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                    return true;
                }

                if (@rename($candidate, $targetPath) || @copy($candidate, $targetPath)) {
                    if ($candidate !== $targetPath && is_file($candidate)) {
                        @unlink($candidate);
                    }

                    return $this->isNonEmptyFile($targetPath);
                }

                // File exists somewhere usable — do not re-download.
                return true;
            }
        }

        return false;
    }

    private function isNonEmptyFile(string $path): bool
    {
        return is_file($path) && filesize($path) > 0;
    }

    private function downloadBinaryToFile(string $url, string $targetPath): void
    {
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 60,
            'max_duration' => 120,
            'headers' => [
                'Accept' => 'image/*,*/*',
                'User-Agent' => 'KvadroProductImport/1.0',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'Image download failed with HTTP %d: %s',
                $response->getStatusCode(),
                $url,
            ));
        }

        $handle = fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot write image file: %s', $targetPath));
        }

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }
                fwrite($handle, $chunk->getContent());
            }
        } catch (Throwable $e) {
            fclose($handle);
            @unlink($targetPath);
            throw $e;
        }
        fclose($handle);

        if (!is_file($targetPath) || filesize($targetPath) === 0) {
            @unlink($targetPath);
            throw new \RuntimeException(sprintf('Downloaded image is empty: %s', $url));
        }
    }

    private function sanitizeImageBasename(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['\\', '/'], '-', $value);
        $value = preg_replace('/[^\w.\-]+/u', '-', $value) ?? '';
        $value = trim($value, '.-_');

        return $value;
    }

    private function guessImageExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $ext = is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
        if (\in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        return 'jpg';
    }

    private function resolveSupplier(): Supplier
    {
        $supplier = $this->supplierRepository->findOneBy(['slug' => 'lugi'])
            ?? $this->supplierRepository->findOneBy(['name' => 'Lugi']);

        if ($supplier !== null) {
            return $supplier;
        }

        $supplier = (new Supplier())
            ->setName('Lugi')
            ->setSlug('lugi')
            ->setDescription('Lugi побутові товари для всіх');
        $this->em->persist($supplier);
        $this->em->flush();

        return $supplier;
    }

    private function releaseImportMemory(): void
    {
        $this->debugDataHolder?->reset();
        gc_collect_cycles();
    }

    /**
     * @return array{0: Category, 1: Supplier}
     */
    private function reloadManagedRefs(int $baseCategoryId, int $supplierId): array
    {
        $baseCategory = $this->categoryRepository->find($baseCategoryId);
        $supplier = $this->supplierRepository->find($supplierId);
        if ($baseCategory === null || $supplier === null) {
            throw new \RuntimeException('Base category or supplier lost after EntityManager clear.');
        }

        return [$baseCategory, $supplier];
    }

    /**
     * @return array{0: ProductImport, 1: ProductImportRun}
     */
    private function reloadImportRun(
        ProductImport $import,
        ProductImportRun $run,
        int $importId,
        ?int $runId,
    ): array {
        if ($importId > 0 && !$this->em->contains($import)) {
            $reloadedImport = $this->em->find(ProductImport::class, $importId);
            if ($reloadedImport instanceof ProductImport) {
                $import = $reloadedImport;
            }
        }

        if ($runId !== null && $runId > 0 && !$this->em->contains($run)) {
            $reloadedRun = $this->em->find(ProductImportRun::class, $runId);
            if ($reloadedRun instanceof ProductImportRun) {
                $run = $reloadedRun;
            }
        }

        return [$import, $run];
    }
}
