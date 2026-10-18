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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seeds / refreshes 4 SEO blog articles with real photos, expanded copy and internal links.
 */
#[AsCommand(
    name: 'app:blog:seed-oct16-batch',
    description: 'Seed/refresh 4 SEO blog articles with real images and internal links',
)]
final class BlogSeedOct16BatchCommand extends Command
{
    /** @var list<string> */
    public const SLUGS = [
        'yak-obraty-hdmi-kabel-dlya-televizora',
        'yak-shovaty-kabeli-za-televizorom',
        'merezhevyy-filtr-chy-dbzh',
        'yak-pidklyuchyty-saundbar-do-televizora',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly BlogCategoryRepository $categoryRepository,
        private readonly BlogArticleRepository $articleRepository,
        private readonly BlogFacebookPostFormatter $facebookPostFormatter,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force-images', null, InputOption::VALUE_NONE, 'Re-download images even if files exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $forceImages = (bool) $input->getOption('force-images');

        $site = $this->siteRepository->find(1);
        $locale = $this->localeRepository->findOneBy(['code' => 'uk']);
        if ($site === null || $locale === null) {
            $io->error('Site #1 or locale uk not found.');

            return Command::FAILURE;
        }

        $category = $this->categoryRepository->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => 'porady',
        ]);
        if ($category === null) {
            $category = (new BlogCategory())
                ->setSite($site)
                ->setLocale($locale)
                ->setName('Поради покупцям')
                ->setSlug('porady')
                ->setEnabled(true)
                ->setPosition(1);
            $this->em->persist($category);
            $this->em->flush();
        }

        $this->ensureImageDirs();
        $rows = [];
        foreach ($this->definitions() as $data) {
            $this->downloadImages($data, $forceImages, $io);
            $article = $this->upsertArticle($site, $locale, $category, $data);
            $rows[] = [
                $article->getSlug(),
                $article->getPublishedAt()?->format('Y-m-d H:i') ?? '',
                $article->getPublishedAt() !== null && $article->getPublishedAt() <= new \DateTimeImmutable()
                    ? 'live'
                    : 'scheduled',
            ];
        }

        $io->table(['Slug', 'Published at', 'Status'], $rows);
        $io->success('4 blog articles updated: real photos, expanded text, internal links.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertArticle(
        object $site,
        object $locale,
        BlogCategory $category,
        array $data,
    ): BlogArticle {
        $article = $this->articleRepository->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => $data['slug'],
        ]) ?? new BlogArticle();

        $hero = '/uploads/blog/'.$data['content_image'];
        $extra = '/uploads/blog/'.$data['content_image_2'];

        $article
            ->setSite($site)
            ->setLocale($locale)
            ->setTitle($data['title'])
            ->setSlug($data['slug'])
            ->setMetaTitle($data['meta_title'])
            ->setMetaDescription($data['meta_description'])
            ->setOgTitle($data['og_title'])
            ->setOgDescription($data['og_description'])
            ->setOgType('article')
            ->setOgImage($data['og_image'])
            ->setTags($data['tags'])
            ->setContent(($data['content'])($hero, $extra, $data['content_image_alt'], $data['content_image_2_alt']))
            ->setEnabled(true)
            ->setPublishedAt(new \DateTimeImmutable($data['published_at']));

        foreach ($article->getCategories()->toArray() as $existing) {
            $article->removeCategory($existing);
        }
        $article->addCategory($category);

        $baseUrl = $this->resolveBaseUrl($site->getDomain());
        $article->setFacebookDraft($this->facebookPostFormatter->format($article, $baseUrl));

        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    /** @return list<array<string, mixed>> */
    private function definitions(): array
    {
        return [
            [
                'slug' => 'yak-obraty-hdmi-kabel-dlya-televizora',
                'title' => 'Як обрати HDMI-кабель для телевізора: версії, довжина і 4K/8K',
                'meta_title' => 'Як обрати HDMI-кабель для ТВ у 2026: HDMI 2.0 vs 2.1, довжина, 4K HDR | Квадро',
                'meta_description' => 'Практичний гід: різниця HDMI 1.4 / 2.0 / 2.1, яка довжина кабелю потрібна, чи важливий «золотий» роз’єм, eARC для саундбара і як не переплатити.',
                'og_title' => 'Як обрати HDMI-кабель для телевізора',
                'og_description' => 'HDMI 2.0 чи 2.1, довжина до 5 м, 4K HDR і eARC — що реально впливає на картинку і звук.',
                'og_image' => 'og-blog-yak-obraty-hdmi-kabel.jpg',
                'content_image' => 'blog-yak-obraty-hdmi-kabel.jpg',
                'content_image_2' => 'blog-yak-obraty-hdmi-kabel-2.jpg',
                'content_image_alt' => 'HDMI та інші кабелі для підключення техніки',
                'content_image_2_alt' => 'Сучасний телевізор у вітальні — джерело 4K-сигналу',
                'tags' => 'hdmi кабель, hdmi 2.1, hdmi 2.0, кабель для телевізора, 4k hdr, earc',
                'published_at' => '2026-10-16 09:00:00',
                'hero_url' => 'https://images.unsplash.com/photo-1625948515291-69613efd103f?auto=format&fit=crop&w=1200&h=675&q=85',
                'extra_url' => 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1?auto=format&fit=crop&w=1200&h=675&q=85',
                'og_url' => 'https://images.unsplash.com/photo-1625948515291-69613efd103f?auto=format&fit=crop&w=1200&h=630&q=85',
                'content' => $this->contentHdmi(...),
            ],
            [
                'slug' => 'yak-shovaty-kabeli-za-televizorom',
                'title' => 'Як сховати кабелі за телевізором: акуратний монтаж без штроблення',
                'meta_title' => 'Як сховати кабелі за ТВ: канал, кронштейн, розетка за екраном | Квадро',
                'meta_description' => 'Покрокові ідеї, як прибрати дроти за телевізором на стіні: кабель-канали, кронштейни з прокладанням, розетка за ТВ і безпечні лайфхаки.',
                'og_title' => 'Як сховати кабелі за телевізором',
                'og_description' => 'Акуратна стіна без «павутини» дротів: кабель-канали, кронштейн і планування розеток.',
                'og_image' => 'og-blog-yak-shovaty-kabeli-za-tv.jpg',
                'content_image' => 'blog-yak-shovaty-kabeli-za-tv.jpg',
                'content_image_2' => 'blog-yak-shovaty-kabeli-za-tv-2.jpg',
                'content_image_alt' => 'Телевізор на стіні в сучасній вітальні',
                'content_image_2_alt' => 'Інтер’єр з ТВ — приклад акуратного монтажу',
                'tags' => 'сховати кабелі, кабель канал, монтаж телевізора, кронштейн тв, unibracket',
                'published_at' => '2026-10-17 10:00:00',
                'hero_url' => 'https://images.unsplash.com/photo-1461151304267-38535e780c79?auto=format&fit=crop&w=1200&h=675&q=85',
                'extra_url' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1200&h=675&q=85',
                'og_url' => 'https://images.unsplash.com/photo-1461151304267-38535e780c79?auto=format&fit=crop&w=1200&h=630&q=85',
                'content' => $this->contentCables(...),
            ],
            [
                'slug' => 'merezhevyy-filtr-chy-dbzh',
                'title' => 'Мережевий фільтр чи ДБЖ: що обрати для телевізора і роутера',
                'meta_title' => 'Мережевий фільтр чи ДБЖ для ТВ і роутера: порівняння 2026 | Квадро',
                'meta_description' => 'Коли вистачить мережевого фільтра, а коли потрібен ДБЖ: стрибки напруги, відключення світла, захист телевізора, роутера і приставки Т2.',
                'og_title' => 'Мережевий фільтр чи ДБЖ — що обрати',
                'og_description' => 'Фільтр захищає від імпульсів, ДБЖ тримає техніку під час блекауту. Коротке порівняння для дому.',
                'og_image' => 'og-blog-merezhevyy-filtr-chy-dbzh.jpg',
                'content_image' => 'blog-merezhevyy-filtr-chy-dbzh.jpg',
                'content_image_2' => 'blog-merezhevyy-filtr-chy-dbzh-2.jpg',
                'content_image_alt' => 'Електроніка і кабелі живлення — захист техніки вдома',
                'content_image_2_alt' => 'Робоче місце з технікою, яку варто захистити від стрибків напруги',
                'tags' => 'мережевий фільтр, дбж, ups, захист техніки, подовжувач, відключення світла',
                'published_at' => '2026-10-18 10:00:00',
                'hero_url' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&h=675&q=85',
                'extra_url' => 'https://images.unsplash.com/photo-1522869635100-9f4c5e86aa37?auto=format&fit=crop&w=1200&h=675&q=85',
                'og_url' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&h=630&q=85',
                'content' => $this->contentFilterUps(...),
            ],
            [
                'slug' => 'yak-pidklyuchyty-saundbar-do-televizora',
                'title' => 'Як підключити саундбар до телевізора: HDMI eARC, оптика і Bluetooth',
                'meta_title' => 'Як підключити саундбар до ТВ: eARC, оптичний кабель, Bluetooth | Квадро',
                'meta_description' => 'Покроково: підключення саундбара через HDMI ARC/eARC, оптику і Bluetooth, налаштування зовнішніх динаміків і типові помилки.',
                'og_title' => 'Як підключити саундбар до телевізора',
                'og_description' => 'eARC, оптика чи Bluetooth — який спосіб обрати і як увімкнути звук через саундбар.',
                'og_image' => 'og-blog-yak-pidklyuchyty-saundbar.jpg',
                'content_image' => 'blog-yak-pidklyuchyty-saundbar.jpg',
                'content_image_2' => 'blog-yak-pidklyuchyty-saundbar-2.jpg',
                'content_image_alt' => 'Акустична система для домашнього кінотеатру',
                'content_image_2_alt' => 'Колонки — приклад якісного звуку в кімнаті',
                'tags' => 'саундбар, hdmi earc, оптичний кабель, bluetooth тв, звук телевізора',
                'published_at' => '2026-10-19 10:00:00',
                'hero_url' => 'https://images.unsplash.com/photo-1545454675-3531b543be5d?auto=format&fit=crop&w=1200&h=675&q=85',
                'extra_url' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=1200&h=675&q=85',
                'og_url' => 'https://images.unsplash.com/photo-1545454675-3531b543be5d?auto=format&fit=crop&w=1200&h=630&q=85',
                'content' => $this->contentSoundbar(...),
            ],
        ];
    }

    private function contentHdmi(string $hero, string $extra, string $alt1, string $alt2): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    На вигляд усі <strong>HDMI-кабелі</strong> однакові, але від версії стандарту залежить,
    чи побачите ви 4K з HDR на 60/120 Гц, чи спрацює eARC для саундбара.
    Нижче — без маркетингового шуму: що реально перевірити перед покупкою і які сторінки магазину варто відкрити поряд.
