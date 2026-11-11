<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use CodeIgniter\I18n\Time;
use XMLWriter;

/**
 * The XML sitemap of the public site (sitemaps.org protocol 0.9, written with XMLWriter): the
 * home page and every page that is **published, public and not marked `noindex`**, once per
 * distinct path (a page translated with other slugs has one URL per language), with its last
 * change. A page whose canonical URL is set elsewhere is left out (search engines index the
 * canonical one). Private pages, drafts, scheduled pages and trashed pages never appear.
 */
final class SitemapBuilder
{
    public function __construct(private readonly PageTree $tree = new PageTree())
    {
    }

    /**
     * @return list<array{loc: string, lastmod: string}>
     */
    public function entries(): array
    {
        $home    = (new HomePageResolver())->id();
        $entries = [['loc' => site_url('/'), 'lastmod' => '']];
        $rows    = PageVisibility::apply(db_connect()->table('pages')->select('pages.id, pages.updated_at, pages.created_at'), null, 'pages', true)
            ->where('pages.noindex', 0)
            ->orderBy('pages.parent_id')->orderBy('pages.position')->orderBy('pages.id')
            ->get()->getResultArray();
        $canonical = $this->canonicals(array_map('intval', array_column($rows, 'id')));

        foreach ($rows as $row) {
            $id      = (int) $row['id'];
            $lastmod = $this->date(is_string($row['updated_at']) ? $row['updated_at'] : (is_string($row['created_at']) ? $row['created_at'] : null));

            if ($id === $home) {
                $entries[0]['lastmod'] = $lastmod;

                continue;
            }

            foreach ($this->tree->paths($id) as $locale => $path) {
                if (! isset($canonical[$id][$locale])) {
                    $entries[] = ['loc' => site_url($path), 'lastmod' => $lastmod];
                }
            }
        }

        return $entries;
    }

    public function xml(): string
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($this->entries() as $entry) {
            $xml->startElement('url');
            $xml->writeElement('loc', $entry['loc']);

            if ($entry['lastmod'] !== '') {
                $xml->writeElement('lastmod', $entry['lastmod']);
            }

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Pages and languages with a canonical URL of their own: id => locale => true.
     *
     * @param list<int> $ids
     *
     * @return array<int, array<string, true>>
     */
    private function canonicals(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = db_connect()->table('page_translations')->select('page_id, locale')
            ->whereIn('page_id', $ids)->where('canonical IS NOT NULL')->where('canonical !=', '')
            ->get()->getResultArray();
        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['page_id']][(string) $row['locale']] = true;
        }

        return $map;
    }

    private function date(?string $value): string
    {
        return $value === null || $value === '' ? '' : Time::parse($value, 'UTC')->format('Y-m-d\TH:i:sP');
    }
}
