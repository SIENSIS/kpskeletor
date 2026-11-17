<?php

declare(strict_types=1);

namespace Modules\Tasks\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Auth\Services\Captcha;
use Modules\Core\Controllers\FrontendController;
use Modules\Tasks\Services\TaskRequests;

/**
 * The public form "request a to-do task" (tutorial 12), while the setting `Tasks.publicRequests`
 * is on (otherwise every URL here is a 404):
 *
 * - `GET tasks/request`: the form (name, email, title, details) with a **captcha that is always
 *   required**, whatever the setting `Auth.captcha` says (Auth's local arithmetic captcha,
 *   D-00-53; its image is served by this module at `GET tasks/request/captcha`);
 * - `POST tasks/request` (`ratelimit:forms`, CSRF): checks the captcha (single use) and the
 *   rules, stores the request, notifies the reviewers and queues the confirmation email
 *   (`TaskRequests::submit()`), then redirects back with a message (post/redirect/get).
 */
final class RequestForm extends FrontendController
{
    public function show(): string
    {
        $this->ensureOpen();

        return $this->form();
    }

    public function send(): RedirectResponse|string
    {
        $this->ensureOpen();

        $data = [];

        foreach (array_keys(TaskRequests::RULES) as $field) {
            $value        = $this->request->getPost($field);
            $data[$field] = is_string($value) ? trim($value) : '';
        }

        // The captcha first: it is single use, so it is checked once whatever else fails.
        if (! (new Captcha())->verify($this->request->getPost('captcha'))) {
            return $this->form($data, ['captcha' => lang('Tasks.form.captchaWrong')]);
        }

        if (! $this->validateData($data, TaskRequests::RULES)) {
            return $this->form($data, $this->validator?->getErrors() ?? []);
        }

        /** @var array{name: string, email: string, title: string, details: string} $data */
        (new TaskRequests())->submit($data, service('request')->getLocale());

        return redirect()->to(site_url('tasks/request'))->with('success', lang('Tasks.form.sent'));
    }

    public function captcha(): ResponseInterface
    {
        $this->ensureOpen();

        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setBody((new Captcha())->image());
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(array $old = [], array $errors = []): string
    {
        $this->meta([
            'description' => lang('Tasks.form.intro'),
            'canonical'   => site_url('tasks/request'),
            'robots'      => 'noindex',
        ]);

        return $this->render('Modules\Tasks\Views\public\request', [
            'title'       => lang('Tasks.form.title'),
            'old'         => $old,
            'errors'      => $errors,
            'breadcrumbs' => [['label' => lang('Tasks.public.title'), 'url' => site_url('tasks')], ['label' => lang('Tasks.form.title')]],
        ]);
    }

    private function ensureOpen(): void
    {
        if (! (bool) setting('Tasks.publicRequests')) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
