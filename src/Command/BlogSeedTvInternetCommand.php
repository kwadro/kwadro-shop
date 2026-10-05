<?php

namespace App\Command;

use App\Entity\BlogArticle;
use App\Entity\BlogCategory;
use App\Repository\BlogArticleRepository;
use App\Repository\BlogCategoryRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use App\Service\Blog\BlogFacebookPostFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:blog:seed-tv-internet',
    description: 'Seed blog article about TV and internet as one media ecosystem',
)]
final class BlogSeedTvInternetCommand extends Command
{
    private const SLUG = 'telebachennya-i-internet-odna-ekosystema';
    private const OG_IMAGE = 'og-blog-tv-internet-ecosystem.jpg';
    private const ARTICLE_IMAGE = '/uploads/blog/blog-tv-internet-ecosystem.jpg';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly BlogCategoryRepository $categoryRepository,
        private readonly BlogArticleRepository $articleRepository,
        private readonly BlogFacebookPostFormatter $facebookPostFormatter,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
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

        $categories = [];
        foreach (['cyfrove-tb' => 'Цифрове ТБ', 'porady' => 'Поради покупцям'] as $slug => $name) {
            $category = $this->categoryRepository->findOneBy([
                'site' => $site,
                'locale' => $locale,
                'slug' => $slug,
            ]);
            if ($category === null) {
                $category = (new BlogCategory())
                    ->setSite($site)
                    ->setLocale($locale)
                    ->setName($name)
                    ->setSlug($slug)
                    ->setEnabled(true);
                $this->em->persist($category);
                $this->em->flush();
            }
            $categories[] = $category;
        }

