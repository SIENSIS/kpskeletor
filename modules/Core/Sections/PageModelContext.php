<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use Closure;

/**
 * What a page model needs to build its sections: the site's languages (texts for each one) and a
 * way to make placeholder images (stored by the caller on the public disk; their keys are
 * remembered so the sections' forms accept them).
 */
final class PageModelContext
{
    /** @var list<string> */
    private array $images = [];

    /**
     * @param list<string>                                                     $locales the site's languages
     * @param Closure(string, int, int, array{0: int, 1: int, 2: int}, array{0: int, 1: int, 2: int}, string): string $store name, width, height, from, to, kind => key
     */
    public function __construct(
        public readonly array $locales,
        public readonly string $defaultLocale,
        private readonly Closure $store,
    ) {
    }

    /**
     * A placeholder image (`PlaceholderImage`): `gradient` (soft shapes on a gradient), `figure`
     * (a simple person on a transparent background) or `logo` (a neutral mark). Returns its key.
     *
     * @param array{0: int, 1: int, 2: int} $from
     * @param array{0: int, 1: int, 2: int} $to
     */
    public function image(string $name, int $width, int $height, array $from, array $to, string $kind = 'gradient'): string
    {
        $key            = ($this->store)($name, $width, $height, $from, $to, $kind);
        $this->images[] = $key;

        return $key;
    }

    /**
     * Texts for every language of the site: `$texts($locale)` gives the parts of one language.
     *
     * @param Closure(string): array<string, mixed> $texts
     *
     * @return array<string, array<string, mixed>>
     */
    public function texts(Closure $texts): array
    {
        $all = [];

        foreach ($this->locales as $locale) {
            $all[$locale] = $texts($locale);
        }

        return $all;
    }

    /**
     * The keys of the images made so far.
     *
     * @return list<string>
     */
    public function images(): array
    {
        return $this->images;
    }
}
