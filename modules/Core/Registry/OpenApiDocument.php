<?php

declare(strict_types=1);

namespace Modules\Core\Registry;

use CodeIgniter\Router\RouteCollection;
use Config\Services;
use Kpapy\OpenApi\Generator;

/**
 * The OpenAPI 3.1 document of `api/v1` written by `modules:sync` (step 10, session 26) to
 * `writable/api/openapi.json`: a static copy for integrators and CI diffs, built by kpapy's
 * generator from the routes (every resource describes itself; nothing runs against the data).
 * It lives in `writable/`, which the web server never serves; the back office serves the live
 * document (`backend/api/docs/openapi.json`) and `Api.publicSpec` the public one.
 */
final class OpenApiDocument
{
    public const PREFIX = 'api/v1';

    public function __construct(private readonly ?string $path = null)
    {
    }

    public function path(): string
    {
        return $this->path ?? WRITEPATH . 'api' . DIRECTORY_SEPARATOR . 'openapi.json';
    }

    /**
     * Builds the document and writes it when it changed.
     *
     * @return array{changed: bool, paths: int, warnings: list<string>}
     */
    public function write(?RouteCollection $routes = null): array
    {
        $generator = new Generator(null, $routes ?? Services::routes(false)->loadRoutes());
        $document  = $generator->generate(self::PREFIX);
        // The server URL depends on how the command is run (CLI base URL): not part of the copy.
        unset($document['servers']);
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        $file = $this->path();

        $changed = ! is_file($file) || file_get_contents($file) !== $json;

        if ($changed) {
            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0750, true);
            }

            file_put_contents($file, $json, LOCK_EX);
            @chmod($file, 0640);
        }

        return [
            'changed'  => $changed,
            'paths'    => count((array) ($document['paths'] ?? [])),
            'warnings' => $generator->warnings,
        ];
    }
}
