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

/**
 * Seeds 4 SEO blog articles (images + OG): one published today, three scheduled one-per-day.
 */
#[AsCommand(
    name: 'app:blog:seed-oct16-batch',
    description: 'Seed 4 SEO blog articles with images (1 live today, 3 scheduled)',
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
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
            $this->generateImages($data);
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
        $io->success('4 blog articles seeded with SEO + images.');

        return Command::SUCCESS;
    }

    /**
     * @param array{
     *     slug: string,
     *     title: string,
     *     meta_title: string,
     *     meta_description: string,
     *     og_title: string,
     *     og_description: string,
     *     og_image: string,
     *     content_image: string,
     *     content_image_alt: string,
     *     tags: string,
     *     published_at: string,
     *     accent: string,
     *     content: callable(string): string
     * } $data
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

        $contentImage = '/uploads/blog/'.$data['content_image'];
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
            ->setContent(($data['content'])($contentImage, $data['content_image_alt']))
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

    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     meta_title: string,
     *     meta_description: string,
     *     og_title: string,
     *     og_description: string,
     *     og_image: string,
     *     content_image: string,
     *     content_image_alt: string,
     *     tags: string,
     *     published_at: string,
     *     accent: string,
     *     content: callable(string, string): string
     * }>
     */
    private function definitions(): array
    {
        return [
            [
                'slug' => 'yak-obraty-hdmi-kabel-dlya-televizora',
                'title' => 'Як обрати HDMI-кабель для телевізора: версії, довжина і 4K/8K',
                'meta_title' => 'Як обрати HDMI-кабель для ТВ у 2026: HDMI 2.0 vs 2.1, довжина, 4K HDR | Квадро',
                'meta_description' => 'Практичний гід: різниця HDMI 1.4 / 2.0 / 2.1, яка довжина кабелю потрібна, чи важливий «золотий» роз’єм і як не переплатити за маркетинг.',
                'og_title' => 'Як обрати HDMI-кабель для телевізора',
                'og_description' => 'HDMI 2.0 чи 2.1, довжина до 5 м, 4K HDR і eARC — коротко, що реально впливає на картинку і звук.',
                'og_image' => 'og-blog-yak-obraty-hdmi-kabel.jpg',
                'content_image' => 'blog-yak-obraty-hdmi-kabel.jpg',
                'content_image_alt' => 'HDMI-кабель підключений до телевізора',
                'tags' => 'hdmi кабель, hdmi 2.1, hdmi 2.0, кабель для телевізора, 4k hdr, earc, кронштейн тв',
                'published_at' => '2026-10-16 09:00:00',
                'accent' => '#2563eb',
                'content' => $this->contentHdmi(...),
            ],
            [
                'slug' => 'yak-shovaty-kabeli-za-televizorom',
                'title' => 'Як сховати кабелі за телевізором: акуратний монтаж без штроблення',
                'meta_title' => 'Як сховати кабелі за ТВ: канал, кабель-канал, бездротові рішення | Квадро',
                'meta_description' => 'Покрокові ідеї, як прибрати дроти за телевізором на стіні: кабель-канали, стійки, кронштейни з прокладанням і безпечні лайфхаки без штроблення.',
                'og_title' => 'Як сховати кабелі за телевізором',
                'og_description' => 'Акуратна стіна без «павутини» дротів: кабель-канали, кронштейн з отворами і правильне планування розеток.',
                'og_image' => 'og-blog-yak-shovaty-kabeli-za-tv.jpg',
                'content_image' => 'blog-yak-shovaty-kabeli-za-tv.jpg',
                'content_image_alt' => 'Телевізор на стіні з акуратним кабель-менеджментом',
                'tags' => 'сховати кабелі, кабель канал, монтаж телевізора, кронштейн тв, інтерʼєр, порядок за тв',
                'published_at' => '2026-10-17 10:00:00',
                'accent' => '#0d9488',
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
                'content_image_alt' => 'Мережевий фільтр і компактний ДБЖ біля телевізора',
                'tags' => 'мережевий фільтр, дбж, ups, захист техніки, відключення світла, роутер, телевізор',
                'published_at' => '2026-10-18 10:00:00',
                'accent' => '#b45309',
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
                'content_image_alt' => 'Саундбар під телевізором на стіні',
                'tags' => 'саундбар, hdmi earc, оптичний кабель, bluetooth тв, звук телевізора, домашний кінотеатр',
                'published_at' => '2026-10-19 10:00:00',
                'accent' => '#7c3aed',
                'content' => $this->contentSoundbar(...),
            ],
        ];
    }

    private function contentHdmi(string $image, string $alt): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    На вигляд усі <strong>HDMI-кабелі</strong> однакові, але від версії стандарту залежить,
    чи побачите ви 4K з HDR на 60/120 Гц, чи спрацює eARC для саундбара.
    Нижче — без маркетингового шуму: що реально перевірити перед покупкою.
