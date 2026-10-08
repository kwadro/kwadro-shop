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
    name: 'app:blog:seed-tv-mount-guide',
    description: 'Seed SEO blog article about choosing a TV wall mount',
)]
final class BlogSeedTvMountGuideCommand extends Command
{
    private const SLUG = 'yak-obraty-kronshteyn-dlya-televizora';
    private const OG_IMAGE = 'og-blog-yak-obraty-kronshteyn-dlya-televizora.jpg';

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

        $article = $this->articleRepository->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => self::SLUG,
        ]) ?? new BlogArticle();

        $article
            ->setSite($site)
            ->setLocale($locale)
            ->setTitle('Як обрати кронштейн для телевізора: повний гайд 2026')
            ->setSlug(self::SLUG)
            ->setMetaTitle('Як обрати кронштейн для телевізора у 2026: VESA, вага, нахил, монтаж | Квадро')
            ->setMetaDescription('Розгорнутий гід з вибору кріплення для ТВ: типи кронштейнів, VESA, навантаження, відстань від стіни, монтаж на бетон і гіпсокартон, чеклист і типові помилки.')
            ->setOgTitle('Як обрати кронштейн для телевізора — повний гід')
            ->setOgDescription('VESA, вага ТВ, фіксований / з нахилом / поворотний, висота підвісу і монтаж на стіну. Практичні поради, щоб кріплення підійшло з першого разу.')
            ->setOgType('article')
            ->setOgImage(self::OG_IMAGE)
            ->setTags('кронштейн для телевізора, кріплення тв, vesa, unibracket, кронштейн на стіну, кронштейн з нахилом, поворотний кронштейн')
            ->setContent($this->content())
            ->setEnabled(true)
            ->setPublishedAt(new \DateTimeImmutable('2026-10-06 16:00:00'));

        foreach ($article->getCategories()->toArray() as $existing) {
            $article->removeCategory($existing);
        }
        $article->addCategory($category);

        $baseUrl = $this->resolveBaseUrl($site->getDomain());
        $article->setFacebookDraft($this->facebookPostFormatter->format($article, $baseUrl));

        $this->em->persist($article);
        $this->em->flush();

        $ogPath = $this->projectDir.'/public/uploads/images/'.self::OG_IMAGE;
        $io->table(
            ['Field', 'Value'],
            [
                ['id', (string) $article->getId()],
                ['slug', $article->getSlug()],
                ['title', $article->getTitle()],
                ['meta_title', (string) $article->getMetaTitle()],
                ['meta_description', (string) $article->getMetaDescription()],
                ['og_title', (string) $article->getOgTitle()],
                ['og_description', (string) $article->getOgDescription()],
                ['og_type', (string) $article->getOgType()],
                ['og_image', (string) $article->getOgImage()],
                ['og_image_file', is_file($ogPath) ? 'yes ('.$ogPath.')' : 'MISSING'],
                ['tags', (string) $article->getTags()],
                ['category', $category->getSlug()],
                ['facebook_draft', $article->getFacebookDraft() !== null ? 'yes' : 'no'],
                ['url', sprintf('/uk/blog/%s', self::SLUG)],
            ],
        );
        if (!is_file($ogPath)) {
            $io->warning(sprintf('OG image file missing: public/uploads/images/%s', self::OG_IMAGE));
        }
        $io->success(sprintf('Article saved: /uk/blog/%s', self::SLUG));

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
        return <<<'HTML'
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
</style>

<p class="blog-lead">
    Правильний <strong>кронштейн для телевізора</strong> — це не лише «щоб ТВ висів».
    Від кріплення залежить безпека, естетика стіни, зручний кут огляду і навіть те, наскільки акуратно виглядатимуть кабелі.
    У цьому розгорнутому гайді зібрали все, що варто перевірити перед покупкою: типи кронштейнів, VESA, навантаження,
    висоту підвісу, монтаж на різні стіни та типові помилки.
</p>

<h2>Чому кронштейн важливіший, ніж здається</h2>
<p>
    Сучасні телевізори тонкі й легші за старі моделі, але все одно важать десятки кілограмів.
    Якщо кріплення підібране «впритул» за вагою або не підходить по VESA, ризик не лише у люфті —
    а й у пошкодженні панелі чи стіни. Добре обраний кронштейн:
</p>
<ul>
    <li>тримає ТВ без провисання і скрипу;</li>
    <li>дає комфортний кут огляду з дивана / ліжка / кухні;</li>
    <li>економить місце в кімнаті порівняно з тумбою;</li>
    <li>допомагає сховати кабелі й зробити інтер’єр акуратнішим.</li>