        $article = $this->articleRepository->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => self::SLUG,
        ]) ?? new BlogArticle();

        $article
            ->setSite($site)
            ->setLocale($locale)
            ->setTitle('Телебачення і інтернет: чому ефір досі рухає онлайн')
            ->setSlug(self::SLUG)
            ->setMetaTitle('Телебачення і інтернет: чому ТБ досі важливе | Квадро')
            ->setMetaDescription('Чому цифрове ТБ і інтернет доповнюють одне одного: звички перегляду, довіра до ефіру, контент у соцмережах і роль Т2 вдома.')
            ->setOgTitle('Телебачення і інтернет — одна медіаекосистема')
            ->setOgDescription('ТБ не змагається з інтернетом: ефір формує тренди, онлайн їх підхоплює. Як це працює для глядача в Україні.')
            ->setOgType('article')
            ->setOgImage(self::OG_IMAGE)
            ->setTags('телебачення, інтернет, цифрове тб, т2, медіа, стримінг')
            ->setContent($this->content())
            ->setEnabled(true)
            ->setPublishedAt(new \DateTimeImmutable('2026-10-04 08:00:00'));

        foreach ($article->getCategories()->toArray() as $existing) {
            $article->removeCategory($existing);
        }
        foreach ($categories as $category) {
            $article->addCategory($category);
        }

        $baseUrl = $this->resolveBaseUrl($site->getDomain());
        $article->setFacebookDraft($this->facebookPostFormatter->format($article, $baseUrl));

        $this->em->persist($article);
        $this->em->flush();

        $io->success(sprintf('Article seeded: /uk/blog/%s', self::SLUG));

        return Command::SUCCESS;
    }

    private function resolveBaseUrl(?string $domain): string
    {
        if ($domain !== null && $domain !== '' && !str_contains($domain, 'localhost') && !str_ends_with($domain, '.local')) {
            return 'https://'.$domain;
        }

        return rtrim($this->defaultUri, '/');
    }

    private function content(): string
    {
        $image = self::ARTICLE_IMAGE;

        return <<<HTML
<style>
.blog-lead{font-size:1.1rem;line-height:1.6;color:#333;margin:0 0 1.25rem}
.blog-note{padding:.85rem 1rem;background:#f5f7fa;border-left:4px solid #f5c518;margin:1.25rem 0;border-radius:0 .4rem .4rem 0}
.blog-figure{margin:1.5rem 0;text-align:center}
.blog-figure img{max-width:100%;height:auto;border-radius:.5rem}
.blog-figure figcaption{margin-top:.5rem;font-size:.875rem;color:#666}
.blog-checklist{list-style:none;padding:0;margin:1.25rem 0}
.blog-checklist li{padding:.55rem .85rem;margin:0 0 .45rem;border:1px solid #e6e6e6;border-radius:.5rem;background:#fff}
</style>

<p class="blog-lead">
    Чи здатен інтернет повністю замінити телебачення? Питання звучить голосно,
    але на практиці відповідь простіша: <strong>ефір і онлайн уже працюють як одна система</strong>.
    ТБ задає спільний момент і довіру, інтернет — глибину, повтор і обговорення.
</p>

<figure class="blog-figure">
    <img src="{$image}" alt="Телебачення і інтернет у одній медіаекосистемі" width="1200" height="675" loading="lazy">
    <figcaption>Великий екран і гаджети поруч — типова схема перегляду сьогодні</figcaption>
</figure>

<h2>Чому ТБ нікуди не зникає</h2>
<p>
    Цифрові платформи зручні й персоналізовані, але ефірне телебачення лишається
    звичним способом отримати новини та розваги «без зайвих кліків».
    Спільний перегляд спорту, святкових програм чи вечірнього випуску досі збирає сім’ю біля одного екрана —
    цього складно повторити стрічкою коротких відео.
</p>
<p>
    В умовах нестабільного зв’язку чи блекаутів окреме значення має
    <a href="/uk/blog/cyfrove-telebachennya-t2">цифрове ефірне ТБ (DVB-T2)</a>:
    сигнал іде з наземних веж, а не залежить від домашнього інтернету.
</p>

<h2>Інтернет не замінює ефір — він його підсилює</h2>
<p>
    Онлайн дає вибір часу, паузу, рекомендації й коментарі. Але часто саме телевізійна подія
    стає приводом відкрити ютуб, соцмережі чи сайт каналу.
    Хайлайти, меми, фрагменти шоу й обговорення народжуються після ефіру — і повертають увагу назад до бренду програми.
</p>
<ul class="blog-checklist">
    <li><strong>Ефір</strong> — масове охоплення й спільний момент</li>
    <li><strong>Стримінг і catch-up</strong> — перегляд у зручний час</li>
    <li><strong>Соцмережі</strong> — реакції, короткі кліпи, віральність</li>
    <li><strong>Пошук і сайти</strong> — деталі, повтори, архів</li>
</ul>

<h2>Що це означає для глядача вдома</h2>
<p>
    Оптимальна схема сьогодні — не «або ТБ, або інтернет», а комбінація.
    Великий екран для вечірнього перегляду, смартфон для кліпів і новин у дорозі,
    а <a href="/uk/category/tuner-t2">тюнер Т2</a> з антеною — як стабільний запасний канал,
    коли мережа «лягає».
</p>
<div class="blog-note">
    Якщо цікавить практичний бік: дивіться наш гайд
    <a href="/uk/blog/cyfrove-telebachennya-t2">як підключити цифрове телебачення Т2</a>
    і <a href="/uk/blog/kanaly-t2-spysok-i-zhanry">список каналів Т2</a>.
</div>

<h2>Довіра як перевага ефіру</h2>
<p>
    У потоці соцмереж легко загубитися між чутками й короткими заголовками.
    Телевізійний випуск із редакційною відповідальністю для багатьох лишається
    «якірним» джерелом. Саме тому бренди й надалі цінують ТБ:
    охоплення, емоція на великому екрані й відчуття авторитету.
</p>

<h2>Яким буде ТБ далі</h2>
<p>
    Майбутнє — за мультиплатформністю: той самий контент живе в ефірі, у застосунках,
    на YouTube і в коротких форматах. Перемагають ті, хто розуміє аудиторію
    і дає якісний, впізнаваний продукт скрізь, де її дивляться.
</p>
<p>
    Для дому висновок простий: інтернет розширює вибір,
    а цифрове телебачення тримає базовий, зрозумілий і часто надійніший канал доступу до ефіру.
    Разом вони й утворюють сучасну медіаекосистему — не конкурентів, а партнерів.
</p>
HTML;
    }
}
