<?php

declare(strict_types=1);

namespace Modules\Devtools\Recipes;

/**
 * A page of `docs/` split into its headings (with GitHub-style ids, so a recipe's `anchor`
 * scrolls to its section) and the plain text under each one. Read-only, shown escaped by
 * `Views/docs.php`; only names of existing `docs/<name>.md` files are accepted.
 */
final class DocPage
{
    public function __construct(private readonly string $root = ROOTPATH . 'docs')
    {
    }

    /**
     * @return list<array{level: int, title: string, id: string, text: string}>|null null when the page does not exist
     */
    public function sections(string $name): ?array
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) !== 1) {
            return null;
        }

        $file = rtrim($this->root, '/') . '/' . $name . '.md';

        if (! is_file($file)) {
            return null;
        }

        $sections = [['level' => 0, 'title' => '', 'id' => '', 'text' => '']];
        $fence    = false;

        foreach (preg_split('/\R/', (string) file_get_contents($file)) ?: [] as $line) {
            if (str_starts_with($line, '```')) {
                $fence = ! $fence;
            }

            if (! $fence && preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $line, $match) === 1) {
                $sections[] = ['level' => strlen($match[1]), 'title' => $match[2], 'id' => self::slug($match[2]), 'text' => ''];

                continue;
            }

            $sections[array_key_last($sections)]['text'] .= $line . "\n";
        }

        foreach ($sections as $i => $section) {
            $sections[$i]['text'] = trim($section['text'], "\n");
        }

        return array_values(array_filter($sections, static fn (array $s): bool => $s['level'] > 0 || $s['text'] !== ''));
    }

    /**
     * The id GitHub gives a heading: lower case, punctuation removed, spaces as hyphens.
     */
    public static function slug(string $heading): string
    {
        $text = mb_strtolower(trim($heading));
        $text = (string) preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $text);

        return (string) preg_replace('/\s/u', '-', $text);
    }
}
