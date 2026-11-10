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
        'main' => ['label' => '{{Name}}.res.main', 'type' => 'page'],{{crudResource}}
    ];

    public array $defaultGrants = [
        'admin' => ['{{name}}-main.*'{{crudGrant}}],
    ];

    public array $menu = [
        ['resource' => 'main', 'label' => '{{Name}}.menu.main', 'icon' => 'bi bi-box', 'order' => 100],{{crudMenu}}
    ];

    /** Settings (Settings › {{Name}}): read with setting('{{Name}}.perPage'). */
    public array $settings = [
        'perPage' => [
            'type'    => 'int',
            'default' => 20,
            'rules'   => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[100]',
            'label'   => '{{Name}}.settings.perPage',
            'help'    => '{{Name}}.settings.perPageHelp',
        ],
    ];

    public array $widgets = [{{Name}}Widget::class];
    public array $tasks   = [{{Name}}Task::class];

    /** Content backed up beyond the module's tables, e.g. ['files' => ['modules/{{name}}']] (storage folders). */
    public array $backup = [];

    /** API resources (kpapy, `api/v1/<path>`, docs/api.md), e.g. ['{{name}}' => ['path' => '{{name}}', 'handler' => '\\Modules\\{{Name}}\\Controllers\\Api\\Items::index']]. */
    public array $api = [];

    /** Extra Content-Security-Policy sources per directive (docs/frontend.md), e.g. ['img-src' => ['https://tiles.example.com']]. */
    public array $csp = [];

    /** HTTPS hosts this module calls through the OutboundClient (docs/outbound-requests.md). */
    public array $outboundHosts = [];
}
