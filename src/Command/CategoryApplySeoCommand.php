<?php

namespace App\Command;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\Category\CategoryOgImagePath;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:category:apply-seo',
    description: 'Apply category-seo.json + copy OG images into public/uploads/images/category and the database',
)]
final class CategoryApplySeoCommand extends Command
{
    private const DEFAULT_DIR = 'og-categories';

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
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Export directory with category-seo.json', self::DEFAULT_DIR)
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to category-seo.json (overrides --dir/category-seo.json)')
            ->addOption('images-dir', null, InputOption::VALUE_REQUIRED, 'Directory with OG images (default: --dir)')
            ->addOption('only-empty', null, InputOption::VALUE_NONE, 'Only fill blank SEO fields / missing og_image')
            ->addOption('skip-images', null, InputOption::VALUE_NONE, 'Update DB only, do not copy image files')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show counts without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = $this->resolvePath((string) $input->getOption('dir'));
        $file = $input->getOption('file');
        $jsonPath = \is_string($file) && $file !== ''
            ? $this->resolvePath($file)
            : $dir.'/category-seo.json';

        $imagesDirOpt = $input->getOption('images-dir');
        $imagesDir = \is_string($imagesDirOpt) && $imagesDirOpt !== ''
            ? $this->resolvePath($imagesDirOpt)
            : $dir;

        $onlyEmpty = (bool) $input->getOption('only-empty');
        $skipImages = (bool) $input->getOption('skip-images');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_file($jsonPath)) {
            $io->error('JSON not found: '.$jsonPath);

            return Command::FAILURE;
        }

        $raw = file_get_contents($jsonPath);
        $data = $raw !== false ? json_decode($raw, true) : null;
        if (!\is_array($data) || !isset($data['categories']) || !\is_array($data['categories'])) {
            $io->error('Invalid category-seo.json structure.');

            return Command::FAILURE;
        }

        $updated = 0;
        $skipped = 0;
        $missingCategories = 0;
        $missingImages = 0;
        $copiedImages = 0;
        $errors = [];

        $io->progressStart(\count($data['categories']));

        foreach ($data['categories'] as $row) {
            $io->progressAdvance();
            if (!\is_array($row)) {
                ++$skipped;
                continue;
            }

            $category = $this->findCategory($row);
            if (!$category instanceof Category) {
                ++$missingCategories;
                continue;
            }

            $ogImage = trim((string) ($row['og_image'] ?? ''));
            if ($ogImage !== '' && !$skipImages) {
                $filename = CategoryOgImagePath::filename($ogImage);
                $row['og_image'] = $filename;
                $src = $imagesDir.'/'.$filename;
                $dst = CategoryOgImagePath::absolutePath($this->projectDir, $filename);
                if (!is_file($src)) {
                    ++$missingImages;
                    $errors[] = sprintf('Image missing for #%d %s: %s', $category->getId(), $category->getSlug(), $filename);
                } elseif ($dst === '') {
                    ++$missingImages;
                    $errors[] = sprintf('Invalid OG path for #%d %s', $category->getId(), $category->getSlug());
                } elseif (!$dryRun && ($onlyEmpty === false || !is_file($dst))) {
                    $dstDir = \dirname($dst);
                    if (!is_dir($dstDir) && !mkdir($dstDir, 0775, true) && !is_dir($dstDir)) {
                        $errors[] = sprintf('Cannot create directory: %s', $dstDir);
                    } elseif ($src !== $dst && !copy($src, $dst)) {
                        $errors[] = sprintf('Cannot copy %s → %s', $src, $dst);
                    } else {
                        ++$copiedImages;
                    }
                } elseif ($dryRun) {
                    ++$copiedImages;
                }
            }

            $changed = $this->applyRow($category, $row, $onlyEmpty);
            if ($changed === []) {
                ++$skipped;
            } else {
                ++$updated;
            }
        }

        $io->progressFinish();

        if ($dryRun) {
            $this->entityManager->clear();
            $io->note('[dry-run] Nothing saved.');
        } else {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'Categories updated: %d | skipped unchanged: %d | missing categories: %d | images copied: %d | missing images: %d',
            $updated,
            $skipped,
            $missingCategories,
            $copiedImages,
            $missingImages,
        ));

        if ($errors !== []) {
            $io->warning('First issues:');
            $io->listing(\array_slice($errors, 0, 20));
        }

        return $missingCategories > 0 || $missingImages > 0 || $errors !== []
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private function applyRow(Category $category, array $row, bool $onlyEmpty): array
    {
        $changed = [];
        $map = [
            'meta_title' => 'setMetaTitle',
            'meta_description' => 'setMetaDescription',
            'og_title' => 'setOgTitle',
            'og_description' => 'setOgDescription',
            'og_type' => 'setOgType',
            'og_image' => 'setOgImage',
        ];

        foreach ($map as $key => $setter) {
            if (!\array_key_exists($key, $row)) {
                continue;
            }
            $value = trim((string) $row[$key]);
            if ($value === '') {
                continue;
            }
            if ($key === 'og_image') {
                $value = CategoryOgImagePath::filename($value);
                if ($value === '') {
                    continue;
                }
            }

            $getter = match ($key) {
                'meta_title' => $category->getMetaTitle(),
                'meta_description' => $category->getMetaDescription(),
                'og_title' => $category->getOgTitle(),
                'og_description' => $category->getOgDescription(),
                'og_type' => $category->getOgType(),
                'og_image' => CategoryOgImagePath::filename($category->getOgImage()),
                default => null,
            };

            if ($onlyEmpty && !$this->isBlank($getter)) {
                continue;
            }

            if ((string) $getter === $value) {
                continue;
            }

            $category->{$setter}($value);
            $changed[] = $key;
        }

        return $changed;
    }

    /** @param array<string, mixed> $row */
    private function findCategory(array $row): ?Category
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id > 0) {
            $category = $this->categoryRepository->find($id);
            if ($category instanceof Category) {
                return $category;
            }
        }

        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        return $this->categoryRepository->findOneBySlug($slug);
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    private function resolvePath(string $path): string
    {
        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir.'/'.ltrim($path, './');
        }

        return rtrim($path, '/');
    }
}
