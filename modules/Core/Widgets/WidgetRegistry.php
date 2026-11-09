<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The dashboard widgets of the enabled modules (manifest `$widgets`, stored by `modules:sync`),
 * by id. A class that is missing, does not implement `WidgetInterface`, cannot be built or has an
 * invalid or repeated id is skipped (and logged), so one broken module never breaks the
 * dashboard.
 */
final class WidgetRegistry
{
    /** "<module>.<name>": lower case letters, digits and dashes. */
    public const ID_PATTERN = '/^[a-z][a-z0-9-]{0,49}\.[a-z][a-z0-9-]{0,49}$/';

    /** @var array<string, array{widget: WidgetInterface, module: string}>|null */
    private ?array $widgets = null;

    public function __construct(private readonly DeclarationRegistry $registry = new DeclarationRegistry())
    {
    }

    /**
     * Every widget of the enabled modules, in manifest order.
     *
     * @return array<string, WidgetInterface> id => widget
     */
    public function all(): array
    {
        return array_map(static fn (array $entry): WidgetInterface => $entry['widget'], $this->load());
    }

    public function get(string $id): ?WidgetInterface
    {
        return $this->load()[$id]['widget'] ?? null;
    }

    /** The module that declares a widget (null when unknown). */
    public function module(string $id): ?string
    {
        return $this->load()[$id]['module'] ?? null;
    }

    /**
     * @return array<string, array{widget: WidgetInterface, module: string}>
     */
    private function load(): array
    {
        if ($this->widgets !== null) {
            return $this->widgets;
        }

        $this->widgets = [];

        foreach ($this->registry->all('widget') as $class => $declaration) {
            try {
                $widget = class_exists($class) && is_subclass_of($class, WidgetInterface::class) ? new $class() : null;
                $id     = $widget?->id();
            } catch (Throwable $e) {
                $widget = $id = null;
                log_message('error', 'Widget {class} could not be built: {message}', ['class' => $class, 'message' => $e->getMessage()]);
            }

            if (! $widget instanceof WidgetInterface || ! is_string($id) || preg_match(self::ID_PATTERN, $id) !== 1 || isset($this->widgets[$id])) {
                log_message('error', 'Widget {class} skipped: not a WidgetInterface, or invalid or repeated id.', ['class' => $class]);

                continue;
            }

            $this->widgets[$id] = ['widget' => $widget, 'module' => $declaration['module']];
        }

        return $this->widgets;
    }
}
