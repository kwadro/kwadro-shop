<?php

namespace App\Service\Blog;

use App\Entity\BlogArticle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BlogFacebookPostFormatter
{
    private const DEFAULT_BODY_LIMIT = 700;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Build a Facebook-ready plain-text post (no HTML).
     * Facebook does not render HTML — post a short teaser + link to the article.
     */
    public function format(BlogArticle $article, ?string $baseUrl = null, int $bodyLimit = self::DEFAULT_BODY_LIMIT): string
    {
        $title = trim($article->getTitle());
        $lead = $this->resolveLead($article);
        $url = $this->resolveArticleUrl($article, $baseUrl);
        $hashtags = $this->buildHashtags($article);

        $parts = [];
        if ($title !== '') {
            $parts[] = $title;
        }
        if ($lead !== '') {
            $parts[] = $this->truncate($lead, $bodyLimit);
        }
        $parts[] = '👉 Читати повністю:'."\n".$url;
        if ($hashtags !== '') {
            $parts[] = $hashtags;
        }

        return implode("\n\n", $parts)."\n";
    }

    /**
     * Longer plain-text draft (still no HTML) — useful as a caption for photo posts.
     */
    public function formatExtended(BlogArticle $article, ?string $baseUrl = null, int $bodyLimit = 1800): string
    {
        $title = trim($article->getTitle());
        $body = $this->htmlToPlainText($article->getContent());
        $url = $this->resolveArticleUrl($article, $baseUrl);
        $hashtags = $this->buildHashtags($article);

        $parts = [];
        if ($title !== '') {
            $parts[] = $title;
        }
        if ($body !== '') {
            $parts[] = $this->truncate($body, $bodyLimit);
        }
        $parts[] = '👉 Повний матеріал на сайті:'."\n".$url;
        if ($hashtags !== '') {
            $parts[] = $hashtags;
        }

        return implode("\n\n", $parts)."\n";
    }

    public function resolveArticleUrl(BlogArticle $article, ?string $baseUrl = null): string
    {
        $locale = $article->getLocale()?->getCode() ?? 'uk';
        $path = $this->urlGenerator->generate('shop_blog_article', [
            '_locale' => $locale,
            'slug' => $article->getSlug(),
        ]);

        if ($baseUrl !== null && $baseUrl !== '') {
            return rtrim($baseUrl, '/').$path;
        }

        $domain = $article->getSite()?->getDomain();
        if ($domain !== null && $domain !== '') {
            $scheme = str_contains($domain, 'localhost') || str_ends_with($domain, '.local') ? 'http' : 'https';

            return $scheme.'://'.$domain.$path;
        }

        return $this->urlGenerator->generate('shop_blog_article', [
            '_locale' => $locale,
            'slug' => $article->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function resolveLead(BlogArticle $article): string
    {
        $meta = trim((string) $article->getMetaDescription());
        if ($meta !== '') {
            return $meta;
        }

        $plain = $this->htmlToPlainText($article->getContent());
        if ($plain === '') {
            return '';
        }

        // First 1–2 sentences as a hook.
        if (preg_match('/^(.+?[.!?…])(?:\s|$)/u', $plain, $m) === 1) {
            return trim($m[1]);
        }

        return $this->truncate($plain, 280);
    }

    public function htmlToPlainText(string $html): string
    {
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html;
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', '', $html) ?? $html;

        // Drop images/logos — Facebook posts cannot embed them from HTML.
        $html = preg_replace('#<img\b[^>]*>#i', '', $html) ?? $html;

        $html = preg_replace('#<(h[1-6])\b[^>]*>#i', "\n\n", $html) ?? $html;
        $html = preg_replace('#</h[1-6]>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<(p|div|section|article|header|footer)\b[^>]*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|section|article|header|footer)>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<li\b[^>]*>#i', "\n• ", $html) ?? $html;
        $html = preg_replace('#</li>#i', '', $html) ?? $html;
        $html = preg_replace('#</?(ul|ol)\b[^>]*>#i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function buildHashtags(BlogArticle $article): string
    {
        $tags = [];
        foreach ($article->getCategories() as $category) {
            $tag = $this->toHashtag($category->getName());
            if ($tag !== '') {
                $tags[$tag] = true;
            }
        }

        $slugHints = [
            'powerbank' => 'Повербанк',
            'antenu' => 'Т2',
            't2' => 'DVB_T2',
            'kanaly' => 'Телебачення',
            'suputnyk' => 'Супутник',
        ];
        foreach ($slugHints as $needle => $label) {
            if (str_contains($article->getSlug(), $needle)) {
                $tag = $this->toHashtag($label);
                if ($tag !== '') {
                    $tags[$tag] = true;
                }
            }
        }

        $tags[$this->toHashtag('Квадро')] = true;

        return implode(' ', array_keys(array_filter($tags)));
    }

    private function toHashtag(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ye',
            'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'yu',
            'я' => 'ya',
        ];

        // Keep Cyrillic hashtags (Facebook supports them); only strip spaces/punctuation.
        $tag = preg_replace('/[^\p{L}\p{N}_]+/u', '', str_replace(' ', '', $value)) ?? '';
        if ($tag === '') {
            $latin = strtr(mb_strtolower($value), $map);
            $tag = preg_replace('/[^a-z0-9_]+/', '', $latin) ?? '';
        }

        return $tag !== '' ? '#'.$tag : '';
    }

    private function truncate(string $text, int $limit): string
    {
        $text = trim($text);
        if ($limit < 1 || mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > (int) ($limit * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t.,;:").'…';
    }
}
