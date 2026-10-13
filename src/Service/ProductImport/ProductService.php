<?php

namespace App\Service\ProductImport;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductOffer;
use App\Entity\Supplier;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use SimpleXMLElement;

readonly class ProductService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private GeneratorSlugService $generatorSlugService,
        private AttributeImportService $attributeImportService,
    ) {
    }

    public function loadProductDataByNode(
        SimpleXMLElement $node
    ): ?array {
        $externalId = (int)$node['id'];
        $available = (bool)$node['available'];
        if (!$available) {
            return null;
        }

        $params = [];
        if (isset($node->param)) {
            foreach ($node->param as $param) {
                $name = trim((string) $param['name']);
                $value = trim((string) $param);
                if ($name === '' || $value === '') {
                    continue;
                }
                $params[] = [
                    'name' => $name,
                    'value' => $value,
                ];
            }
        }

        return [
            'id' => $externalId,
            'available' => true,
            'name' => (string)$node->name,
            'category_id' => (int)$node->categoryId,
            'price' => (int)$node->price,
            'description' => (string)$node->description,
            'brand' => (string)$node->vendor,
            'stock_qty' => (int)$node->quantity_in_stock,
            'sku' => 'homeware-' . $node->vendorCode,
            'params' => $params,
            'weight' => WeightParser::fromParams($params),
        ];
    }


    public function update(
        $em,
        $productData,
        $supplier,
        Category $baseCategory,
        array $result,
        bool $dryRun = false
    ): array {
        if (!$this->em->isOpen()) {
            throw new \RuntimeException(
                'EntityManager is closed (previous product flush failed, often a duplicate slug).'
            );
        }
        $sku = trim($productData['sku']);
        try {
            $existing = $this->productRepository->findOneBy(['sku' => $sku]);

            if ($existing === null) {
                $product = $this->createProduct($productData, $supplier, $baseCategory);
                if (!$dryRun) {
                    $em->persist($product);
                }
                $result['created']++;
                return $result;
            }
            $this->syncExistingProduct($existing, $supplier, $productData);
            if (!$dryRun) {
                $em->persist($existing);
            }
            if ($productData['force_disabled']) {
                $result['disabled']++;
            }
            $result['updated']++;
        } catch (Exception $e) {
            echo $sku . ' error: ' . $e->getMessage();
            exit;
        }

        return $result;
    }

    private function createProduct($productData, $supplier, $baseCategory): Product
    {
        $inStock = $productData['stock_qty'] > 0;
        $category = $this->categoryRepository->findOneBy(['external_id' => $productData['category_id']]);
        $baseSlug = $this->generatorSlugService->transferSkuAndIdToSlug($productData['sku'],$productData['id']);

        $product = (new Product())
            ->setModel($productData['model'] ?? null)
            ->setBrand($productData['brand'] !== '' ? $productData['brand'] : null)
            ->setColor($productData['color'] ?? null)
            ->setType($productData['type'] ?? null)
            ->setSku($productData['sku'])
            ->setName($productData['name'] ?? null)
            ->setDescription($this->toHtmlDescription($productData['description']))
            ->setShortDescription('')
            ->setInStock($inStock)
            ->setStockQty($inStock ? max(1, $productData['stock_qty']) : 0)
            ->setBadge($inStock ? null : 'Очікується')
            ->setOgType('product')
            ->setEnabled(!$productData['force_disabled'])
            ->addCategory($baseCategory)
            ->addCategory($category);

        if (($productData['weight'] ?? null) !== null) {
            $product->setWeight($productData['weight']);
        }

        $this->generatorSlugService->assignUniqueSlug($product, $baseSlug);
        $parentCategory = $category->getParent();
        if ($parentCategory !== null) {
            $product->addCategory($parentCategory);
        }
        $this->ensureUniqueProductSlug($product);

        if ($productData['price'] !== null && $productData['price'] > 0) {
            $this->upsertOffer($product, $productData, $supplier);
        }

        $this->attributeImportService->syncProductValues(
            $product,
            (int) $productData['category_id'],
            $productData['params'] ?? [],
        );

        return $product;
    }

    private function syncExistingProduct($product, $supplier, $productData): void
    {
        $inStock = $productData['stock_qty'] > 0;
        $product
            ->setDescription($this->toHtmlDescription((string) ($productData['description'] ?? '')))
            ->setInStock($inStock)
            ->setStockQty($inStock ? max(1, $productData['stock_qty']) : 0)
            ->setBadge($inStock ? null : 'Очікується');

        if (($productData['weight'] ?? null) !== null) {
            $product->setWeight($productData['weight']);
        }

        if ($productData['force_disabled']) {
            $product->setEnabled(false);
        }

        if ($productData['price'] !== null && $productData['price'] > 0) {
            $this->upsertOffer($product, $productData, $supplier);
        }

        $this->attributeImportService->syncProductValues(
            $product,
            (int) $productData['category_id'],
            $productData['params'] ?? [],
        );
    }

    /**
     * Convert feed description text into HTML while preserving/restoring line breaks.
     * Lugi YML often flattens paragraphs to 2–3 spaces inside CDATA.
     */
    public function toHtmlDescription(string $text): ?string
    {
        $text = $this->normalizeDescriptionSource($text);
        if ($text === '') {
            return null;
        }

        // Keep already-structured HTML (with line-break tags).
        if (preg_match('/<(?:p|br|ul|ol|li|div|h[1-6])\b/i', $text) === 1) {
            return $this->sanitizeHtmlDescription($text);
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Long space runs are paragraph separators in the feed.
        $text = preg_replace('/ {3,}/u', "\n\n", $text) ?? $text;

        // ".  Next sentence" / "!  " → paragraph break.
        $text = preg_replace('/([.!?…]) {2,}(?=\S)/u', "$1\n\n", $text) ?? $text;

        // Insert breaks before common section titles.
        $headers = [
            'Переваги',
            'Характеристики',
            'Правила догляду',
            'Особливості',
            'Основні особливості',
            'Комплектація',
            'Застосування',
            'Інструкція',
            'Увага',
            'Склад',
            'Матеріали',
            'Опис',
        ];
        $headerPattern = implode('|', array_map(static fn (string $h): string => preg_quote($h, '/'), $headers));
        $text = preg_replace('/\s+('.$headerPattern.')(?:\s*[:：]\s*|\s+)/u', "\n\n$1\n", $text) ?? $text;

        // Characteristic rows: "Тип: …. Матеріал: …." → one per line.
        $text = preg_replace('/\.\s+(?=[\p{L}][^.\n]{0,40}:)/u', ".\n", $text) ?? $text;

        // Leftover double spaces (wrap artifacts) → single space.
        $text = preg_replace('/ {2,}/u', ' ', $text) ?? $text;
        $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $blocks = preg_split("/\n\s*\n/u", $text) ?: [];
        $html = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $lines = preg_split("/\n/u", $block) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
            if ($lines === []) {
                continue;
            }

            // First line alone matching a section header → emphasize it.
            $first = $lines[0];
            $isHeader = preg_match('/^('.$headerPattern.')$/u', $first) === 1;
            if ($isHeader && \count($lines) > 1) {
                $bodyText = implode("\n", array_slice($lines, 1));
                // Feature-like short sentences under a section → one per line.
                if (\in_array($first, ['Переваги', 'Особливості', 'Основні особливості', 'Правила догляду'], true)) {
                    $bodyText = preg_replace('/\.\s+(?=\S)/u', ".\n", $bodyText) ?? $bodyText;
                }
                $escapedHeader = htmlspecialchars($first, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $escapedBody = htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $escapedBody = nl2br($escapedBody, false);
                $html[] = '<p><strong>'.$escapedHeader.'</strong><br>'.$escapedBody.'</p>';
                continue;
            }

            if ($isHeader) {
                $html[] = '<p><strong>'.htmlspecialchars($first, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</strong></p>';
                continue;
            }

            $escaped = htmlspecialchars(implode("\n", $lines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html[] = '<p>'.nl2br($escaped, false).'</p>';
        }

        return $html === [] ? null : implode('', $html);
    }

    private function normalizeDescriptionSource(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/<!\[CDATA\[(.*)\]\]>/s', $text, $m) === 1) {
            $text = $m[1];
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\0", '', $text);

        return trim($text);
    }

    private function sanitizeHtmlDescription(string $html): string
    {
        $html = trim($html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        // Normalize <br> variants; keep basic structure tags.
        $html = preg_replace('/<br\s*\/?>/i', '<br>', $html) ?? $html;
        $allowed = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><h2><h3><h4><div>');
        $allowed = trim($allowed);

        return $allowed;
    }

    public function ensureUniqueProductSlug(Product $product): void
    {
        $product->ensureSlug();
        $base = $product->getSlug();
        $slug = $base;
        $i = 2;
        while ($this->productRepository->slugExists($slug, $product->getId())) {
            $slug = $base . '-' . $i;
            ++$i;
        }
        $product->setSlug($slug);
    }


    public function upsertOffer($product, $productData, $supplier): void
    {
        $offer = null;
        foreach ($product->getOffers() as $existing) {
            if ($existing->getSupplier()?->getId() === $supplier->getId()) {
                $offer = $existing;
                break;
            }
        }
        if ($offer === null) {
            $offer = new ProductOffer();
            $offer->setSupplier($supplier);
            $product->addOffer($offer);
        }
        $offer
            ->setSku($productData['sku'])
            ->setPrice($productData['price'])
            ->setQty($productData['stock_qty']);
    }

    /**
     * @param array<string, true> $seenSkus lowercase sku map
     */
    public function disableMissingProducts(Supplier $supplier, array $seenSkus, bool $dryRun): int
    {
        $products = $this->productRepository->createQueryBuilder('p')
            ->innerJoin('p.offers', 'o')
            ->innerJoin('o.supplier', 's')
            ->andWhere('s = :supplier')
            ->setParameter('supplier', $supplier)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }
            $skuKey = mb_strtolower($product->getSku());
            if (isset($seenSkus[$skuKey])) {
                continue;
            }
            if (!$product->isEnabled()) {
                continue;
            }
            $product->setEnabled(false);
            ++$count;
        }

        if (!$dryRun && $count > 0) {
            $this->em->flush();
        }

        return $count;
    }
}
