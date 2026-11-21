<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The page models of the enabled modules (manifest `$pageModels`) and of the public theme and
 * its ancestors (`Theme.php` `$pageModels`), by key (D-47-02). A class that is missing, does not
 * extend `PageModel`, cannot be built, or has an invalid or repeated key (or a key whose owner
 * part is not the declaring module or theme) is skipped and logged.
 */
final class PageModelRegistry
{
    /** @var array<string, PageModel>|null */
    private ?array $models = null;

    public function __construct(private readonly DeclarationRegistry $registry = new DeclarationRegistry())
    {
    }

    /**
     * @return array<string, PageModel> key => model, modules first (manifest order), then themes
     */
    public function all(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $this->models = [];
        $owners       = array_map('strtolower', $this->registry->pageModels());

        try {
            $owners += service('theme')->pageModels('public');
        } catch (Throwable $e) {
            log_message('error', 'Theme page models could not be read: {message}', ['message' => $e->getMessage()]);
        }

        foreach ($owners as $class => $owner) {
            try {
                $model = class_exists($class) && is_subclass_of($class, PageModel::class) ? new $class() : null;
                $key   = $model?->key();
            } catch (Throwable $e) {
                $model = $key = null;
                log_message('error', 'Page model {class} could not be built: {message}', ['class' => $class, 'message' => $e->getMessage()]);
            }

            if (! $model instanceof PageModel || ! is_string($key) || preg_match(PageModel::KEY_PATTERN, $key) !== 1
                || $model->owner() !== strtolower(str_replace('-', '', $owner)) || isset($this->models[$key])) {
                log_message('error', 'Page model {class} skipped: not a PageModel, or an invalid, foreign or repeated key.', ['class' => $class]);

                continue;
            }

            $this->models[$key] = $model;
        }

        return $this->models;
    }

    public function get(string $key): ?PageModel
    {
        return $this->all()[$key] ?? null;
    }
}