</ul>

<h2>Які бувають кронштейни для ТВ</h2>
<ul class="blog-checklist">
    <li><strong>Фіксований</strong> — екран максимально близько до стіни; найпростіший і найдоступніший варіант</li>
    <li><strong>З нахилом</strong> — можна нахилити екран вниз; зручно, якщо ТВ вище рівня очей</li>
    <li><strong>Поворотний (кронштейн-рука)</strong> — винос і поворот убік; для кута кімнати або перегляду з різних зон</li>
    <li><strong>Стельовий</strong> — рідше для дому, частіше для барів, шоурумів, офісів</li>
</ul>
<div class="blog-note">
    Для більшості квартир вистачає фіксованого кронштейна або моделі з невеликим нахилом.
    Поворотний беріть лише тоді, коли реально потрібен винос екрана від стіни.
</div>

<div class="blog-table-wrap">
<table class="blog-table">
    <thead>
        <tr>
            <th>Тип</th>
            <th>Кому підходить</th>
            <th>Плюси</th>
            <th>Мінуси</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Фіксований</td>
            <td>Вітальня з диваном навпроти ТВ</td>
            <td>Тонкий профіль, низька ціна, надійність</td>
            <td>Без регулювання кута</td>
        </tr>
        <tr>
            <td>З нахилом</td>
            <td>Спальня, кухня, високе кріплення</td>
            <td>Менше відблисків, зручніше дивитись зверху</td>
            <td>Трохи товстіший за фіксований</td>
        </tr>
        <tr>
            <td>Поворотний</td>
            <td>Кутова кімната, відкритий простір</td>
            <td>Гнучкий кут і винос</td>
            <td>Дорожче, вимагає міцнішої стіни</td>
        </tr>
    </tbody>
</table>
</div>

<h2>5 параметрів, які треба звірити перед покупкою</h2>
<ol class="blog-steps">
    <li><strong>Діагональ ТВ</strong> — у кронштейна має бути ваш діапазон (наприклад, 23–43″ або 32–55″).</li>
    <li><strong>Вага телевізора</strong> — беріть запас 20–30%. Якщо ТВ 18 кг, краще кріплення «до 25–30 кг», ніж «до 20 кг» впритул.</li>
    <li><strong>VESA</strong> — розмір отворів на задній панелі (100×100, 200×200 мм тощо) має входити в список підтримуваних.</li>
    <li><strong>Тип стіни</strong> — бетон, цегла, газоблок, гіпсокартон. Від цього залежить кріпильний комплект.</li>
    <li><strong>Відстань від стіни і кабелі</strong> — чи вистачить місця для HDMI, живлення, антени/інтернету та кабель-каналу.</li>
</ol>

<h2>Що таке VESA і як її виміряти</h2>
<p>
    <strong>VESA</strong> — міжнародний стандарт розташування отворів для кріплення на задній кришці телевізора.
    Розмір пишуть у міліметрах по горизонталі × вертикалі: <em>75×75</em>, <em>100×100</em>, <em>200×200</em>, <em>400×400</em> тощо.
</p>
<ul>
    <li>Подивіться в інструкції або на наклейці ззаду ТВ.</li>
    <li>Або виміряйте рулеткою відстань між центрами отворів.</li>
    <li>Обирайте кронштейн, у якого ваш розмір є в переліку підтримуваних VESA.</li>
</ul>
<div class="blog-note">
    Якщо VESA телевізора не підтримується кронштейном, адаптери допомагають не завжди і не для всіх моделей.
    Надійніше одразу взяти сумісне кріплення.
</div>

<h2>Фіксований, з нахилом чи поворотний — як вирішити</h2>
<p>
    <strong>Фіксований</strong> обирайте, коли місце перегляду стабільне: диван навпроти стіни, кімната без складних кутів.
    Це найтонший і найпростіший варіант «поставив і забув».
</p>
<p>
    <strong>З нахилом</strong> — якщо телевізор висить вище рівня очей (над комодом, у спальні навпроти ліжка, на кухні).
    Невеликий кут зменшує відблиски і робить перегляд комфортнішим.
</p>
<p>
    <strong>Поворотний</strong> потрібен, коли екран треба розвернути до столу, кухні-вітальні чи кутового дивана.
    Врахуйте: конструкція масивніша, після монтажу ТВ відійде від стіни далі, ніж на фіксованому кріпленні.
</p>