</p>
<figure class="blog-figure">
    <img src="{$hero}" alt="{$alt1}" width="1200" height="675" loading="lazy">
    <figcaption>Кабелі — «тиха» ланка між ТВ, приставкою і саундбаром; економія тут часто виходить боком</figcaption>
</figure>

<h2>Навіщо взагалі думати про версію HDMI</h2>
<p>
    Сучасний телевізор рідко працює сам: до нього підключають тюнер
    <a href="/uk/category/tuner-t2">DVB-T2</a>,
    приставку, ігрову консоль, ПК або саундбар.
    Якщо кабель «застарий» для потрібного режиму, ви отримаєте не чорний екран, а тихіші симптоми:
    картинка падає з 4K на Full HD, HDR вимикається, звук через ARC пропадає після сну ТВ.
</p>
<p>
    Перед вибором кабелю варто також розуміти, як саме ви вішаєте телевізор:
    у гайді
    <a href="/uk/blog/yak-obraty-kronshteyn-dlya-televizora">як обрати кронштейн для телевізора</a>
    є чеклист по VESA і відстані від стіни — від цього залежить потрібна довжина HDMI.
</p>

<figure class="blog-figure">
    <img src="{$extra}" alt="{$alt2}" width="1200" height="675" loading="lazy">
    <figcaption>4K HDR виглядає ефектно лише коли і панель, і джерело, і кабель тягнуть потрібну смугу</figcaption>
