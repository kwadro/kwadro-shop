<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\ContentAgentAction;
use App\AiContentAgent\Dto\SeoArticleDraft;
use App\AiContentAgent\Entity\PublicationQueueItem;
use App\Entity\BlogArticle;
use App\Entity\BlogCategory;
use App\Entity\Locale;
use App\Entity\Site;
use App\Repository\BlogArticleRepository;
use App\Repository\BlogCategoryRepository;
use App\Repository\LocaleRepository;
use App\Repository\SiteRepository;
use App\Service\Blog\BlogFacebookPostFormatter;
use Doctrine\ORM\EntityManagerInterface;

final class ArticlePublisher
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteRepository $sites,
        private readonly LocaleRepository $locales,
        private readonly BlogCategoryRepository $categories,
        private readonly BlogArticleRepository $articles,
        private readonly BlogFacebookPostFormatter $facebookPostFormatter,
        private readonly ArticleSlugger $slugger,
        private readonly ContentAgentLogger $logger,
        private readonly int $siteId,
        private readonly string $localeCode,
        private readonly string $categorySlug,
    ) {
    }

    public function publish(PublicationQueueItem $item, SeoArticleDraft $draft): BlogArticle
    {
        $site = $this->requireSite();
        $locale = $this->requireLocale();
        $category = $this->requireCategory($site, $locale);

        $article = $item->getBlogArticle() ?? new BlogArticle();
        $slug = $this->uniqueSlug($draft->slug, $site, $locale, $article->getId());
        $publishedAt = $article->getPublishedAt() ?? new \DateTimeImmutable();

        $article
            ->setSite($site)
            ->setLocale($locale)
            ->setTitle($draft->title)
            ->setSlug($slug)
            ->setMetaTitle($draft->metaTitle)
            ->setMetaDescription($draft->metaDescription)
            ->setOgTitle($draft->ogTitle)
            ->setOgDescription($draft->ogDescription)
            ->setOgType('article')
            ->setTags($draft->tags)
            ->setContent($draft->contentHtml)
            ->setEnabled(true)
            ->setPublishedAt($publishedAt);

        if (!$article->getCategories()->contains($category)) {
            $article->addCategory($category);
        }

        if ($article->getId() === null) {
            $this->entityManager->persist($article);
        }

        $this->attachFacebookDraft($article, $site);
        $this->entityManager->flush();

        $item->setBlogArticle($article);
        $this->entityManager->flush();

        $this->logger->info(ContentAgentAction::ARTICLE_PUBLISHED, 'Article published.', [
            'queueItemId' => $item->getId(),
            'articleId' => $article->getId(),
            'slug' => $article->getSlug(),
            'metaTitle' => $draft->metaTitle,
        ], $item->getId());

        return $article;
    }

    private function requireSite(): Site
    {
        $site = $this->siteId > 0 ? $this->sites->find($this->siteId) : null;
        if (!$site instanceof Site) {
            throw new \RuntimeException(sprintf('Site #%d was not found.', $this->siteId));
        }

        return $site;
    }

    private function requireLocale(): Locale
    {
        $locale = $this->locales->findOneBy(['code' => $this->localeCode]);
        if (!$locale instanceof Locale) {
            throw new \RuntimeException(sprintf('Locale "%s" was not found.', $this->localeCode));
        }

        return $locale;
    }

    private function requireCategory(Site $site, Locale $locale): BlogCategory
    {
        $category = $this->categories->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => $this->categorySlug,
        ]);
        if ($category instanceof BlogCategory) {
            return $category;
        }

        $name = $this->localeCode === 'uk' ? 'Поради покупцям' : 'Buyer guides';
        $category = (new BlogCategory())
            ->setSite($site)
            ->setLocale($locale)
            ->setName($name)
            ->setSlug($this->slugger->slugify($this->categorySlug))
            ->setEnabled(true)
            ->setPosition(1);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function uniqueSlug(string $base, Site $site, Locale $locale, ?int $currentId): string
    {
        $slug = $this->slugger->slugify($base);
        $candidate = $slug;
        $suffix = 2;

        while ($this->slugTaken($candidate, $site, $locale, $currentId)) {
            $candidate = $slug.'-'.$suffix;
            ++$suffix;
            if ($suffix > 50) {
                $candidate = $slug.'-'.substr(sha1($slug.$suffix), 0, 6);
                break;
            }
        }

        return $candidate;
    }

    private function slugTaken(string $slug, Site $site, Locale $locale, ?int $currentId): bool
    {
        $existing = $this->articles->findOneBy([
            'site' => $site,
            'locale' => $locale,
            'slug' => $slug,
        ]);

        return $existing instanceof BlogArticle && $existing->getId() !== $currentId;
    }

    private function attachFacebookDraft(BlogArticle $article, Site $site): void
    {
        try {
            $domain = trim((string) $site->getDomain());
            $base = $domain === '' ? null : (str_starts_with($domain, 'http') ? $domain : 'https://'.$domain);
            $article->setFacebookDraft($this->facebookPostFormatter->format($article, $base));
        } catch (\Throwable $exception) {
            $this->logger->warning(ContentAgentAction::ARTICLE_PUBLISHED, 'Facebook draft was skipped.', [
                'slug' => $article->getSlug(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
