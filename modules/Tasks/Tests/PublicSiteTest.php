<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

use Modules\Auth\Services\Captcha;
use Modules\Tasks\Menu\ProjectLink;
use Modules\Tasks\Notifications\TaskRequested;

/**
 * The public site (tutorials 5 and 12): the public page with only public to-do tasks, the menu
 * link type, and the request form with its captcha, validation, rate limit, notification and
 * confirmation email.
 *
 * @internal
 */
final class PublicSiteTest extends TasksTestCase
{
    public function testThePublicPageShowsOnlyPublicToDoTasks(): void
    {
        $web    = $this->project('Nou web');
        $hidden = $this->project('Arxivat', ['active' => 0]);
        $this->task('Pública del web', ['visibility' => 'public', 'project_id' => $web, 'labels' => '["design"]']);
        $this->task('Privada del web', ['project_id' => $web]);
        $this->task('Pública d’un projecte inactiu', ['visibility' => 'public', 'project_id' => $hidden]);
        $this->task('Pública a la paperera', ['visibility' => 'public', 'deleted_at' => date('Y-m-d H:i:s')]);
        $this->task('Pública sense projecte', ['visibility' => 'public', 'status' => 'done']);

        $page = $this->body('GET', 'tasks');
        $this->assertStringContainsString('Pública del web', $page);
        $this->assertStringContainsString('Pública sense projecte', $page);
        $this->assertStringContainsString(lang('Tasks.labels.design'), $page);
        $this->assertStringNotContainsString('Privada del web', $page);
        $this->assertStringNotContainsString('projecte inactiu', $page);
        $this->assertStringNotContainsString('paperera', $page);
        $this->assertStringContainsString('<link rel="canonical" href="' . site_url('tasks') . '"', $page);
        $this->assertStringContainsString('<meta name="description"', $page);

        $project = $this->body('GET', 'tasks/project/nou-web');
        $this->assertStringContainsString('Pública del web', $project);
        $this->assertStringNotContainsString('Pública sense projecte', $project);

        $this->assertNotFound('GET', 'tasks/project/arxivat');
        $this->assertNotFound('GET', 'tasks/project/no-existeix');
    }

    public function testTheMenuLinkTypeOffersTheActiveProjects(): void
    {
        $this->project('Nou web');
        $this->project('Arxivat', ['active' => 0]);
        $link = new ProjectLink();

        $this->assertSame('tasks.project', $link->key());
        $this->assertSame(['*', 'nou-web'], array_keys($link->options()));
        $this->assertSame(site_url('tasks'), $link->url('*'));
        $this->assertSame(site_url('tasks/project/nou-web'), $link->url('nou-web'));
        $this->assertNull($link->url('arxivat'), 'An inactive project hides the menu item.');
    }

    public function testTheRequestFormNeedsTheRightCaptcha(): void
    {
        $this->body('GET', 'tasks/request');
        $this->assertStringContainsString('tasks/request/captcha', $this->body('GET', 'tasks/request'));

        // No captcha, a wrong one, and a right one used twice: refused, nothing stored.
        $this->send('POST', 'tasks/request', $this->request())->assertOK();
        $this->challenge(7);
        $this->send('POST', 'tasks/request', $this->request(['captcha' => '8']))->assertOK();
        $this->assertSame(0, db_connect()->table('task_requests')->countAllResults());
        $this->assertStringContainsString(lang('Tasks.form.captchaWrong'), $this->body('POST', 'tasks/request', $this->request(['captcha' => '7'])), 'Single use: the wrong answer removed the challenge.');
        $this->assertSame(0, db_connect()->table('task_requests')->countAllResults());
    }

    public function testTheCaptchaImageIsServedEvenWithTheLoginCaptchaOff(): void
    {
        setting()->set('Auth.captcha', false);
        $response = $this->send('GET', 'tasks/request/captcha');
        $response->assertOK();
        $this->assertSame('image/png', $response->response()->getHeaderLine('Content-Type'));
        $this->assertIsArray($_SESSION[Captcha::SESSION_KEY] ?? null, 'A new challenge is stored in the session.');
    }

