<?php

namespace App\Command;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:category:seed-kronshteyny',
    description: 'Seed Кронштейни category and SiViTek sheet subcategories with SEO/OG',
)]
final class CategorySeedKronshteynyCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $default = $this->categoryRepository->findOrCreateDefault();

        $parent = $this->upsertCategory(
            name: 'Кронштейни',
            slug: 'kronshteyny',
            parent: $default,
            position: 2,
            metaTitle: 'Кронштейни — купити кріплення для ТВ і техніки | Квадро',
            metaDescription: 'Кронштейни та кріплення для телевізорів, моніторів, мікрохвильовок, проекторів і планшетів. Доставка по Україні.',
            ogTitle: 'Кронштейни та кріплення | Квадро',
            ogDescription: 'Каталог кронштейнів і кріплень: ТВ, монітори, СВЧ, проектори, стійки та аксесуари.',
            ogImage: 'og-category-kronshteyny.jpg',
        );
        $io->text('✓ '.$parent->getName());

        $position = 1;
        foreach ($this->subcategories() as $row) {
            $category = $this->upsertCategory(
                name: $row['name'],
                slug: $row['slug'],
                parent: $parent,
                position: $position,
                metaTitle: $row['meta_title'],
                metaDescription: $row['meta_description'],
                ogTitle: $row['og_title'],
                ogDescription: $row['og_description'],
                ogImage: $row['og_image'],
            );
            $io->text(sprintf('  ✓ %s (%s)', $category->getName(), $category->getSlug()));
            ++$position;
        }

        $this->em->flush();
        $io->success(sprintf('Кронштейни + %d subcategories seeded with SEO/OG.', \count($this->subcategories())));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{
     *   name: string,
     *   slug: string,
     *   meta_title: string,
     *   meta_description: string,
     *   og_title: string,
     *   og_description: string,
     *   og_image: string
     * }>
     */
    private function subcategories(): array
    {
        // Sheet names from SiViTek price file (except generic "Price")
        return [
            [
                'name' => 'UniBracket',
                'slug' => 'unibracket',
                'meta_title' => 'Кронштейни UniBracket — купити | Квадро',
                'meta_description' => 'Кронштейни UniBracket для ТВ та техніки. Надійне кріплення, доставка по Україні.',
                'og_title' => 'UniBracket — кронштейни | Квадро',
                'og_description' => 'Каталог кронштейнів UniBracket для телевізорів і обладнання.',
                'og_image' => 'og-category-unibracket.jpg',
            ],
            [
                'name' => 'iTECHmount',
                'slug' => 'itechmount',
                'meta_title' => 'Кронштейни iTECHmount — купити | Квадро',
                'meta_description' => 'Кронштейни iTECHmount для телевізорів і моніторів. Якісне кріплення з доставкою.',
                'og_title' => 'iTECHmount — кронштейни | Квадро',
                'og_description' => 'Каталог кріплень iTECHmount для ТВ і моніторів.',
                'og_image' => 'og-category-itechmount.jpg',
            ],
            [
                'name' => 'Brateck',
                'slug' => 'brateck',
                'meta_title' => 'Кронштейни Brateck — купити | Квадро',
                'meta_description' => 'Кронштейни Brateck для ТВ, моніторів та техніки. Широкий вибір моделей.',
                'og_title' => 'Brateck — кронштейни | Квадро',
                'og_description' => 'Каталог кронштейнів Brateck для дому та офісу.',
                'og_image' => 'og-category-brateck.jpg',
            ],
            [
                'name' => 'для СВЧ',
                'slug' => 'dlya-svch',
                'meta_title' => 'Кронштейни для СВЧ — купити кріплення | Квадро',
                'meta_description' => 'Кронштейни та кріплення для мікрохвильових печей. Зручний монтаж, доставка по Україні.',
                'og_title' => 'Кронштейни для СВЧ | Квадро',
                'og_description' => 'Кріплення для мікрохвильовок: компактні рішення для кухні.',
                'og_image' => 'og-category-dlya-svch.jpg',
            ],
            [
                'name' => 'настольные для мониторов и тв',
                'slug' => 'nastolnye-dlya-monitorov-i-tv',
                'meta_title' => 'Настільні кронштейни для моніторів і ТВ | Квадро',
                'meta_description' => 'Настільні кронштейни та стійки для моніторів і телевізорів. Ергономіка робочого місця.',
                'og_title' => 'Настільні кронштейни для моніторів і ТВ',
                'og_description' => 'Настільні кріплення для моніторів і телевізорів — зручно для дому та офісу.',
                'og_image' => 'og-category-nastolnye-monitorov-tv.jpg',
            ],
            [
                'name' => 'Стійки, столи, тумби',
                'slug' => 'stiyky-stoly-tumby',
                'meta_title' => 'Стійки, столи, тумби для ТВ — купити | Квадро',
                'meta_description' => 'ТВ-стійки, столи та тумби для техніки. Практичні рішення для вітальні та офісу.',
                'og_title' => 'Стійки, столи, тумби | Квадро',
                'og_description' => 'Каталог стійок, столів і тумб для телевізорів та техніки.',
                'og_image' => 'og-category-stiyky-stoly-tumby.jpg',
            ],
            [
                'name' => 'для проекторов',
                'slug' => 'dlya-proektorov',
                'meta_title' => 'Кронштейни для проекторів — купити | Квадро',
                'meta_description' => 'Стельові та настінні кронштейни для проекторів. Надійний монтаж і доставка.',
                'og_title' => 'Кронштейни для проекторів | Квадро',
                'og_description' => 'Кріплення для проекторів: стеля, стіна, зручне налаштування.',
                'og_image' => 'og-category-dlya-proektorov.jpg',
            ],
            [
                'name' => 'Кабель TM Ultra',
                'slug' => 'kabel-tm-ultra',
                'meta_title' => 'Кабель TM Ultra — купити | Квадро',
                'meta_description' => 'Кабелі TM Ultra для аудіо/відео та підключення техніки. Якість і доставка по Україні.',
                'og_title' => 'Кабель TM Ultra | Квадро',
                'og_description' => 'Асортимент кабелів TM Ultra для техніки та інсталяцій.',
                'og_image' => 'og-category-kabel-tm-ultra.jpg',
            ],
            [
                'name' => 'Кабель TM Prolink',
                'slug' => 'kabel-tm-prolink',
                'meta_title' => 'Кабель TM Prolink — купити | Квадро',
                'meta_description' => 'Кабелі TM Prolink для підключення ТВ, аудіо та периферії. Доставка Новою Поштою.',
                'og_title' => 'Кабель TM Prolink | Квадро',
                'og_description' => 'Каталог кабелів TM Prolink для домашньої та офісної техніки.',
                'og_image' => 'og-category-kabel-tm-prolink.jpg',
            ],
            [
                'name' => 'Кабель TM Logan inc',
                'slug' => 'kabel-tm-logan-inc',
                'meta_title' => 'Кабель TM Logan inc — купити | Квадро',
                'meta_description' => 'Кабелі TM Logan inc для електроніки та інсталяцій. Наявність і швидка доставка.',
                'og_title' => 'Кабель TM Logan inc | Квадро',
                'og_description' => 'Кабелі TM Logan inc — надійне підключення техніки.',
                'og_image' => 'og-category-kabel-tm-logan.jpg',
            ],
            [
                'name' => 'Подовжувачі ТМ Logan inc',
                'slug' => 'podovzhuvachi-tm-logan-inc',
                'meta_title' => 'Подовжувачі ТМ Logan inc — купити | Квадро',
                'meta_description' => 'Подовжувачі TM Logan inc для дому та офісу. Безпечне живлення техніки.',
                'og_title' => 'Подовжувачі ТМ Logan inc | Квадро',
                'og_description' => 'Каталог подовжувачів Logan inc для зручного підключення техніки.',
                'og_image' => 'og-category-podovzhuvachi-logan.jpg',
            ],
            [
                'name' => 'LED лампи',
                'slug' => 'led-lampy',
                'meta_title' => 'LED лампи — купити | Квадро',
                'meta_description' => 'Світлодіодні LED лампи для дому та офісу. Економія енергії, доставка по Україні.',
                'og_title' => 'LED лампи | Квадро',
                'og_description' => 'Каталог LED ламп для освітлення дому та робочого місця.',
                'og_image' => 'og-category-led-lampy.jpg',
            ],
            [
                'name' => 'Побутові товари',
                'slug' => 'pobutovi-tovary',
                'meta_title' => 'Побутові товари — купити | Квадро',
                'meta_description' => 'Побутові товари та корисні аксесуари для дому. Доставка Новою Поштою.',
                'og_title' => 'Побутові товари | Квадро',
                'og_description' => 'Аксесуари та побутові товари для дому в каталозі Квадро.',
                'og_image' => 'og-category-pobutovi-tovary.jpg',
            ],
            [
                'name' => 'Тримачі для планшетів',
                'slug' => 'trymachi-dlya-planshetiv',
                'meta_title' => 'Тримачі для планшетів — купити | Квадро',
                'meta_description' => 'Тримачі та кріплення для планшетів. Зручний кут огляду для дому, авто та офісу.',
                'og_title' => 'Тримачі для планшетів | Квадро',
                'og_description' => 'Каталог тримачів для планшетів — комфортний перегляд і робота.',
                'og_image' => 'og-category-trymachi-planshetiv.jpg',
            ],
        ];
    }

    private function upsertCategory(
        string $name,
        string $slug,
        Category $parent,
        int $position,
        string $metaTitle,
        string $metaDescription,
        string $ogTitle,
        string $ogDescription,
        string $ogImage,
    ): Category {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]) ?? new Category();
        $category
            ->setName($name)
            ->setSlug($slug)
            ->setParent($parent)
            ->setEnabled(true)
            ->setPosition($position)
            ->setMetaTitle($metaTitle)
            ->setMetaDescription($metaDescription)
            ->setOgTitle($ogTitle)
            ->setOgDescription($ogDescription)
            ->setOgType('website')
            ->setOgImage($ogImage);
        $category->recalculateLevel();
        $this->em->persist($category);

        return $category;
    }
}