</figure>

<h2>HDMI 1.4, 2.0 і 2.1 — коротка різниця</h2>
<ul class="blog-checklist">
    <li><strong>HDMI 1.4</strong> — Full HD комфортно; 4K лише з обмеженнями (часто 30 Гц)</li>
    <li><strong>HDMI 2.0 / 2.0a/b</strong> — 4K@60 Гц, HDR; оптимально для більшості ТВ 2020–2024</li>
    <li><strong>HDMI 2.1</strong> — 4K@120 Гц, VRR, ALLM; потрібно для нових консолей і ТВ з портами 2.1</li>
</ul>
<div class="blog-note">
    Кабель не «додає» герців сам по собі: і ТВ, і джерело мають підтримувати режим.
    Дивіться маркування порту на задній панелі — іноді лише 1–2 роз’єми з 4 є HDMI 2.1.
</div>

<h2>eARC, саундбар і окремий кабель</h2>
<p>
    Якщо плануєте зовнішній звук, одразу закладайте кабель під ARC/eARC.
    У статті
    <a href="/uk/blog/yak-pidklyuchyty-saundbar-do-televizora">як підключити саундбар до телевізора</a>
    розібрані HDMI, оптика і Bluetooth.
    Для eARC беріть сертифікований HDMI 2.0/2.1 — дешеві «no name» на довгих трасах часто дають клацання в звуці.
