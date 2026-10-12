<?php

namespace App\Service\Product;

/**
 * Turns flat supplier descriptions ("Переваги тканини: Виготовлений із котону Добре поглинає вологу ...")
 * into structured HTML: section headings followed by <ul> lists, one item per feature/sentence.
 * Descriptions that already contain lists, tables or headings are returned unchanged.
 */
final class ProductDescriptionFormatter
{
    private const HEADING_MARK = "\x01";

    /** Section headings that suppliers glue into the running text. */
    private const INLINE_HEADING_PATTERN = '(?:'
        . '(?:Основні\s+(?:переваги|особливості|характеристики)|Переваги|Особливості|Характеристики|Технічні\s+характеристики)(?:\s+[\p{Ll}\'’ʼ-]+){0,5}\s*:'
        . '|Розміри?\s*:'
        . '|Комплектація\s*:'
        . '|Правила\s+(?:використання\s+(?:та|і)\s+)?догляду(?:\s+за(?:\s+[\p{Ll}\'’ʼ-]+){1,4}\s*:|\s*:?)'
        . '|Як\s+доглядати\s*:?'
        . '|Догляд\s*:'
        . '|Загальні\s+рекомендації\s*:?'
        . ')';

    /** Headings that stand on their own line without a colon ("<strong>Переваги</strong><br>..."). */
    private const BARE_HEADING_PATTERN = '/^(?:Основні\s+)?(?:Переваги|Особливості|Характеристики|Технічні\s+характеристики|Комплектація|Догляд|Розміри?)$/u';

    private const BULLET_PATTERN = '/[•●▪◦‣⁃∙]/u';

    /** @var array<string, string> */
    private array $cache = [];

    public function format(?string $html): string
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        if (preg_match('~<(ul|ol|li|table|h[1-6])\b~i', $html)) {
            return $html;
        }