    public function testAValidRequestIsStoredNotifiedAndConfirmedByEmail(): void
    {
        $boss = $this->createUser('boss@example.com', 'admin');
        $pau  = $this->createUser('pau@example.com', 'user');

        $this->challenge(5);
        $this->send('POST', 'tasks/request', $this->request(['captcha' => '5']))->assertRedirectTo(site_url('tasks/request'));

        $row = db_connect()->table('task_requests')->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('Afegir el calendari', $row['title']);
        $this->assertSame('new', $row['status']);
        $this->assertSame('en', $row['locale'], 'The visitor\'s language (tests run in English).');

        $this->assertSame(1, $this->notificationsOf((int) $boss->id, TaskRequested::class), 'Reviewers (tasks-requests.list) are notified.');
        $this->assertSame(0, $this->notificationsOf((int) $pau->id, TaskRequested::class));

        $mail = db_connect()->table('mail_queue')->where('to_email', 'marta@example.com')->get()->getRowArray();
        $this->assertIsArray($mail, 'A confirmation email is queued.');
        $this->assertSame(lang('Tasks.email.subject', [site_name()]), $mail['subject']);
        $this->assertStringContainsString('Afegir el calendari', (string) $mail['body']);
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'request_received')->countAllResults());
    }

    public function testTheConfirmationEmailIsInTheVisitorsLanguage(): void
    {
        $this->challenge(5);
        $this->withHeaders(['Accept-Language' => 'es-ES,es;q=0.9'])->send('POST', 'tasks/request', $this->request(['captcha' => '5']));
        $row = db_connect()->table('task_requests')->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('es', $row['locale']);

        $mail = db_connect()->table('mail_queue')->get()->getRowArray();
        $this->assertSame(lang('Tasks.email.subject', [site_name()], 'es'), $mail['subject']);
        $this->assertStringContainsString('lang="es"', (string) $mail['body']);
    }

    public function testInvalidRequestsAreRefusedWithTheirErrors(): void
    {
        $this->challenge(5);
        $page = $this->body('POST', 'tasks/request', $this->request(['captcha' => '5', 'email' => 'no-és-un-correu', 'title' => '']));

        $this->assertStringContainsString(lang('Tasks.form.errors'), $page);
        $this->assertStringContainsString('is-invalid', $page);
        $this->assertSame(0, db_connect()->table('task_requests')->countAllResults());
    }

    public function testTheFormIsGoneWhenTheSettingIsOff(): void
    {
        setting()->set('Tasks.publicRequests', false);

        $this->assertNotFound('GET', 'tasks/request');
        $this->assertNotFound('GET', 'tasks/request/captcha');
        $this->challenge(5);
        $this->assertNotFound('POST', 'tasks/request', $this->request(['captcha' => '5']));
        $this->assertStringNotContainsString('tasks/request', $this->body('GET', 'tasks'));
        $this->assertSame(0, db_connect()->table('task_requests')->countAllResults());

        setting()->forget('Tasks.publicRequests');
    }

    public function testTheFormIsRateLimited(): void
    {
        $this->assertContains('ratelimit:forms', $this->routeFilters('POST', 'tasks/request'));
    }

    public function testAcceptingARequestCreatesAPrivateToDoTask(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        db_connect()->table('task_requests')->insert(['name' => 'Marta', 'email' => 'marta@example.com', 'title' => 'Una idea', 'details' => 'Detalls', 'locale' => 'ca', 'status' => 'new', 'created_at' => date('Y-m-d H:i:s')]);
        $id   = (int) db_connect()->insertID();
        $code = \Kpcrudy\Crud::maskId($id, 'task_requests');

        $this->send('POST', 'backend/tasks/requests/accept/' . $code)->assertRedirect();

        $request = db_connect()->table('task_requests')->where('id', $id)->get()->getRowArray();
        $this->assertSame('accepted', $request['status']);
        $task = $this->taskRow((int) $request['task_id']);
        $this->assertSame('Una idea', $task['title']);
        $this->assertSame('private', $task['visibility']);

        // Already handled: nothing more happens.
        $this->send('POST', 'backend/tasks/requests/reject/' . $code)->assertRedirect();
        $this->assertSame('accepted', db_connect()->table('task_requests')->where('id', $id)->get()->getRowArray()['status']);
        $this->assertSame(1, db_connect()->table('tasks')->countAllResults());
    }

    public function testRequestsCannotBeAcceptedWithoutThePermission(): void
    {
        $this->loginWith('tasks-requests.list', 'tasks-requests.show');
        db_connect()->table('task_requests')->insert(['name' => 'Marta', 'email' => 'marta@example.com', 'title' => 'Una idea', 'locale' => 'ca', 'status' => 'new', 'created_at' => date('Y-m-d H:i:s')]);
        $code = \Kpcrudy\Crud::maskId((int) db_connect()->insertID(), 'task_requests');
        $list = 'backend/tasks/requests';

        $this->assertCrudRefused($this->send('POST', $list . '/accept/' . $code), $list, 'accept');
        $this->assertCrudRefused($this->send('POST', $list . '/reject/' . $code), $list, 'reject');
        $this->assertSame('new', db_connect()->table('task_requests')->get()->getRowArray()['status']);
        $this->assertSame(0, db_connect()->table('tasks')->countAllResults());
    }

    /**
     * A captcha challenge whose answer is `$answer` in the session.
     */
    private function challenge(int $answer): void
    {
        $_SESSION[Captcha::SESSION_KEY] = ['key' => 'test-key', 'hash' => hash_hmac('sha256', (string) $answer, 'test-key')];
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    private function request(array $values = []): array
    {
        return $values + ['name' => 'Marta Riera', 'email' => 'marta@example.com', 'title' => 'Afegir el calendari', 'details' => 'Al web públic.'];
    }

    /**
     * The filters of a route.
     *
     * @return list<string>
     */
    private function routeFilters(string $method, string $path): array
    {
        $routes = service('routes');
        $routes->loadRoutes();

        return $routes->getFiltersForRoute($path, $method);
    }
}
