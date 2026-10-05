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
    name: 'app:blog:seed-refresh-and-ups',
    description: 'Refresh SEO/OG for existing blog articles and seed UPS history article',
)]
final class BlogSeedRefreshAndUpsCommand extends Command
{
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

        $baseUrl = $this->resolveBaseUrl($site->getDomain());
        $cats = $this->loadCategories($site, $locale);

        foreach ($this->existingArticleUpdates() as $data) {
            $article = $this->articleRepository->findOneBy([
                'site' => $site,
                'locale' => $locale,
                'slug' => $data['slug'],
            ]);
            if ($article === null) {
                $io->warning('Missing article: '.$data['slug']);
                continue;
            }

            $article
                ->setTitle($data['title'])
                ->setMetaTitle($data['meta_title'])
                ->setMetaDescription($data['meta_description'])
                ->setOgTitle($data['og_title'])
                ->setOgDescription($data['og_description'])
                ->setOgType('article')
                ->setOgImage($data['og_image'])
                ->setTags($data['tags']);

            if (isset($data['content'])) {
                $article->setContent($data['content']);
            }

            $article->setFacebookDraft($this->facebookPostFormatter->format($article, $baseUrl));
            $this->em->persist($article);
            $io->text('✓ refreshed '.$data['slug']);
        }

