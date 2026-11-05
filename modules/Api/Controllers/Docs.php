<?php

declare(strict_types=1);

namespace Modules\Api\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Kpapy\OpenApi\DocsController;

/**
 * The API documentation (kpapy's `DocsController`): the page with "Try it" and the OpenAPI 3.1
 * document at `backend/api/docs` (`can:api.docs`), only the `api/v1` routes. With the setting
 * `Api.publicSpec` the document is also public at `api/v1/openapi.json` (`kpapy:public`, rate
 * limited per IP); otherwise that URL answers 404.
 */
final class Docs extends DocsController
{
    public function publicSpec(): ResponseInterface
    {
        if (setting('Api.publicSpec') !== true) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->spec();
    }

    protected function prefix(): string
    {
        return 'api/v1';
    }
}
