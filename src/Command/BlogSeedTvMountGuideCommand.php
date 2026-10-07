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
            ->setTitle('Як обрати кронштейн для телевізора: повний гайд')
            ->setSlug(self::SLUG)
            ->setMetaTitle('Як обрати кронштейн для телевізора: VESA, навантаження, нахил | Квадро')
            ->setMetaDescription('Практичний гід з вибору кріплення для ТВ: типи кронштейнів, стандарт VESA, навантаження, відстань від стіни та типові помилки при покупці.')
            ->setOgTitle('Як обрати кронштейн для телевізора — коротко і по суті')
            ->setOgDescription('Фіксований, з нахилом чи поворотний? Пояснюємо VESA, вагу ТВ і відстань від стіни, щоб кріплення підійшло з першого разу.')
            ->setOgType('article')
            ->setOgImage(self::OG_IMAGE)
            ->setTags('кронштейн для телевізора, кріплення тв, vesa, unibracket, кронштейн на стіну')
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
.blog-lead{font-size:1.1rem;line-height:1.6;color:#333;margin:0 0 1.25rem}
.blog-note{padding:.85rem 1rem;background:#f5f7fa;border-left:4px solid #f5c518;margin:1.25rem 0;border-radius:0 .4rem .4rem 0}
.blog-steps{margin:1.25rem 0 1.75rem;padding-left:1.2rem}
.blog-steps li{margin:.45rem 0;line-height:1.5}
.blog-checklist{list-style:none;padding:0;margin:1.25rem 0}
.blog-checklist li{padding:.55rem .85rem;margin:0 0 .45rem;border:1px solid #e6e6e6;border-radius:.5rem;background:#fff}
.blog-checklist strong{display:inline-block;min-width:8.5rem}
</style>

<p class="blog-lead">
    Правильний <strong>кронштейн для телевізора</strong> тримає ТВ безпечно, виглядає акуратно і дає зручний кут огляду.
    Нижче — практичний гайд: які бувають кріплення, що перевірити перед покупкою і як не помилитись із VESA та вагою.
</p>

<h2>Які бувають кронштейни</h2>
<ul class="blog-checklist">
    <li><strong>Фіксований</strong> — ТВ максимально близько до стіни, мінімальна ціна, ідеально для «картини» на стіні</li>
    <li><strong>З нахилом</strong> — можна опустити екран вниз (кухня, спальня, високе кріплення)</li>
    <li><strong>Поворотний / кронштейн-рука</strong> — висувається і повертається; зручно для кута або перегляду з різних зон</li>
    <li><strong>Стельовий</strong> — для специфічних приміщень, барів, шоурумів</li>
</ul>
<div class="blog-note">
    Для більшості квартир вистачає фіксованого кронштейна або моделі з невеликим нахилом.
    Поворотний беріть лише якщо реально потрібен винос екрана від стіни.
</div>

<h2>Що перевірити перед покупкою</h2>
<ol class="blog-steps">
    <li><strong>Діагональ ТВ</strong> — у характеристиках кронштейна має бути ваш діапазон (наприклад, 23–43″ або 32–55″).</li>
    <li><strong>Вага телевізора</strong> — беріть запас: якщо ТВ 18 кг, кріплення «до 25–30 кг» надійніше, ніж «до 20 кг» впритул.</li>
    <li><strong>VESA</strong> — стандарт отворів на задній панелі ТВ (наприклад, 100×100, 200×200 мм). Має збігатися з кронштейном.</li>
    <li><strong>Тип стіни</strong> — бетон, цегла, гіпсокартон. Для ГКЛ потрібні спеціальні дюбелі/закладки.</li>
    <li><strong>Відстань від стіни</strong> — фіксовані моделі часто 15–50 мм; поворотні — значно більше.</li>
</ol>

<h2>Що таке VESA і де її подивитись</h2>
<p>
    VESA — це розмір між отворами для кріплення на задній кришці телевізора.
    Найчастіше вказують у мм: <em>75×75</em>, <em>100×100</em>, <em>200×200</em>, <em>400×400</em> тощо.
</p>
<ul>
    <li>Подивіться в інструкції до ТВ або на наклейці ззаду.</li>
    <li>Виміряйте відстань між центрами отворів по горизонталі і вертикалі.</li>
    <li>Обирайте кронштейн, у якого ваш розмір є у списку підтримуваних VESA.</li>
</ul>
<div class="blog-note">
    Якщо VESA ТВ не входить у діапазон кронштейна — адаптери допомагають не завжди.
    Краще одразу взяти сумісну модель.
</div>

<h2>Фіксований, з нахилом чи поворотний?</h2>
<p>
    <strong>Фіксований</strong> — найтонший профіль, менше деталей, надійніше «на роки».
    Підходить, коли диван навпроти ТВ і кут огляду комфортний.
</p>
<p>
    <strong>З нахилом</strong> — якщо телевізор висить вище рівня очей (спальня, кухня).
    Невеликий кут знімає відблиски і розвантажує шию.
</p>
<p>
    <strong>Поворотний</strong> — коли треба повернути екран до кухні/столу або винести ТВ зі стіни.
    Врахуйте: кріплення товстіше, дорожче і вимагає міцнішої стіни.
</p>

<h2>Типові помилки при виборі</h2>
<ul>
    <li>Дивляться лише на діагональ і ігнорують <strong>вагу</strong> та VESA.</li>
    <li>Купують поворотний «на всяк випадок», хоча вистачило б фіксованого.</li>
    <li>Кріплять у гіпсокартон звичайними дюбелями без закладок.</li>
    <li>Забувають про кабелі: HDMI, живлення, інтернет — залиште місце для роз’ємів і кабель-каналу.</li>
    <li>Ставлять ТВ надто високо — комфортний центр екрана приблизно на рівні очей сидячи.</li>
</ul>

<h2>Короткий чеклист перед замовленням</h2>
<ul class="blog-checklist">
    <li><strong>Діагональ</strong> — у діапазоні кронштейна</li>
    <li><strong>Вага</strong> — з запасом 20–30%</li>
    <li><strong>VESA</strong> — точний збіг</li>
    <li><strong>Тип</strong> — фіксований / нахил / поворот</li>
    <li><strong>Стіна</strong> — правильний кріпильний комплект</li>
</ul>

<h2>Приклад: компактний кронштейн UniBracket</h2>
<p>
    Якщо потрібне акуратне кріплення для середніх ТВ, подивіться моделі на кшталт
    <a href="/uk/product/bz12-22-unibracket">UniBracket BZ12-22</a>:
    підтримка поширених VESA, нахил і адекватне навантаження для домашнього використання.
</p>

<div class="blog-note">
    У каталозі Квадро є розділ
    <a href="/uk/category/kronshteyny">кронштейни для телевізорів</a>
    і лінійка <a href="/uk/category/unibracket">UniBracket</a>.
    Якщо сумніваєтесь — напишіть діагональ, вагу і VESA вашого ТВ, підкажемо модель.
</div>
HTML;
    }
}