</p>
<figure class="blog-figure">
    <img src="{$image}" alt="{$alt}" width="1200" height="675" loading="lazy">
    <figcaption>Якісний HDMI-кабель — основа стабільної картинки і звуку між ТВ, приставкою і саундбаром</figcaption>
</figure>
<h2>HDMI 1.4, 2.0 і 2.1 — коротка різниця</h2>
<ul class="blog-checklist">
    <li><strong>HDMI 1.4</strong> — Full HD комфортно; 4K лише з обмеженнями</li>
    <li><strong>HDMI 2.0 / 2.0a/b</strong> — 4K@60 Гц, HDR; оптимально для більшості ТВ 2020–2024</li>
    <li><strong>HDMI 2.1</strong> — 4K@120 Гц, VRR, ALLM; потрібно для ігрових консолей нового покоління і ТВ з HDMI 2.1</li>
</ul>
<div class="blog-note">
    Кабель не «додає» герців сам по собі: і ТВ, і джерело (приставка, ПК, консоль) мають підтримувати потрібний режим.
</div>
<h2>Яку довжину брати</h2>
<p>
    Для відстані <strong>до 3–5 м</strong> вистачає звичайного мідного кабеля HDMI 2.0/2.1.
    На 10+ м краще активний кабель або оптичний HDMI — інакше можливі артефакти і пропадання сигналу.
</p>
<h2>Чеклист перед покупкою</h2>
<ol class="blog-steps">
    <li>Подивіться, які порти є на ТВ (ARC/eARC, HDMI 2.1).</li>
    <li>Визначте максимальну роздільність джерела (приставка, ПК, консоль).</li>
    <li>Виміряйте шлях кабелю з урахуванням кронштейна і кабель-каналу.</li>
    <li>Не женіться за «позлоченими» роз’ємами — важливіша сертифікація і довжина.</li>
</ol>
HTML);
    }

    private function contentCables(string $image, string $alt): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Телевізор на стіні виглядає стильно лише тоді, коли за ним немає «павутини» дротів.
    Ось робочі способи <strong>сховати кабелі</strong> без штроблення і з мінімумом інструментів.
</p>
<figure class="blog-figure">
    <img src="{$image}" alt="{$alt}" width="1200" height="675" loading="lazy">
    <figcaption>Акуратний кабель-менеджмент робить монтаж ТВ завершеним</figcaption>
</figure>
<h2>1. Кабель-канал під колір стіни</h2>
<p>
    Найпростіший і найдешевший варіант: пластиковий канал від ТВ до розетки / тумби.
    Беріть з запасом по ширині — HDMI + живлення + оптика займають місце.
</p>
<h2>2. Кронштейн з прокладанням кабелів</h2>
<p>
    Багато кронштейнів мають отвори або жолоби під дроти. Прокладайте кабелі <em>до</em> фінального затягування болтів —
    так простіше і акуратніше.
</p>
<h2>3. Розетка за телевізором</h2>
<p>
    Ідеальний сценарій — розетка і інтернет саме за екраном. Якщо ремонту ще немає, закладіть це в план:
    менше видимих дротів і безпечніше для дітей і тварин.
