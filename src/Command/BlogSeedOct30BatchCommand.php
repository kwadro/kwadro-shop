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
 * Seeds 3 buyer-advice articles with downloaded photos and staggered publish dates.
 */
#[AsCommand(
    name: 'app:blog:seed-oct30-batch',
    description: 'Seed 3 blog articles: TV size, Smart TV network, power bank during outages',
)]
final class BlogSeedOct30BatchCommand extends Command
{
    /** @var list<string> */
    public const SLUGS = [
        'yak-pidibraty-diagonal-televizora',
        'wifi-chy-kabel-dlya-smart-tv',
        'shcho-zhyvyty-powerbankom-pid-chas-vidklyuchennya',
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

        $categories = $this->ensureCategories($site, $locale);
        $this->ensureImageDirs();

        $rows = [];
        $failed = false;
        foreach ($this->definitions() as $data) {
            if (!$this->downloadImages($data, $forceImages, $io)) {
                $failed = true;
            }
            $article = $this->upsertArticle($site, $locale, $categories, $data);
            $rows[] = [
                $article->getSlug(),
                $article->getPublishedAt()?->format('Y-m-d H:i') ?? '',
                $article->getPublishedAt() !== null && $article->getPublishedAt() <= new \DateTimeImmutable()
                    ? 'live'
                    : 'scheduled',
            ];
        }

        $io->table(['Slug', 'Published at', 'Status'], $rows);
        if ($failed) {
            $io->error('One or more images failed to download. Articles were saved, but photos may be missing.');

            return Command::FAILURE;
        }

        $io->success('3 blog articles saved with real photos and staggered publish dates.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, BlogCategory> $categories
     * @param array<string, mixed> $data
     */
    private function upsertArticle(
        object $site,
        object $locale,
        array $categories,
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
        foreach ($data['categories'] as $slug) {
            if (isset($categories[$slug])) {
                $article->addCategory($categories[$slug]);
            }
        }

        $baseUrl = $this->resolveBaseUrl($site->getDomain());
        $article->setFacebookDraft($this->facebookPostFormatter->format($article, $baseUrl));

        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    /**
     * @return array<string, BlogCategory>
     */
    private function ensureCategories(object $site, object $locale): array
    {
        $map = [];
        foreach ([
            'porady' => 'Поради покупцям',
            'powerbanky' => 'Повербанки',
        ] as $slug => $name) {
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
                    ->setEnabled(true)
                    ->setPosition(1);
                $this->em->persist($category);
                $this->em->flush();
            }
            $map[$slug] = $category;
        }

        return $map;
    }

    /** @return list<array<string, mixed>> */
    private function definitions(): array
    {
        return [
            [
                'slug' => 'yak-pidibraty-diagonal-televizora',
                'title' => 'Як підібрати діагональ телевізора під кімнату: відстань від дивана',
                'meta_title' => 'Як підібрати діагональ телевізора під кімнату у 2026 | Квадро',
                'meta_description' => 'Практична таблиця: 43, 55 чи 65 дюймів під вашу відстань від дивана. На що дивитись у 4K, як міряти кімнату і на якій висоті вішати екран.',
                'og_title' => 'Як підібрати діагональ телевізора під кімнату',
                'og_description' => 'Не купуйте «найбільший, що влізає у вітрину». Відстань від дивана важливіша за дюйми на ціннику.',
                'og_image' => 'og-blog-yak-pidibraty-diagonal-televizora.jpg',
                'content_image' => 'blog-yak-pidibraty-diagonal-televizora.jpg',
                'content_image_2' => 'blog-yak-pidibraty-diagonal-televizora-2.jpg',
                'content_image_alt' => 'Вітальня з диваном і телевізором на стіні — відстань перегляду',
                'content_image_2_alt' => 'Телевізор у світлій вітальні навпроти м’яких меблів',
                'tags' => 'діагональ телевізора, розмір тв, відстань від дивана, 55 дюймів, 65 дюймів, 4k',
                'categories' => ['porady'],
                'published_at' => '2026-10-30 08:30:00',
                'hero_url' => 'https://images.pexels.com/photos/1457842/pexels-photo-1457842.jpeg?auto=compress&cs=tinysrgb&w=1400&h=788&fit=crop',
                'extra_url' => 'https://images.pexels.com/photos/1571458/pexels-photo-1571458.jpeg?auto=compress&cs=tinysrgb&w=1400&h=788&fit=crop',
                'og_url' => 'https://images.pexels.com/photos/1457842/pexels-photo-1457842.jpeg?auto=compress&cs=tinysrgb&w=1200&h=630&fit=crop',
                'content' => $this->contentDiagonal(...),
            ],
            [
                'slug' => 'wifi-chy-kabel-dlya-smart-tv',
                'title' => 'Wi-Fi чи кабель для Smart TV: як підключити телевізор до інтернету',
                'meta_title' => 'Wi-Fi чи Ethernet для Smart TV: що стабільніше у 2026 | Квадро',
                'meta_description' => 'Коли Smart TV вистачить Wi-Fi, а коли краще вита пара. 5 ГГц і 2.4 ГГц, скільки мегабіт треба на 4K і як не посадити роутер за шафою.',
                'og_title' => 'Wi-Fi чи кабель для Smart TV',
                'og_description' => 'Фільм у 4K зривається не через «поганий телевізор», а через слабкий Wi-Fi. Коротко, що обрати.',
                'og_image' => 'og-blog-wifi-chy-kabel-dlya-smart-tv.jpg',
                'content_image' => 'blog-wifi-chy-kabel-dlya-smart-tv.jpg',
                'content_image_2' => 'blog-wifi-chy-kabel-dlya-smart-tv-2.jpg',
                'content_image_alt' => 'Домашній Wi-Fi роутер з антенами',
                'content_image_2_alt' => 'Мережеві кабелі, підключені до LAN-портів',
                'tags' => 'smart tv, wi-fi, ethernet, роутер, інтернет для телевізора, 4k стрім',
                'categories' => ['porady'],
                'published_at' => '2026-10-31 09:00:00',
                'hero_url' => 'https://images.pexels.com/photos/4218546/pexels-photo-4218546.jpeg?auto=compress&cs=tinysrgb&w=1400&h=788&fit=crop',
                'extra_url' => 'https://images.pexels.com/photos/159304/network-cable-ethernet-computer-159304.jpeg?auto=compress&cs=tinysrgb&w=1400&h=788&fit=crop',
                'og_url' => 'https://images.pexels.com/photos/4218546/pexels-photo-4218546.jpeg?auto=compress&cs=tinysrgb&w=1200&h=630&fit=crop',
                'content' => $this->contentWifi(...),
            ],
            [
                'slug' => 'shcho-zhyvyty-powerbankom-pid-chas-vidklyuchennya',
                'title' => 'Що живити повербанком під час відключення світла: телефон, роутер і лампа',
                'meta_title' => 'Що заряджати повербанком під час відключення світла | Квадро',
                'meta_description' => 'Реальний список: телефон, навушники, USB-лампа і коли роутер повербанк не потягне. Як порахувати ват-години і чому чайник краще не чіпати.',
                'og_title' => 'Що живити повербанком під час відключення світла',
                'og_description' => 'Повербанк тримає зв’язок і світло. Холодильник, телевізор і чайник — уже задача для ДБЖ або генератора.',
                'og_image' => 'og-blog-shcho-zhyvyty-powerbankom.jpg',
                'content_image' => 'blog-shcho-zhyvyty-powerbankom.jpg',
                'content_image_2' => 'blog-shcho-zhyvyty-powerbankom-2.jpg',
                'content_image_alt' => 'Повербанк у руці — резерв живлення для гаджетів',
                'content_image_2_alt' => 'Смартфон на зарядці поруч із ноутбуком',
                'tags' => 'повербанк, відключення світла, powerbank, роутер, usb лампа, блекаут',
                'categories' => ['porady', 'powerbanky'],
                'published_at' => '2026-11-01 09:00:00',
                'hero_url' => 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?auto=format&fit=crop&w=1400&h=788&q=85',
                'extra_url' => 'https://images.pexels.com/photos/4195325/pexels-photo-4195325.jpeg?auto=compress&cs=tinysrgb&w=1400&h=788&fit=crop',
                'og_url' => 'https://images.unsplash.com/photo-1609091839311-d5365f9ff1c5?auto=format&fit=crop&w=1200&h=630&q=85',
                'content' => $this->contentPowerbank(...),
            ],
        ];
    }

    private function contentDiagonal(string $hero, string $extra, string $alt1, string $alt2): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Найчастіша помилка — купити діагональ «як у сусіда», а потім сидіти занадто близько
    або, навпаки, мружитися на маленький екран через кімнату.
    Для 4K важлива не лише кількість дюймів, а <strong>відстань від очей до телевізора</strong>.
</p>
<figure class="blog-figure">
    <img src="{$hero}" alt="{$alt1}" width="1400" height="788" loading="lazy">
    <figcaption>Спочатку виміряйте кімнату: від місця, де сидите, до стіни, де стоятиме екран</figcaption>
</figure>

<h2>З чого почати</h2>
<ol class="blog-steps">
    <li>Сядьте туди, де реально дивитеся фільми, і виміряйте метром відстань до стіни.</li>
    <li>Відніміть 10–15 см, якщо телевізор стоятиме на тумбі, а не врівень зі стіною.</li>
    <li>Подивіться таблицю нижче і оберіть діагональ, для якої ваш метраж потрапляє в комфортний діапазон.</li>
    <li>Перевірте, чи вистачає стіни: екран 55″ завширшки близько 123 см, 65″ — близько 145 см, плюс запас з боків.</li>
</ol>

<h2>Орієнтовна відстань</h2>
<p>
    Для <strong>4K</strong> пікселі дрібні, тож сидіти можна ближче, ніж до старого Full HD.
    Якщо дивитеся переважно ефірне ТБ або канал зі слабкою картинкою, краще взяти меншу діагональ
    або сісти трохи далі — артефакти стиснення на великому екрані помітніші.
</p>
<div class="blog-table-wrap">
<table class="blog-table">
    <thead>
        <tr>
            <th>Діагональ</th>
            <th>Комфортно для 4K</th>
            <th>Якщо картинка звичайна (HD / ефір)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>43″</td>
            <td>1,3–1,8 м</td>
            <td>1,7–2,3 м</td>
        </tr>
        <tr>
            <td>50″</td>
            <td>1,5–2,1 м</td>
            <td>2,0–2,7 м</td>
        </tr>
        <tr>
            <td>55″</td>
            <td>1,7–2,4 м</td>
            <td>2,2–3,0 м</td>
        </tr>
        <tr>
            <td>65″</td>
            <td>2,0–2,8 м</td>
            <td>2,6–3,5 м</td>
        </tr>
        <tr>
            <td>75″</td>
            <td>2,3–3,2 м</td>
            <td>3,0–4,0 м</td>
        </tr>
    </tbody>
</table>
</div>
<div class="blog-note">
    Це орієнтир для вітальні, не медична норма. Якщо після 40 хвилин втомлюються очі — або сядьте далі,
    або зменшіть яскравість. У темній кімнаті велика діагональ переноситься легше, ніж навпроти вікна.
</div>

<h2>Висота, а не лише дюйми</h2>
<figure class="blog-figure">
    <img src="{$extra}" alt="{$alt2}" width="1400" height="788" loading="lazy">
    <figcaption>Центр екрана зручно тримати приблизно на рівні очей, коли ви сидите</figcaption>
</figure>
<p>
    На тумбі телевізор часто опиняється занизько, на кронштейні — зависоко, якщо повісити «як картину над диваном».
    Практичне правило: центр екрана приблизно на висоті очей у положенні сидячи, зазвичай 90–110 см від підлоги.
    Якщо вішаєте вище, беріть кронштейн із нахилом, щоб не задирати голову.
    Як підібрати кріплення —
    <a href="/uk/blog/yak-obraty-kronshteyn-dlya-televizora">гайд з вибору кронштейна</a>,
    моделі є в розділі
    <a href="/uk/category/kronshteyny">Кронштейни</a>.
</p>

<h2>Що ще перевірити до покупки</h2>
<ul class="blog-checklist">
    <li><strong>Стіна.</strong> Гіпсокартон без закладних погано тримає 65″ і важче. Для цегли й бетону запас міцності більший.</li>
    <li><strong>Розетки.</strong> Краще спланувати живлення за екраном, ніж тягнути подовжувач через всю стіну. Як прибрати дроти — у матеріалі <a href="/uk/blog/yak-shovaty-kabeli-za-televizorom">як сховати кабелі за телевізором</a>.</li>
    <li><strong>Джерела картинки.</strong> Приставка, тюнер <a href="/uk/category/tuner-t2">T2</a> і саундбар сідають на HDMI. Який кабель брати для 4K — <a href="/uk/blog/yak-obraty-hdmi-kabel-dlya-televizora">окремий гід</a>.</li>
    <li><strong>Ефір без інтернету.</strong> Великий екран не замінює антену. Базовий сценарій описаний у статті <a href="/uk/blog/cyfrove-telebachennya-t2">цифрове телебачення Т2</a>.</li>
</ul>
<p>
    Інші розбори перед покупкою —
    <a href="/uk/blog/category/porady">у розділі «Поради покупцям»</a>.
</p>
HTML);
    }

