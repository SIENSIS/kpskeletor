<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

use Modules\Core\Cache\CoreCache;
use Throwable;

/**
 * Renders widgets with failure isolation: an exception in a widget is written to the
 * application log and the card shows an error message instead of breaking the page. Async
 * widgets get a placeholder with their component and data URL; their data is cached per user,
 * language and options (`widgets` cache scope, `AsyncWidgetInterface::cacheSeconds()`).
 *
 * The renderer does not check permissions: the caller (the Dashboard module) only passes widgets
 * the user may see.
 */
final class WidgetRenderer
{
    public function __construct(private readonly CoreCache $cache = new CoreCache())
    {
    }

    /**
     * A widget's card: header (icon, title), body and the grid classes of its size.
     *
     * @param array<string, mixed>  $options Validated options
     * @param string|null           $dataUrl URL of the data of an async widget
     * @param array<string, string> $attributes Extra `data-*` attributes of the card's column
     */
    public function card(WidgetInterface $widget, array $options, string $size, ?string $dataUrl = null, array $attributes = []): string
    {
        $body = $this->body($widget, $options, $dataUrl);

        return theme_view('Modules\Core\Views\widgets\card', [
            'widget'     => $widget,
            'id'         => $widget->id(),
            'title'      => $this->safe(static fn (): string => $widget->title(), $widget->id()),
            'icon'       => $this->safe(static fn (): string => $widget->icon(), 'bi bi-grid'),
            'size'       => $size,
            'columns'    => WidgetSizes::columns($size),
            'body'       => $body['html'],
            'failed'     => $body['failed'],
            'attributes' => $attributes,
        ]);
    }

    /**
     * The body of a widget (its HTML, or the error message when it throws).
     *
     * @param array<string, mixed> $options
     *
     * @return array{html: string, failed: bool}
     */
    public function body(WidgetInterface $widget, array $options, ?string $dataUrl = null): array
    {
        try {
            $html = $widget->render($options);

            if ($widget instanceof AsyncWidgetInterface) {
                $html = theme_view('Modules\Core\Views\widgets\async', [
                    'component'   => $widget->component(),
                    'url'         => (string) $dataUrl,
                    'refresh'     => max(0, $widget->refreshSeconds()),
                    'placeholder' => $html,
                ]);
            }

            return ['html' => $html, 'failed' => false];
        } catch (Throwable $e) {
            $this->report($widget, $e);

            return ['html' => $this->error(), 'failed' => true];
        }
    }

    /**
     * The data of an async widget for a user (cached). Exceptions are not caught here: the
     * caller reports them (`report()`) and answers with `error()`.
     *
     * @param array<string, mixed> $options
     *
     * @return array{data: array<string, mixed>, html: string}
     */
    public function data(AsyncWidgetInterface $widget, int $userId, array $options): array
    {
        $compute = static function () use ($widget, $options): array {
            $data = $widget->data($options);

            return ['data' => $data, 'html' => $widget->renderData($data, $options)];
        };

        $seconds = $widget->cacheSeconds();

        if ($seconds <= 0) {
            return $compute();
        }

        $key = implode('.', ['data', $widget->id(), $userId, service('request')->getLocale(), substr(sha1((string) json_encode($options)), 0, 16)]);

        return $this->cache->remember('widgets', $key, $compute, $seconds);
    }

    /** Writes a widget failure to the application log. */
    public function report(WidgetInterface $widget, Throwable $e): void
    {
        log_message('error', 'Widget {id} failed: {class}: {message} in {file}:{line}', [
            'id'      => $widget->id(),
            'class'   => $e::class,
            'message' => $e->getMessage(),
            'file'    => clean_path($e->getFile()),
            'line'    => $e->getLine(),
        ]);
    }

    /** The body shown instead of a failing widget. */
    public function error(): string
    {
        return theme_view('Modules\Core\Views\widgets\error');
    }

    /**
     * @param \Closure(): string $value
     */
    private function safe(\Closure $value, string $fallback): string
    {
        try {
            return $value();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
