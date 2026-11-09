<?php
/**
 * Placeholder of an async widget: the component loads `url` (JSON `{data, html}`), shows the
 * result and refreshes it every `refresh` seconds (0: never).
 *
 * @var string $component
 * @var string $url
 * @var int    $refresh
 * @var string $placeholder HTML shown while loading (escaped by the widget)
 */
?>
<div data-component="<?= esc($component, 'attr') ?>" data-url="<?= esc($url, 'attr') ?>" data-refresh="<?= (int) $refresh ?>" aria-live="polite" aria-busy="true">
    <div data-widget-content><?= $placeholder ?></div>
</div>
