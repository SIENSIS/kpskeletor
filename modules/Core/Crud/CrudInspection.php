<?php

declare(strict_types=1);

namespace Modules\Core\Crud;

use Kpcrudy\Crud;

/**
 * Thrown by `BackendController::renderCrud()` while `CrudFactory::inspect()` is on, so
 * `permissions:check` gets the configured CRUD without rendering anything.
 *
 * @internal
 */
final class CrudInspection extends \RuntimeException
{
    public function __construct(public readonly Crud $crud)
    {
        parent::__construct('CRUD inspected: ' . $crud->getResource());
    }
}