<h2>На якій висоті вішати телевізор</h2>
<p>
    Орієнтир для вітальні: центр екрана приблизно на рівні очей людини, яка сидить на дивані.
    Занадто високий підвіс — часта помилка: доводиться задирати голову, з’являється втома шиї.
</p>
<ul>
    <li>Спочатку сядьте на звичному місці перегляду і поміряйте висоту очей.</li>
    <li>Відмітьте на стіні майбутній центр екрана.</li>
    <li>Лише потім прикладайте кронштейн і розмічайте отвори.</li>
</ul>

<h2>Монтаж: бетон, цегла і гіпсокартон</h2>
<p>
    На бетон і повнотілу цеглу більшість кронштейнів ставляться штатними анкерами з комплекту.
    На гіпсокартон (ГКЛ) звичайні дюбелі — погана ідея: потрібні закладки в каркас або спеціальні кріплення для порожнистих стін.
</p>
<ul>
    <li>Перевірте комплектацію: болти під VESA, анкери, рівень, інструкція.</li>
    <li>Використовуйте бульбашковий рівень — криво підвішений ТВ одразу помітний.</li>
    <li>Не затягуйте болти «від душі» до деформації панелі: рівномірно, хрест-навхрест.</li>
    <li>Залиште петлю кабелів — щоб при сервісі можна було обережно відсунути/зняти ТВ.</li>
</ul>
<div class="blog-note">
    Якщо стіна з газоблоку або тонкого ГКЛ без закладки — краще проконсультуватись перед свердлінням.
    Іноді правильніше поставити ТВ на тумбу або посилити місце кріплення.
</div>

<h2>Кабелі, роз’єми і «чиста» стіна</h2>
<p>
    Навіть ідеальний кронштейн виглядає неохайно, якщо з-під ТВ звисає «борода» з проводів.
    Продумайте заздалегідь:
</p>
<ul>
    <li>де буде розетка 220 В і чи потрібен подовжувач / кабель-канал;</li>
    <li>скільки HDMI (приставка, soundbar, ігрова консоль);</li>
    <li>чи піде антенний кабель або ethernet;</li>
    <li>чи не закриє кронштейн роз’єми на задній панелі ТВ.</li>
</ul>

<h2>Типові помилки при виборі та встановленні</h2>
<ul>
    <li>Дивляться лише на діагональ і ігнорують <strong>вагу</strong> та VESA.</li>
    <li>Купують поворотний «на всяк випадок», хоча вистачило б фіксованого.</li>
    <li>Кріплять у гіпсокартон звичайними дюбелями.</li>
    <li>Вішають ТВ надто високо.</li>
    <li>Забувають про місце під кабелі й роз’єми.</li>
    <li>Беруть кріплення без запасу по навантаженню.</li>
</ul>

<h2>Чеклист перед замовленням</h2>
<ul class="blog-checklist">
    <li><strong>Діагональ</strong> — у діапазоні кронштейна</li>
    <li><strong>Вага</strong> — запас 20–30%</li>
    <li><strong>VESA</strong> — точний збіг</li>
    <li><strong>Тип</strong> — фіксований / нахил / поворот</li>
    <li><strong>Стіна</strong> — правильний кріпильний комплект</li>
    <li><strong>Кабелі</strong> — розетка, HDMI, кабель-канал</li>
    <li><strong>Висота</strong> — центр екрана на рівні очей</li>
</ul>

<h2>Приклади з каталогу Квадро</h2>
<p>
    Для середніх діагоналей зручні компактні моделі на кшталт
    <a href="/uk/product/bz12-22-unibracket">UniBracket BZ12-22</a>:
    поширені VESA, нахил і адекватне навантаження для домашнього використання.
    Якщо потрібен інший формат — дивіться весь розділ
    <a href="/uk/category/kronshteyny">кронштейни для телевізорів</a>
    і лінійку <a href="/uk/category/unibracket">UniBracket</a>.
</p>

<div class="blog-note">
    Не впевнені з вибором? Напишіть нам діагональ, вагу і VESA вашого телевізора —
    підкажемо 1–2 моделі під вашу стіну і сценарій перегляду.
    Також корисно прочитати наші матеріали про
    <a href="/uk/blog/cyfrove-telebachennya-t2">цифрове телебачення Т2</a>
    і <a href="/uk/blog/yak-obraty-antenu-dlya-tunera-t2">вибір антени</a>,
    якщо збираєте домашню ТВ-систему з нуля.
</div>
HTML;
    }
}
