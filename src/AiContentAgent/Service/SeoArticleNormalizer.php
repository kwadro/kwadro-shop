<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\Dto\SeoArticleDraft;

/**
 * Enforces SEO limits used by the shop blog: one page title, meta length, slug charset, no extra H1.
 */
final class SeoArticleNormalizer
{
    private const META_TITLE_MAX = 70;
    private const META_DESCRIPTION_MIN = 110;
    private const META_DESCRIPTION_MAX = 160;

    public function __construct(
        private readonly ArticleSlugger $slugger,
    ) {
    }

    /**
     * @param list<string> $extraWarnings
     */
    public function normalize(SeoArticleDraft $draft, string $locale, array $extraWarnings = []): SeoArticleDraft
    {
        $locale = $locale === 'uk' ? 'uk' : 'en';
        $brand = $locale === 'uk' ? 'Квадро' : 'Kvadro';
        $warnings = $extraWarnings;

        $keyword = trim($draft->focusKeyword);
        $title = $this->singleLine($draft->title !== '' ? $draft->title : $keyword);
        if ($title === '') {
            $title = $locale === 'uk' ? 'Практичний гід' : 'Practical guide';
        }
        if (mb_strlen($title) > 180) {
            $title = rtrim(mb_substr($title, 0, 180));
            $warnings[] = 'Article title was shortened to 180 characters.';
        }

        $metaTitle = $this->fitMetaTitle(
            $this->singleLine($draft->metaTitle !== '' ? $draft->metaTitle : $title.' | '.$brand),
            $title,
            $brand,
        );
        $descriptionSeed = $this->singleLine($draft->metaDescription !== '' ? $draft->metaDescription : $title);
        $metaDescription = $this->fitDescription($descriptionSeed, $locale, $brand);
        if (mb_strlen($descriptionSeed) > self::META_DESCRIPTION_MAX) {
            $warnings[] = 'Meta description was shortened to '.self::META_DESCRIPTION_MAX.' characters.';
        }

        $html = $this->sanitizeHtml($draft->contentHtml);
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if ($keyword !== '' && mb_stripos($plain, $keyword) === false) {
            $html = '<p>'.$this->e($keyword).'.</p>'."\n".$html;
            $warnings[] = 'Focus keyword was missing from the body and was added to the opening.';
        }
        if (preg_match_all('#<h2\b#i', $html) < 2) {
            $warnings[] = 'Article has fewer than two H2 headings.';
        }

        $slugSource = $draft->slug !== '' ? $draft->slug : $title;
        $slug = $this->slugger->slugify($slugSource);
        $tags = $this->tags($draft->tags, $keyword);

        return new SeoArticleDraft(
            title: $title,
            metaTitle: $metaTitle,
            metaDescription: $metaDescription,
            ogTitle: $this->singleLine($draft->ogTitle !== '' ? $draft->ogTitle : $title),
            ogDescription: $this->singleLine($draft->ogDescription !== '' ? $draft->ogDescription : $metaDescription),
            slug: $slug,
            tags: $tags,
            focusKeyword: $keyword !== '' ? $keyword : $title,
            contentHtml: $html,
            warnings: $warnings,
        );
    }

    private function fitMetaTitle(string $metaTitle, string $title, string $brand): string
    {
        $metaTitle = $this->singleLine($metaTitle);
        if ($metaTitle === '') {
            $metaTitle = $title.' | '.$brand;
        }
        if (mb_strlen($metaTitle) <= self::META_TITLE_MAX) {
            return $metaTitle;
        }

        $suffix = ' | '.$brand;
        $room = self::META_TITLE_MAX - mb_strlen($suffix);
        if ($room < 12) {
            return mb_substr($title, 0, self::META_TITLE_MAX);
        }

        $cut = mb_substr($title, 0, $room);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > 12) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t,.;:-|").$suffix;
    }

    private function fitDescription(string $text, string $locale, string $brand): string
    {
        $text = $this->singleLine($text);
        if (mb_strlen($text) > self::META_DESCRIPTION_MAX) {
            $cut = mb_substr($text, 0, self::META_DESCRIPTION_MAX);
            $space = mb_strrpos($cut, ' ');
            if ($space !== false && $space >= self::META_DESCRIPTION_MIN) {
                $cut = mb_substr($cut, 0, $space);
            }

            return rtrim($cut, " \t,.;:-");
        }

        if (mb_strlen($text) < self::META_DESCRIPTION_MIN) {
            $pad = $locale === 'uk'
                ? ' Практичний чеклист перед покупкою в '.$brand.'.'
                : ' Practical checklist before you buy from '.$brand.'.';
            $text = rtrim($text, '.').'.'.trim($pad);
            if (mb_strlen($text) > self::META_DESCRIPTION_MAX) {
                return $this->fitDescription(mb_substr($text, 0, self::META_DESCRIPTION_MAX), $locale, $brand);
            }
        }

        return $text;
    }

    private function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*/\s*>#is', '', $html) ?? $html;
        $html = preg_replace('#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
        $html = preg_replace('#<h1\b([^>]*)>#i', '<h2$1>', $html) ?? $html;
        $html = preg_replace('#</h1>#i', '</h2>', $html) ?? $html;

        return trim($html);
    }

    private function tags(string $tags, string $keyword): string
    {
        $parts = preg_split('/\s*,\s*/u', $tags) ?: [];
        if ($keyword !== '') {
            array_unshift($parts, $keyword);
        }

        $unique = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $key = mb_strtolower($part);
            $unique[$key] = mb_substr($part, 0, 80);
            if (count($unique) >= 8) {
                break;
            }
        }

        return implode(', ', array_values($unique));
    }

    private function singleLine(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