</p>

<h2>Яку довжину брати</h2>
<p>
    Для відстані <strong>до 3–5 м</strong> (типовий шлях від тумби / кронштейна до приставки) вистачає звичайного мідного HDMI 2.0/2.1.
    На 10+ м краще активний або оптичний HDMI — інакше можливі артефакти.
    Якщо ТВ уже на стіні, спочатку оберіть
    <a href="/uk/category/kronshteyny">кронштейн</a>
    і прокладіть шлях кабелю (з урахуванням
    <a href="/uk/blog/yak-shovaty-kabeli-za-televizorom">схову дротів за телевізором</a>),
    і лише потім купуйте кабель з запасом 30–50 см.
</p>

<h2>Чеклист перед покупкою</h2>
<ol class="blog-steps">
    <li>Подивіться порти на ТВ (ARC/eARC, HDMI 2.1) і на джерелі.</li>
    <li>Визначте максимальну роздільність і герцовку (4K60 vs 4K120).</li>
    <li>Виміряйте шлях з урахуванням кронштейна і кабель-каналу.</li>
    <li>Не женіться за «позлоченими» роз’ємами — важливіша сертифікація і адекватна довжина.</li>
    <li>Для ефірного ТБ перевірте також
        <a href="/uk/category/digital-tb">розділ цифрового телебачення</a>
        і гайд
        <a href="/uk/blog/cyfrove-telebachennya-t2">що таке Т2 і як підключити</a>.</li>
</ol>

<p>
    Більше практичних матеріалів — у категорії блогу
    <a href="/uk/blog/category/porady">Поради покупцям</a>.
</p>
HTML);
    }

    private function contentCables(string $hero, string $extra, string $alt1, string $alt2): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Телевізор на стіні виглядає стильно лише тоді, коли за ним немає «павутини» дротів.
    Нижче — робочі способи <strong>сховати кабелі</strong> без штроблення, з прив’язкою до кронштейна і розеток.
</p>
<figure class="blog-figure">
    <img src="{$hero}" alt="{$alt1}" width="1200" height="675" loading="lazy">
    <figcaption>Коли екран «висить у повітрі», будь-який видимий дріт одразу псує враження</figcaption>
</figure>

<h2>Спочатку план: кронштейн, розетка, довжина кабелів</h2>
<p>
    Акуратний результат починається не з кабель-каналу, а з розмітки.
    Оберіть місце підвісу за гайдом
    <a href="/uk/blog/yak-obraty-kronshteyn-dlya-televizora">як обрати кронштейн для телевізора</a>,
    перегляньте моделі в каталозі
    <a href="/uk/category/kronshteyny">Кронштейни</a>
    (зокрема
    <a href="/uk/category/unibracket">UniBracket</a>)
    і закладіть розетку / інтернет якомога ближче до центру екрана.
</p>
<p>
    Популярні варіанти кріплень:
    <a href="/uk/product/bz02-21-unibracket">BZ02-21</a>,
    <a href="/uk/product/bz12-22-unibracket">BZ12-22</a> —
    у багатьох є отвори або жолоби під дроти; прокладайте HDMI і живлення <em>до</em> фінального затягування болтів.
</p>

<figure class="blog-figure">
    <img src="{$extra}" alt="{$alt2}" width="1200" height="675" loading="lazy">
    <figcaption>Інтер’єр «читається» цілісно, коли техніка і дроти не конкурують зі стіною</figcaption>
</figure>

<h2>1. Кабель-канал під колір стіни</h2>
<p>
    Найпростіший варіант: пластиковий канал від ТВ до тумби чи розетки.
    Беріть з запасом по ширині — HDMI + живлення + оптика / Ethernet займають місце.
    Канал можна фарбувати; кутові переходи виглядають акуратніше за «злам» гофрою.