</p>
<div class="blog-note">
    Не ховайте кабелі під натяжною стелею без доступу і не перетискайте HDMI гострими краями профілю.
</div>
HTML);
    }

    private function contentFilterUps(string $image, string $alt): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Після стрибків напруги і відключень світла питання стоїть так:
    <strong>мережевий фільтр чи ДБЖ</strong>? Коротко — вони вирішують різні задачі і часто потрібні обидва.
</p>
<figure class="blog-figure">
    <img src="{$image}" alt="{$alt}" width="1200" height="675" loading="lazy">
    <figcaption>Фільтр гасить імпульси; ДБЖ дає час коректно вимкнути техніку</figcaption>
</figure>
<h2>Мережевий фільтр</h2>
<ul class="blog-checklist">
    <li>Захищає від коротких імпульсів і перешкод у мережі</li>
    <li>Не тримає живлення, коли світла немає</li>
    <li>Дешевший і компактніший</li>
</ul>
<h2>ДБЖ (UPS)</h2>
<ul class="blog-checklist">
    <li>Дає кілька–десятки хвилин автономії для роутера, ОНТ, приставки</li>
    <li>Допомагає коректно вимкнути ТВ / ПК під час блекауту</li>
    <li>Важливі ємність (VA/Вт) і тип хвилі (апроксимована / чиста синусоїда)</li>
</ul>
<div class="blog-note">
    Для роутера часто вистачає компактного ДБЖ на 300–600 ВА.
    Для телевізора рахуйте реальне споживання панелі + саундбар.
</div>
<h2>Що обрати</h2>
<ol class="blog-steps">
    <li>Є лише «клацання» в розетці / індукція від сусідів → почніть з якісного фільтра.</li>
    <li>Часті відключення світла → ДБЖ для мережі (роутер) обовʼязково.</li>
    <li>Дорога AV-техніка → фільтр + ДБЖ за потреби, не економте на заземленні.</li>
</ol>
HTML);
    }

    private function contentSoundbar(string $image, string $alt): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Вбудовані динаміки ТВ звучать плоско. <strong>Саундбар</strong> додає обʼєм, але важливо правильно його підключити:
    HDMI eARC, оптика чи Bluetooth — від цього залежить якість і зручність.
</p>
<figure class="blog-figure">
    <img src="{$image}" alt="{$alt}" width="1200" height="675" loading="lazy">
    <figcaption>Саундбар під екраном: найкраще працює через HDMI ARC/eARC</figcaption>
</figure>
<h2>HDMI ARC / eARC</h2>
<p>
    Найкращий варіант: один HDMI між портом ARC/eARC на ТВ і відповідним портом на саундбарі.
    У меню ТВ увімкніть зовнішні динаміки / HDMI ARC і вимкніть динаміки телевізора.
</p>
<h2>Оптичний кабель (Toslink)</h2>
<p>
    Стабільний цифровий звук, але без частини сучасних форматів, які дає eARC.
    Підходить, якщо на ТВ немає вільного HDMI або ARC зайнятий.
</p>
<h2>Bluetooth</h2>
<p>
    Зручно, але можливі затримки губ (lip-sync). Для фільмів краще кабель; для музики — нормально.
</p>
<div class="blog-note">
    Після підключення перевірте: CEC/Anynet+/Bravia Sync інколи конфліктує з пультом — за потреби вимкніть зайве керування.
</div>
<ol class="blog-steps">
    <li>Підключіть кабель і живлення саундбара.</li>
    <li>Оберіть джерело звуку HDMI ARC / Optical на ТВ.</li>
    <li>Прогоніть тестовий ролик з діалогами і басом.</li>
    <li>За потреби підлаштуйте lip-sync у меню саундбара.</li>
