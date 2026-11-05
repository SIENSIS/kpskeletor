<?php

declare(strict_types=1);

namespace Tests\Support\Crud;

use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Controllers\BackendController;

/**
 * The sample CRUDs of the kpcrudy integration tests (routes in `SampleRoutes`):
 *
 * - `index()`: every note (`sample-notes`); the author field is hidden from users without
 *   `sample-notes.author` (`hideFields()`), and a `publish` custom operation (`addOperation()`);
 * - `mine()`: the user's own notes (`sample-mine`, `where('owner_id', …)`);
 * - `undeclared()` and `raw()`: mistakes `permissions:check` must report.
 */
final class SampleNotes extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('sample-notes')
            ->setTable('sample_notes')
            ->setSubject('Note', 'Notes')
            ->columns(['title', 'author', 'status'])
            ->fields(['title', 'body', 'author', 'status'])
            ->showFields(['title', 'body', 'author', 'status'])
            ->exportColumns(['title', 'author', 'status'])
            ->searchFields(['title', 'author'])
            ->addOperation('publish', static function (string $id, array $row, Crud $crud): string {
                $crud->getModel()->update($id, ['status' => 'published']);

                return 'Published.';
            }, ['label' => 'Publish', 'icon' => 'bi bi-send', 'confirm' => 'Publish this note?']);

        if (! $this->can('sample-notes.author')) {
            $crud->hideFields(['author']);
        }

        return $this->renderCrud($crud);
    }

    public function mine(): ResponseInterface|string
    {
        $crud = $this->crud('sample-mine')
            ->setTable('sample_notes')
            ->setSubject('Note', 'My notes')
            ->columns(['title', 'status'])
            ->fields(['title', 'body'])
            ->unsetAdd()->unsetDelete()->unsetExport() // not in the resource (manifest 'except')
            ->where('owner_id', (int) auth()->id());

        return $this->renderCrud($crud);
    }

    /**
     * A CRUD with a custom operation its manifest does not declare (caught by `CrudAuditor` and,
     * at run time outside production, by `CrudPermissions`).
     */
    public function undeclared(): ResponseInterface|string
    {
        $crud = $this->crud('sample-notes')->setTable('sample_notes')
            ->addOperation('archive', static fn (): null => null);

        return $this->renderCrud($crud);
    }

    /**
     * A controller that builds a CRUD without the factory (caught by `CrudAuditor`).
     */
    public function raw(): ResponseInterface|string
    {
        return (new Crud())->setTable('sample_notes')->setResource('sample-notes')->render();
    }
}
