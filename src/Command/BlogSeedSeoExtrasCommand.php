<?php

namespace App\Command;

use App\Repository\BlogArticleRepository;
use App\Repository\BlogCategoryRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:blog:seed-seo-extras',
    description: 'Seed blog article tags and category SEO/OG fields',
)]
final class BlogSeedSeoExtrasCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly BlogCategoryRepository $categoryRepository,
        private readonly BlogArticleRepository $articleRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $site = $this->siteRepository->find(1);
        $locale = $this->localeRepository->findOneBy(['code' => 'uk']);
        if ($site === null || $locale === null) {
            $io->error('Site #1 or locale uk not found.');

            return Command::FAILURE;
        }

        $categories = [
            'porady' => [
                'meta_title' => 'Поради покупцям — блог Квадро',
                'meta_description' => 'Практичні поради з вибору тюнерів, антен, повербанків та цифрового ТБ від магазину Квадро.',
                'og_title' => 'Поради покупцям | Блог Квадро',
                'og_description' => 'Корисні гіди для покупців: як обрати обладнання для Т2, супутника та гаджетів.',
                'og_image' => 'og-blog-category-porady.jpg',
            ],
            'cyfrove-tb' => [
                'meta_title' => 'Цифрове ТБ — статті про DVB-T2 | Квадро',
                'meta_description' => 'Статті про цифрове ефірне телебачення Т2: підключення, канали, тюнери та антени.',
                'og_title' => 'Цифрове ТБ | Блог Квадро',
                'og_description' => 'Гайди з цифрового телебачення DVB-T2 в Україні від магазину Квадро.',
                'og_image' => 'og-blog-category-cyfrove-tb.jpg',
            ],
            'suputnykove-tb' => [
                'meta_title' => 'Супутникове ТБ — статті та огляди | Квадро',
                'meta_description' => 'Матеріали про супутникове телебачення: українські канали, супутники Amos, Hot Bird, Astra.',
                'og_title' => 'Супутникове ТБ | Блог Квадро',
                'og_description' => 'Огляди супутникових каналів і поради з налаштування від магазину Квадро.',
                'og_image' => 'og-blog-category-suputnykove-tb.jpg',
            ],
            'powerbanky' => [
                'meta_title' => 'Повербанки — поради з вибору | Квадро',
                'meta_description' => 'Як обрати повербанк: ємність, потужність, порти та захист. Поради магазину Квадро.',
                'og_title' => 'Повербанки | Блог Квадро',
                'og_description' => 'Короткі гіди з вибору powerbank для телефону, роутера та подорожей.',
                'og_image' => 'og-blog-category-powerbanky.jpg',
            ],
        ];

        foreach ($categories as $slug => $seo) {
            $category = $this->categoryRepository->findOneBy([
                'site' => $site,
                'locale' => $locale,
                'slug' => $slug,
            ]);
            if ($category === null) {
                $io->warning('Category not found: '.$slug);
                continue;
            }
            $category
                ->setMetaTitle($seo['meta_title'])
                ->setMetaDescription($seo['meta_description'])
                ->setOgTitle($seo['og_title'])
                ->setOgDescription($seo['og_description'])
                ->setOgType('website')
                ->setOgImage($seo['og_image']);
            $this->em->persist($category);
            $io->text('✓ category '.$slug);
        }

        $articleTags = [
            'yak-obraty-powerbank' => 'повербанк, powerbank, зарядка, поради',
            'yak-obraty-antenu-dlya-tunera-t2' => 'антена, т2, dvb-t2, тюнер',
            'kanaly-t2-spysok-i-zhanry' => 'канали т2, цифрове тб, dvb-t2',
            'ukrayinski-kanaly-na-suputnykakh' => 'супутник, канали, amos, hot bird, astra',
            'cyfrove-telebachennya-t2' => 'цифрове телебачення, т2, dvb-t2, тюнер т2, антена',
        ];

        foreach ($articleTags as $slug => $tags) {
            $article = $this->articleRepository->findOneBy([
                'site' => $site,
                'locale' => $locale,
                'slug' => $slug,
            ]);
            if ($article === null) {
                $io->warning('Article not found: '.$slug);
                continue;
            }
            $article->setTags($tags);
            $this->em->persist($article);
            $io->text('✓ tags '.$slug);
        }

        $this->em->flush();
        $io->success('Blog SEO extras seeded.');

        return Command::SUCCESS;
    }
}
