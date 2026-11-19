<?php

declare(strict_types=1);

/**
 * Events of the Pages module (discovered by CodeIgniter, also while the module is disabled, so
 * the listener checks it):
 *
 * - `backendPathCheck` (D-40-01): a back-office prefix cannot be a top-level page slug.
 * - `homePage` (the Core's `App\Controllers\Home`, `GET /`): when Settings › Pages names a home
 *   page that the visitor may read, it is shown instead of the theme's default home.
 */

use CodeIgniter\Events\Events;
use Modules\Core\Frontend\HomePage;
use Modules\Core\Registry\ModuleState;
use Modules\Core\Routing\BackendPathCheck;
use Modules\Pages\Services\HomePageResolver;

Events::on(HomePage::EVENT, static function (HomePage $home): void {
    if ((new ModuleState())->isEnabled('Pages')) {
        (new HomePageResolver())->answer($home);
    }
});

// A back-office prefix (D-40-01) cannot be the address of a top-level page in any language.
Events::on('backendPathCheck', static function (BackendPathCheck $check): void {
    try {
        $taken = db_connect()->tableExists('page_translations') && db_connect()->table('page_translations')
            ->join('pages', 'pages.id = page_translations.page_id')
            ->where('pages.parent_id', null)
            ->where('page_translations.slug', $check->candidate)
            ->countAllResults() > 0;
    } catch (Throwable) {
        $taken = false; // no database yet (installer): nothing to clash with
    }

    if ($taken) {
        $check->refuse(lang('Core.backendPath.page', [$check->candidate]));
    }
});
