<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\Product\ProductImagePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:product:migrate-gallery-paths',
    description: 'Store only filenames in product gallery JSON and move files to /uploads/products/{a}/{b}/file',
)]
final class ProductMigrateGalleryPathsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report only, do not write');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $products = $this->productRepository->findAll();
        $updated = 0;
        $moved = 0;
        $unchanged = 0;

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $gallery = $product->getGallery();
            if ($gallery === []) {
                ++$unchanged;
                continue;
            }

            $normalized = [];
            $changed = false;

            foreach ($gallery as $item) {
                if (!\is_array($item)) {
                    continue;
                }

                $raw = (string) ($item['full'] ?? $item['thumb'] ?? '');
                $filename = ProductImagePath::filename($raw);
                if ($filename === '') {
                    continue;
                }

                if ($raw !== $filename) {
                    $changed = true;
                }

                if (!$dryRun) {
                    $before = ProductImagePath::absolutePath($this->projectDir, $filename);
                    $flat = $this->projectDir.'/public'.ProductImagePath::WEB_BASE.'/'.$filename;
                    if (!is_file($before) && is_file($flat)) {
                        ProductImagePath::ensureStored($this->projectDir, $filename);
                        ++$moved;
                    } elseif (!is_file($before)) {
                        ProductImagePath::ensureStored($this->projectDir, $filename);
                        if (is_file($before)) {
                            ++$moved;
                        }
                    }
                } else {
                    $flat = $this->projectDir.'/public'.ProductImagePath::WEB_BASE.'/'.$filename;
                    $target = ProductImagePath::absolutePath($this->projectDir, $filename);
                    if (is_file($flat) && !is_file($target)) {
                        ++$moved;
                        $io->text(sprintf('  would move %s → %s', $filename, ProductImagePath::relativePath($filename)));
                    }
                }

                $normalized[] = [
                    'thumb' => $filename,
                    'full' => $filename,
                    'alt' => trim((string) ($item['alt'] ?? $product->getName())),
                ];
            }

            if (!$changed && $normalized === $gallery) {
                ++$unchanged;
                continue;
            }

            if (!$dryRun) {
                $product->setGallery($normalized);
            }
            ++$updated;
            $io->text(sprintf('  ✓ product #%d (%d images)', $product->getId(), \count($normalized)));
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%sProducts updated: %d, files moved: %d, unchanged: %d',
            $dryRun ? '[dry-run] ' : '',
            $updated,
            $moved,
            $unchanged,
        ));

        return Command::SUCCESS;
    }
}
