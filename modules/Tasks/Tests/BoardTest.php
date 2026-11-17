<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

/**
 * The board (tutorials 3 and 14): who sees which to-do tasks, the JSON endpoint of the component
 * `tasks/board`, and who may move a card (forged requests included).
 *
 * @internal
 */
final class BoardTest extends TasksTestCase
{
    public function testAUserSeesOnlyTheToDoTasksAssignedToThem(): void
    {
        $pau   = $this->createUser('pau@example.com', 'user');
        $other = $this->createUser('laia@example.com', 'user');
        $this->task('La meva', [], [(int) $pau->id]);
        $this->task('D’una altra persona', [], [(int) $other->id]);
        $this->login('pau@example.com');

        $page = $this->body('GET', 'backend/tasks');
        $this->assertStringContainsString('La meva', $page);
        $this->assertStringNotContainsString('D’una altra persona', $page);
        $this->assertStringContainsString('data-component="tasks/board"', $page);

        $data = json_decode((string) $this->send('GET', 'backend/tasks/board/data')->response()->getBody(), true);
        $this->assertSame(['La meva'], array_column($data['columns']['todo'], 'title'));
        $this->assertTrue($data['columns']['todo'][0]['canMove'], 'An assignee may move their to-do task.');
    }

    public function testUsersWhoListToDoTasksSeeThemAll(): void
    {
        $this->loginWith('tasks-board.view', 'tasks-tasks.list');
        $this->task('Una');
        $this->task('Dues', ['status' => 'doing']);

        $data = json_decode((string) $this->send('GET', 'backend/tasks/board/data')->response()->getBody(), true);
        $this->assertSame(['Una'], array_column($data['columns']['todo'], 'title'));
        $this->assertSame(['Dues'], array_column($data['columns']['doing'], 'title'));
        $this->assertFalse($data['columns']['todo'][0]['canMove'], 'Without tasks-tasks.edit and not assigned: read only.');
    }

    public function testTheBoardNeedsItsPermission(): void
    {
        $this->send('GET', 'backend/tasks')->assertRedirectTo(site_url('login'));

        $user = $this->loginWith();
        db_connect()->table('auth_groups_users')->where('user_id', $user->id)->delete();
        $this->login('ana@example.com');

        $this->send('GET', 'backend/tasks')->assertStatus(403);
        $this->send('GET', 'backend/tasks/board/data')->assertStatus(403);
    }

    public function testAnAssigneeMovesTheirToDoTaskAndTheMoveIsLogged(): void
    {
        $pau = $this->createUser('pau@example.com', 'user');
        $id  = $this->task('Moure-la', [], [(int) $pau->id]);
        $this->login('pau@example.com');

        $response = $this->withHeaders(['Accept' => 'application/json'])->send('POST', 'backend/tasks/board/' . $this->taskCode($id) . '/move', ['status' => 'doing']);
        $response->assertOK();
        $this->assertSame('doing', json_decode((string) $response->response()->getBody(), true)['status']);
        $this->assertSame('doing', $this->taskRow($id)['status']);

        // The same endpoint without JavaScript: a form post and a redirect.
        $this->withHeaders(['Accept' => 'text/html'])->send('POST', 'backend/tasks/board/' . $this->taskCode($id) . '/move', ['status' => 'done'])->assertRedirectTo(site_url('backend/tasks'));
        $this->assertSame('done', $this->taskRow($id)['status']);
        $this->assertNotNull($this->taskRow($id)['completed_at']);
        $this->assertSame(2, db_connect()->table('activity_log')->where('module', 'Tasks')->where('action', 'moved')->countAllResults());
    }

    public function testForgedMovesAreRefused(): void
    {
        $pau   = $this->createUser('pau@example.com', 'user');
        $other = $this->createUser('laia@example.com', 'user');
        $mine  = $this->task('La meva', [], [(int) $pau->id]);
        $their = $this->task('D’una altra', [], [(int) $other->id]);
        $this->login('pau@example.com');

        // Not visible to the user: 404, nothing changes.
        $this->assertNotFound('POST', 'backend/tasks/board/' . $this->taskCode($their) . '/move', ['status' => 'done']);
        $this->assertNotFound('POST', 'backend/tasks/board/not-a-code/move', ['status' => 'done']);
        $this->assertSame('todo', $this->taskRow($their)['status']);

        // An unknown status.
        $this->withHeaders(['Accept' => 'application/json'])->send('POST', 'backend/tasks/board/' . $this->taskCode($mine) . '/move', ['status' => 'archived'])->assertStatus(422);
        $this->assertSame('todo', $this->taskRow($mine)['status']);

        // Visible (tasks-tasks.list/show) but neither editor nor assignee: 403 and a security entry.
        $this->createUser('reader@example.com', 'user')->addPermission('tasks-tasks.list', 'tasks-tasks.show');
        $this->login('reader@example.com');
        $this->withHeaders(['Accept' => 'application/json'])->send('POST', 'backend/tasks/board/' . $this->taskCode($their) . '/move', ['status' => 'done'])->assertStatus(403);
        $this->assertSame('todo', $this->taskRow($their)['status']);
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'move_refused')->countAllResults());

        // A GET never moves.
        $this->assertNotFound('GET', 'backend/tasks/board/' . $this->taskCode($their) . '/move');
    }

    public function testEditorsMoveAnyToDoTask(): void
    {
        $this->loginWith('tasks-board.view', 'tasks-tasks.list', 'tasks-tasks.show', 'tasks-tasks.edit');
        $id = $this->task('Qualsevol');

        $this->withHeaders(['Accept' => 'application/json'])->send('POST', 'backend/tasks/board/' . $this->taskCode($id) . '/move', ['status' => 'doing'])->assertOK();
        $this->assertSame('doing', $this->taskRow($id)['status']);
    }
}
