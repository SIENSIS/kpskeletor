<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Settings\Config\Settings as BaseSettings;
use Modules\Core\Settings\ManifestDefaultsHandler;

/**
 * codeigniter4/settings: values stored in the database; the manifest defaults of the module
 * settings answer when nothing has been saved (decision D-00-06).
 */
class Settings extends BaseSettings
{
    /**
     * @var list<string>
     */
    public $handlers = ['database', 'manifest'];

    /**
     * Manifest defaults of module settings (read only, after the database).
     *
     * @var array<string, mixed>
     */
    public $manifest = [
        'class'     => ManifestDefaultsHandler::class,
        'writeable' => false,
    ];
}
