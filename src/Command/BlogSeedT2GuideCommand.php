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
    name: 'app:blog:seed-t2-guide',
    description: 'Seed SEO blog article about digital TV T2 (DVB-T2)',
)]
final class BlogSeedT2GuideCommand extends Command
{
    private const SLUG = 'cyfrove-telebachennya-t2';
    private const OG_IMAGE = 'og-blog-cyfrove-telebachennya-t2.jpg';

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

        $category = $this->categoryRepository->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => 'cyfrove-tb',
        ]);
        if ($category === null) {
            $category = (new BlogCategory())
                ->setSite($site)
                ->setLocale($locale)
                ->setName('Цифрове ТБ')
                ->setSlug('cyfrove-tb')
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
            ->setTitle('Цифрове телебачення Т2: що це і як підключити вдома')
            ->setSlug(self::SLUG)
            ->setMetaTitle('Цифрове телебачення Т2: як підключити DVB-T2 | Квадро')
            ->setMetaDescription('Що таке цифрове телебачення Т2 (DVB-T2) в Україні: обладнання, антена, налаштування тюнера та поради для стабільного ефірного сигналу.')
            ->setOgTitle('Цифрове телебачення Т2 — повний гайд з підключення')
            ->setOgDescription('Пояснюємо DVB-T2 простими словами: який тюнер і антена потрібні, як налаштувати канали та уникнути слабкого сигналу.')
            ->setOgType('article')
            ->setOgImage(self::OG_IMAGE)
            ->setContent($this->content())
            ->setEnabled(true)
            ->setPublishedAt(new \DateTimeImmutable('2026-10-03 12:00:00'));

        foreach ($article->getCategories()->toArray() as $existing) {
            $article->removeCategory($existing);
        }
        $article->addCategory($category);

        $baseUrl = $this->resolveBaseUrl($site->getDomain());
        $article->setFacebookDraft($this->facebookPostFormatter->format($article, $baseUrl));

        $this->em->persist($article);
        $this->em->flush();

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
                ['category', $category->getSlug()],
                ['facebook_draft', $article->getFacebookDraft() !== null ? 'yes ('.strlen($article->getFacebookDraft()).' chars)' : 'no'],
                ['enabled', $article->isEnabled() ? '1' : '0'],
                ['published_at', $article->getPublishedAt()?->format('Y-m-d H:i:s') ?? ''],
                ['url', sprintf('/uk/blog/%s', self::SLUG)],
            ],
        );
        $io->success(sprintf('Article saved to DB: /uk/blog/%s', self::SLUG));

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
.blog-checklist strong{display:inline-block;min-width:7.5rem}
</style>

<p class="blog-lead">
    <strong>Цифрове телебачення Т2</strong> (стандарт DVB-T2) — це безкоштовне ефірне ТБ в Україні:
    десятки каналів у цифровій якості без абонплати за кабель чи IPTV.
    Нижче — зрозумілий гайд: що потрібно купити, як підключити і налаштувати сигнал вдома.
</p>

<h2>Що таке цифрове телебачення Т2</h2>
<p>
    DVB-T2 — сучасний стандарт ефірного мовлення. Сигнал іде з наземних веж, а не зі супутника.
    Щоб дивитися канали, потрібні антена (кімнатна або зовнішня) і приймач DVB-T2 —
    окремий тюнер або телевізор із вбудованим тюнером Т2.
</p>
<p>
    На відміну від аналогового ефіру, картинка стабільніша, звук чистіший, а список каналів
    можна оновити автоматичним пошуком на тюнері.
</p>

<h2>Яке обладнання потрібне</h2>
<ul class="blog-checklist">
    <li><strong>Тюнер Т2</strong> — якщо ТВ без вбудованого DVB-T2 (HDMI + пульт)</li>
    <li><strong>Антена</strong> — кімнатна для сильного сигналу, зовнішня — для дачі чи околиць</li>
    <li><strong>Кабель</strong> — коаксіальний 75 Ом із коректними F-роз’ємами</li>
    <li><strong>Підсилювач</strong> — лише якщо сигнал слабкий (не ставте «на всяк випадок»)</li>
</ul>
<div class="blog-note">
    Перед покупкою перевірте: чи є у телевізора позначка DVB-T2 / T2.
    Якщо так — часто достатньо лише антени.
</div>

<h2>Як підключити Т2 за 5 кроків</h2>
<ol class="blog-steps">
    <li>Розмістіть антену біля вікна або на зовнішній щоглі, спрямовану в бік найближчої вежі.</li>
    <li>Підключіть кабель від антени до входу <em>ANT IN / RF IN</em> на тюнері або ТВ.</li>
    <li>З’єднайте тюнер із телевізором кабелем HDMI (або AV, якщо HDMI немає).</li>
    <li>Увімкніть обладнання і запустіть <strong>автопошук каналів</strong> у меню тюнера.</li>
    <li>Збережіть знайдені канали та перевірте рівень сигналу на кількох мультиплексах.</li>
</ol>

<h2>Як отримати стабільний сигнал</h2>
<ul>
    <li>Не ховайте кімнатну антену за шафою чи металевими жалюзі.</li>
    <li>Уникайте довгих пошкоджених кабелів і зайвих розгалужувачів.</li>
    <li>Якщо «квадратики» на екрані — спробуйте інше місце антени або зовнішню модель.</li>
    <li>Підсилювач допомагає при слабкому сигналі, але при сильному може навпаки заважати.</li>
</ul>

<h2>Типові помилки початківців</h2>
<ul>
    <li>Купують тюнер DVB-T замість <strong>DVB-T2</strong> — старий стандарт уже не актуальний.</li>
    <li>Ставлять потужний підсилювач без потреби.</li>
    <li>Забувають оновити список каналів після змін у мультиплексах.</li>
    <li>Чекають «ідеальної» антени без перевірки покриття у своєму районі.</li>
</ul>

<h2>Т2, супутник чи IPTV — коротке порівняння</h2>
<p>
    <strong>Т2</strong> — безкоштовні ефірні канали, мінімум обладнання.
    <strong>Супутник</strong> — більше каналів і незалежність від місцевої вежі, але потрібна тарілка.
    <strong>IPTV</strong> — залежить від інтернету; зручно, якщо є стабільний канал.
    Багато сімей комбінують Т2 як «запасний» варіант на випадок збоїв мережі.
</p>

<div class="blog-note">
    У магазині Квадро є <a href="/uk/category/tuner-t2">тюнери Т2</a> та
    <a href="/uk/category/antenu">ТВ-антени</a>.
    Якщо сумніваєтесь із вибором — підкажемо комплект під ваш регіон і тип будинку.
    Також читайте:
    <a href="/uk/blog/yak-obraty-antenu-dlya-tunera-t2">як обрати антену для тюнера T2</a>
    і <a href="/uk/blog/kanaly-t2-spysok-i-zhanry">список каналів Т2</a>.
</div>
HTML;
    }
}
