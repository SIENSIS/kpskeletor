<?php

declare(strict_types=1);

namespace Modules\Core\Crud;

use CodeIgniter\Router\RouteCollection;
use Config\Kpcrudy as KpcrudyConfig;
use Kpcrudy\Crud;

/**
 * The only way modules create and route kpcrudy CRUDs (docs/specification.md, "CRUDs with
 * kpcrudy"; docs/cruds.md).
 *
 * ```php
 * // Config/Routes.php
 * CrudFactory::routes($routes, 'news-posts', 'backend/news/posts', '\Modules\News\Controllers\Posts::index');
 *
 * // Controllers/Posts.php (extends BackendController)
 * $crud = $this->crud('news-posts')->setTable('news_posts')->…;
 * return $this->renderCrud($crud);
 * ```
 *
 * `make()` gives a `Crud` (an `AuditedCrud`: every write goes to the activity log) with
 * `setResource($resource)` and the active theme's back-office layout;
 * nothing else is mapped by hand: `Config\Kpcrudy` selects `CrudPermissions` and
 * `denyByDefault`, so every operation is `<resource>.<operation>` in the catalogue.
 *
 * For `php spark permissions:check`, `inspect()` records the CRUDs a controller builds instead of
 * rendering them (`BackendController::renderCrud()` stops there).
 */
final class CrudFactory
{
    /** Resource names: `<module>-<resource>` or a Core/single-resource override (`backup`). */
    private const RESOURCE = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    /** @var list<Crud>|null CRUDs made while inspecting (null: not inspecting) */
    private static ?array $inspected = null;

    /**
     * A CRUD for a resource of the catalogue (e.g. `'news-posts'`).
     *
     * @throws \InvalidArgumentException for an invalid resource name
     */
    public static function make(string $resource, ?KpcrudyConfig $config = null): AuditedCrud
    {
        self::assertResource($resource);

        $config ??= config(KpcrudyConfig::class);
        $crud = new AuditedCrud($config);
        $crud->setResource($resource);

        if (function_exists('theme_layout')) {
            $crud->setLayout(theme_layout('backend'), 'content');
        }

        if (self::$inspected !== null) {
            self::$inspected[] = $crud;
        }

        return $crud;
    }

    /**
     * `Crud::routes()` with the `backend` chain, `can:<resource>.list` (each operation is then
     * checked by the resolver) and the route name `<resource>` (menus, `url_to()`).
     *
     * @param array<string, mixed> $options Extra CodeIgniter route options; extra filters are
     *                                      appended (e.g. `['filter' => 'reauth']`)
     */
    public static function routes(RouteCollection $routes, string $resource, string $path, string $handler, array $options = []): void
    {
        self::assertResource($resource);

        $extra = $options['filter'] ?? [];
        $extra = is_string($extra) ? [$extra] : (is_array($extra) ? array_values(array_filter($extra, is_string(...))) : []);

        $options['filter'] = array_values(array_unique(['backend', 'can:' . $resource . '.list', ...$extra]));
        $options['as']     = $resource;

        Crud::routes($routes, $path, $handler, $options);
    }

    /**
     * Starts recording the CRUDs `make()` creates (`permissions:check`).
     */
    public static function inspect(): void
    {
        self::$inspected = [];
    }

    public static function inspecting(): bool
    {
        return self::$inspected !== null;
    }

    /**
     * Stops recording and returns the CRUDs made since `inspect()`.
     *
     * @return list<Crud>
     */
    public static function inspected(): array
    {
        $cruds           = self::$inspected ?? [];
        self::$inspected = null;

        return $cruds;
    }

    private static function assertResource(string $resource): void
    {
        if (preg_match(self::RESOURCE, $resource) !== 1) {
            throw new \InvalidArgumentException("Invalid CRUD resource name \"{$resource}\" (expected <module>-<resource>, e.g. news-posts).");
        }
    }
}
