<?php

declare(strict_types=1);

namespace Config;

use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Security\MaskKey;

/**
 * kpcrudy configuration of every back-office CRUD (docs/specification.md, "CRUDs with kpcrudy";
 * every option and its default: vendor/siensis/kpcrudy/src/Config/Kpcrudy.php).
 *
 * CRUDs are created with `Modules\Core\Crud\CrudFactory::make('<module>-<resource>')`, which also
 * sets the active theme's back-office layout; never `new Crud()` in a module.
 */
class Kpcrudy extends \Kpcrudy\Config\Kpcrudy
{
    public string $theme = 'bootstrap5';

    /**
     * The theme's back-office layout with its `content` section (set by the constructor from the
     * active theme; `false` = only the CRUD fragment, never the library's CDN page).
     */
    public string|false $layout = false;

    public ?string $layoutSection = 'content';

    /** The layout prints the title and the breadcrumbs (page header). */
    public bool $showTitle = false;

    /**
     * The CRUD's messages go to the session keys the layout shows as toasts
     * (themes/default/Views/partials/flash.php).
     *
     * @var array<string, string>
     */
    public array $flashKeys = [
        'success' => 'success',
        'danger'  => 'error',
        'warning' => 'warning',
        'info'    => 'info',
    ];

    /** One resolver for every CRUD: `<resource>.<operation>` through the Core's RBAC. */
    public ?string $permissionResolver = CrudPermissions::class;

    /** What no permission allows is denied. */
    public bool $denyByDefault = true;

    /** Ids in URLs are masked (never access control: every action is still authorised). */
    public bool $maskIds = true;

    /** `app.maskKey`, shared with kpapy (set by the constructor). */
    public string $maskKey = '';

    /**
     * kpcrudy's `upload`/`image` fields bypass the Storage service, so modules never use them
     * (file fields use the Storage-backed file picker; docs/library-requests.md). The path is a
     * non-public folder nothing reads.
     */
    public string $uploadPath = WRITEPATH . 'kpcrudy-unused-uploads';

    public string $uploadUrl = '';

    /** Date formats of the Core (Catalan/Spanish style). */
    public string $dateFormat = 'd/m/Y';

    public string $datetimeFormat = 'd/m/Y H:i';

    public function __construct()
    {
        parent::__construct();

        $this->maskKey = MaskKey::value();

        // Local copies (public/assets/vendor) instead of the library's CDN defaults; only the
        // library's own 'default' layout would load them.
        $base               = rtrim(config(App::class)->baseURL, '/') . '/assets/vendor/';
        $this->bootstrapCss = $base . 'bootstrap/css/bootstrap.min.css';
        $this->bootstrapJs  = $base . 'bootstrap/js/bootstrap.bundle.min.js';
        $this->iconsCss     = $base . 'bootstrap-icons/font/bootstrap-icons.min.css';

        if ($this->layout === false && function_exists('theme_layout')) {
            try {
                $this->layout = theme_layout('backend');
            } catch (\Throwable) {
                // No theme available (e.g. some CLI contexts): the CRUD renders as a fragment.
            }
        }
    }
}