</p>

<h2>2. Кронштейн з прокладанням + короткий HDMI</h2>
<p>
    Не купуйте 10-метровий HDMI «про запас», якщо реально треба 2–3 м:
    зайва бухта за екраном заважає скласти кронштейн і псує вентиляцію.
    Про вибір кабелю —
    <a href="/uk/blog/yak-obraty-hdmi-kabel-dlya-televizora">окремий матеріал про HDMI</a>.
    Для звуку дивіться
    <a href="/uk/blog/yak-pidklyuchyty-saundbar-do-televizora">підключення саундбара</a>.
</p>

<h2>3. Розетка і фільтр за телевізором</h2>
<p>
    Ідеально — розетка саме за панеллю. Якщо розетка нижче, сховайте і подовжувач:
    у каталозі є
    <a href="/uk/category/podovzhuvachi-tm-logan-inc">подовжувачі та мережеві фільтри</a>,
    а порівняння з ДБЖ —
    у статті
    <a href="/uk/blog/merezhevyy-filtr-chy-dbzh">мережевий фільтр чи ДБЖ</a>.
</p>
<div class="blog-note">
    Не ховайте кабелі під натяжною стелею без лючка доступу і не перетискайте HDMI гострими краями профілю.
    Живлення — тільки в сертифіковані подовжувачі, не в «скрутки».
</div>

<ol class="blog-steps">
    <li>Розмітьте центр екрана і отвори VESA.</li>
    <li>Закріпіть кронштейн і протягніть кабелі через жолоби.</li>
    <li>Зберіть кабель-канал вертикально до розетки.</li>
    <li>Залиште сервісну петлю 20–30 см, щоб зняти ТВ без обрізання дротів.</li>
</ol>
<p>
    Ще поради з вибору техніки —
    <a href="/uk/blog/category/porady">у блозі «Поради покупцям»</a>.
</p>
HTML);
    }

    private function contentFilterUps(string $hero, string $extra, string $alt1, string $alt2): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Після стрибків напруги і відключень світла питання звучить так:
    <strong>мережевий фільтр чи ДБЖ</strong>?
    Вони вирішують різні задачі: фільтр гасить імпульси, ДБЖ дає час пережити блекаут.
    Часто вдома потрібні обидва — просто на різних лініях.
</p>
<figure class="blog-figure">
    <img src="{$hero}" alt="{$alt1}" width="1200" height="675" loading="lazy">
    <figcaption>Кожна розетка з технікою — точка ризику при імпульсі в мережі</figcaption>
</figure>

<h2>Мережевий фільтр: від чого захищає</h2>
<ul class="blog-checklist">
    <li>Короткі імпульси й перешкоди в мережі 220 В</li>
    <li>Зручне розведення кількох розеток + USB для гаджетів</li>
    <li>Не дає автономії, коли світла вже немає</li>
</ul>
<p>
    У каталозі зібрані готові варіанти —
    <a href="/uk/category/podovzhuvachi-tm-logan-inc">подовжувачі та мережеві фільтри</a>,
    наприклад
    <a href="/uk/product/homeware-hp-5-15-0">фільтр на 6 розеток з USB/Type-C</a>
    чи
    <a href="/uk/product/homeware-hp-5-23-0">компактний F06U на 3 м</a>.
    Для ТВ і приставки це базовий мінімум захисту.
</p>

<figure class="blog-figure">
    <img src="{$extra}" alt="{$alt2}" width="1200" height="675" loading="lazy">
    <figcaption>Роутер і медіатехніка «лягають» першими під час відключення — саме їх часто садять на ДБЖ</figcaption>
</figure>

<h2>ДБЖ (UPS): коли без нього погано</h2>
<ul class="blog-checklist">
    <li>Кілька–десятки хвилин автономії для роутера, ОНТ, тюнера</li>
    <li>Коректне вимкнення ТВ / ПК під час блекауту</li>
    <li>Важливі Вт/ВА і тип хвилі (апроксимована / чиста синусоїда)</li>
</ul>
<p>
    Якщо цікавить еволюція таких рішень —
    почитайте
    <a href="/uk/blog/istoriya-dzherel-bezperebiynogo-zhyvlennya">історію джерел безперебійного живлення</a>.
    Для портативної зарядки гаджетів інший інструмент —
    <a href="/uk/blog/yak-obraty-powerbank">як обрати повербанк</a>
    і підбірка
    <a href="/uk/blog/category/powerbanky">статей про повербанки</a>.
