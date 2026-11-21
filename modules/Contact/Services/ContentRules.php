<?php

declare(strict_types=1);

namespace Modules\Contact\Services;

use Closure;

/**
 * Content rules of the contact form (spam layer 5): at most `Contact.maxLinks` links, none of
 * the `Contact.blockedWords` (case and accents ignored), and, when `Contact.latinScriptOnly` is
 * on, no message written mostly in another script. Single-line fields (name, email, subject)
 * must not contain control characters (no header injection).
 */
final class ContentRules
{
    /** Share of letters outside the Latin script above which a message is refused. */
    public const OTHER_SCRIPT_SHARE = 0.3;

    /** @var Closure(string): mixed */
    private readonly Closure $setting;

    /**
     * @param (Closure(string): mixed)|null $setting setting reader (default: `setting()`)
     */
    public function __construct(?Closure $setting = null)
    {
        $this->setting = $setting ?? static fn (string $key): mixed => setting($key);
    }

    /**
     * The reason a message is refused, or null.
     *
     * @param array{name: string, email: string, subject: string, message: string} $data
     *
     * @return string|null `header_injection`, `too_many_links`, `blocked_word` or `script`
     */
    public function check(array $data): ?string
    {
        foreach (['name', 'email', 'subject'] as $field) {
            if (self::hasControlCharacters($data[$field])) {
                return 'header_injection';
            }
        }

        $text = $data['name'] . "\n" . $data['subject'] . "\n" . $data['message'];

        if (self::countLinks($text) > max(0, (int) ($this->setting)('Contact.maxLinks'))) {
            return 'too_many_links';
        }

        if ($this->blockedWord($text) !== null) {
            return 'blocked_word';
        }

        if ((bool) ($this->setting)('Contact.latinScriptOnly') && self::otherScriptShare($data['subject'] . ' ' . $data['message']) > self::OTHER_SCRIPT_SHARE) {
            return 'script';
        }

        return null;
    }

    public static function hasControlCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/u', $value) === 1 || preg_match('/[\x{0085}\x{2028}\x{2029}]/u', $value) === 1;
    }

    /**
     * Links in a text: addresses with a scheme, `www.` addresses, HTML anchors and BBCode links.
     */
    public static function countLinks(string $text): int
    {
        $count = preg_match_all('~(?:https?|ftp)://|\bwww\.|<a\s|\[url~iu', $text);

        return $count === false ? 0 : $count;
    }

    /**
     * The first blocked word found in the text, or null.
     */
    public function blockedWord(string $text): ?string
    {
        $words = ($this->setting)('Contact.blockedWords');
        $text  = self::fold($text);

        foreach (is_array($words) ? $words : [] as $word) {
            $word = self::fold(trim((string) $word));

            if ($word !== '' && str_contains($text, $word)) {
                return $word;
            }
        }

        return null;
    }

    /**
     * The share (0–1) of letters that are not Latin.
     */
    public static function otherScriptShare(string $text): float
    {
        $letters = preg_match_all('/\p{L}/u', $text);
        $latin   = preg_match_all('/\p{Latin}/u', $text);

        return $letters === false || $letters === 0 ? 0.0 : ($letters - (int) $latin) / $letters;
    }

    /**
     * Lower case without accents, for comparisons.
     */
    private static function fold(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        if (class_exists(\Normalizer::class)) {
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_D);
        }

        return (string) preg_replace('/\p{Mn}/u', '', $text);
    }
}
