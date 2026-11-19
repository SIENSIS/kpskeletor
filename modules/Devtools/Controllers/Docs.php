<?php

declare(strict_types=1);

namespace Modules\Devtools\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use Modules\Core\Controllers\BackendController;
use Modules\Devtools\Recipes\DocPage;

/**
 * A page of `docs/` as plain text with its headings (`backend/devtools/docs/<name>`,
 * `devtools.view`), so the recipes link the section they come from.
 */
final class Docs extends BackendController
{
    public function show(string $name): string
    {
        $sections = (new DocPage())->sections($name);

        if ($sections === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->breadcrumb(lang('Devtools.title'), backend_url('devtools'))
            ->breadcrumb(lang('Devtools.recipes.title'), backend_url('devtools/recipes'))
            ->breadcrumb('docs/' . $name . '.md');

        return $this->render('Modules\Devtools\Views\docs', [
            'title'    => 'docs/' . $name . '.md',
            'icon'     => 'bi bi-book',
            'sections' => $sections,
        ]);
    }
}
