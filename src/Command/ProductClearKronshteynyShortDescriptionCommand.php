<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:product:clear-kronshteyny-short-description',
    description: 'Clear short_description for all products in Kronshteyny category (and children)',
)]
final class ProductClearKronshteynyShortDescriptionCommand extends Command
{
    private const CATEGORY_SLUG = 'kronshteyny';

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
            ->addOption('live', null, InputOption::VALUE_NONE, 'Run against live DB from --live-env-file')
            ->addOption(
                'live-env-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Env file with live DATABASE_URL',
                $this->projectDir.'/.env-live.local',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report only, do not write')
            ->addOption(
                'slug',
                null,
                InputOption::VALUE_REQUIRED,
                'Category slug (parent Kronshteyny)',
                self::CATEGORY_SLUG,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $live = (bool) $input->getOption('live');
        $slug = trim((string) $input->getOption('slug'));

        try {
            $connection = $live
                ? $this->connectLive((string) $input->getOption('live-env-file'))
                : $this->localConnection;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $category = $connection->fetchAssociative(
            'SELECT id, name, slug FROM shop_category WHERE slug = ?',
            [$slug],
        );
        if ($category === false) {
            $io->error(sprintf('Category slug "%s" not found in %s DB.', $slug, $live ? 'live' : 'local'));

            return Command::FAILURE;
        }

        $categoryId = (int) $category['id'];
        $countSql = '
            SELECT COUNT(DISTINCT p.id)
            FROM shop_product p
            INNER JOIN shop_product_category pc ON pc.product_id = p.id
            INNER JOIN shop_category c ON c.id = pc.category_id
            WHERE (c.id = :categoryId OR c.parent_id = :categoryId)
              AND p.short_description IS NOT NULL
              AND TRIM(p.short_description) != \'\'
        ';
        $toClear = (int) $connection->fetchOne($countSql, ['categoryId' => $categoryId]);

        $io->section(sprintf(
            '%s · #%d %s (%s)',
            $live ? 'LIVE' : 'LOCAL',
            $categoryId,
            $category['name'],
            $category['slug'],
        ));
        $io->text(sprintf('Products with non-empty short_description: %d', $toClear));

        if ($toClear === 0) {
            $io->success('Nothing to clear.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->success(sprintf('[dry-run] Would clear short_description for %d product(s).', $toClear));

            return Command::SUCCESS;
        }

        $updated = (int) $connection->executeStatement(
            '
            UPDATE shop_product p
            INNER JOIN (
                SELECT DISTINCT p2.id
                FROM shop_product p2
                INNER JOIN shop_product_category pc ON pc.product_id = p2.id
                INNER JOIN shop_category c ON c.id = pc.category_id
                WHERE (c.id = :categoryId OR c.parent_id = :categoryId)
                  AND p2.short_description IS NOT NULL
                  AND TRIM(p2.short_description) != \'\'
            ) x ON x.id = p.id
            SET p.short_description = NULL,
                p.updated_at = :now
            ',
            [
                'categoryId' => $categoryId,
                'now' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ],
        );

        $io->success(sprintf('Cleared short_description for %d product(s) on %s.', $updated, $live ? 'live' : 'local'));

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
}
