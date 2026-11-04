<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;

/**
 * Base controller of every public page.
 *
 * Skeleton: the public layout, page metadata (SEO) and theme regions are added by the themes
 * session.
 */
abstract class FrontendController extends BaseController
{
    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        return view($view, $data);
    }
}
