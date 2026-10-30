<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\Dto\DiscoveredSource;
use App\AiContentAgent\Dto\SeoArticleDraft;

/**
 * Builds a structured SEO article from a topic and previously stored sources.
 * Article body language follows the shop locale (uk or en).
 */
final class SeoArticleComposer
{
    /**
     * @param list<DiscoveredSource> $sources
     */
    public function compose(string $topic, array $sources, string $locale): SeoArticleDraft
    {
        $locale = $locale === 'uk' ? 'uk' : 'en';
        $keyword = trim(preg_replace('/\s+/u', ' ', $topic) ?? $topic);
        $year = (new \DateTimeImmutable())->format('Y');
        $brand = $locale === 'uk' ? 'Квадро' : 'Kvadro';
        $title = $this->title($keyword, $year, $locale);
        $description = $this->description($keyword, $year, $brand, $locale);
        $tags = $locale === 'uk'
            ? $keyword.', поради, гід, покупка'
            : $keyword.', guide, tips, buying';

        return new SeoArticleDraft(
            title: $title,
            metaTitle: $title.' | '.$brand,
            metaDescription: $description,
            ogTitle: $title,
            ogDescription: $description,
            slug: '',
            tags: $tags,
            focusKeyword: $keyword,
            contentHtml: $this->html($keyword, $year, $locale, $sources),
        );
    }

    private function title(string $keyword, string $year, string $locale): string
    {
        if (preg_match('/\b20\d{2}\b/u', $keyword)) {
            return $keyword;
        }

        $suffix = $locale === 'uk' ? ': практичний гід '.$year : ': practical guide '.$year;
        if (mb_strlen($keyword.$suffix) > 180) {
            return $keyword;
        }

        return $keyword.$suffix;
    }

    private function description(string $keyword, string $year, string $brand, string $locale): string
    {
        if ($locale === 'uk') {
            return $keyword.' у '.$year.': що перевірити перед покупкою, як порівняти варіанти і яких помилок уникнути. Практичний чеклист від '.$brand.'.';
        }

        return $keyword.' in '.$year.': what to check before buying, how to compare options, and which mistakes to avoid. A practical checklist from '.$brand.'.';
    }

