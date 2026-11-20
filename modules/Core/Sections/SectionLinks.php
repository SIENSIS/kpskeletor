<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use Modules\Core\Menus\MenuLinkType;
use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The targets of a section's links (docs/sections.md, "Links"), checked like menu items:
 *
 * - a module's link type (`$menuLinks`, the Core's `MenuLinkType`: a page, a news category…):
 *   one of its choices; the address is computed when the section is shown, so a renamed or moved
 *   target keeps working, and a target that is gone hides the link;
 * - `route`: the name of a GET route without parameters;
 * - `url`: an `https://` address with a host and no user name or password, or an internal path
 *   starting with a single `/`. Everything else is refused: `http:`, `javascript:`, `data:`,
 *   `mailto:`, protocol-relative `//host`, backslashes, spaces and control characters.
 */
final class SectionLinks
{
    public const URL   = 'url';
    public const ROUTE = 'route';

    public const MAX_LENGTH = 500;

    /** @var array<string, MenuLinkType>|null */
    private ?array $types = null;

    public function __construct(private readonly DeclarationRegistry $registry = new DeclarationRegistry())
    {
    }

    /**
     * The module link types of the enabled modules.
     *
     * @return array<string, MenuLinkType> key => type
     */
    public function types(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $this->types = [];

        foreach ($this->registry->menuLinkTypes() as $class) {
            try {
                $type = class_exists($class) ? new $class() : null;
            } catch (Throwable) {
                $type = null;
            }

            if ($type instanceof MenuLinkType) {
                $this->types[$type->key()] = $type;
            }
        }

        return $this->types;
    }

    /**
     * Every kind of target with its translated name: the module link types first.
     *
     * @return array<string, string>
     */
    public function typeLabels(): array
    {
        return array_map(static fn (MenuLinkType $type): string => $type->label(), $this->types())
            + [self::URL => lang('Sections.link.url'), self::ROUTE => lang('Sections.link.route')];
    }

    /**
     * Why a target cannot be saved, or null when it can.
     */
    public function error(string $type, string $value): ?string
    {
        $value = trim($value);

        if ($type === self::URL) {
            return self::checkUrl($value) === null ? lang('Sections.errors.url') : null;
        }

        if ($type === self::ROUTE) {
            return $this->routePath($value) === null ? lang('Sections.errors.route') : null;
        }

        $linkType = $this->types()[$type] ?? null;

        if ($linkType === null) {
            return lang('Sections.errors.linkType');
        }

        return array_key_exists($value, $linkType->options()) ? null : lang('Sections.errors.linkChoice');
    }

    /**
     * The address of a target (absolute), or null when it is gone.
     */
    public function href(string $type, string $value): ?string
    {
        if ($type === self::URL) {
            $url = self::checkUrl($value);

            if ($url === null) {
                return null;
            }

            return self::isInternal($url) ? site_url(ltrim($url, '/')) : $url;
        }

        if ($type === self::ROUTE) {
            $path = $this->routePath($value);

            return $path === null ? null : site_url($path);
        }

        $linkType = $this->types()[$type] ?? null;

        try {
            $url = $linkType?->url($value);
        } catch (Throwable) {
            $url = null;
        }

        return is_string($url) && (self::checkUrl($url) !== null || preg_match('#^https?://#i', $url) === 1) ? $url : null;
    }

    /**
     * The address when it is acceptable (`https://` or an internal path), else null.
     */
    public static function checkUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > self::MAX_LENGTH || preg_match('/[\s\x00-\x1F\x7F\\\\]/u', $url) === 1 || preg_match('//u', $url) !== 1) {
            return null;
        }

        if (self::isInternal($url)) {
            return $url;
        }

        if (preg_match('#^https://#i', $url) !== 1) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    /** An internal path: `/` followed by anything but a second `/` (protocol-relative). */
    public static function isInternal(string $url): bool
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//');
    }

    /** The path (no leading slash) of a GET route name without parameters, else null. */
    private function routePath(string $name): ?string
    {
        if ($name === '' || preg_match('/^[A-Za-z0-9_.:-]+$/', $name) !== 1) {
            return null;
        }

        try {
            $path = route_to($name);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($path) || str_contains($path, '(')) {
            return null;
        }

        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');
        $base = trim((string) parse_url(site_url(), PHP_URL_PATH), '/');

        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = trim(substr($path, strlen($base)), '/');
        }

        $path = (string) preg_replace('#^index\.php/?#', '', $path);

        return $path;
    }
}
