<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\I18n\Locales;

/**
 * What a section view knows about where it is shown: the language (and the default one), the
 * reader (null for guests), whether it is a preview and its position on the page (0 = first;
 * the page's `<h1>` is its title, so section titles are `<h2>`).
 */
final class RenderContext
{
    public function __construct(
        public readonly string $locale,
        public readonly string $defaultLocale,
        public readonly ?User $user = null,
        public readonly bool $preview = false,
        public readonly int $index = 0,
    ) {
    }

    public static function current(?User $user = null, bool $preview = false): self
    {
        return new self(service('request')->getLocale(), Locales::default(), $user, $preview);
    }

    public function at(int $index): self
    {
        return new self($this->locale, $this->defaultLocale, $this->user, $this->preview, $index);
    }

    public function isFirst(): bool
    {
        return $this->index === 0;
    }
}