</ol>
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
</style>
{$body}
HTML;
    }

    /** @param array{og_image: string, content_image: string, og_title: string, accent: string} $data */
    private function generateImages(array $data): void
    {
        $ogPath = $this->projectDir.'/public/uploads/images/'.$data['og_image'];
        $contentPath = $this->projectDir.'/public/uploads/blog/'.$data['content_image'];
        $title = $data['og_title'];
        $accent = $data['accent'];

        if (!is_file($ogPath)) {
            $this->renderImage($ogPath, 1200, 630, $title, $accent);
        }
        if (!is_file($contentPath)) {
            $this->renderImage($contentPath, 1200, 675, $title, $accent);
        }
    }

    private function renderImage(string $path, int $width, int $height, string $title, string $accent): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create directory: '.$dir);
        }

        $hex = ltrim($accent, '#');
        $tmpCaption = sys_get_temp_dir().'/blog-caption-'.md5($path).'.png';
        $tmpBg = $path.'.bg.jpg';
        $captionCmd = sprintf(
            'convert -background none -fill white -font DejaVu-Sans-Bold -pointsize %d -size %dx caption:%s %s',
            $width >= 1200 ? 44 : 36,
            (int) ($width * 0.84),
            escapeshellarg($title),
            escapeshellarg($tmpCaption),
        );
        $bgCmd = sprintf(
            'convert -size %dx%d gradient:#%s-#0f172a %s',
            $width,
            $height,
            preg_replace('/[^0-9a-fA-F]/', '', $hex) ?: '2563eb',
            escapeshellarg($tmpBg),
        );
        $composeCmd = sprintf(
            'convert %s %s -gravity center -compose over -composite -quality 88 %s',
            escapeshellarg($tmpBg),
            escapeshellarg($tmpCaption),
            escapeshellarg($path),
        );

        exec($captionCmd.' 2>&1', $out1, $code1);
        exec($bgCmd.' 2>&1', $out2, $code2);
        exec($composeCmd.' 2>&1', $out3, $code3);
        @unlink($tmpCaption);
        @unlink($tmpBg);

        if ($code1 !== 0 || $code2 !== 0 || $code3 !== 0 || !is_file($path)) {
            $this->renderImageGd($path, $width, $height, $title, $accent);
        }
    }

    private function renderImageGd(string $path, int $width, int $height, string $title, string $accent): void
    {
        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            throw new \RuntimeException('GD create failed');
        }
        [$r, $g, $b] = sscanf(ltrim($accent, '#'), '%02x%02x%02x') ?: [37, 99, 235];
        for ($y = 0; $y < $height; ++$y) {
            $t = $y / max(1, $height - 1);
            $col = imagecolorallocate(
                $img,
                (int) ((1 - $t) * (int) $r + $t * 15),
                (int) ((1 - $t) * (int) $g + $t * 23),
                (int) ((1 - $t) * (int) $b + $t * 42),
            );
            imageline($img, 0, $y, $width, $y, $col);
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        $fontFile = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $lines = $this->wrapTextGd($title, 28);
        $size = $width >= 1200 ? 36 : 28;
        $lineHeight = (int) ($size * 1.35);
        $totalH = \count($lines) * $lineHeight;
        $y = (int) (($height - $totalH) / 2) + $size;
        foreach ($lines as $line) {
            if (is_file($fontFile)) {
                $box = imagettfbbox($size, 0, $fontFile, $line);
                $textWidth = $box !== false ? (int) abs($box[2] - $box[0]) : 0;
                imagettftext($img, $size, 0, (int) max(24, ($width - $textWidth) / 2), $y, $white, $fontFile, $line);
            } else {
                imagestring($img, 5, 40, $y - 20, $line, $white);
            }
            $y += $lineHeight;
        }
        imagejpeg($img, $path, 88);
        imagedestroy($img);
    }

    /** @return list<string> */
    private function wrapTextGd(string $text, int $width): array
    {
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $trial = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($trial) > $width && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $trial;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function ensureImageDirs(): void
    {
        foreach ([
            $this->projectDir.'/public/uploads/images',
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
