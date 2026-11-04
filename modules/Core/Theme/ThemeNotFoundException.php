<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

use RuntimeException;

/**
 * A theme folder or manifest is missing or invalid, or its parent chain is broken.
 */
final class ThemeNotFoundException extends RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self(lang('Core.theme.notFound', [$slug]));
    }

    public static function forManifest(string $slug, string $class): self
    {
        return new self(lang('Core.theme.notManifest', [$slug, $class]));
    }

    public static function forCycle(string $chain): self
    {
        return new self(lang('Core.theme.cycle', [$chain]));
    }
}
