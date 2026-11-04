<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Config;

use Modules\{{Name}}\Tasks\{{Name}}Task;
use Modules\{{Name}}\Widgets\{{Name}}Widget;

/**
 * Manifest of the {{Name}} module (see docs/specification.md, "The module contract").
 */
final class Module extends \Modules\Core\Config\ModuleManifest
{
    public string $name     = '{{Name}}';
    public string $version  = '0.1.0';
    public string $icon     = 'bi bi-box';
    public array $requires  = ['Core' => '{{coreConstraint}}'];

    public array $resources = [
        'main' => ['label' => '{{Name}}.res.main', 'type' => 'page'],
    ];

    public array $defaultGrants = [
        'admin' => ['{{name}}-main.*'],
    ];

    public array $menu = [
        ['resource' => 'main', 'label' => '{{Name}}.menu.main', 'icon' => 'bi bi-box', 'order' => 100],
    ];

    public array $widgets = [{{Name}}Widget::class];
    public array $tasks   = [{{Name}}Task::class];
}
