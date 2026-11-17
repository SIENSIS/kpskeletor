<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

use CodeIgniter\I18n\Time;
use Kpcrudy\Crud;

/**
 * Helpers of the Tasks tests (tutorial 14): write projects and to-do tasks directly, mask ids.
 */
trait TasksFixtures
{
    /**
     * @param array<string, mixed> $values
     */
    protected function project(string $name = 'Nou web', array $values = []): int
    {
        $now = Time::now()->toDateTimeString();
        $db  = db_connect();
        $db->table('task_projects')->insert($values + [
            'slug' => url_title(strtolower($name), '-', true), 'color' => '#0d6efd', 'active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();
        $db->table('task_project_translations')->insert(['project_id' => $id, 'locale' => 'ca', 'name' => $name, 'description' => 'Descripció de ' . $name]);

        return $id;
    }

    /**
     * A to-do task; defaults: private, to do, normal priority.
     *
     * @param array<string, mixed> $values
     * @param list<int>            $assignees user ids
     */
    protected function task(string $title, array $values = [], array $assignees = []): int
    {
        $now = Time::now()->toDateTimeString();
        $db  = db_connect();
        $db->table('tasks')->insert($values + [
            'title' => $title, 'status' => 'todo', 'priority' => 'normal', 'visibility' => 'private', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $db->insertID();

        foreach ($assignees as $userId) {
            $db->table('task_assignees')->insert(['task_id' => $id, 'user_id' => $userId]);
        }

        return $id;
    }

    protected function taskCode(int $id): string
    {
        return Crud::maskId($id, 'tasks');
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskRow(int $id): array
    {
        $row = db_connect()->table('tasks')->where('id', $id)->get()->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }

    protected function notificationsOf(int $userId, ?string $type = null): int
    {
        $builder = db_connect()->table('notifications')->where('user_id', $userId);

        if ($type !== null) {
            $builder->where('type', $type);
        }

        return $builder->countAllResults();
    }
}