    private function contentWifi(string $hero, string $extra, string $alt1, string $alt2): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Smart TV «бачить» інтернет двома шляхами: через Wi-Fi або через кабель у роз’єм LAN.
    Для новини й YouTube часто вистачає бездротового зв’язку.
    Для фільму в 4K і матчу наживо кабель зазвичай спокійніший: менше буферизації і стрибків якості.
</p>
<figure class="blog-figure">
    <img src="{$hero}" alt="{$alt1}" width="1400" height="788" loading="lazy">
    <figcaption>Роутер — вузьке місце. Телевізор у дальній кімнаті за двома стінами Wi-Fi майже завжди програє кабелю</figcaption>
</figure>

<h2>Скільки швидкості реально треба</h2>
<div class="blog-table-wrap">
<table class="blog-table">
    <thead>
        <tr>
            <th>Що дивитеся</th>
            <th>Орієнтир швидкості</th>
            <th>Що зазвичай вистачає</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Ефір Т2, без стрімінгу</td>
            <td>Інтернет не потрібен</td>
            <td>Тюнер і антена</td>
        </tr>
        <tr>
            <td>HD-відео</td>
            <td>близько 5–8 Мбіт/с стабільно</td>
            <td>Wi-Fi 2.4 або 5 ГГц</td>
        </tr>
        <tr>
            <td>4K з HDR</td>
            <td>близько 15–25 Мбіт/с без просідань</td>
            <td>5 ГГц поруч із роутером або Ethernet</td>
        </tr>
        <tr>
            <td>Кілька пристроїв одночасно</td>
            <td>запас зверху, не «впритул»</td>
            <td>Кабель на ТВ, Wi-Fi лишити телефонам</td>
        </tr>
    </tbody>
</table>
</div>
<p>
    Цифра на тарифі провайдера — це стеля до квартири, а не те, що долітає до телевізора.
    Перевіряйте швидкість саме на ТВ або хоча б телефоном, стоячи біля екрана.
</p>

<h2>Коли Wi-Fi нормальний</h2>
<ul class="blog-checklist">
    <li><strong>Роутер у тій самій кімнаті</strong> або через одну легку перегородку.</li>
    <li><strong>Діапазон 5 ГГц</strong> для телевізора: він швидший, хоча гірше проходить крізь бетон.</li>
    <li><strong>2.4 ГГц</strong> лишіть для далеких кімнат і дрібних гаджетів. На ньому 4K частіше «замислюється».</li>
    <li>Роутер стоїть відкрито, не в шафі й не за металевим кронштейном телевізора.</li>
</ul>

<h2>Коли краще кабель</h2>
<figure class="blog-figure">
    <img src="{$extra}" alt="{$alt2}" width="1400" height="788" loading="lazy">
    <figcaption>Вита пара не ділить ефір із мікрохвильовкою, сусідськими мережами й телефоном біля роутера</figcaption>
</figure>
<p>
    Ethernet має сенс, якщо картинка регулярно падає з 4K у HD, звук відстає від зображення
    або телевізор стоїть через несучу стіну.
    Звичайного кабелю категорії 5e вистачає для домашнього стрімінгу на дистанції в межах квартири.
    Прокласти його можна разом з рештою дротів —
    дивіться <a href="/uk/blog/yak-shovaty-kabeli-za-televizorom">як сховати кабелі за телевізором</a>.
</p>
<div class="blog-note">
    Якщо на телевізорі немає LAN-порту, не ліпіть «перший-ліпший» перехідник з ринку без перевірки живлення.
    Надійніший шлях — роутер ближче до ТВ, Mesh-точка в кімнаті або кабель до окремої точки доступу.
</div>

<h2>Що зробити за вечір</h2>
<ol class="blog-steps">
    <li>У меню телевізора підключіться до своєї мережі й забудьте гостьовий Wi-Fi сусіда.</li>
    <li>Поставте роутер вище (полиця, а не підлога) і приберіть його від мікрохвильовки.</li>
    <li>Для 4K спробуйте 5 ГГц. Якщо сигнал слабкий — тягніть кабель.</li>
    <li>Оновіть прошивку ТВ: частина «лагів» — це старий застосунок, а не інтернет.</li>
    <li>Живлення роутера під час відключень тримайте через <a href="/uk/blog/merezhevyy-filtr-chy-dbzh">ДБЖ, а не лише мережевий фільтр</a>. Фільтр не дає хвилин роботи без світла.</li>
</ol>
<p>
    Ефірні канали від інтернету не залежать. Як зібрати Т2 вдома —
    у статті <a href="/uk/blog/cyfrove-telebachennya-t2">цифрове телебачення Т2</a>
    і розділі <a href="/uk/category/digital-tb">Цифрове телебачення</a>.
    Звук виносьте на саундбар за гідом
    <a href="/uk/blog/yak-pidklyuchyty-saundbar-do-televizora">як підключити саундбар</a>.
</p>
HTML);
    }

    private function contentPowerbank(string $hero, string $extra, string $alt1, string $alt2): string
    {
        return $this->wrapHtml(<<<HTML
<p class="blog-lead">
    Під час відключення світла повербанк рятує зв’язок і невелике світло.
    Він не замінює генератор і не витягне холодильник.
    Нижче — що реально підключати, як прикинути години роботи і де вже потрібен
    <a href="/uk/blog/merezhevyy-filtr-chy-dbzh">ДБЖ</a>.
</p>
<figure class="blog-figure">
    <img src="{$hero}" alt="{$alt1}" width="1400" height="788" loading="lazy">
    <figcaption>Повербанк зручний саме для кишенькових пристроїв: телефон, навушники, ліхтарик</figcaption>
</figure>

<h2>Що можна</h2>
<ul class="blog-checklist">
    <li><strong>Телефон і планшет.</strong> Головний сенс банку. Скільки циклів дає модель на 20000 mAh — у розрахунку <a href="/uk/blog/skilky-raziv-powerbank-20000-zaryadyt-telefon">скільки разів 20000 mAh зарядить телефон</a>.</li>
    <li><strong>Навушники, годинник, ліхтарик з USB.</strong> Їдять мало, один банк тримає їх багато вечорів.</li>
    <li><strong>USB-лампа на 3–5 Вт.</strong> М’яке світло на кілька годин. Звичайна кімнатна люстра на 220 В до USB не підключається.</li>
    <li><strong>Роутер — лише якщо збігається живлення.</strong> Багато домашніх роутерів хочуть 12 В по круглому роз’єму. Звичайний USB-кабель їх не ввімкне. Потрібен банк із DC-виходом потрібної напруги або окремий ДБЖ. Дивіться наклейку на блоці живлення роутера, а не «приблизно підійде».</li>
</ul>

<h2>Чого краще не чіпати</h2>
<div class="blog-note">
    Чайник, праска, бойлер, обігрівач, холодильник і телевізор повербанк не потягне.
    Це сотні ват і інша розетка. Для роутера й ТВ-приставки на кілька годин дивіться ДБЖ,
    для довгого блекауту — зарядну станцію або генератор, не кишеньковий банк.
</div>

<h2>Як прикинути час роботи</h2>
<p>
    На корпусі пишуть міліампер-години, а пристрої їдять вати.
    Груба оцінка для літієвого банку: <strong>Вт·год ≈ mAh × 3,7 / 1000</strong>.
    Після перетворення на 5 В реально доступно приблизно 60–70% цієї цифри.
</p>
<div class="blog-table-wrap">
<table class="blog-table">
    <thead>
        <tr>
            <th>Банк</th>
            <th>Енергія «на папері»</th>
            <th>Що це дає на практиці</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>10 000 mAh</td>
            <td>близько 37 Вт·год</td>
            <td>1–2 зарядки телефона або USB-лампа 5 Вт на 4–5 годин</td>
        </tr>
        <tr>
            <td>20 000 mAh</td>
            <td>близько 74 Вт·год</td>
            <td>3–4 зарядки телефона або лампа на довгий вечір плюс зв’язок</td>
        </tr>
        <tr>
            <td>30 000 mAh</td>
            <td>близько 110 Вт·год</td>
            <td>Телефони родини й резерв, якщо банк не важкий для вас</td>
        </tr>
    </tbody>
</table>
</div>
<p>
    Ноутбук від банку заряджається тільки якщо є USB-C Power Delivery і вистачає ват
    (часто 45–65 Вт і більше). Дешевий банк «лише на телефон» ноутбук може лише підтримувати у сні або не побачити зовсім.
    Як обрати ємність і порти — у статті
    <a href="/uk/blog/yak-obraty-powerbank">як обрати повербанк</a>,
    моделі зібрані в розділі <a href="/uk/category/poverbanky">Повербанки</a>.
</p>
<figure class="blog-figure">
    <img src="{$extra}" alt="{$alt2}" width="1400" height="788" loading="lazy">
    <figcaption>Телефон і ноутбук ділять один запас енергії. Спочатку закрийте зв’язок, потім уже «дозаряд» великої техніки</figcaption>
</figure>

<h2>Короткий порядок на вечір без світла</h2>
<ol class="blog-steps">
    <li>Тримайте банк зарядженим до відключення, а не згадуйте про нього, коли згасло.</li>
    <li>Спочатку телефон, далі світло, і лише потім другорядні гаджети.</li>
    <li>Роутер саджайте на ДБЖ або на банк із правильною напругою. Не подавайте 5 В туди, де написано 12 В.</li>
    <li>Ефірне телебачення без інтернету описане окремо: <a href="/uk/blog/cyfrove-telebachennya-t2">як підключити Т2</a>. Сам телевізор від повербанка не живиться.</li>
    <li>Кабель до банку беріть короткий і цілий: тонкий шнур гріється і краде частину заряду.</li>
</ol>
<p>
    Ще поради щодо техніки для дому —
    <a href="/uk/blog/category/powerbanky">у статтях про повербанки</a>
    та <a href="/uk/blog/category/porady">у розділі «Поради покупцям»</a>.
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
.blog-checklist strong{display:inline-block;min-width:9rem}
.blog-table-wrap{overflow-x:auto;margin:1.25rem 0 1.75rem}
.blog-table{width:100%;border-collapse:collapse;font-size:.95rem}
.blog-table th,.blog-table td{border:1px solid #e5e7eb;padding:.55rem .7rem;text-align:left;vertical-align:top}
.blog-table th{background:#f8fafc;font-weight:700}
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

    /**
     * @param array<string, mixed> $data
     */
    private function downloadImages(array $data, bool $force, SymfonyStyle $io): bool
    {
        $map = [
            $this->projectDir.'/public/uploads/blog/'.$data['content_image'] => $data['hero_url'],
            $this->projectDir.'/public/uploads/blog/'.$data['content_image_2'] => $data['extra_url'],
            $this->projectDir.'/public/uploads/images/blog/'.$data['og_image'] => $data['og_url'],
        ];

        $ok = true;
        foreach ($map as $path => $url) {
            if (is_file($path) && !$force && filesize($path) > 20000) {
                continue;
            }
            if (!$this->downloadFile((string) $url, $path, $io)) {
                $ok = false;
            }
        }

        return $ok;
    }

    private function downloadFile(string $url, string $path, SymfonyStyle $io): bool
    {
        $dir = \dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create: '.$dir);
        }

        $tmp = $path.'.tmp';
        $cmd = sprintf(
            'curl -fsSL --max-time 60 -A %s -o %s %s',
            escapeshellarg('Mozilla/5.0'),
            escapeshellarg($tmp),
            escapeshellarg($url),
        );
        exec($cmd, $out, $code);
        if ($code !== 0 || !is_file($tmp) || filesize($tmp) < 8000) {
            @unlink($tmp);
            $io->warning('Failed to download: '.$url);

            return false;
        }

        $mime = (string) (@mime_content_type($tmp) ?: '');
        if (!str_contains($mime, 'jpeg') && !str_contains($mime, 'jpg') && !str_contains($mime, 'png') && !str_contains($mime, 'webp')) {
            @unlink($tmp);
            $io->warning('Download was not an image: '.$url);

            return false;
        }

        rename($tmp, $path);
        $io->text('Image: '.str_replace($this->projectDir.'/', '', $path).' ('.filesize($path).' bytes)');

        return true;
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
