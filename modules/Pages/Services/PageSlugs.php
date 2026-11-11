<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use Modules\Core\Content\ContentTranslations;
use Modules\Core\Text\Slugger;

/**
 * Slugs of pages: generated from the title when left empty, **unique among the pages of the same
 * parent in the same language** (also trashed ones, which can be restored), and a root page never
 * takes the first segment of another route (`news`, `backend`, `login`…) or of a folder the web
 * server serves (`assets`, `uploads`…), since those would hide it.
 */
final class PageSlugs
{
    /** First segments always reserved (folders served by the web server, the front controller). */
    public const RESERVED = ['api', 'assets', 'backend', 'themes', 'uploads', 'vendor', 'writable'];

    /**
     * Fills the empty slugs of the posted translations (`kp_tr`) from their titles, made unique
     * among the siblings in that language; typed slugs are only trimmed and lower-cased (the
     * validation rule and `clash()` check them).
     *
     * @param array<string, mixed> $translations kp_tr: locale => fields
     *
     * @return array<string, mixed>
     */
    public function fill(array $translations, ?int $parent, ?int $exceptId): array
    {
        foreach ($translations as $locale => $fields) {
            if (! is_array($fields) || ! array_key_exists($locale, ContentTranslations::LOCALES)) {
                continue;
            }

            $title = trim(is_string($fields['title'] ?? null) ? $fields['title'] : '');
            $slug  = strtolower(trim(is_string($fields['slug'] ?? null) ? $fields['slug'] : ''));

            if ($slug === '' && $title !== '') {
                $base = Slugger::slug($title, 'page');
                $slug = $base;

                for ($i = 2; $this->taken($slug, (string) $locale, $parent, $exceptId) || ($parent === null && $this->reserved($slug)); $i++) {
                    $slug = $base . '-' . $i;
                }
            }

            $translations[$locale]['slug'] = $slug;
        }

        return $translations;
    }

    /**
     * The error of the first slug that clashes with a sibling or a reserved route, or null.
     *
     * @param array<string, array<string, mixed>> $translations locale => fields (kpcrudy's postedTranslations())
     */
    public function clash(array $translations, ?int $parent, ?int $exceptId): ?string
    {
        foreach ($translations as $locale => $fields) {
            $slug = is_string($fields['slug'] ?? null) ? $fields['slug'] : '';

            if ($slug === '') {
                continue;
            }

            $language = ContentTranslations::LOCALES[$locale] ?? $locale;

            if ($parent === null && $this->reserved($slug)) {
                return lang('Pages.errors.slugReserved', [$slug]);
            }

            if ($this->taken($slug, (string) $locale, $parent, $exceptId)) {
                return lang('Pages.errors.slugTaken', [$slug, $language]);
            }
        }

        return null;
    }

    /**
     * Whether a sibling page already uses the slug in the language.
     */
    public function taken(string $slug, string $locale, ?int $parent, ?int $exceptId): bool
    {
        $builder = db_connect()->table('page_translations')
            ->join('pages', 'pages.id = page_translations.page_id')
            ->where('page_translations.slug', $slug)
            ->where('page_translations.locale', $locale);

        $parent === null ? $builder->where('pages.parent_id', null) : $builder->where('pages.parent_id', $parent);

        if ($exceptId !== null) {
            $builder->where('pages.id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Whether a root slug would be hidden by another route or a served folder.
     */
    public function reserved(string $slug): bool
    {
        return in_array($slug, $this->reservedSlugs(), true);
    }

    /**
     * Reserved first segments: the constant list plus the first static segment of every route.
     *
     * @return list<string>
     */
    public function reservedSlugs(): array
    {
        $reserved = self::RESERVED;

        foreach (['GET', 'POST', '*'] as $verb) {
            foreach (array_keys(service('routes')->getRoutes($verb)) as $route) {
                $first = strtolower(explode('/', ltrim((string) $route, '/'))[0]);

                if ($first !== '' && preg_match(PageResolver::SLUG, $first) === 1) {
                    $reserved[] = $first;
                }
            }
        }

        return array_values(array_unique($reserved));
    }
}
