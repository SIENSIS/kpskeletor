<?php

declare(strict_types=1);

namespace Modules\Dashboard\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Widgets\AsyncWidgetInterface;
use Modules\Core\Widgets\WidgetRegistry;
use Modules\Core\Widgets\WidgetRenderer;
use Modules\Dashboard\Services\DashboardLayout;
use Throwable;

/**
 * Data of an async widget (`backend/dashboard/widgets/<id>/data`, `dashboard.view` + the
 * `widgets` rate limit). Answers 403 unless the widget is async and available to the user (its
 * permission **and** enabled for one of their groups); unknown ids answer the same, so the URL
 * does not tell which widgets exist. The response (`{widget, data, html}`) is cached per user;
 * a failing widget answers 500 with a generic message (the error goes to the log).
 */
final class WidgetData extends BackendController
{
    public function show(string $id): ResponseInterface
    {
        $user   = auth()->user();
        $entry  = $user instanceof User && preg_match(WidgetRegistry::ID_PATTERN, $id) === 1
            ? ((new DashboardLayout())->available($user)[$id] ?? null)
            : null;
        $widget = $entry['widget'] ?? null;

        if ($entry === null || ! $widget instanceof AsyncWidgetInterface) {
            security_log('Dashboard', 'widget_forbidden', [
                'resource' => 'dashboard',
                'summary'  => mb_substr($id, 0, 101),
            ]);

            return $this->json(['error' => lang('Dashboard.forbidden')], 403);
        }

        $renderer = new WidgetRenderer();

        try {
            $result = $renderer->data($widget, (int) auth()->id(), $entry['options']);
        } catch (Throwable $e) {
            $renderer->report($widget, $e);

            return $this->json(['error' => lang('Core.widgets.failed')], 500);
        }

        $this->response->setHeader('Cache-Control', 'no-store, private');

        return $this->json(['widget' => $id] + $result);
    }
}
