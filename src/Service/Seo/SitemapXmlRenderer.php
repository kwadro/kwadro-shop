<?php

namespace App\Service\Seo;

final class SitemapXmlRenderer
{
    /**
     * @param list<array{
     *     loc: string,
     *     lastmod: \DateTimeImmutable|null,
     *     changefreq: string,
     *     priority: string
     * }> $entries
     */
    public function render(array $entries, string $stylesheetUrl): string
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('    ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->writePi('xml-stylesheet', sprintf('type="text/xsl" href="%s"', $stylesheetUrl));
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($entries as $entry) {
            $writer->startElement('url');
            $writer->writeElement('loc', $entry['loc']);

            if ($entry['lastmod'] instanceof \DateTimeImmutable) {
                $writer->writeElement('lastmod', $entry['lastmod']->format('Y-m-d'));
            }

            $writer->writeElement('changefreq', $entry['changefreq']);
            $writer->writeElement('priority', $entry['priority']);
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return (string) $writer->outputMemory();
    }
}
