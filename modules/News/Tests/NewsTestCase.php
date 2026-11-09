<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use Kpcrudy\Crud;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Dashboard\Tests\DashboardTestCase;

/**
 * Base of the News feature tests: the Auth fixture, every manifest's declarations (widgets,
 * settings), the permission catalogue and default grants (News included), and helpers that write
 * posts directly (`post()`), mask ids and list the posts a page shows.
 */
abstract class NewsTestCase extends DashboardTestCase
{
    protected array $widgetModules = ['Core', 'Dashboard', 'Users', 'News'];

    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
    }

    /**
     * Inserts a post; defaults: a published private article by `$author`, Catalan text.
     *
     * @param array<string, mixed> $values
     * @param list<string>         $groups group names (visibility `groups`)
     */
    protected function newsPost(string $title, array $values = [], array $groups = []): int
    {
        $now = Time::now();
        $db  = db_connect();

        $db->table('news')->insert($values + [
            'slug'         => url_title(strtolower($title), '-', true),
            'type'         => 'article',
            'priority'     => 'info',
            'pinned'       => 0,
            'visibility'   => 'private',
            'status'       => 'published',
            'published_at' => $now->subHours(1)->toDateTimeString(),
            'created_at'   => $now->toDateTimeString(),
            'updated_at'   => $now->toDateTimeString(),
        ]);
        $id = (int) $db->insertID();

        $db->table('news_translations')->insert([
            'news_id' => $id, 'locale' => 'ca', 'title' => $title, 'summary' => 'Resum de ' . $title, 'body' => '<p>Text de ' . $title . '</p>',
        ]);

        foreach ($groups as $group) {
            $row = $db->table('groups')->where('name', $group)->get()->getRowArray();
            $this->assertIsArray($row, "Group {$group} exists.");
            $db->table('news_groups')->insert(['news_id' => $id, 'group_id' => (int) $row['id']]);
        }

        return $id;
    }

    protected function code(int $id): string
    {
        return Crud::maskId($id, 'news');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(int $id): array
    {
        $row = db_connect()->table('news')->where('id', $id)->get()->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }

    /**
     * The page as text with its HTML entities decoded (URLs in attributes are escaped).
     *
     * @param array<string, mixed> $params
     */
    protected function body(string $method, string $path, array $params = []): string
    {
        return html_entity_decode((string) $this->send($method, $path, $params)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * The request answers 404 (a feature test gets the exception, a real request the error page).
     *
     * @param array<string, mixed> $params
     */
    protected function assertNotFound(string $method, string $path, array $params = []): void
    {
        try {
            $status = $this->send($method, $path, $params)->response()->getStatusCode();
        } catch (PageNotFoundException) {
            $status = 404;
        }

        $this->assertSame(404, $status, "{$method} {$path}");
    }

    /**
     * Notifications stored for a user (database channel).
     */
    protected function notificationsOf(int $userId): int
    {
        return db_connect()->table('notifications')->where('user_id', $userId)->countAllResults();
    }
}
