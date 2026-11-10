<?php

declare(strict_types=1);

namespace Modules\News\Services;

use XMLWriter;

/**
 * The RSS 2.0 document of the public feed (`news/feed.xml`), written with XMLWriter (escaping
 * done by the writer; no view, so nothing can precede the XML declaration). Only public posts
 * reach it, with their summary as description.
 */
final class FeedWriter
{
    /**
     * @param list<array<string, mixed>> $items rows of `NewsRepository::page()`
     */
    public static function rss(string $title, string $link, string $self, string $description, string $language, array $items): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->startElement('channel');
        $xml->writeElement('title', $title);
        $xml->writeElement('link', $link);
        $xml->writeElement('description', $description);
        $xml->writeElement('language', $language);
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $self);
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        foreach ($items as $item) {
            $url = site_url('news/' . rawurlencode((string) $item['slug']));

            $xml->startElement('item');
            $xml->writeElement('title', (string) $item['title']);
            $xml->writeElement('link', $url);
            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', 'true');
            $xml->text($url);
            $xml->endElement();
            $xml->writeElement('pubDate', gmdate(DATE_RSS, (int) strtotime((string) $item['published_at'] . ' UTC')));

            if (($item['category'] ?? null) !== null) {
                $xml->writeElement('category', (string) $item['category']);
            }

            $xml->writeElement('description', (string) $item['summary']);
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