        $ups = $this->articleRepository->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => 'istoriya-dzherel-bezperebiynogo-zhyvlennya',
        ]) ?? new BlogArticle();

        $ups
            ->setSite($site)
            ->setLocale($locale)
            ->setTitle('Історія джерел безперебійного живлення: від резервних станцій до домашнього ДБЖ')
            ->setSlug('istoriya-dzherel-bezperebiynogo-zhyvlennya')
            ->setMetaTitle('Історія ДБЖ (UPS): як з’явилися джерела безперебійного живлення | Квадро')
            ->setMetaDescription('Коротка історія UPS/ДБЖ: від перших резервних систем живлення до сучасних домашніх і офісних безперебійників. Навіщо вони потрібні сьогодні.')
            ->setOgTitle('Історія джерел безперебійного живлення (ДБЖ / UPS)')
            ->setOgDescription('Як з’явилися UPS, чим offline, line-interactive і online відрізняються, і чому ДБЖ досі актуальні вдома й в офісі.')
            ->setOgType('article')
            ->setOgImage('og-blog-istoriya-dbzh.jpg')
            ->setTags('дбж, ups, безперебійник, джерело живлення, історія, повербанк')
            ->setContent($this->contentUpsHistory())
            ->setEnabled(true)
            ->setPublishedAt(new \DateTimeImmutable('2026-10-05 10:00:00', new \DateTimeZone('Europe/Kyiv')));

        foreach ($ups->getCategories()->toArray() as $existing) {
            $ups->removeCategory($existing);
        }
        $ups->addCategory($cats['porady']);
        if (isset($cats['powerbanky'])) {
            $ups->addCategory($cats['powerbanky']);
        }

        $ups->setFacebookDraft($this->facebookPostFormatter->format($ups, $baseUrl));
        $this->em->persist($ups);

        $this->em->flush();
        $io->success('Blog SEO refreshed + UPS article scheduled for 2026-10-05 10:00 (Europe/Kyiv).');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, BlogCategory>
     */
    private function loadCategories(\App\Entity\Site $site, \App\Entity\Locale $locale): array
    {
        $map = [];
        foreach (['porady' => 'Поради покупцям', 'powerbanky' => 'Повербанки', 'cyfrove-tb' => 'Цифрове ТБ'] as $slug => $name) {
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
            $map[$slug] = $category;
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function existingArticleUpdates(): array
    {
        return [
            [
                'slug' => 'yak-obraty-powerbank',
                'title' => 'Як обрати повербанк: ємність, потужність і порти',
                'meta_title' => 'Як обрати повербанк — ємність, PD і порти | Квадро',
                'meta_description' => 'Шпаргалка з вибору повербанка: ємність mAh, Power Delivery, кількість портів, дисплей і захист. Поради магазину Квадро.',
                'og_title' => 'Як обрати повербанк — коротко і по суті',
                'og_description' => 'На що дивитися при покупці powerbank: ємність, швидка зарядка, порти Type-C/USB-A і захист акумулятора.',
                'og_image' => 'og-blog-yak-obraty-powerbank.jpg',
                'tags' => 'повербанк, powerbank, зарядка, поради, PD',
                'content' => $this->contentPowerbank(),
            ],
            [
                'slug' => 'yak-obraty-antenu-dlya-tunera-t2',
                'title' => 'Як обрати антену для тюнера DVB-T2',
                'meta_title' => 'Антена для тюнера T2 — як вибрати | Квадро',
                'meta_description' => 'Кімнатна чи зовнішня антена для DVB-T2, підсилювач, кабель і прості поради для стабільного ефірного сигналу.',
                'og_title' => 'Антена для тюнера Т2 — як не помилитись',
                'og_description' => 'Підбираємо антену під квартиру чи дачу: тип, підсилювач, кабель і розташування для стабільного Т2.',
                'og_image' => 'og-blog-yak-obraty-antenu-t2.jpg',
                'tags' => 'антена, т2, dvb-t2, тюнер, ефір',
                'content' => $this->contentAntenna(),
            ],
            [
                'slug' => 'kanaly-t2-spysok-i-zhanry',
                'title' => 'Канали Т2 в Україні: список і жанри',
                'meta_title' => 'Список каналів Т2 з жанрами | Квадро',
                'meta_description' => 'Огляд популярних каналів ефірного цифрового телебачення DVB-T2 в Україні: новини, кіно, спорт, дитячі та розважальні.',
                'og_title' => 'Канали Т2 в Україні — огляд жанрів',
                'og_description' => 'Які канали дивитися в цифровому ефірі Т2: коротко про жанри та популярні телеканали.',
                'og_image' => 'og-blog-kanaly-t2.jpg',
                'tags' => 'канали т2, цифрове тб, dvb-t2, ефірне тб',
            ],
            [
                'slug' => 'ukrayinski-kanaly-na-suputnykakh',
                'title' => 'Українські канали на супутниках Amos, Hot Bird і Astra',
                'meta_title' => 'Українські супутникові канали: Amos, Hot Bird, Astra | Квадро',
                'meta_description' => 'Огляд україномовних телеканалів на супутниках Amos, Hot Bird і Astra з жанрами та порадами для налаштування.',
                'og_title' => 'Українські канали на супутнику — короткий огляд',
                'og_description' => 'Де шукати українські канали на Amos, Hot Bird і Astra та що врахувати при налаштуванні тарілки.',
                'og_image' => 'og-blog-ukrayinski-kanaly-suputnyk.jpg',
                'tags' => 'супутник, канали, amos, hot bird, astra',
            ],
            [
                'slug' => 'cyfrove-telebachennya-t2',
                'title' => 'Цифрове телебачення Т2: що це і як підключити вдома',
                'meta_title' => 'Цифрове телебачення Т2: як підключити DVB-T2 | Квадро',
                'meta_description' => 'Що таке цифрове телебачення Т2 (DVB-T2) в Україні: обладнання, антена, налаштування тюнера та поради для стабільного ефірного сигналу.',
                'og_title' => 'Цифрове телебачення Т2 — повний гайд з підключення',
                'og_description' => 'Пояснюємо DVB-T2 простими словами: який тюнер і антена потрібні, як налаштувати канали та уникнути слабкого сигналу.',
                'og_image' => 'og-blog-cyfrove-telebachennya-t2.jpg',
                'tags' => 'цифрове телебачення, т2, dvb-t2, тюнер т2, антена',
            ],
            [
                'slug' => 'telebachennya-internet',
                'title' => 'Телебачення і інтернет: чому ефір досі рухає онлайн',
                'meta_title' => 'Телебачення і інтернет: чому ТБ досі важливе | Квадро',
                'meta_description' => 'Чому цифрове ТБ і інтернет доповнюють одне одного: звички перегляду, довіра до ефіру, контент у соцмережах і роль Т2 вдома.',
                'og_title' => 'Телебачення і інтернет — одна медіаекосистема',
                'og_description' => 'ТБ не змагається з інтернетом: ефір формує тренди, онлайн їх підхоплює. Як це працює для глядача в Україні.',
                'og_image' => 'og-blog-tv-internet-ecosystem.jpg',
                'tags' => 'телебачення, інтернет, цифрове тб, т2, медіа, стримінг',
            ],
        ];
    }

    private function styles(): string
    {
        return <<<'HTML'
<style>
.blog-lead{font-size:1.1rem;line-height:1.6;color:#333;margin:0 0 1.25rem}
.blog-note{padding:.85rem 1rem;background:#f5f7fa;border-left:4px solid #f5c518;margin:1.25rem 0;border-radius:0 .4rem .4rem 0}
.blog-figure{margin:1.5rem 0;text-align:center}
.blog-figure img{max-width:100%;height:auto;border-radius:.5rem}
.blog-figure figcaption{margin-top:.5rem;font-size:.875rem;color:#666}
.blog-checklist{list-style:none;padding:0;margin:1.25rem 0}
.blog-checklist li{padding:.55rem .85rem;margin:0 0 .45rem;border:1px solid #e6e6e6;border-radius:.5rem;background:#fff}
</style>
HTML;
    }

    private function contentPowerbank(): string
    {
        return $this->styles().<<<'HTML'
<p class="blog-lead">Повербанк рятує, коли розетка далеко, а телефон сідає у найгірший момент. Нижче — коротка шпаргалка, щоб не промахнутись із покупкою.</p>
<h2>1. Ємність (mAh)</h2>
<ul>
<li><strong>5 000–10 000 mAh</strong> — на день–два, легкий у сумці</li>
<li><strong>10 000–20 000 mAh</strong> — оптимально для більшості</li>
<li><strong>20 000+ mAh</strong> — у дорогу, для роутера чи кількох гаджетів</li>
</ul>
<h2>2. Потужність зарядки</h2>
<p>Шукайте підтримку <strong>Power Delivery (PD)</strong> або швидку зарядку — телефон набере заряд швидше. Для ноутбуків і потужних гаджетів дивіться реальну потужність у ватах, а не лише mAh.</p>
<h2>3. Порти та дисплей</h2>
<p>Зручно, коли є <strong>Type-C</strong> і <strong>USB-A</strong>. Цифровий дисплей показує залишок заряду без здогадок.</p>
<h2>4. Захист</h2>
<p>Перевіряйте захист від перегріву, короткого замикання, перевантаження та перезаряду.</p>
<div class="blog-note">
    У магазині Квадро підкажемо модель під ваші задачі.
    Якщо потрібен резерв для техніки вдома під час вимкнень світла — дивіться також матеріал про
    <a href="/uk/blog/istoriya-dzherel-bezperebiynogo-zhyvlennya">історію джерел безперебійного живлення</a>.
</div>
HTML;
    }

    private function contentAntenna(): string
    {
        return $this->styles().<<<'HTML'
<p class="blog-lead">Стабільна картинка на тюнері DVB-T2 залежить не лише від приймача, а й від антени. Ось як підібрати варіант під квартиру чи будинок.</p>
<h2>Кімнатна чи зовнішня?</h2>
<ul>
<li><strong>Кімнатна</strong> — зручна в місті, якщо передавач близько і немає сильних перешкод</li>
<li><strong>Зовнішня</strong> — кращий вибір за містом, на 1–2 поверсі або при слабкому сигналі</li>
</ul>
<h2>На що звернути увагу</h2>
<ol>
<li><strong>Підсилювач</strong> — допомагає при слабкому сигналі, але зайвий шум при дуже сильному</li>
<li><strong>Кабель</strong> — якісний коаксіал і коротша довжина зменшують втрати</li>
<li><strong>Розташування</strong> — біля вікна, вище від техніки, подалі від Wi‑Fi роутера</li>
<li><strong>Напрямок</strong> — орієнтуйте антену на місцевий передавач Т2</li>
</ol>
<div class="blog-note">
    Після встановлення зробіть повний пошук каналів у меню тюнера.
    Базовий гайд:
    <a href="/uk/blog/cyfrove-telebachennya-t2">як підключити цифрове телебачення Т2</a>
    і каталог
    <a href="/uk/category/antenu">ТВ-антен</a>.
</div>
HTML;
    }

    private function contentUpsHistory(): string
    {
        $image = '/uploads/blog/blog-istoriya-dbzh.jpg';

        return $this->styles().<<<HTML
<p class="blog-lead">
    Джерело безперебійного живлення (ДБЖ, UPS) сьогодні асоціюється з компактним блоком біля комп’ютера чи роутера.
    Але шлях до сучасного UPS почався задовго до домашніх офісів — з потреби захистити критичну техніку від зникнення напруги.
</p>

<figure class="blog-figure">
    <img src="{$image}" alt="Історія джерел безперебійного живлення" width="1200" height="675" loading="lazy">
    <figcaption>Від великих резервних систем до компактних домашніх ДБЖ</figcaption>
</figure>

<h2>Навіщо взагалі з’явився UPS</h2>
<p>
    Щойно обчислювальна техніка й системи зв’язку стали критичними для бізнесу та держави,
    короткі провали напруги почали коштувати дорого: зупинка серверів, втрата даних, обрив зв’язку.
    Рішення шукали в резервному живленні — спочатку великими станціями і акумуляторними банками,
    пізніше — у компактніших модулях, які можна поставити поруч з обладнанням.
</p>

<h2>Короткий шлях розвитку</h2>
<ul class="blog-checklist">
    <li><strong>Ранні резервні системи</strong> — великі акумуляторні масиви й перемикання на резерв для промисловості та зв’язку</li>
    <li><strong>Класичний offline / standby UPS</strong> — просте й доступне рішення: при зникненні мережі живлення переходить на батарею</li>
    <li><strong>Line-interactive</strong> — додає стабілізацію та краще реагує на просідання напруги</li>
    <li><strong>Online (double conversion)</strong> — максимальна якість живлення для серверів і чутливої техніки</li>
</ul>

<h2>Що змінилось для дому й малого офісу</h2>
<p>
    Сучасний ДБЖ уже не лише «для серверної». Його ставлять на роутер, комп’ютер, касову техніку,
    системи відеоспостереження — усе, що має пережити коротке вимкнення світла.
    Паралельно з’явилися повербанки й зарядні станції, але класичний UPS лишається зручним,
    коли потрібно саме <em>безперервне</em> живлення розетки без ручного перемикання.
</p>

<h2>На що дивитися при виборі сьогодні</h2>
<ol>
    <li><strong>Потужність (ВА / Вт)</strong> — запас під реальне навантаження техніки</li>
    <li><strong>Тип</strong> — offline, line-interactive або online залежно від чутливості обладнання</li>
    <li><strong>Час автономії</strong> — хвилини для коректного вимкнення чи довша робота роутера</li>
    <li><strong>Форма хвилі</strong> — апроксимована синусоїда чи чиста синусоїда для чутливих блоків живлення</li>
</ol>

<div class="blog-note">
    Якщо потрібен мобільний резерв для телефону чи планшета — дивіться гайд
    <a href="/uk/blog/yak-obraty-powerbank">як обрати повербанк</a>.
    Для стаціонарної техніки краще підібрати ДБЖ під потужність і час автономії.
</div>

<h2>Висновок</h2>
<p>
    Історія UPS — це історія боротьби за стабільність: від великих резервних станцій до компактних домашніх блоків.
    Сьогодні ДБЖ лишається практичним інструментом, коли важлива не лише «зарядка гаджета»,
    а безперервна робота техніки в моменті, коли мережа зникає.
</p>
HTML;
    }

    private function resolveBaseUrl(?string $domain): string
    {
        if ($domain !== null && $domain !== '' && !str_contains($domain, 'localhost') && !str_ends_with($domain, '.local')) {
            return 'https://'.$domain;
        }

        return rtrim($this->defaultUri, '/');
    }
}
