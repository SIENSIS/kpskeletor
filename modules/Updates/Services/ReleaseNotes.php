<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

use Modules\Core\Security\HtmlSanitizer;

/**
 * Release notes (Markdown written in the repository) as safe HTML: everything is escaped first,
 * then a small subset is formatted (headings, lists, paragraphs, **bold**, `code`, https links)
 * and the result goes through HTMLPurifier. Anything else stays as text.
 */
final class ReleaseNotes
{
    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));

        if ($markdown === '') {
            return '';
        }

        $html = [];
        $list = [];
        $para = [];

        foreach (explode("\n", $markdown) as $line) {
            $line = rtrim($line);

            if (trim($line) === '') {
                self::flush($html, $list, $para);
            } elseif (preg_match('/\A(#{1,6})\s+(.+)\z/', $line, $m) === 1) {
                self::flush($html, $list, $para);
                $level  = min(6, strlen($m[1]) + 3);
                $html[] = '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
            } elseif (preg_match('/\A\s*[-*+]\s+(.+)\z/', $line, $m) === 1) {
                $none = [];
                self::flush($html, $none, $para);
                $list[] = self::inline($m[1]);
            } else {
                $none = [];
                self::flush($html, $list, $none);
                $para[] = self::inline(trim($line));
            }
        }

        self::flush($html, $list, $para);

        return HtmlSanitizer::clean(implode("\n", $html));
    }

    /**
     * Closes the open paragraph and list.
     *
     * @param list<string> $html
     * @param list<string> $list
     * @param list<string> $para
     */
    private static function flush(array &$html, array &$list, array &$para): void
    {
        if ($para !== []) {
            $html[] = '<p>' . implode('<br>', $para) . '</p>';
            $para   = [];
        }

        if ($list !== []) {
            $html[] = '<ul><li>' . implode('</li><li>', $list) . '</li></ul>';
            $list   = [];
        }
    }

    private static function inline(string $text): string
    {
        $text = esc($text);
        $text = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);

        return (string) preg_replace('/\[([^\]]+)\]\((https:\/\/[^\s)"<>]+)\)/', '<a href="$2" rel="noopener noreferrer">$1</a>', $text);
    }
}