</p>

<div class="blog-note">
    Для роутера часто вистачає компактного ДБЖ на 300–600 ВА.
    Для телевізора рахуйте реальне споживання панелі + саундбар і не перевантажуйте розетки «гірляндою» трійників.
</div>

<h2>Що обрати саме вам</h2>
<ol class="blog-steps">
    <li>Є лише «клацання» в мережі / індукція → якісний мережевий фільтр на лінію ТВ.</li>
    <li>Часті відключення світла → ДБЖ на роутер (інтернет) обовʼязково.</li>
    <li>Дорога AV-система → фільтр на ТВ/саундбар + ДБЖ на мережу; дивіться також
        <a href="/uk/category/digital-tb">цифрове ТБ</a> і
        <a href="/uk/blog/cyfrove-telebachennya-t2">гайд з підключення Т2</a>.</li>
    <li>Кабелі за екраном ховайте акуратно —
        <a href="/uk/blog/yak-shovaty-kabeli-za-televizorom">поради з кабель-менеджменту</a>.</li>
</ol>
HTML);
    }

    private function contentSoundbar(string $hero, string $extra, string $alt1, string $alt2): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Вбудовані динаміки телевізора звучать плоско — тонкий корпус просто не вміщує нормальний НЧ-динамік.
    <strong>Саундбар</strong> додає обʼєм, але якість залежить від підключення:
    HDMI eARC, оптика чи Bluetooth. Нижче — що обрати і в якому порядку налаштовувати меню ТВ.
</p>
<figure class="blog-figure">
    <img src="{$hero}" alt="{$alt1}" width="1200" height="675" loading="lazy">
    <figcaption>Зовнішня акустика розкриває саундтреки й діалоги значно краще за динаміки в рамці ТВ</figcaption>
</figure>

<h2>HDMI ARC / eARC — найкращий варіант</h2>
<p>
    Один кабель між портом ARC/eARC на телевізорі і відповідним портом на саундбарі.
    Потрібен адекватний HDMI — дивіться
    <a href="/uk/blog/yak-obraty-hdmi-kabel-dlya-televizora">гайд з вибору HDMI-кабеля</a>.
    У меню ТВ увімкніть зовнішні динаміки / HDMI ARC і вимкніть динаміки панелі.
</p>
<p>
    Якщо ТВ на кронштейні, заздалегідь заложіть довжину кабелю і шлях за стіною:
    <a href="/uk/blog/yak-obraty-kronshteyn-dlya-televizora">вибір кронштейна</a>,
    каталог
    <a href="/uk/category/kronshteyny">кріплень</a>
    і
    <a href="/uk/blog/yak-shovaty-kabeli-za-televizorom">як сховати дроти</a>.
</p>

<figure class="blog-figure">
    <img src="{$extra}" alt="{$alt2}" width="1200" height="675" loading="lazy">
    <figcaption>Чим чистіший тракт (кабель + правильний порт), тим менше сюрпризів з lip-sync</figcaption>
</figure>

<h2>Оптичний кабель (Toslink)</h2>
<p>
    Стабільний цифровий звук, але без частини сучасних форматів, які дає eARC (наприклад, частини lossless-доріжок).
    Підходить, якщо HDMI зайняті тюнером
    <a href="/uk/category/tuner-t2">DVB-T2</a>,
    приставкою чи ігровою консоллю.
    Про ефірне ТБ загалом —
    <a href="/uk/blog/cyfrove-telebachennya-t2">цифрове телебачення Т2</a>
    і розділ
    <a href="/uk/category/digital-tb">Цифрове телебачення</a>.
</p>

<h2>Bluetooth</h2>
<p>
    Зручно для музики, для фільмів часто з’являється затримка губ.
    Якщо лишаєте Bluetooth — шукайте в меню саундбара/ТВ налаштування lip-sync.
</p>
<div class="blog-note">
    CEC / Anynet+ / Bravia Sync інколи конфліктує з пультом саундбара.
    Якщо ТВ «сам» вмикає не ті виходи — тимчасово вимкніть зайве HDMI-CEC керування.
</div>