    /**
     * @param list<DiscoveredSource> $sources
     */
    private function html(string $keyword, string $year, string $locale, array $sources): string
    {
        $keywordHtml = $this->e($keyword);
        $blocks = [];

        if ($locale === 'uk') {
            $blocks[] = '<p>'.$keywordHtml.' простіше обрати, коли перед замовленням звірити сумісність, реальні обмеження і типові помилки покупців. Нижче — практичний гід '.$this->e($year).', який можна пройти як чеклист.</p>';
            $blocks[] = '<h2>Що вирішити на старті</h2>';
            $blocks[] = '<p>Перед тим як порівнювати ціни, зафіксуйте задачу. '.$keywordHtml.' має відповідати місцю використання, а не лише назві моделі.</p>';
            $blocks[] = '<ul>'
                .'<li>Де і як довго річ буде використовуватись.</li>'
                .'<li>Обмеження: розмір, вага, стандарт кріплення, умови монтажу.</li>'
                .'<li>Що обовʼязкове, а що лише бажане.</li>'
                .'</ul>';
            $blocks[] = '<h2>Як обрати: '.$keywordHtml.'</h2>';
            $blocks[] = '<p>Ідіть від обмежень до опцій. Так '.$keywordHtml.' не доведеться міняти після доставки.</p>';
            $blocks[] = '<ol>'
                .'<li>Запишіть параметри пристрою або місця, з якими покупка має зійтись.</li>'
                .'<li>Відкиньте варіанти, які не проходять за вагою, розміром або стандартом.</li>'
                .'<li>Порівняйте 2–3 моделі, що лишились, за комплектом і умовами монтажу.</li>'
                .'<li>Перевірте, що в комплекті є все потрібне для встановлення.</li>'
                .'<li>Лише після цього дивіться на ціну і термін відправки.</li>'
                .'</ol>';
            $blocks[] = '<h2>Помилки, які коштують грошей</h2>';
            $blocks[] = '<ul>'
                .'<li>Орієнтуватись лише на фото, без розмірів і допустимого навантаження.</li>'
                .'<li>Ігнорувати тип стіни, меблів або умов, де все буде стояти.</li>'
                .'<li>Брати «із запасом» там, де важливі точні розміри.</li>'
                .'<li>Не читати, що входить у комплект, і докуповувати кріплення окремо вже після оплати.</li>'
                .'</ul>';
            $blocks[] = '<h2>Короткі відповіді</h2>';
            $blocks[] = '<h3>Чи достатньо обрати найдешевший варіант?</h3>';
            $blocks[] = '<p>Ні. Для теми «'.$keywordHtml.'» спершу має зійтись сумісність. Ціна має сенс серед варіантів, які вже підходять.</p>';
            $blocks[] = '<h3>Що перевірити в день отримання?</h3>';
            $blocks[] = '<p>Комплект, кріплення і відсутність пошкоджень. Якщо щось не збігається з карткою товару, не починайте монтаж.</p>';
            $blocks[] = '<h3>Коли варто звернутись до консультанта?</h3>';
            $blocks[] = '<p>Якщо не сходяться розміри, стандарт кріплення або допустима вага. Краще уточнити до оплати, ніж повертати замовлення.</p>';
        } else {
            $blocks[] = '<p>'.$keywordHtml.' is easier to choose when you check compatibility, real limits, and the mistakes buyers usually make. Use this '.$this->e($year).' checklist before you order.</p>';
            $blocks[] = '<h2>What to decide first</h2>';
            $blocks[] = '<p>Fix the job before you compare prices. '.$keywordHtml.' has to fit the place and the device, not only the product title.</p>';
            $blocks[] = '<ul>'
                .'<li>Where it will be used and for how long.</li>'
                .'<li>Limits: size, weight, mounting standard, and installation conditions.</li>'
                .'<li>What is required and what is only nice to have.</li>'
                .'</ul>';
            $blocks[] = '<h2>How to choose: '.$keywordHtml.'</h2>';
            $blocks[] = '<p>Start from constraints, then look at options. That keeps '.$keywordHtml.' from coming back after delivery.</p>';
            $blocks[] = '<ol>'
                .'<li>Write down the device or place the purchase must match.</li>'
                .'<li>Drop options that fail weight, size, or the required standard.</li>'
                .'<li>Compare the two or three that remain by what is included and how they are installed.</li>'
                .'<li>Confirm the box contains the hardware you need.</li>'
                .'<li>Only then compare price and shipping time.</li>'
                .'</ol>';
            $blocks[] = '<h2>Mistakes that cost money</h2>';
            $blocks[] = '<ul>'
                .'<li>Choosing from photos only, without size or load limits.</li>'
                .'<li>Ignoring the wall, furniture, or conditions where it will live.</li>'
                .'<li>Buying extra capacity where the exact size is what matters.</li>'
                .'<li>Skipping the included hardware and discovering a missing mount after payment.</li>'
                .'</ul>';
            $blocks[] = '<h2>Short answers</h2>';
            $blocks[] = '<h3>Is the cheapest option enough?</h3>';
            $blocks[] = '<p>No. For '.$keywordHtml.', compatibility comes first. Price matters among options that already fit.</p>';
            $blocks[] = '<h3>What should you check on delivery day?</h3>';
            $blocks[] = '<p>The kit, the hardware, and any damage. If it does not match the product card, do not start installation.</p>';
            $blocks[] = '<h3>When should you ask for advice?</h3>';
            $blocks[] = '<p>When size, mounting standard, or allowed weight do not line up. Ask before you pay.</p>';
        }

        $sourcesHtml = $this->sourcesBlock($sources, $locale);
        if ($sourcesHtml !== '') {
            $blocks[] = $sourcesHtml;
        }

        return implode("\n", $blocks);
    }

    /**
     * @param list<DiscoveredSource> $sources
     */
    private function sourcesBlock(array $sources, string $locale): string
    {
        $items = [];
        foreach ($sources as $source) {
            $url = trim($source->url);
            if ($url === '') {
                continue;
            }
            $label = trim($source->title) !== '' ? trim($source->title) : $url;
            $line = '<li><a href="'.$this->e($url).'" rel="nofollow noopener noreferrer" target="_blank">'.$this->e($label).'</a>';
            $excerpt = trim($source->excerpt);
            if ($excerpt !== '') {
                $line .= ' — '.$this->e(mb_substr($excerpt, 0, 240));
            }
            $line .= '</li>';
            $items[] = $line;
        }

        if ($items === []) {
            return '';
        }

        $heading = $locale === 'uk' ? 'Джерела, на які спирається гід' : 'Sources used for this guide';

        return '<h2>'.$heading.'</h2>'."\n".'<ul>'.implode('', $items).'</ul>';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
