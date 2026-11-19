<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Backup module (discovered by CodeIgniter; never queries the database).
 *
 * - `backend/backups`: the list (kpcrudy, `backup.list`; delete `backup.delete`);
 * - `backend/backups/create` (`backup.create`), `verify/<id>` (`backup.list`),
 *   `download/<id>` (`backup.download` + `reauth`), `recover/<id>` (restore) and `upload`
 *   (`backup.restore` + `reauth`), `jobs/<job>` (the result of a restore, `backup.restore`);
 *   registered before the CRUD; not `restore/<id>`, which is kpcrudy's own trash URL;
 * - `progress/backup/<job>`: the progress of a running restore as JSON (step, percentage, status;
 *   the job id, 128 random bits, is the capability). Outside `backend/` because the database and
 *   the session may be replaced while it runs: `progress/*` paths run no filter that reads the
 *   database or the session (app/Config/Filters.php) and maintenance mode lets it through.
 *
 * @var RouteCollection $routes
 */
$routes->group(backend_path('backups'), ['namespace' => 'Modules\Backup\Controllers'], static function (RouteCollection $routes): void {
    $create  = ['filter' => 'can:backup.create'];
    $list    = ['filter' => 'can:backup.list'];
    $restore = ['filter' => ['can:backup.restore', 'reauth']];

    $routes->get('create', 'Backups::createForm', $create + ['as' => 'backup-create']);
    $routes->post('create', 'Backups::create', ['filter' => ['can:backup.create', 'ratelimit:forms']]);
    $routes->get('verify/(:segment)', 'Backups::verifyForm/$1', $list);
    $routes->post('verify/(:segment)', 'Backups::verify/$1', ['filter' => ['can:backup.list', 'ratelimit:forms']]);
    $routes->get('download/(:segment)', 'Backups::download/$1', ['filter' => ['can:backup.download', 'reauth']]);
    $routes->get('recover/(:segment)', 'Backups::restoreForm/$1', $restore);
    $routes->post('recover/(:segment)', 'Backups::restore/$1', ['filter' => ['can:backup.restore', 'reauth', 'ratelimit:forms']]);
    $routes->get('upload', 'Backups::uploadForm', $restore + ['as' => 'backup-upload']);
    $routes->post('upload', 'Backups::upload', ['filter' => ['can:backup.restore', 'reauth', 'ratelimit:uploads']]);
    $routes->get('jobs/(:segment)', 'Backups::job/$1', ['filter' => 'can:backup.restore']);
});

CrudFactory::routes($routes, 'backup', backend_path('backups'), '\Modules\Backup\Controllers\Backups::index');

// No rate limit: it reads one small file, and the limiter's settings live in the database being restored.
$routes->get('progress/backup/(:segment)', '\Modules\Backup\Controllers\Jobs::progress/$1');
