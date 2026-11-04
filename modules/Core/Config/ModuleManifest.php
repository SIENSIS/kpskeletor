<?php

declare(strict_types=1);

namespace Modules\Core\Config;

/**
 * Base class of every module manifest (`modules/<Name>/Config/Module.php`).
 *
 * The manifest is the single source of truth for everything the skeleton needs to know about a
 * module. Every key is typed and has a default; `ManifestValidator` checks the contents on
 * `modules:sync` and fails with a message naming the key. See docs/specification.md,
 * "The module contract".
 */
abstract class ModuleManifest
{
    /** Module name: StudlyCase, equal to the folder name (e.g. "News"). */
    public string $name = '';

    /** Semantic version of the module (e.g. "1.0.0"). */
    public string $version = '0.0.0';

    /** Bootstrap Icons class shown in menus and on the Modules screen. */
    public string $icon = 'bi bi-box';

    /**
     * Module dependencies: name => version constraint (e.g. ['Core' => '^1.0']).
     *
     * @var array<string, string>
     */
    public array $requires = [];

    /** Where Updates looks for new versions (e.g. "github:acme/news-module"). */
    public ?string $repository = null;

    /**
     * Resources: one per CRUD or screen. Permission names are "<module>-<resource>.<operation>".
     * Keys of each definition: label, type ('crud'|'page'), except (crud operations to drop),
     * extra (operation => label) and name (prefix override, Core and single-resource modules).
     *
     * @var array<string, array<string, mixed>>
     */
    public array $resources = [];

    /**
     * Default grants per group (atomic names or Shield wildcards), applied on first install.
     *
     * @var array<string, list<string>>
     */
    public array $defaultGrants = [];

    /**
     * Back-office menu entries (resource or route + permission, label, icon, order, zone, parent).
     *
     * @var list<array<string, mixed>>
     */
    public array $menu = [];

    /**
     * Link types offered to the public menu editor (class names).
     *
     * @var list<string>
     */
    public array $menuLinks = [];

    /**
     * Runtime settings: key => definition (type, default, rules, label, options).
     *
     * @var array<string, array<string, mixed>>
     */
    public array $settings = [];

    /** @var list<string> Dashboard widgets */
    public array $widgets = [];

    /** @var list<string> Scheduled tasks */
    public array $tasks = [];

    /** @var list<string> Notification types */
    public array $notifications = [];

    /**
     * Content the module contributes to backups beyond its tables (e.g. ['files' => ['news']]).
     *
     * @var array<string, list<string>>
     */
    public array $backup = [];

    /**
     * Calendars the module publishes events to: key => [label, color, permission (optional)].
     *
     * @var array<string, array<string, string>>
     */
    public array $calendars = [];

    /** @var list<string> On-the-fly calendar event providers */
    public array $calendarProviders = [];

    /**
     * API resources (kpapy) under /api/v1: key => [path, handler]; checked against Config/Routes.php.
     *
     * @var array<string, array<string, string>>
     */
    public array $api = [];

    /**
     * Extra Content-Security-Policy sources: directive => list of sources.
     *
     * @var array<string, list<string>>
     */
    public array $csp = [];

    /** @var list<string> Outbound host names the module calls through OutboundClient */
    public array $outboundHosts = [];

    /**
     * The permission prefix of a resource: its 'name' override or "<module>-<resource>".
     */
    public function permissionPrefix(string $resource): string
    {
        $definition = $this->resources[$resource] ?? [];
        $name       = $definition['name'] ?? null;

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return strtolower($this->name) . '-' . $resource;
    }
}
