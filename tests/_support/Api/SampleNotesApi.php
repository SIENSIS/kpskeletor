<?php

declare(strict_types=1);

namespace Tests\Support\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Router\RouteCollection;
use Kpapy\Resource;

/**
 * The API side of the test-only "Sample" module (tests/_support/Crud): `sample_notes` as the
 * kpapy resource `sample-notes`, the same resource name and table as the sample CRUD, so a grant
 * and a masked id mean the same in both. Routes: `api/v1/sample/notes`.
 */
final class SampleNotesApi extends Controller
{
    public function index(): ResponseInterface
    {
        return (new Resource())
            ->setTable('sample_notes')
            ->setResource('sample-notes')
            ->fields(['id', 'title', 'body', 'status'])
            ->writeFields(['title', 'body', 'status'])
            ->setRules(['title' => 'required|max_length[150]'])
            ->render();
    }

    public static function routes(RouteCollection $routes): void
    {
        Resource::routes($routes, 'api/v1/sample/notes', '\\' . self::class . '::index', ['filter' => 'kpapy']);
    }
}
