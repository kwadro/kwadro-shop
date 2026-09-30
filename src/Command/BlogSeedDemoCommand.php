<?php

namespace App\Command;

use App\Entity\BlogArticle;
use App\Entity\BlogCategory;
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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:blog:seed-demo',
    description: 'Seed demo blog categories and Ukrainian articles about powerbanks, T2 and satellite TV',
)]
final class BlogSeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly LocaleRepository $localeRepository,
        private readonly BlogCategoryRepository $categoryRepository,
        private readonly BlogArticleRepository $articleRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
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

        $this->ensureLogos();

        $catAdvice = $this->upsertCategory($site, $locale, 'Поради покупцям', 'porady', 0, null);
        $catTv = $this->upsertCategory($site, $locale, 'Цифрове ТБ', 'cyfrove-tb', 1, null);
        $catSat = $this->upsertCategory($site, $locale, 'Супутникове ТБ', 'suputnykove-tb', 2, null);
        $catPower = $this->upsertCategory($site, $locale, 'Повербанки', 'powerbanky', 0, $catAdvice);

        $articles = [
            [
                'title' => 'Як обрати повербанк: ємність, потужність і порти',
                'slug' => 'yak-obraty-powerbank',
                'meta_title' => 'Як обрати повербанк — поради від магазину Квадро',
                'meta_description' => 'Коротка шпаргалка з вибору повербанка: ємність mAh, Power Delivery, кількість портів і захист.',
                'categories' => [$catAdvice, $catPower],
                'content' => $this->contentPowerbank(),
            ],
            [
                'title' => 'Як обрати антену для тюнера DVB-T2',
                'slug' => 'yak-obraty-antenu-dlya-tunerа-t2',
                'meta_title' => 'Антена для тюнера T2 — як вибрати',
                'meta_description' => 'Кімнатна чи зовнішня антена, підсилювач, кабель і прості поради для стабільного прийому DVB-T2.',
                'categories' => [$catAdvice, $catTv],
                'content' => $this->contentAntenna(),
            ],
            [
                'title' => 'Канали Т2 в Україні: список і жанри',
                'slug' => 'kanaly-t2-spysok-i-zhanry',
                'meta_title' => 'Список каналів Т2 з жанрами та логотипами',
                'meta_description' => 'Огляд популярних каналів ефірного цифрового телебачення DVB-T2 в Україні: новини, кіно, спорт, діти.',
                'categories' => [$catTv],
                'content' => $this->contentT2Channels(),
            ],
            [
                'title' => 'Українські канали на супутниках Amos, Hot Bird і Astra',
                'slug' => 'ukrayinski-kanaly-na-suputnykakh',
                'meta_title' => 'Українські супутникові канали: Amos, Hot Bird, Astra',
                'meta_description' => 'Огляд україномовних телеканалів на трьох супутниках з логотипами та жанрами.',
                'categories' => [$catSat],
                'content' => $this->contentSatelliteChannels(),
            ],
        ];

        // Fix typo in slug - use ascii only
        $articles[1]['slug'] = 'yak-obraty-antenu-dlya-tunera-t2';

        foreach ($articles as $index => $data) {
            $article = $this->articleRepository->findOneBy([
                'site' => $site,
                'locale' => $locale,
                'slug' => $data['slug'],
            ]) ?? new BlogArticle();

            $article
                ->setSite($site)
                ->setLocale($locale)
                ->setTitle($data['title'])
                ->setSlug($data['slug'])
                ->setMetaTitle($data['meta_title'])
                ->setMetaDescription($data['meta_description'])
                ->setContent($data['content'])
                ->setEnabled(true)
                ->setPublishedAt((new \DateTimeImmutable())->modify(sprintf('-%d days', 3 - $index)));

            foreach ($article->getCategories()->toArray() as $existing) {
                $article->removeCategory($existing);
            }
            foreach ($data['categories'] as $category) {
                $article->addCategory($category);
            }

            $this->em->persist($article);
            $io->text('✓ '.$data['title']);
        }

        $this->em->flush();
        $io->success('Demo blog articles seeded (uk).');

        return Command::SUCCESS;
    }

    private function upsertCategory(
        \App\Entity\Site $site,
        \App\Entity\Locale $locale,
        string $name,
        string $slug,
        int $position,
        ?BlogCategory $parent,
    ): BlogCategory {
        $category = $this->categoryRepository->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => $slug,
        ]) ?? new BlogCategory();

        $category
            ->setSite($site)
            ->setLocale($locale)
            ->setName($name)
            ->setSlug($slug)
            ->setPosition($position)
            ->setParent($parent)
            ->setEnabled(true);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    private function ensureLogos(): void
    {
        $dir = $this->projectDir.'/public/uploads/blog/logos';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $logos = [
            'ua-pershyi' => ['UA:П1', '#003399'],
            '1plus1' => ['1+1', '#e30613'],
            'ictv' => ['ICTV', '#d4002a'],
            'stb' => ['СТБ', '#111111'],
            'inter' => ['Інтер', '#0033a0'],
            'novyi' => ['Новий', '#ff6600'],
            'tet' => ['ТЕТ', '#8b1e8b'],
            '2plus2' => ['2+2', '#00a651'],
            'k1' => ['К1', '#c41230'],
            '5kanal' => ['5', '#e31e24'],
            'pixel' => ['PIXEL', '#00a0e3'],
            'enter-film' => ['Enter', '#6b2d8b'],
            'zoom' => ['ZOOM', '#ffcc00'],
            'plusplus' => ['++', '#00aeef'],
            'mega' => ['MEGA', '#ed1c24'],
            'ntn' => ['НТН', '#1a1a1a'],
            'army' => ['Армія', '#4b5320'],
            'rada' => ['Рада', '#0057b8'],
            'suspilne' => ['Суспільне', '#003399'],
            'espreso' => ['Еспресо', '#d32f2f'],
            'pryamyy' => ['Прямий', '#c8102e'],
            'channel24' => ['24', '#e30613'],
            'football1' => ['Ф1', '#006633'],
            'football2' => ['Ф2', '#004d99'],
            'setanta' => ['Setanta', '#e87722'],
            'filmua' => ['FilmUA', '#222222'],
            'sonce' => ['Сонце', '#f4a300'],
            'm1' => ['M1', '#111111'],
            'm2' => ['M2', '#333333'],
            'nlo' => ['NLO', '#5b2c6f'],
        ];

        foreach ($logos as $slug => [$label, $color]) {
            $path = $dir.'/'.$slug.'.svg';
            if (is_file($path)) {
                continue;
            }
            $safe = htmlspecialchars($label, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96" role="img" aria-label="{$safe}">
  <rect width="96" height="96" rx="18" fill="{$color}"/>
  <text x="48" y="54" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="22" font-weight="700" fill="#ffffff">{$safe}</text>
</svg>
SVG;
            file_put_contents($path, $svg);
        }
    }

    private function channelCard(string $logoSlug, string $name, string $genre, string $desc): string
    {
        $logo = '/uploads/blog/logos/'.$logoSlug.'.svg';

        return <<<HTML
<div class="blog-channel-card">
  <img class="blog-channel-card__logo" src="{$logo}" alt="{$name}" width="64" height="64" loading="lazy">
  <div class="blog-channel-card__body">
    <div class="blog-channel-card__name">{$name}</div>
    <div class="blog-channel-card__genre">{$genre}</div>
    <p class="blog-channel-card__desc">{$desc}</p>
  </div>
</div>
HTML;
    }

    private function contentStyles(): string
    {
        return <<<HTML
<style>
.blog-lead{font-size:1.1rem;line-height:1.6;color:#333;margin:0 0 1.25rem}
.blog-note{padding:.85rem 1rem;background:#f5f7fa;border-left:4px solid #f5c518;margin:1.25rem 0;border-radius:0 .4rem .4rem 0}
.blog-channel-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;margin:1.25rem 0 1.75rem}
.blog-channel-card{display:flex;gap:.85rem;align-items:flex-start;padding:1rem;border:1px solid #e6e6e6;border-radius:.75rem;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.blog-channel-card__logo{flex:0 0 64px;width:64px;height:64px;border-radius:14px;object-fit:cover;background:#f0f0f0}
.blog-channel-card__name{font-weight:800;font-size:1.05rem;margin:0 0 .2rem}
.blog-channel-card__genre{display:inline-block;font-size:.75rem;font-weight:700;letter-spacing:.02em;text-transform:uppercase;color:#146c2e;background:#e8f7ea;padding:.15rem .5rem;border-radius:999px;margin-bottom:.45rem}
.blog-channel-card__desc{margin:0;font-size:.92rem;line-height:1.45;color:#444}
.blog-sat-block{margin:1.75rem 0}
.blog-sat-block h3{margin:0 0 .35rem}
.blog-sat-block__meta{color:#666;font-size:.92rem;margin:0 0 1rem}
</style>
HTML;
    }

    private function contentPowerbank(): string
    {
        return $this->contentStyles().<<<HTML
<p class="blog-lead">Повербанк рятує, коли розетка далеко, а телефон сідає у найгірший момент. Нижче — коротка шпаргалка, щоб не промахнутись із покупкою.</p>
<h2>1. Ємність (mAh)</h2>
<ul>
<li><strong>5 000–10 000 mAh</strong> — на день–два, легкий у сумці</li>
<li><strong>10 000–20 000 mAh</strong> — оптимально для більшості</li>
<li><strong>20 000+ mAh</strong> — у дорогу, для роутера чи кількох гаджетів</li>
</ul>
<h2>2. Потужність зарядки</h2>
<p>Шукайте підтримку <strong>Power Delivery (PD)</strong> або швидку зарядку — телефон набере заряд швидше.</p>
<h2>3. Порти та дисплей</h2>
<p>Зручно, коли є <strong>Type-C</strong> і <strong>USB-A</strong>. Цифровий дисплей показує залишок заряду без здогадок.</p>
<h2>4. Захист</h2>
<p>Перевіряйте захист від перегріву, короткого замикання, перевантаження та перезаряду.</p>
<div class="blog-note">У магазині Квадро є повербанки різної ємності — підкажемо модель під ваші задачі.</div>
HTML;
    }

    private function contentAntenna(): string
    {
        return $this->contentStyles().<<<HTML
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
<div class="blog-note">Після встановлення зробіть повний пошук каналів у меню тюнера. Якщо «сипле» один мультиплекс — спробуйте трохи змінити положення антени.</div>
HTML;
    }

    private function contentT2Channels(): string
    {
        $cards = [
            $this->channelCard('ua-pershyi', 'Суспільне / UA:Перший', 'Новини · Публіцистика', 'Суспільно-політичні програми, новини та документалістика.'),
            $this->channelCard('1plus1', '1+1', 'Загальний · Серіали', 'Розважальні шоу, серіали та інформаційні блоки.'),
            $this->channelCard('ictv', 'ICTV', 'Новини · Шоу', 'Новини, ток-шоу, серіали та розважальний контент.'),
            $this->channelCard('stb', 'СТБ', 'Розваги · Реаліті', 'Реаліті-шоу, серіали та вечірній прайм.'),
            $this->channelCard('inter', 'Інтер', 'Загальний', 'Фільми, серіали, новини та розважальні програми.'),
            $this->channelCard('novyi', 'Новий канал', 'Розваги', 'Шоу, гумор, серіали для широкої аудиторії.'),
            $this->channelCard('tet', 'ТЕТ', 'Розваги · Гумор', 'Комедії, розважальні формати та серіали.'),
            $this->channelCard('2plus2', '2+2', 'Спорт · Фільми', 'Спортивні трансляції, бойовики та чоловічий контент.'),
            $this->channelCard('k1', 'К1', 'Фільми · Серіали', 'Кінопокази та серіальний контент.'),
            $this->channelCard('5kanal', '5 канал', 'Новини', 'Інформаційні програми та суспільно-політичні блоки.'),
            $this->channelCard('pixel', 'Піксель', 'Дитячий', 'Мультфільми та програми для дітей.'),
            $this->channelCard('enter-film', 'Enter-фільм', 'Кіно', 'Художні фільми різних жанрів.'),
            $this->channelCard('zoom', 'Zoom', 'Розваги', 'Легкий розважальний контент і серіали.'),
            $this->channelCard('plusplus', 'PlusPlus', 'Дитячий', 'Освітні та розважальні програми для дітей.'),
            $this->channelCard('ntn', 'НТН', 'Фільми', 'Кіно та серіали.'),
            $this->channelCard('army', 'Армія TV', 'Інформаційний', 'Військова тематика, новини та спецпроєкти.'),
        ];

        return $this->contentStyles().<<<HTML
<p class="blog-lead">Ефірне цифрове телебачення DVB-T2 в Україні дає десятки безкоштовних каналів у кількох мультиплексах. Нижче — орієнтовний огляд популярних каналів і їхніх жанрів. Точний склад залежить від регіону та оновлень оператора.</p>
<div class="blog-note">Після зміни частот або робіт на передавачі зробіть повторний пошук каналів у тюнері T2.</div>
<h2>Популярні канали Т2</h2>
<div class="blog-channel-grid">
HTML.implode("\n", $cards).<<<HTML
</div>
<h2>Жанри коротко</h2>
<ul>
<li><strong>Новини / інформація</strong> — оперативні випуски та публіцистика</li>
<li><strong>Загальні / розважальні</strong> — шоу, серіали, прайм-тайм</li>
<li><strong>Кіно</strong> — художні фільми та кінопокази</li>
<li><strong>Спорт</strong> — матчі та спортивні огляди</li>
<li><strong>Дитячі</strong> — мультфільми та освітній контент</li>
</ul>
HTML;
    }

    private function contentSatelliteChannels(): string
    {
        $amos = [
            $this->channelCard('1plus1', '1+1 Україна', 'Загальний', 'Україномовний загальний канал для супутникового прийому.'),
            $this->channelCard('ictv', 'ICTV', 'Новини · Шоу', 'Інформаційно-розважальне мовлення українською.'),
            $this->channelCard('stb', 'СТБ', 'Розваги', 'Реаліті та серіали.'),
            $this->channelCard('novyi', 'Новий канал', 'Розваги', 'Шоу та серіали українською.'),
            $this->channelCard('inter', 'Інтер', 'Загальний', 'Фільми, серіали, новини.'),
            $this->channelCard('suspilne', 'Суспільне', 'Публіцистика', 'Суспільно-політичні програми.'),
        ];
        $hotbird = [
            $this->channelCard('channel24', '24 Канал', 'Новини', 'Новини та аналітика українською.'),
            $this->channelCard('espreso', 'Еспресо TV', 'Новини', 'Інформаційний канал.'),
            $this->channelCard('pryamyy', 'Прямий', 'Новини · Політика', 'Суспільно-політичне мовлення.'),
            $this->channelCard('rada', 'Рада', 'Політика', 'Парламентське телебачення.'),
            $this->channelCard('5kanal', '5 канал', 'Новини', 'Інформаційні програми.'),
            $this->channelCard('m1', 'M1', 'Музика', 'Музичні кліпи та шоу.'),
        ];
        $astra = [
            $this->channelCard('football1', 'Футбол 1', 'Спорт', 'Футбольні трансляції та огляди.'),
            $this->channelCard('football2', 'Футбол 2', 'Спорт', 'Матчі та спортивні програми.'),
            $this->channelCard('setanta', 'Setanta Sports', 'Спорт', 'Міжнародний і український спорт.'),
            $this->channelCard('filmua', 'FilmUA Drama', 'Кіно · Серіали', 'Серіали та кіноконтент.'),
            $this->channelCard('mega', 'MEGA', 'Кіно', 'Фільми українською / з українською доріжкою.'),
            $this->channelCard('nlo', 'NLO TV', 'Розваги · Кіно', 'Фільми та розважальні блоки.'),
        ];

        return $this->contentStyles().<<<HTML
<p class="blog-lead">Для прийому україномовних каналів через тарілку найчастіше використовують позиції <strong>Amos</strong>, <strong>Hot Bird</strong> і <strong>Astra</strong>. Нижче — оглядовий список популярних каналів із жанрами. Параметри (частота, SR, FEC) перевіряйте в актуальному частотному плані — вони змінюються.</p>
<div class="blog-note">У статті зібрані саме україномовні / українські канали. Пакет може вимагати CAM/картку або бути FTA — залежить від конкретного каналу.</div>

<section class="blog-sat-block">
  <h2>Amos (≈ 4° W)</h2>
  <p class="blog-sat-block__meta">Популярна позиція для українського FTA та комерційних пакетів.</p>
  <div class="blog-channel-grid">
HTML.implode("\n", $amos).<<<HTML
  </div>
</section>

<section class="blog-sat-block">
  <h2>Hot Bird (13° E)</h2>
  <p class="blog-sat-block__meta">Європейська позиція з інформаційними та розважальними українськими каналами.</p>
  <div class="blog-channel-grid">
HTML.implode("\n", $hotbird).<<<HTML
  </div>
</section>

<section class="blog-sat-block">
  <h2>Astra (≈ 4.8° E)</h2>
  <p class="blog-sat-block__meta">Позиція з акцентом на спорт і кіноконтент українською.</p>
  <div class="blog-channel-grid">
HTML.implode("\n", $astra).<<<HTML
  </div>
</section>

<h2>Жанри на супутнику</h2>
<ul>
<li><strong>Новини</strong> — оперативне інформування українською</li>
<li><strong>Загальні / розважальні</strong> — шоу та серіали</li>
<li><strong>Спорт</strong> — футбол і спортивні трансляції</li>
<li><strong>Кіно</strong> — фільми та серіальні пакети</li>
<li><strong>Музика</strong> — кліпи та музичні шоу</li>
</ul>
HTML;
    }
}
