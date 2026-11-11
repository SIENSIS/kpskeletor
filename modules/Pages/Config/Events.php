<?php

declare(strict_types=1);

/**
 * Events of the Pages module (discovered by CodeIgniter, also while the module is disabled, so
 * the listener checks it):
 *
 * - `homePage` (the Core's `App\Controllers\Home`, `GET /`): when Settings › Pages names a home
 *   page that the visitor may read, it is shown instead of the theme's default home.
 */

use CodeIgniter\Events\Events;
use Modules\Core\Frontend\HomePage;
use Modules\Core\Registry\ModuleState;
use Modules\Pages\Services\HomePageResolver;

Events::on(HomePage::EVENT, static function (HomePage $home): void {
    if ((new ModuleState())->isEnabled('Pages')) {
        (new HomePageResolver())->answer($home);
    }
});