        return $this->cache[$html] ??= $this->build($html);
    }

    private function build(string $html): string
    {
        $lines = $this->markHeadings($this->joinContinuationLines($this->toLines($html)));

        $blocks = [];
        $section = null;
        $hasStructure = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, self::HEADING_MARK)) {
                if ($section !== null) {
                    $blocks[] = $this->renderSection($section['heading'], $section['lines']);
                }
                $section = ['heading' => substr($line, 1), 'lines' => []];
                $hasStructure = true;
                continue;
            }

            if ($section !== null) {
                $section['lines'][] = $line;
                continue;
            }

            $bullets = $this->splitBullets($line, false);
            if (\count($bullets) > 1) {
                $hasStructure = true;
                $blocks[] = $this->renderList($bullets);
            } else {
                $blocks[] = '<p>' . $this->escape($line) . '</p>';
            }
        }

        if ($section !== null) {
            $blocks[] = $this->renderSection($section['heading'], $section['lines']);
        }

        return $hasStructure ? implode("\n", $blocks) : $html;
    }

    /** @return list<string> */
    private function toLines(string $html): array
    {
        $text = preg_replace('~<br\s*/?>~i', "\n", $html) ?? $html;
        $text = preg_replace('~</(p|div|section|article|blockquote)>~i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r", "\u{00A0}", "\t"], ['', ' ', '  '], $text);
        // Two or more spaces separate items in pasted plain text.
        $text = preg_replace('/ {2,}/u', "\n", $text) ?? $text;

        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * A line starting with a lowercase letter continues the previous one
     * (e.g. "<strong>Переваги</strong><br>тканини:").
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function joinContinuationLines(array $lines): array
    {
        $joined = [];
        foreach ($lines as $line) {
            if ($joined !== [] && preg_match('/^\p{Ll}/u', $line)) {
                $joined[\count($joined) - 1] .= ' ' . $line;
                continue;
            }
            $joined[] = $line;
        }

        return $joined;
    }

    /**
     * Moves known headings onto their own lines (prefixed with HEADING_MARK);
     * a short line ending with ":" is a heading too.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function markHeadings(array $lines): array
    {
        $pattern = '/(?:^|(?<=[\p{L}\d.!?)%°»"]\s))(' . self::INLINE_HEADING_PATTERN . ')(?=\s|$)/u';
        $result = [];

        foreach ($lines as $line) {
            $parts = array_values(array_filter(
                array_map('trim', preg_split($pattern, $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [$line]),
                static fn (string $part): bool => $part !== '',
            ));

            for ($i = 0, $count = \count($parts); $i < $count; ++$i) {
                $part = $parts[$i];

                // "Розміри: 22×15×20 см; Матеріал: ..." is a key-value list, not a section.
                if (preg_match('/^Розміри?\s*:$/u', $part) && isset($parts[$i + 1]) && str_contains($parts[$i + 1], ';')) {
                    $result[] = $part . ' ' . $parts[++$i];
                    continue;
                }

                if (preg_match('/^' . self::INLINE_HEADING_PATTERN . '$/u', $part) === 1 || $this->isStandaloneHeading($part)) {
                    $result[] = self::HEADING_MARK . $part;
                } else {
                    $result[] = $part;
                }
            }
        }

        return $result;
    }

    private function isStandaloneHeading(string $line): bool
    {
        if (preg_match(self::BARE_HEADING_PATTERN, $line)) {
            return true;
        }

        if (!preg_match('/^(\p{Lu}[\p{L}\'’ʼ\s-]{1,60}):$/u', $line, $matches)) {
            return false;
        }

        return \count(preg_split('/\s+/u', trim($matches[1])) ?: []) <= 6;
    }

    /** @param list<string> $lines */
    private function renderSection(string $heading, array $lines): string
    {
        $items = [];
        $hasMarkers = false;
        foreach ($lines as $line) {
            $lineItems = $this->splitItems($line, $markers);
            $hasMarkers = $hasMarkers || $markers;
            array_push($items, ...$lineItems);
        }

        $title = rtrim(trim($heading), ': ');

        if ($items === []) {
            return '<h3 class="shop-description__heading">' . $this->escape($title) . '</h3>';
        }

        if (\count($items) === 1 && !$hasMarkers) {
            return '<p><strong>' . $this->escape($title) . ':</strong> ' . $this->escape($items[0]) . '</p>';
        }

        return '<h3 class="shop-description__heading">' . $this->escape($title) . '</h3>' . "\n" . $this->renderList($items);
    }

    /**
     * @param-out bool $hadMarkers
     *
     * @return list<string>
     */
    private function splitItems(string $text, ?bool &$hadMarkers = null): array
    {
        $bullets = $this->splitBullets($text);
        if (\count($bullets) > 1 || preg_match(self::BULLET_PATTERN, $text)) {
            $hadMarkers = true;

            return $bullets;
        }

        $hadMarkers = false;
        $items = [];
        foreach (preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', $text) ?: [$text] as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            // Items glued without punctuation: "... котону Добре поглинає вологу ...", "... 40°C Не ...".
            if (!preg_match('/[.!?]$/u', $sentence)) {
                $parts = preg_split('/(?<=[\p{Ll}\d)%])\s+(?=\p{Lu}\p{Ll})|(?<=°C)\s+(?=\p{Lu})/u', $sentence) ?: [$sentence];
                foreach ($parts as $part) {
                    if (trim($part) !== '') {
                        $items[] = trim($part);
                    }
                }
                continue;
            }

            $items[] = $sentence;
        }

        return $items;
    }

    /** @return list<string> */
    private function splitBullets(string $text, bool $allowSemicolons = true): array
    {
        if (preg_match(self::BULLET_PATTERN, $text)) {
            $parts = preg_split(self::BULLET_PATTERN, $text) ?: [$text];
        } elseif ($allowSemicolons && str_contains($text, ';') && !preg_match('/[.!?]\s+\p{Lu}/u', $text)) {
            $parts = explode(';', $text);
        } else {
            return [$text];
        }

        $items = [];
        foreach ($parts as $part) {
            $part = trim(rtrim(trim($part), ';'));
            if ($part !== '' && preg_match('/[\p{L}\d]/u', $part)) {
                $items[] = $part;
            }
        }

        return $items;
    }

    /** @param list<string> $items */
    private function renderList(array $items): string
    {
        $html = '<ul class="shop-description__list">';
        foreach ($items as $item) {
            if (preg_match('/^(\p{L}[^:.!?]{1,39}):\s+(.+)$/u', $item, $matches)) {
                $html .= '<li><strong>' . $this->escape($matches[1]) . ':</strong> ' . $this->escape($matches[2]) . '</li>';
            } else {
                $html .= '<li>' . $this->escape($item) . '</li>';
            }
        }

        return $html . '</ul>';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }
}
