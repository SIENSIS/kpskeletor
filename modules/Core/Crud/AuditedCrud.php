<?php

declare(strict_types=1);

namespace Modules\Core\Crud;

use Closure;
use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Activity\ActivityLogger;

/**
 * kpcrudy's `Crud` with the activity log built in (docs/activity.md). `CrudFactory::make()`
 * creates it, so every module CRUD logs without code of its own:
 *
 * - every write (insert, update, delete, restore; bulk actions and CSV imports included), after
 *   the module's own `after*`/`afterWrite` hooks, which keep working as documented;
 * - every custom operation that changes something (`addOperation()` with `method` `post`) and
 *   every custom bulk action, once the callback did not cancel it (`false`);
 * - every CSV export (who exported what).
 *
 * `skipActivity('read', …)` leaves out operations that only change the user's own view state
 * (e.g. marking a notification as read).
 */
class AuditedCrud extends Crud
{
    /** @var list<string> */
    private array $skipActivity = [];

    /**
     * Custom operations or bulk actions (keys) that are not written to the activity log.
     */
    public function skipActivity(string ...$keys): static
    {
        $this->skipActivity = array_values(array_unique([...$this->skipActivity, ...$keys]));

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function addOperation(string $key, Closure $callback, array $options = []): static
    {
        $method = strtolower((string) ($options['method'] ?? 'post'));

        if ($method !== 'get') {
            $inner    = $callback;
            $callback = function (?string $id, ?array $row, Crud $crud) use ($inner, $key): mixed {
                $result = $inner($id, $row, $crud);

                if ($result !== false && ! in_array($key, $this->skipActivity, true)) {
                    $this->logActivity($key, $id, $row);
                }

                return $result;
            };
        }

        return parent::addOperation($key, $callback, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function addBulkAction(string $key, string $label, Closure $callback, array $options = []): static
    {
        $inner    = $callback;
        $callback = function (array $ids, Crud $crud) use ($inner, $key): mixed {
            $result = $inner($ids, $crud);

            if ($result !== false && ! in_array($key, $this->skipActivity, true)) {
                $this->activity()->log($this->activity()->moduleOf($this->getResource()), $key, [
                    'resource' => $this->getResource(),
                    'changes'  => ['ids' => array_map('strval', array_values($ids))],
                ]);
            }

            return $result;
        };

        return parent::addBulkAction($key, $label, $callback, $options);
    }

    protected function callHook(string $name, mixed ...$args): mixed
    {
        $result = parent::callHook($name, ...$args);

        $write = match ($name) {
            'afterInsert'  => ['insert', (string) $args[0], (array) $args[1], null],
            'afterUpdate'  => ['update', (string) $args[0], (array) $args[1], isset($args[2]) ? (array) $args[2] : null],
            'afterDelete'  => ['delete', (string) $args[0], [], isset($args[1]) ? (array) $args[1] : null],
            'afterRestore' => ['restore', (string) $args[0], [], isset($args[1]) ? (array) $args[1] : null],
            default        => null,
        };

        if ($write !== null && $this->getResource() !== '') {
            $this->activity()->crudWrite($this->getResource(), ...$write);
        }

        return $result;
    }

    protected function exportOperation(): ResponseInterface
    {
        $response = parent::exportOperation();

        if (str_contains($response->getHeaderLine('Content-Disposition'), 'attachment')) {
            $this->logActivity('export', null, null);
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $row
     */
    private function logActivity(string $action, ?string $id, ?array $row): void
    {
        $resource = $this->getResource();

        if ($resource === '') {
            return;
        }

        $this->activity()->log($this->activity()->moduleOf($resource), $action, [
            'resource'  => $resource,
            'record_id' => $id,
            'summary'   => ActivityLogger::labelOf($row ?? []),
        ]);
    }

    private function activity(): ActivityLogger
    {
        return service('activity');
    }
}
