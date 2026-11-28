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

#[AsCommand(
    name: 'app:category:fill-tvorchist-meta',
    description: 'Fill google/fb product categories + SEO/OG meta for «Товари для творчості» (310) and «Картини за номерами» (311)',
)]
final class CategoryFillTvorchistMetaCommand extends Command
{
    /**
     * Curated feed + SEO data keyed by category id (same on local and live).
     *
     * @var array<int, array{
     *     slug: string,
     *     google_product_category: string,
     *     fb_product_category: string,
     *     meta_title: string,
     *     meta_description: string,
     *     og_title: string,
     *     og_description: string,
     *     og_type: string,
     *     publish_to_feed: bool
     * }>
     */
    private const DATA = [
        310 => [
            'slug' => 'tovary-dlya-tvorchosti',
            'google_product_category' => 'Arts & Entertainment > Hobbies & Creative Arts > Arts & Crafts',
            'fb_product_category' => 'arts & crafts',
            'meta_title' => 'Товари для творчості — купити набори | Квадро',
            'meta_description' => 'Товари для творчості: картини за номерами та набори для хобі. Актуальні ціни та наявність. Доставка Новою Поштою по Україні, оплата онлайн або при отриманні.',
            'og_title' => 'Товари для творчості | Квадро',
            'og_description' => 'Набори для творчості та хобі — картини за номерами та ідеї для натхнення в магазині Квадро.',
            'og_type' => 'website',
            'publish_to_feed' => true,
        ],
        311 => [
            'slug' => 'kartyny-za-nomeramy',
            'google_product_category' => 'Arts & Entertainment > Hobbies & Creative Arts > Arts & Crafts > Art & Craft Kits',
            'fb_product_category' => 'arts & crafts > art supplies',
            'meta_title' => 'Картини за номерами — купити набори | Квадро',
            'meta_description' => 'Картини за номерами: готові набори для малювання вдома. Актуальні ціни та наявність. Доставка Новою Поштою по Україні, оплата онлайн або при отриманні.',
            'og_title' => 'Картини за номерами | Квадро',
            'og_description' => 'Набори картин за номерами — купити онлайн з доставкою по Україні в магазині Квадро.',
            'og_type' => 'website',
            'publish_to_feed' => true,
        ],
    ];

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without saving')
            ->addOption(
                'only-empty',
                null,
                InputOption::VALUE_NONE,
                'Only fill blank fields (do not overwrite existing values)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $onlyEmpty = (bool) $input->getOption('only-empty');

        $rows = [];
        $missing = [];
        $changedCount = 0;

        foreach (self::DATA as $id => $data) {
            $category = $this->categoryRepository->find($id)
                ?? $this->categoryRepository->findOneBy(['slug' => $data['slug']]);
            if (!$category instanceof Category) {
                $missing[] = sprintf('id=%d slug=%s', $id, $data['slug']);
                continue;
            }

            $changed = $this->apply($category, $data, $onlyEmpty);
            if ($changed !== []) {
                ++$changedCount;
            }
            $rows[] = [
                (string) $category->getId(),
                $category->getName(),
                $category->getSlug(),
                $changed === [] ? '—' : implode(', ', $changed),
            ];
        }

        if ($rows !== []) {
            $io->table(['ID', 'Name', 'Slug', 'Changed fields'], $rows);
        }

        if ($missing !== []) {
            $io->error('Categories not found:');
            $io->listing($missing);

            return Command::FAILURE;
        }

        if ($changedCount === 0) {
            $io->success('Nothing to update: categories already have the expected values.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('[dry-run] Nothing saved.');

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('Updated %d categor%s.', $changedCount, $changedCount === 1 ? 'y' : 'ies'));

        return Command::SUCCESS;
    }

    /**
     * @param array{
     *     slug: string,
     *     google_product_category: string,
     *     fb_product_category: string,
     *     meta_title: string,
     *     meta_description: string,
     *     og_title: string,
     *     og_description: string,
     *     og_type: string,
     *     publish_to_feed: bool
     * } $data
     *
     * @return list<string>
     */
    private function apply(Category $category, array $data, bool $onlyEmpty): array
    {
        $changed = [];

        if ($this->shouldWrite($category->getGoogleProductCategory(), $data['google_product_category'], $onlyEmpty)) {
            $category->setGoogleProductCategory($data['google_product_category']);
            $changed[] = 'google_product_category';
        }
        if ($this->shouldWrite($category->getFbProductCategory(), $data['fb_product_category'], $onlyEmpty)) {
            $category->setFbProductCategory($data['fb_product_category']);
            $changed[] = 'fb_product_category';
        }
        if ($this->shouldWrite($category->getMetaTitle(), $data['meta_title'], $onlyEmpty)) {
            $category->setMetaTitle($data['meta_title']);
            $changed[] = 'meta_title';
        }
        if ($this->shouldWrite($category->getMetaDescription(), $data['meta_description'], $onlyEmpty)) {
            $category->setMetaDescription($data['meta_description']);
            $changed[] = 'meta_description';
        }
        if ($this->shouldWrite($category->getOgTitle(), $data['og_title'], $onlyEmpty)) {
            $category->setOgTitle($data['og_title']);
            $changed[] = 'og_title';
        }
        if ($this->shouldWrite($category->getOgDescription(), $data['og_description'], $onlyEmpty)) {
            $category->setOgDescription($data['og_description']);
            $changed[] = 'og_description';
        }
        if ($this->shouldWrite($category->getOgType(), $data['og_type'], $onlyEmpty)) {
            $category->setOgType($data['og_type']);
            $changed[] = 'og_type';
        }
        if ($data['publish_to_feed'] && !$category->isPublishToFeed()) {
            $category->setPublishToFeed(true);
            $changed[] = 'publish_to_feed';
        }

        return $changed;
    }

    private function shouldWrite(?string $current, string $next, bool $onlyEmpty): bool
    {
        $blank = $current === null || trim($current) === '';
        if ($onlyEmpty && !$blank) {
            return false;
        }

        return $current !== $next;
    }
}
