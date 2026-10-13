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
    name: 'app:category:fill-seo',
    description: 'Fill empty SEO / OG fields of storefront categories (existing values are kept)',
)]
final class CategoryFillSeoCommand extends Command
{
    private const BRAND = 'Квадро';
    private const META_TITLE_MAX = 70;

    /**
     * Hand-written copy for top-level categories, keyed by slug.
     *
     * @var array<string, array{meta_title?: string, meta_description?: string, og_title?: string, og_description?: string, og_image?: string}>
     */
    private const CURATED = [
        'digital-tb' => [
            'meta_title' => 'Цифрове телебачення — купити обладнання | Квадро',
            'meta_description' => 'Цифрове телебачення: тюнери DVB-T2, ТВ-антени та супутнє обладнання. Доставка Новою Поштою по Україні. Оплата онлайн або при отриманні.',
            'og_title' => 'Цифрове телебачення DVB-T2 | Квадро',
            'og_description' => 'Тюнери DVB-T2 і ТВ-антени для впевненого прийому цифрового сигналу. Доставка по Україні, оплата онлайн або при отриманні.',
            'og_image' => 'og-category-digital-tb.jpg',
        ],
        'homeware' => [
            'meta_title' => 'Товари для дому — посуд, текстиль, техніка | Квадро',
            'meta_description' => 'Товари для дому та комфортного життя: посуд, кухонне приладдя, домашній текстиль, техніка, декор і новорічні товари. Доставка Новою Поштою по Україні.',
            'og_title' => 'Товари для дому | Квадро',
            'og_description' => 'Посуд, текстиль, техніка для дому, декор і товари для відпочинку — усе для затишку в одному каталозі.',
            'og_image' => 'og-category-lugi.jpg',
        ],
    ];

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
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without saving');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $default = $this->categoryRepository->findDefaultItem();
        if ($default === null) {
            $io->error('Default root category not found.');

            return Command::FAILURE;
        }

        $rows = [];
        foreach ($this->categoryRepository->findAll() as $category) {
            $topLevel = $this->resolveTopLevel($category, $default);
            if ($topLevel === null) {
                continue;
            }

            $changed = $this->fillCategory($category, $topLevel);
            if ($changed !== []) {
                $rows[] = [$category->getId(), $category->getName(), implode(', ', $changed)];
            }
        }

        if ($rows === []) {
            $io->success('Nothing to fill: all storefront categories already have SEO fields.');

            return Command::SUCCESS;
        }

        $io->table(['ID', 'Category', 'Filled fields'], $rows);

        if ($dryRun) {
            $io->note('[dry-run] Nothing saved.');

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(sprintf('Updated %d categories.', \count($rows)));

        return Command::SUCCESS;
    }

    /** @return list<string> names of filled fields */
    private function fillCategory(Category $category, Category $topLevel): array
    {
        $name = trim($category->getName());
        $curated = $category === $topLevel ? (self::CURATED[$category->getSlug()] ?? []) : [];
        $changed = [];

        $metaDescription = (string) $category->getMetaDescription();
        $plainDescription = trim(html_entity_decode(strip_tags($metaDescription), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plainDescription !== $metaDescription && $plainDescription !== '') {
            $category->setMetaDescription($plainDescription);
            $changed[] = 'meta_description (html stripped)';
        }

        $metaTitle = $curated['meta_title'] ?? $this->buildMetaTitle($name);
        if ($this->isBlank($category->getMetaTitle())) {
            $category->setMetaTitle($metaTitle);
            $changed[] = 'meta_title';
        }

        if ($this->isBlank($category->getMetaDescription())) {
            $category->setMetaDescription($curated['meta_description'] ?? sprintf(
                '%s у каталозі «%s»: актуальні ціни та наявність. Доставка Новою Поштою по Україні, оплата онлайн або при отриманні.',
                $name,
                trim($topLevel->getName()),
            ));
            $changed[] = 'meta_description';
        }

        if ($this->isBlank($category->getOgTitle())) {
            $category->setOgTitle($curated['og_title'] ?? sprintf('%s | %s', $name, self::BRAND));
            $changed[] = 'og_title';
        }

        if ($this->isBlank($category->getOgDescription())) {
            $category->setOgDescription($curated['og_description'] ?? sprintf(
                '%s — ціни, наявність і доставка по Україні в магазині %s.',
                $name,
                self::BRAND,
            ));
            $changed[] = 'og_description';
        }

        if ($this->isBlank($category->getOgType())) {
            $category->setOgType('website');
            $changed[] = 'og_type';
        }

        if ($this->isBlank($category->getOgImage())) {
            $image = $curated['og_image'] ?? $this->findAncestorOgImage($category, $topLevel);
            if ($image !== null && is_file($this->projectDir.'/public/uploads/images/'.$image)) {
                $category->setOgImage($image);
                $changed[] = 'og_image';
            }
        }

        return $changed;
    }

    private function buildMetaTitle(string $name): string
    {
        $long = sprintf('%s — купити в інтернет-магазині | %s', $name, self::BRAND);
        if (mb_strlen($long) <= self::META_TITLE_MAX) {
            return $long;
        }

        return sprintf('%s — купити | %s', $name, self::BRAND);
    }

    /** Level-1 category under Default, or null when the category is outside the storefront tree. */
    private function resolveTopLevel(Category $category, Category $default): ?Category
    {
        $node = $category;
        while ($node->getParent() !== null) {
            if ($node->getParent() === $default) {
                return $node;
            }
            $node = $node->getParent();
        }

        return null;
    }

    private function findAncestorOgImage(Category $category, Category $topLevel): ?string
    {
        for ($node = $category->getParent(); $node !== null; $node = $node->getParent()) {
            if (!$this->isBlank($node->getOgImage())) {
                return $node->getOgImage();
            }
            if ($node === $topLevel) {
                break;
            }
        }

        return self::CURATED[$topLevel->getSlug()]['og_image'] ?? null;
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
