<?php

declare(strict_types=1);

namespace Modules\Devtools\Controllers;

use Modules\Core\Controllers\BackendController;

/**
 * `backend/devtools` (`devtools.view`): the development tools and the locks that keep them out
 * of production (docs/devtools.md).
 */
final class Home extends BackendController
{
    public function index(): string
    {
        return $this->render('Modules\Devtools\Views\index', [
            'title' => lang('Devtools.title'),
            'icon'  => 'bi bi-tools',
            'tools' => [
                ['url' => backend_url('devtools/recipes'), 'icon' => 'bi bi-journal-code', 'title' => lang('Devtools.recipes.title'), 'text' => lang('Devtools.recipes.intro')],
            ],
        ]);
    }
}
