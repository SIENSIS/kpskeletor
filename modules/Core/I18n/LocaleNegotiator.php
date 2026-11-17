<?php

declare(strict_types=1);

namespace Modules\Core\I18n;

/**
 * Chooses the locale of a request among the site's available locales: the user's profile, then
 * the browser (`Accept-Language`, by quality; `ca-ES` matches `ca`), then the site's default
 * (docs/i18n.md).
 */
final class LocaleNegotiator
{
    /**
     * @param list<string>|null $available the site's available locales (default: Locales::available())
     */
    public function __construct(
        private readonly ?array $available = null,
        private readonly ?string $default = null,
    ) {
    }

    public function negotiate(?string $profileLocale, string $acceptLanguage = ''): string
    {
        $available = $this->available ?? Locales::available();

        if (is_string($profileLocale) && in_array($profileLocale, $available, true)) {
            return $profileLocale;
        }

        return $this->fromHeader($acceptLanguage, $available) ?? $this->default ?? Locales::default();
    }

    /**
     * The best available locale of an `Accept-Language` header, or null.
     *
     * @param list<string> $available
     */
    public function fromHeader(string $header, array $available): ?string
    {
        $ranges = [];

        foreach (explode(',', $header) as $position => $part) {
            $pieces = array_map('trim', explode(';', $part));
            $tag    = strtolower(array_shift($pieces));

            if ($tag === '' || ! preg_match('/^(?:\*|[a-z]{1,8}(?:-[a-z0-9]{1,8})*)$/', $tag)) {
                continue;
            }

            $quality = 1.0;

            foreach ($pieces as $piece) {
                if (preg_match('/^q=([01](?:\.\d{0,3})?)$/i', $piece, $m)) {
                    $quality = (float) $m[1];
                }
            }

            if ($quality > 0) {
                $ranges[] = ['tag' => $tag, 'q' => $quality, 'pos' => $position];
            }
        }

        // Highest quality first; equal qualities keep the header's order.
        usort($ranges, static fn (array $a, array $b): int => [$b['q'], $a['pos']] <=> [$a['q'], $b['pos']]);

        foreach ($ranges as $range) {
            if ($range['tag'] === '*') {
                return null; // "anything": the site's default
            }

            foreach ([$range['tag'], explode('-', $range['tag'])[0]] as $candidate) {
                foreach ($available as $locale) {
                    if (strtolower($locale) === $candidate) {
                        return $locale;
                    }
                }
            }
        }

        return null;
    }
}
