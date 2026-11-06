<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;
use Modules\Core\Crud\CrudFactory;

/**
 * Routes of the Activity module (discovered by CodeIgniter; never queries the database).
 *
 * Both screens are read-only kpcrudy CRUDs (`backend` chain + `can:<resource>.list`). "My
 * activity" is registered first so `backend/activity/mine…` is not taken as an operation of
 * the full log.
 *
 * @var RouteCollection $routes
 */
CrudFactory::routes($routes, 'activity-mine', 'backend/activity/mine', '\Modules\Activity\Controllers\Activity::mine');
CrudFactory::routes($routes, 'activity-log', 'backend/activity', '\Modules\Activity\Controllers\Activity::index');