<h2>Покрокове підключення</h2>
<ol class="blog-steps">
    <li>Поставте саундбар по центру під екраном (або на стійку / полицю).</li>
    <li>Підключіть HDMI eARC або оптику + живлення.</li>
    <li>У меню ТВ оберіть вихід звуку HDMI ARC / Optical.</li>
    <li>Прогоніть трейлер з діалогами і басом; за потреби підкрутіть sync.</li>
    <li>Живлення саундбара краще вести через
        <a href="/uk/category/podovzhuvachi-tm-logan-inc">мережевий фільтр</a>
        — чому не завжди вистачає трійника, читайте в
        <a href="/uk/blog/merezhevyy-filtr-chy-dbzh">фільтр чи ДБЖ</a>.</li>
</ol>
<p>
    Інші поради з вибору техніки для дому —
    <a href="/uk/blog/category/porady">у розділі блогу «Поради»</a>
    та на
    <a href="/uk/blog">головній блогу</a>.
</p>
HTML);
    }

    private function wrapHtml(string $body): string
    {
        return <<<HTML
<style>
.blog-lead{font-size:1.1rem;line-height:1.65;color:#333;margin:0 0 1.35rem}
.blog-note{padding:.9rem 1.05rem;background:#f5f7fa;border-left:4px solid #f5c518;margin:1.35rem 0;border-radius:0 .45rem .45rem 0}
.blog-steps{margin:1.25rem 0 1.75rem;padding-left:1.25rem}
.blog-steps li{margin:.5rem 0;line-height:1.55}
.blog-checklist{list-style:none;padding:0;margin:1.25rem 0}
.blog-checklist li{padding:.6rem .9rem;margin:0 0 .5rem;border:1px solid #e6e6e6;border-radius:.5rem;background:#fff}
.blog-figure{margin:1.5rem 0;text-align:center}
.blog-figure img{max-width:100%;height:auto;border-radius:.5rem}
.blog-figure figcaption{margin-top:.5rem;font-size:.875rem;color:#666}
.blog-article a{color:#1d4ed8;text-decoration:underline;text-underline-offset:2px}
</style>
<div class="blog-article">
{$body}
</div>
HTML;
    }

    /** @param array<string, mixed> $data */
    private function downloadImages(array $data, bool $force, SymfonyStyle $io): void
    {
        $map = [
            $this->projectDir.'/public/uploads/blog/'.$data['content_image'] => $data['hero_url'],
            $this->projectDir.'/public/uploads/blog/'.$data['content_image_2'] => $data['extra_url'],
            $this->projectDir.'/public/uploads/images/blog/'.$data['og_image'] => $data['og_url'],
        ];

        foreach ($map as $path => $url) {
            $exists = is_file($path);
            $size = $exists ? (int) filesize($path) : 0;
            // Old gradient+text cards were ~35–50KB; real photos are usually larger.
            $looksLikePlaceholder = $exists && $size > 0 && $size < 90000;
            if ($exists && !$force && !$looksLikePlaceholder) {
                continue;
            }
            $this->downloadFile((string) $url, $path, $io);
        }
    }

    private function downloadFile(string $url, string $path, SymfonyStyle $io): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create: '.$dir);
        }

        $tmp = $path.'.tmp';
        $cmd = sprintf('curl -sL --fail --max-time 45 -o %s %s', escapeshellarg($tmp), escapeshellarg($url));
        exec($cmd, $out, $code);
        if ($code !== 0 || !is_file($tmp) || filesize($tmp) < 5000) {
            @unlink($tmp);
            $io->warning('Failed to download: '.$url);

            return;
        }

        $mime = (string) (@mime_content_type($tmp) ?: '');
        if (!str_contains($mime, 'jpeg') && !str_contains($mime, 'jpg') && !str_contains($mime, 'png') && !str_contains($mime, 'webp')) {
            // Unsplash sometimes returns HTML error pages
            $head = (string) file_get_contents($tmp, false, null, 0, 32);
            if (str_starts_with(ltrim($head), '<')) {
                @unlink($tmp);
                $io->warning('Download was not an image: '.$url);

                return;
            }
        }

        rename($tmp, $path);
        $io->text('Image: '.str_replace($this->projectDir.'/', '', $path));
    }

    private function ensureImageDirs(): void
    {
        foreach ([
            $this->projectDir.'/public/uploads/images',
            $this->projectDir.'/public/uploads/images/blog',
            $this->projectDir.'/public/uploads/blog',
        ] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create: '.$dir);
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
}
