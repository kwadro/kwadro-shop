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
    name: 'app:blog:seed-powerbank-20000',
    description: 'Seed blog article: how many phone charges from a 20000 mAh power bank',
)]
final class BlogSeedPowerbank20000Command extends Command
{
    private const SLUG = 'skilky-raziv-powerbank-20000-zaryadyt-telefon';
    private const OG_IMAGE = 'og-blog-powerbank-20000-zaryadka-telefonu.jpg';
    private const IMAGE_DESK = '/uploads/blog/blog-powerbank-20000-desk.jpg';
    private const IMAGE_CHARGES = '/uploads/blog/blog-powerbank-20000-charges.jpg';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly BlogCategoryRepository $categoryRepository,
        private readonly BlogArticleRepository $articleRepository,
        private readonly BlogFacebookPostFormatter $facebookPostFormatter,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
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
        foreach (['porady' => 'Поради покупцям', 'powerbanky' => 'Повербанки'] as $slug => $name) {
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
            ->setTitle('Скільки разів повербанк 20000 mAh зарядить телефон')
            ->setSlug(self::SLUG)
            ->setMetaTitle('Скільки разів повербанк 20000 mAh зарядить телефон | Квадро')
            ->setMetaDescription('Реальний розрахунок: скільки повних зарядів смартфона дає повербанк 20000 mAh. Формула, втрати енергії, приклади для батарей 3000–5000 mAh.')
            ->setOgTitle('Скільки разів повербанк 20000 mAh зарядить телефон')
            ->setOgDescription('Не 5–6 «ідеальних» циклів, а реалістична оцінка з урахуванням ККД. Приклади для популярних ємностей батареї телефону.')
            ->setOgType('article')
            ->setOgImage(self::OG_IMAGE)
            ->setTags('повербанк, powerbank, 20000 mAh, зарядка телефону, ємність, поради')
            ->setContent($this->content())
            ->setEnabled(true)
            ->setPublishedAt(new \DateTimeImmutable('2026-10-08 10:00:00'));

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

        $this->warnMissingImages($io);

        $io->success(sprintf('Article seeded: /uk/blog/%s', self::SLUG));
        $io->table(
            ['Field', 'Value'],
            [
                ['slug', self::SLUG],
                ['og_image', self::OG_IMAGE],
                ['content images', self::IMAGE_DESK.', '.self::IMAGE_CHARGES],
            ],
        );

        return Command::SUCCESS;
    }

    private function warnMissingImages(SymfonyStyle $io): void
    {
        $paths = [
            $this->projectDir.'/public/uploads/images/'.self::OG_IMAGE,
            $this->projectDir.'/public'.self::IMAGE_DESK,
            $this->projectDir.'/public'.self::IMAGE_CHARGES,
        ];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                $io->warning('Missing image file: '.$path);
            }
        }
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
        $imageDesk = self::IMAGE_DESK;
        $imageCharges = self::IMAGE_CHARGES;

        return <<<HTML
