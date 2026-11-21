<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Sections\PageModelRegistry;
use Modules\Core\Sections\SectionValidationException;
use Modules\Pages\Services\PageModelBuilder;

/**
 * "New page from a model" (`backend/pages/models`, `pages.add`; D-47-02): the gallery of the
 * page models of the enabled modules and the public theme, and the creation of a draft page in
 * `sections` layout from one of them (then the section editor opens).
 */
final class PageModels extends BackendController
{
    public function index(): string
    {
        $this->breadcrumb(lang('Pages.pages'), backend_url('pages'))->breadcrumb(lang('Pages.models.new'));

        return $this->render('Modules\Pages\Views\editor\models', [
            'title'   => lang('Pages.models.new'),
            'icon'    => 'bi bi-file-earmark-plus',
            'models'  => (new PageModelRegistry())->all(),
            'action'  => backend_url('pages/models/create'),
            'backUrl' => backend_url('pages'),
        ]);
    }

    public function create(): RedirectResponse
    {
        $model = (new PageModelRegistry())->get((string) $this->request->getPost('model'));

        if ($model === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $id = (new PageModelBuilder())->create($model, (int) auth()->id());
        } catch (SectionValidationException $e) {
            log_message('error', 'Page model {model} is not valid: {message}', ['model' => $model->key(), 'message' => $e->getMessage()]);

            return redirect()->to(backend_url('pages/models'))->with('error', lang('Pages.models.invalid'));
        }

        activity_log('Pages', 'page_from_model', ['resource' => 'pages', 'record_id' => (string) $id, 'summary' => $model->label()]);

        return redirect()->to(backend_url('pages/' . $id . '/sections'))->with('success', lang('Pages.models.created', [$model->label()]));
    }
}
