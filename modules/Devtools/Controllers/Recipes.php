<?php

declare(strict_types=1);

namespace Modules\Devtools\Controllers;

use Modules\Core\Controllers\BackendController;
use Modules\Devtools\Recipes\RecipeBook;

/**
 * The CRUD recipes (`backend/devtools/recipes`, `devtools.view`): one card per variant with what
 * it is, when to use it, real code and the doc section (RecipeBook).
 */
final class Recipes extends BackendController
{
    public function index(): string
    {
        $this->breadcrumb(lang('Devtools.title'), backend_url('devtools'))->breadcrumb(lang('Devtools.recipes.title'));

        return $this->render('Modules\Devtools\Views\recipes', [
            'title'   => lang('Devtools.recipes.title'),
            'icon'    => 'bi bi-journal-code',
            'recipes' => (new RecipeBook())->all(),
        ]);
    }
}