<style>
.blog-lead{font-size:1.1rem;line-height:1.6;color:#333;margin:0 0 1.25rem}
.blog-note{padding:.85rem 1rem;background:#f5f7fa;border-left:4px solid #f5c518;margin:1.25rem 0;border-radius:0 .4rem .4rem 0}
.blog-figure{margin:1.5rem 0;text-align:center}
.blog-figure img{max-width:100%;height:auto;border-radius:.5rem}
.blog-figure figcaption{margin-top:.5rem;font-size:.875rem;color:#666}
.blog-checklist{list-style:none;padding:0;margin:1.25rem 0}
.blog-checklist li{padding:.55rem .85rem;margin:0 0 .45rem;border:1px solid #e6e6e6;border-radius:.5rem;background:#fff}
.blog-table{width:100%;border-collapse:collapse;margin:1.25rem 0;font-size:.95rem}
.blog-table th,.blog-table td{border:1px solid #e6e6e6;padding:.55rem .7rem;text-align:left}
.blog-table th{background:#f7f7f7}
</style>

<p class="blog-lead">
    На коробці повербанка часто пишуть <strong>20000 mAh</strong> — і здається, що телефон
    з батареєю 4000–5000 mAh можна зарядити «п’ять разів». На практиці виходить менше.
    Нижче — проста формула і реалістичні цифри, без маркетингових перебільшень.
</p>

<figure class="blog-figure">
    <img src="{$imageDesk}" alt="Повербанк 20000 mAh і смартфон на зарядці" width="1200" height="675" loading="lazy">
    <figcaption>Повербанк і телефон: реальна кількість циклів залежить від ККД і ємності батареї</figcaption>
</figure>

<h2>Чому 20000 mAh ≠ 20000 mAh у телефоні</h2>
<p>
    Ємність повербанка зазвичай вказують для елементів на <strong>3,7 В</strong>.
    Телефон заряджається через USB на <strong>5 В</strong> (або вище при PD/QC),
    а частина енергії губиться на перетворення, кабель і нагрівання.
    Реальний «корисний» запас часто становить близько <strong>60–70%</strong> від номіналу.
</p>
<div class="blog-note">
    Орієнтир для швидкої оцінки: корисна ємність ≈ <strong>20000 × 0,65 ≈ 13000 mAh</strong>
    (у перерахунку на заряд телефону). Саме цю цифру ділимо на ємність батареї смартфона.
</div>

<h2>Проста формула</h2>
<p>
    Кількість повних зарядів ≈ <strong>(ємність повербанка × ККД) ÷ ємність батареї телефону</strong>.
</p>
<p>
    Для повербанка 20000 mAh і ККД 0,65:
</p>
<ul class="blog-checklist">
    <li>Телефон <strong>3000 mAh</strong> → близько <strong>4,3</strong> повних зарядів</li>
    <li>Телефон <strong>4000 mAh</strong> → близько <strong>3,2</strong> повних зарядів</li>
    <li>Телефон <strong>5000 mAh</strong> → близько <strong>2,6</strong> повних зарядів</li>
    <li>Телефон <strong>6000 mAh</strong> → близько <strong>2,1</strong> повних зарядів</li>
</ul>

<figure class="blog-figure">
    <img src="{$imageCharges}" alt="Схема: один повербанк 20000 mAh і кілька зарядів смартфона" width="1200" height="675" loading="lazy">
    <figcaption>Один повербанк 20000 mAh зазвичай дає 2–4 повні заряди сучасного смартфона</figcaption>
</figure>

<h2>Таблиця-орієнтир</h2>
<table class="blog-table">
    <thead>
        <tr>
            <th>Батарея телефону</th>
            <th>Очікувані повні заряди (ККД ~65%)</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>3000 mAh</td><td>≈ 4–4,5</td></tr>
        <tr><td>4000 mAh</td><td>≈ 3–3,5</td></tr>
        <tr><td>4500–5000 mAh</td><td>≈ 2,5–3</td></tr>
        <tr><td>5500–6000 mAh</td><td>≈ 2–2,5</td></tr>
    </tbody>
</table>
<p>
    Якщо заряджаєте не «з нуля до 100%», а підтримуєте батарею в діапазоні 20–80%,
    кількість «підзарядок» буде більшою — але повних циклів усе одно не стане як на упаковці.
</p>

<h2>Що зменшує кількість зарядів</h2>
<ul>
    <li><strong>Швидка зарядка</strong> — зручно, але більше втрат на нагрівання</li>
    <li><strong>Тонкий або довгий кабель</strong> — падіння напруги</li>
    <li><strong>Холод / спека</strong> — гірша віддача акумуляторів</li>
    <li><strong>Одночасна зарядка двох пристроїв</strong> — енергія ділиться</li>
    <li><strong>Старий повербанк</strong> — реальна ємність з часом падає</li>
</ul>

<h2>Коли 20000 mAh — хороший вибір</h2>
<p>
    Такий об’єм зручний для подорожей на 1–3 дні, якщо берете телефон, навушники
    і іноді планшет. Для одного смартфона з батареєю ~5000 mAh це зазвичай
    <strong>два–три повні цикли</strong> плюс запас. Якщо потрібен легший варіант «на день» —
    дивіться моделі 10000 mAh; якщо хочете «на тиждень у дорозі» —
    дивіться більші ємності й вагу в руках.
</p>
<div class="blog-note">
    Перед покупкою також варто глянути порти (USB-C PD), максимальну потужність у ватах
    і чи підтримує ваш телефон швидку зарядку. Більше критеріїв —
    у статті <a href="/uk/blog/yak-obraty-powerbank">як обрати повербанк</a>
    та в каталозі <a href="/uk/category/powerbanky">повербанків</a>.
</div>

<h2>Короткий висновок</h2>
<p>
    Повербанк <strong>20000 mAh</strong> рідко дає «п’ять ідеальних зарядів».
    Для типового телефону 4000–5000 mAh реалістично розраховуйте на
    <strong>близько 2,5–3,5 повних циклів</strong>. Якщо виробник обіцяє значно більше —
    перевіряйте, чи не рахують вони часткові підзарядки або ідеальні умови без втрат.
</p>
HTML;
    }
}
