<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Notifications\Inbox;
use Modules\Core\Notifications\Notifier;

/**
 * The logged-in user's notifications (resource `core-notifications`, granted to every group):
 * the list (kpcrudy, own records only), the bell's JSON, "open" (marks read and goes to the
 * notification's page) and "mark all as read".
 */
final class Notifications extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $userId = (int) auth()->id();

        $crud = $this->crud('core-notifications')
            ->setTable('notifications')
            ->where('user_id', $userId)
            ->setSubject(lang('Core.notifications.notification'), lang('Core.notifications.title'))
            ->columns(['type', 'created_at', 'read_at'])
            ->showFields(['type', 'created_at', 'read_at'])
            ->unsortable(['type'])
            ->filters(['read_at'])
            ->displayAs([
                'type'       => lang('Core.notifications.notification'),
                'created_at' => lang('Core.notifications.date'),
                'read_at'    => lang('Core.notifications.read'),
            ])
            ->callbackColumn('type', static fn ($value, array $row): string => self::cell($row))
            ->callbackColumn('created_at', static fn ($value): string => ui_date((string) $value, 'datetime'))
            ->callbackColumn('read_at', static fn ($value): string => $value === null || $value === ''
                ? ui_badge(lang('Core.notifications.unread'), 'primary')
                : ui_date((string) $value, 'datetime'))
            ->defaultOrder('id', 'desc')
            ->unsetAdd()->unsetEdit()->unsetExport()->unsetSearch()
            ->addOperation('read', static function (?string $id) use ($userId): string {
                (new Inbox($userId))->markRead((int) $id);

                return lang('Core.notifications.markedRead');
            }, [
                'label'      => lang('Core.notifications.markRead'),
                'icon'       => 'bi bi-check2',
                'permission' => 'list',
                'visible'    => static fn (?array $row): bool => $row === null || ($row['read_at'] ?? null) === null,
            ])
            ->addOperation('readall', static function () use ($userId): string {
                return lang('Core.notifications.markedAllRead', [(new Inbox($userId))->markAllRead()]);
            }, [
                'row'        => false,
                'label'      => lang('Core.notifications.markAllRead'),
                'icon'       => 'bi bi-check2-all',
                'permission' => 'list',
            ])
            ->addBulkAction('read', lang('Core.notifications.markRead'), static function (array $ids) use ($userId): string {
                return lang('Core.notifications.markedAllRead', [(new Inbox($userId))->markAllRead(array_map(intval(...), $ids))]);
            }, ['permission' => 'list'])
            ->addHeaderAction(lang('Core.notifications.preferences'), 'backend/profile/notifications', ['icon' => 'bi bi-sliders']);

        return $this->renderCrud($crud);
    }

    /**
     * GET backend/notifications/bell: unread count and the latest notifications (JSON).
     */
    public function bell(): ResponseInterface
    {
        return $this->json((new Inbox((int) auth()->id()))->bell())->setHeader('Cache-Control', 'no-store');
    }

    /**
     * GET backend/notifications/open/{id}: marks the user's notification as read and goes to its
     * page (internal paths only), or to the list.
     */
    public function open(string $id): RedirectResponse
    {
        $inbox = new Inbox((int) auth()->id());
        $row   = $inbox->find((int) $id);

        if ($row === null) {
            return redirect()->to(site_url('backend/notifications'))->with('error', lang('Core.notifications.notFound'));
        }

        $inbox->markRead((int) $row['id']);
        $path = Notifier::messageOf($row)->path();

        return redirect()->to(site_url($path ?? 'backend/notifications/show/' . Crud::idForUrl((int) $row['id'], 'notifications')));
    }

    /**
     * POST backend/notifications/read-all: JSON for the bell, otherwise back to the list.
     */
    public function readAll(): ResponseInterface
    {
        $count = (new Inbox((int) auth()->id()))->markAllRead();

        if ($this->request->isAJAX()) {
            return $this->json(['marked' => $count, 'unread' => 0]);
        }

        return redirect()->to(site_url('backend/notifications'))->with('success', lang('Core.notifications.markedAllRead', [$count]));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function cell(array $row): string
    {
        $message = Notifier::messageOf($row);
        $html    = '<i class="' . esc(Notifier::iconOf($row), 'attr') . ' me-2" aria-hidden="true"></i>';
        $html .= $row['read_at'] === null ? '<strong>' . esc($message->title) . '</strong>' : esc($message->title);

        if ($message->body !== '') {
            $html .= '<div class="small text-body-secondary text-break">' . nl2br(esc($message->body)) . '</div>';
        }

        if ($message->path() !== null) {
            $html .= '<div class="small"><a href="' . esc(site_url('backend/notifications/open/' . (int) $row['id']), 'attr') . '">' . esc(lang('Core.notifications.open')) . '</a></div>';
        }

        return $html;
    }
}
