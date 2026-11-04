<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Controllers;

use Modules\Core\Controllers\BackendController;

/**
 * Main back-office screen of the {{Name}} module (resource "main").
 */
final class Home extends BackendController
{
    public function index(): string
    {
        return $this->render('Modules\{{Name}}\Views\index', [
            'title' => lang('{{Name}}.title'),
        ]);
    }
}
