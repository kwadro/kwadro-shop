<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:category:push-live',
    description: 'Push local category + children + child products into live DB category',
)]
final class CategoryPushLiveCommand extends Command
{
    public function __construct(
        private readonly Connection $localConnection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('local-id', InputArgument::REQUIRED, 'Local shop_category.id')
            ->addArgument('live-id', InputArgument::REQUIRED, 'Live shop_category.id (target parent)')
            ->addOption(
                'live-env-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Env file with live DATABASE_URL',
                $this->projectDir.'/.env-live.local',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show actions without writing to live')
            ->addOption(
                'overwrite-slug',
                null,
                InputOption::VALUE_NONE,
                'Also overwrite live parent category slug from local',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $localId = (int) $input->getArgument('local-id');
        $liveId = (int) $input->getArgument('live-id');
        $dryRun = (bool) $input->getOption('dry-run');
        $overwriteSlug = (bool) $input->getOption('overwrite-slug');
        $liveEnvFile = (string) $input->getOption('live-env-file');

        if ($localId < 1 || $liveId < 1) {
            $io->error('Both local-id and live-id must be positive integers.');

            return Command::FAILURE;
        }

        try {
            $live = $this->connectLive($liveEnvFile);
        } catch (\Throwable $e) {
            $io->error('Cannot connect to live DB: '.$e->getMessage());

            return Command::FAILURE;
        }

        $local = $this->localConnection;
        $localCategory = $local->fetchAssociative('SELECT * FROM shop_category WHERE id = ?', [$localId]);
        if ($localCategory === false) {
            $io->error(sprintf('Local category id=%d not found.', $localId));

            return Command::FAILURE;
        }

        $liveCategory = $live->fetchAssociative('SELECT * FROM shop_category WHERE id = ?', [$liveId]);
        if ($liveCategory === false) {
            $io->error(sprintf('Live category id=%d not found.', $liveId));

            return Command::FAILURE;
        }

        $io->section(sprintf(
            'Local #%d %s (%s) → Live #%d %s (%s)',
            $localId,
            $localCategory['name'],
            $localCategory['slug'],
            $liveId,
            $liveCategory['name'],
            $liveCategory['slug'],
        ));

        $children = $local->fetchAllAssociative(
            'SELECT * FROM shop_category WHERE parent_id = ? ORDER BY position ASC, id ASC',
            [$localId],
        );

        $productRows = $local->fetchAllAssociative(
            'SELECT DISTINCT p.*
             FROM shop_product p
             INNER JOIN shop_product_category pc ON pc.product_id = p.id
             INNER JOIN shop_category c ON c.id = pc.category_id
             WHERE c.parent_id = ?
             ORDER BY p.id ASC',
            [$localId],
        );

        $io->text(sprintf('Children: %d, products in children: %d', \count($children), \count($productRows)));

        if ($dryRun) {
            $io->table(['Local child', 'slug'], array_map(
                static fn (array $c): array => [$c['name'], $c['slug']],
                $children,
            ));
            $io->success('[dry-run] No changes written.');

            return Command::SUCCESS;
        }

        $live->beginTransaction();
        try {
            $this->updateLiveCategory($live, $liveId, $localCategory, $overwriteSlug, preserveParent: true);
            $io->text(sprintf('✓ Updated live category #%d', $liveId));

            /** @var array<int, int> $childMap localChildId => liveChildId */
            $childMap = [];
            foreach ($children as $child) {
                $liveChildId = $this->upsertLiveChildCategory($live, $liveId, $child);
                $childMap[(int) $child['id']] = $liveChildId;
                $io->text(sprintf(
                    '  ✓ child %s (local #%d → live #%d)',
                    $child['slug'],
                    $child['id'],
                    $liveChildId,
                ));
            }

            $productsUpdated = 0;
            $productsCreated = 0;
            foreach ($productRows as $product) {
                $localProductId = (int) $product['id'];
                $localCategoryIds = $local->fetchFirstColumn(
                    'SELECT category_id FROM shop_product_category WHERE product_id = ?',
                    [$localProductId],
                );

                $liveCategoryIds = [$liveId];
                foreach ($localCategoryIds as $localCategoryId) {
                    $localCategoryId = (int) $localCategoryId;
                    if (isset($childMap[$localCategoryId])) {
                        $liveCategoryIds[] = $childMap[$localCategoryId];
                    }
                }
                $liveCategoryIds = array_values(array_unique($liveCategoryIds));

                $offers = $local->fetchAllAssociative(
                    'SELECT o.*, s.slug AS supplier_slug, s.name AS supplier_name, s.description AS supplier_description,
                            s.phone AS supplier_phone, s.email AS supplier_email,
                            s.meta_title AS supplier_meta_title, s.meta_description AS supplier_meta_description,
                            s.og_title AS supplier_og_title, s.og_description AS supplier_og_description,
                            s.og_type AS supplier_og_type, s.og_image AS supplier_og_image
                     FROM shop_product_offer o
                     INNER JOIN shop_supplier s ON s.id = o.supplier_id
                     WHERE o.product_id = ?',
                    [$localProductId],
                );

                $result = $this->upsertLiveProduct($live, $product, $liveCategoryIds, $offers);
                if ($result['created']) {
                    ++$productsCreated;
                    $io->text(sprintf('  + product %s → live #%d', $product['sku'], $result['id']));
                } else {
                    ++$productsUpdated;
                    $io->text(sprintf('  ~ product %s → live #%d', $product['sku'], $result['id']));
                }
            }

            $live->commit();
        } catch (\Throwable $e) {
            $live->rollBack();
            $io->error('Push failed, live transaction rolled back: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Done. Children: %d, products created: %d, products updated: %d',
            \count($childMap),
            $productsCreated,
            $productsUpdated,
        ));
        $io->note('Product/category image files are not uploaded by this command. Sync public/uploads separately if needed.');

        return Command::SUCCESS;
    }

    private function connectLive(string $envFile): Connection
    {
        if (!is_file($envFile)) {
            throw new \RuntimeException('Live env file not found: '.$envFile);
        }

        $url = $this->readDatabaseUrlFromEnvFile($envFile);
        if ($url === null || $url === '') {
            throw new \RuntimeException('DATABASE_URL missing in '.$envFile);
        }

        return DriverManager::getConnection($this->connectionParamsFromUrl($url));
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionParamsFromUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new \RuntimeException('Invalid DATABASE_URL for live connection.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'mysql'));
        $driver = match ($scheme) {
            'mysql', 'mysqli', 'pdo_mysql' => 'pdo_mysql',
            'pgsql', 'postgres', 'postgresql', 'pdo_pgsql' => 'pdo_pgsql',
            default => throw new \RuntimeException('Unsupported DB scheme: '.$scheme),
        };

        $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
        if ($dbname === '') {
            throw new \RuntimeException('DATABASE_URL has empty database name.');
        }

        $params = [
            'driver' => $driver,
            'host' => $parts['host'],
            'port' => isset($parts['port']) ? (int) $parts['port'] : null,
            'dbname' => $dbname,
            'user' => isset($parts['user']) ? rawurldecode($parts['user']) : null,
            'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
            'charset' => 'utf8mb4',
        ];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (isset($query['charset']) && \is_string($query['charset']) && $query['charset'] !== '') {
                $params['charset'] = $query['charset'];
            }
            if (isset($query['serverVersion']) && \is_string($query['serverVersion'])) {
                $params['serverVersion'] = $query['serverVersion'];
            }
        }

        return $params;
    }

    private function readDatabaseUrlFromEnvFile(string $path): ?string
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_starts_with($line, 'DATABASE_URL=')) {
                continue;
            }
            $value = substr($line, \strlen('DATABASE_URL='));
            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            return $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function updateLiveCategory(
        Connection $live,
        int $liveId,
        array $source,
        bool $overwriteSlug,
        bool $preserveParent,
    ): void {
        $fields = [
            'name' => $source['name'],
            'enabled' => (int) $source['enabled'],
            'show_filters' => (int) ($source['show_filters'] ?? 1),
            'meta_title' => $source['meta_title'],
            'meta_description' => $source['meta_description'],
            'og_title' => $source['og_title'],
            'og_description' => $source['og_description'],
            'og_type' => $source['og_type'],
            'og_image' => $source['og_image'],
            'position' => (int) $source['position'],
            'updated_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ];

        if ($overwriteSlug) {
            $fields['slug'] = $source['slug'];
        }

        if (!$preserveParent) {
            $fields['parent_id'] = $source['parent_id'];
            $fields['level'] = (int) $source['level'];
        } else {
            $parentId = $live->fetchOne('SELECT parent_id FROM shop_category WHERE id = ?', [$liveId]);
            $fields['level'] = $parentId ? 1 + (int) $live->fetchOne(
                'SELECT level FROM shop_category WHERE id = ?',
                [$parentId],
            ) : 0;
        }

        $sets = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $sets[] = $column.' = ?';
            $params[] = $value;
        }
        $params[] = $liveId;
        $live->executeStatement(
            'UPDATE shop_category SET '.implode(', ', $sets).' WHERE id = ?',
            $params,
        );
    }

    /**
     * @param array<string, mixed> $child
     */
    private function upsertLiveChildCategory(Connection $live, int $liveParentId, array $child): int
    {
        $existingId = $live->fetchOne(
            'SELECT id FROM shop_category WHERE slug = ?',
            [$child['slug']],
        );

        $parentLevel = (int) $live->fetchOne('SELECT level FROM shop_category WHERE id = ?', [$liveParentId]);
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $data = [
            'parent_id' => $liveParentId,
            'name' => $child['name'],
            'slug' => $child['slug'],
            'level' => $parentLevel + 1,
            'position' => (int) $child['position'],
            'enabled' => (int) $child['enabled'],
            'show_filters' => (int) ($child['show_filters'] ?? 1),
            'meta_title' => $child['meta_title'],
            'meta_description' => $child['meta_description'],
            'og_title' => $child['og_title'],
            'og_description' => $child['og_description'],
            'og_type' => $child['og_type'],
            'og_image' => $child['og_image'],
            'updated_at' => $now,
        ];

        if ($existingId) {
            $sets = [];
            $params = [];
            foreach ($data as $column => $value) {
                $sets[] = $column.' = ?';
                $params[] = $value;
            }
            $params[] = (int) $existingId;
            $live->executeStatement(
                'UPDATE shop_category SET '.implode(', ', $sets).' WHERE id = ?',
                $params,
            );

            return (int) $existingId;
        }

        $data['created_at'] = $now;
        $live->insert('shop_category', $data);

        return (int) $live->lastInsertId();
    }

    /**
     * @param array<string, mixed> $product
     * @param list<int> $liveCategoryIds
     * @param list<array<string, mixed>> $offers
     * @return array{id: int, created: bool}
     */
    private function upsertLiveProduct(
        Connection $live,
        array $product,
        array $liveCategoryIds,
        array $offers,
    ): array {
        $existingId = $live->fetchOne(
            'SELECT id FROM shop_product WHERE sku = ?',
            [$product['sku']],
        );
        if (!$existingId) {
            $existingId = $live->fetchOne(
                'SELECT id FROM shop_product WHERE slug = ?',
                [$product['slug']],
            );
        }

        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $data = [
            'name' => $product['name'],
            'sku' => $product['sku'],
            'slug' => $product['slug'],
            'weight' => $product['weight'],
            'enabled' => (int) ($product['enabled'] ?? 1),
            'in_stock' => (int) $product['in_stock'],
            'stock_qty' => (int) $product['stock_qty'],
            'badge' => $product['badge'],
            'short_description' => $product['short_description'],
            'description' => $product['description'],
            'features' => $product['features'],
            'gallery' => $product['gallery'],
            'package_height' => $product['package_height'],
            'package_width' => $product['package_width'],
            'package_length' => $product['package_length'],
            'title' => $product['title'],
            'meta_title' => $product['meta_title'],
            'meta_description' => $product['meta_description'],
            'og_title' => $product['og_title'],
            'og_description' => $product['og_description'],
            'og_type' => $product['og_type'],
            'og_image' => $product['og_image'],
            'model' => $product['model'],
            'brand' => $product['brand'],
            'color' => $product['color'],
            'type' => $product['type'],
            'updated_at' => $now,
        ];

        $created = false;
        if ($existingId) {
            $sets = [];
            $params = [];
            foreach ($data as $column => $value) {
                $sets[] = $column.' = ?';
                $params[] = $value;
            }
            $params[] = (int) $existingId;
            $live->executeStatement(
                'UPDATE shop_product SET '.implode(', ', $sets).' WHERE id = ?',
                $params,
            );
            $liveProductId = (int) $existingId;
        } else {
            $data['created_at'] = $now;
            // Avoid unique slug collision if sku was new but slug exists unexpectedly.
            $slugExists = (bool) $live->fetchOne(
                'SELECT id FROM shop_product WHERE slug = ?',
                [$data['slug']],
            );
            if ($slugExists) {
                $data['slug'] = $data['slug'].'-'.$product['sku'];
            }
            $live->insert('shop_product', $data);
            $liveProductId = (int) $live->lastInsertId();
            $created = true;
        }

        foreach ($liveCategoryIds as $categoryId) {
            $exists = $live->fetchOne(
                'SELECT 1 FROM shop_product_category WHERE product_id = ? AND category_id = ?',
                [$liveProductId, $categoryId],
            );
            if (!$exists) {
                $live->insert('shop_product_category', [
                    'product_id' => $liveProductId,
                    'category_id' => $categoryId,
                ]);
            }
        }

        foreach ($offers as $offer) {
            $supplierId = $this->upsertLiveSupplier($live, $offer);
            $existingOfferId = $live->fetchOne(
                'SELECT id FROM shop_product_offer WHERE product_id = ? AND supplier_id = ?',
                [$liveProductId, $supplierId],
            );
            $offerData = [
                'sku' => $offer['sku'],
                'price' => $offer['price'],
                'old_price' => $offer['old_price'],
                'qty' => (int) $offer['qty'],
                'product_id' => $liveProductId,
                'supplier_id' => $supplierId,
                'updated_at' => $now,
            ];
            if ($existingOfferId) {
                $sets = [];
                $params = [];
                foreach ($offerData as $column => $value) {
                    $sets[] = $column.' = ?';
                    $params[] = $value;
                }
                $params[] = (int) $existingOfferId;
                $live->executeStatement(
                    'UPDATE shop_product_offer SET '.implode(', ', $sets).' WHERE id = ?',
                    $params,
                );
            } else {
                $offerData['created_at'] = $now;
                $live->insert('shop_product_offer', $offerData);
            }
        }

        return ['id' => $liveProductId, 'created' => $created];
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function upsertLiveSupplier(Connection $live, array $offer): int
    {
        $slug = (string) ($offer['supplier_slug'] ?? '');
        if ($slug === '') {
            $slug = 'supplier';
        }

        $existingId = $live->fetchOne('SELECT id FROM shop_supplier WHERE slug = ?', [$slug]);
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $data = [
            'name' => $offer['supplier_name'] ?? $slug,
            'slug' => $slug,
            'description' => $offer['supplier_description'] ?? null,
            'phone' => $offer['supplier_phone'] ?? null,
            'email' => $offer['supplier_email'] ?? null,
            'meta_title' => $offer['supplier_meta_title'] ?? null,
            'meta_description' => $offer['supplier_meta_description'] ?? null,
            'og_title' => $offer['supplier_og_title'] ?? null,
            'og_description' => $offer['supplier_og_description'] ?? null,
            'og_type' => $offer['supplier_og_type'] ?? null,
            'og_image' => $offer['supplier_og_image'] ?? null,
            'updated_at' => $now,
        ];

        if ($existingId) {
            $sets = [];
            $params = [];
            foreach ($data as $column => $value) {
                $sets[] = $column.' = ?';
                $params[] = $value;
            }
            $params[] = (int) $existingId;
            $live->executeStatement(
                'UPDATE shop_supplier SET '.implode(', ', $sets).' WHERE id = ?',
                $params,
            );

            return (int) $existingId;
        }

        $data['created_at'] = $now;
        $live->insert('shop_supplier', $data);

        return (int) $live->lastInsertId();
    }
}
