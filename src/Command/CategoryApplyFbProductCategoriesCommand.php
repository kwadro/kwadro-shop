<?php

namespace App\Command;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:category:apply-fb-product-categories',
    description: 'Apply fb_product_category from CSV and set Publish to feed = Yes',
)]
final class CategoryApplyFbProductCategoriesCommand extends Command
{
    private const DEFAULT_FILE = 'data/category_fb_product_categories.csv';

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'file',
                null,
                InputOption::VALUE_REQUIRED,
                'CSV path (columns: slug, category, fb_product_category)',
                self::DEFAULT_FILE,
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show counts without writing')
            ->addOption(
                'skip-feed',
                null,
                InputOption::VALUE_NONE,
                'Do not force publish_to_feed = Yes',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $this->resolvePath((string) $input->getOption('file'));
        $dryRun = (bool) $input->getOption('dry-run');
        $skipFeed = (bool) $input->getOption('skip-feed');

        if (!is_file($file)) {
            $io->error('CSV not found: '.$file);

            return Command::FAILURE;
        }

        $rows = $this->readCsv($file);
        if ($rows === []) {
            $io->error('CSV has no data rows.');

            return Command::FAILURE;
        }

        $updated = 0;
        $unchanged = 0;
        $missing = 0;
        $missingSlugs = [];

        foreach ($rows as $row) {
            $slug = $row['slug'];
            $fb = $row['fb_product_category'];
            if ($slug === '' || $slug === 'default') {
                continue;
            }

            $category = $this->categoryRepository->findOneBySlug($slug);
            if (!$category instanceof Category) {
                ++$missing;
                $missingSlugs[] = $slug;
                continue;
            }

            $changed = false;
            if ($category->getFbProductCategory() !== $fb) {
                $category->setFbProductCategory($fb);
                $changed = true;
            }
            if (!$skipFeed && !$category->isPublishToFeed()) {
                $category->setPublishToFeed(true);
                $changed = true;
            }

            if ($changed) {
                ++$updated;
            } else {
                ++$unchanged;
            }
        }

        if (!$dryRun && $updated > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s: updated=%d, unchanged=%d, missing=%d, rows=%d',
            $dryRun ? 'Dry-run' : 'Done',
            $updated,
            $unchanged,
            $missing,
            \count($rows),
        ));

        if ($missingSlugs !== []) {
            $io->warning('Categories not found for slugs:');
            $io->listing(\array_slice($missingSlugs, 0, 30));
            if (\count($missingSlugs) > 30) {
                $io->text(sprintf('… and %d more', \count($missingSlugs) - 30));
            }
        }

        return $missing > 0 && $updated === 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<array{slug: string, category: string, fb_product_category: string}>
     */
    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!\is_array($header)) {
            fclose($handle);

            return [];
        }

        $header = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $header,
        );
        $slugIdx = array_search('slug', $header, true);
        $categoryIdx = array_search('category', $header, true);
        $fbIdx = array_search('fb_product_category', $header, true);
        if ($fbIdx === false) {
            $fbIdx = array_search('facebook category', $header, true);
        }
        if ($categoryIdx === false) {
            $categoryIdx = array_search('категорія сайту', $header, true);
            if ($categoryIdx === false) {
                $categoryIdx = array_search('category_site', $header, true);
            }
        }
        if ($slugIdx === false || $fbIdx === false) {
            fclose($handle);

            return [];
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }
            $slug = trim((string) ($data[$slugIdx] ?? ''));
            $fb = trim((string) ($data[$fbIdx] ?? ''));
            if ($slug === '' || $fb === '') {
                continue;
            }
            $rows[] = [
                'slug' => $slug,
                'category' => trim((string) ($data[$categoryIdx !== false ? $categoryIdx : -1] ?? '')),
                'fb_product_category' => $fb,
            ];
        }
        fclose($handle);

        return $rows;
    }

    private function resolvePath(string $path): string
    {
        if ($path !== '' && $path[0] === '/') {
            return $path;
        }

        return rtrim($this->projectDir, '/').'/'.$path;
    }
}
