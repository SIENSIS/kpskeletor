<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

/**
 * Query parameters of one section (docs/sections.md, "Query parameters"): a section that pages or
 * searches (e.g. a full news list) names its parameters after its id (`page-12`, `q-12`), so two
 * lists on one page never clash, and builds links that keep the rest of the query string.
 */
final class SectionQuery
{
    public function __construct(private readonly SectionData $section)
    {
    }

    /** The parameter's name for this section: `<name>-<section id>` (`<name>` for unsaved ones). */
    public function name(string $name): string
    {
        return $this->section->id === null ? $name : $name . '-' . $this->section->id;
    }

    /** The parameter's value in the current request ('' when missing or not a string). */
    public function get(string $name): string
    {
        $value = service('request')->getGet($this->name($name));

        return is_string($value) ? trim($value) : '';
    }

    /** A positive whole number (1 when missing or not valid), e.g. the page of a list. */
    public function page(string $name = 'page', int $max = 10000): int
    {
        $value = $this->get($name);

        return ctype_digit($value) ? max(1, min($max, (int) $value)) : 1;
    }

    /** The id of the section's element the links jump back to. */
    public function anchor(): string
    {
        return 'kp-s' . ($this->section->id ?? 0);
    }

    /**
     * The current page's URL with this section's parameters changed ('' or null removes one) and
     * the others kept, jumping back to the section.
     *
     * @param array<string, int|string|null> $changes name => value
     */
    public function url(array $changes): string
    {
        /** @var array<string, mixed> $query */
        $query = (array) service('request')->getGet();

        foreach ($changes as $name => $value) {
            $key = $this->name($name);

            if ($value === null || $value === '' || ($name === 'page' && (int) $value <= 1)) {
                unset($query[$key]);
            } else {
                $query[$key] = (string) $value;
            }
        }

        $base = current_url();

        return $base . ($query === [] ? '' : '?' . http_build_query($query)) . '#' . $this->anchor();
    }
}
