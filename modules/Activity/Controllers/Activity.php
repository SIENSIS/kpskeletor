<?php

declare(strict_types=1);

namespace Modules\Activity\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Activity\ActivityLogger;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Crud\AuditedCrud;
use Modules\Core\Permissions\Authorization;

/**
 * The activity and security log, read-only (kpcrudy `readOnly()`; docs/activity.md):
 *
 * - `backend/activity` (resource `activity-log`): every entry; filters by user, type, module,
 *   action and date range; search; CSV export (`activity-log.export`);
 * - `backend/activity/mine` (resource `activity-mine`, every group): the same screen limited to
 *   the user's own entries with `where('user_id', me)`; an entry of someone else is "not found".
 *
 * The details page shows the masked before/after changes as a table. IP addresses and user
 * agents are hidden everywhere (list, details, search, filters, export) from users without
 * `activity-log.network`.
 */
final class Activity extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->configure($this->crud('activity-log'), false)
            ->setSubject(lang('Activity.entry'), lang('Activity.title'));

        return $this->renderCrud($crud);
    }

    public function mine(): ResponseInterface|string
    {
        $crud = $this->configure($this->crud('activity-mine'), true)
            ->where('user_id', (int) auth()->id())
            ->setSubject(lang('Activity.entry'), lang('Activity.mineTitle'))
            ->unsetExport();

        return $this->renderCrud($crud);
    }

    /**
     * The label of an action code: `<Module>.activity.<action>`, then `Activity.actions.<action>`,
     * else the code itself.
     */
    public static function actionLabel(string $module, string $action): string
    {
        $keys = $module !== '' && preg_match('/^[A-Z][A-Za-z0-9]*$/', $module) === 1 ? [$module . '.activity.' . $action] : [];
        $keys[] = 'Activity.actions.' . $action;

        foreach ($keys as $key) {
            $text = lang($key);

            if (is_string($text) && $text !== $key) {
                return $text;
            }
        }

        return $action;
    }

    /**
     * The changes column of the details page: a before/after table (secrets already masked
     * when written), or the other details as key/value rows.
     */
    public static function changesTable(?string $json): string
    {
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : null;

        if (! is_array($data) || $data === []) {
            return '<span class="text-body-secondary">—</span>';
        }

        $html   = '';
        $before = is_array($data['before'] ?? null) ? $data['before'] : [];
        $after  = is_array($data['after'] ?? null) ? $data['after'] : [];

        if ($before !== [] || $after !== []) {
            $html .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-2 activity-changes">'
                . '<thead><tr><th scope="col">' . esc(lang('Activity.field')) . '</th><th scope="col">' . esc(lang('Activity.before')) . '</th><th scope="col">' . esc(lang('Activity.after')) . '</th></tr></thead><tbody>';

            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                $html .= '<tr><th scope="row">' . esc((string) $key) . '</th>'
                    . '<td>' . self::value($before[$key] ?? null, array_key_exists($key, $before)) . '</td>'
                    . '<td>' . self::value($after[$key] ?? null, array_key_exists($key, $after)) . '</td></tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $other = array_diff_key($data, ['before' => true, 'after' => true]);
        $other = is_array($other['changes'] ?? null) ? $other['changes'] + array_diff_key($other, ['changes' => true]) : $other;

        if ($other !== []) {
            $html .= '<dl class="row mb-0 activity-details">';

            foreach ($other as $key => $value) {
                $html .= '<dt class="col-sm-4">' . esc((string) $key) . '</dt><dd class="col-sm-8">' . self::value($value, true) . '</dd>';
            }

            $html .= '</dl>';
        }

        return $html;
    }

    private function configure(AuditedCrud $crud, bool $mine): AuditedCrud
    {
        $types   = [
            ActivityLogger::TYPE_ACTIVITY => lang('Activity.types.activity'),
            ActivityLogger::TYPE_SECURITY => lang('Activity.types.security'),
        ];
        $columns = $mine
            ? ['created_at', 'type', 'module', 'action', 'record', 'summary']
            : ['created_at', 'user_id', 'type', 'module', 'action', 'record', 'summary'];
        $show    = ['created_at', 'user_id', 'type', 'module', 'resource', 'action', 'record_id', 'summary', 'changes', 'ip_address', 'user_agent'];
        $export  = ['created_at', 'user_id', 'type', 'module', 'resource', 'action', 'record_id', 'summary', 'ip_address', 'user_agent'];
        $filters = $mine ? ['type', 'module', 'action', 'created_at'] : ['user_id', 'type', 'module', 'action', 'created_at'];

        $crud->setTable('activity_log')
            ->readOnly()
            ->setRelation('user_id', 'users', 'username')
            ->fieldType('type', 'select', ['options' => $types])
            ->fieldType('module', 'select', ['options' => $this->distinct('module', $mine)])
            ->fieldType('action', 'select', ['options' => $this->actionOptions($mine)])
            ->fieldType('created_at', 'datetime')
            ->columns($columns)
            ->showFields($show)
            ->exportColumns($export)
            ->filters($filters)
            ->searchFields(['summary', 'resource', 'record_id'])
            ->unsortable(['record', 'summary'])
            ->displayAs([
                'created_at' => lang('Activity.date'),
                'user_id'    => lang('Activity.user'),
                'type'       => lang('Activity.type'),
                'module'     => lang('Activity.module'),
                'resource'   => lang('Activity.resource'),
                'action'     => lang('Activity.action'),
                'record'     => lang('Activity.record'),
                'record_id'  => lang('Activity.record'),
                'summary'    => lang('Activity.summary'),
                'changes'    => lang('Activity.changes'),
                'ip_address' => lang('Activity.ip'),
                'user_agent' => lang('Activity.userAgent'),
            ])
            ->callbackColumn('created_at', static fn ($value): string => ui_date((string) $value, 'datetime'))
            ->callbackColumn('type', static fn ($value): string => $value === ActivityLogger::TYPE_SECURITY
                ? ui_badge(lang('Activity.types.security'), 'danger')
                : ui_badge(lang('Activity.types.activity'), 'secondary'))
            ->callbackColumn('action', static fn ($value, array $row): string => esc(self::actionLabel((string) ($row['module'] ?? ''), (string) $value)))
            ->callbackColumn('record', static fn ($value, array $row): string => esc(trim((string) ($row['resource'] ?? '') . ((string) ($row['record_id'] ?? '') !== '' ? ' #' . $row['record_id'] : ''))))
            ->callbackColumn('changes', static fn ($value): string => self::changesTable(is_string($value) ? $value : null))
            ->callbackExport('created_at', static fn ($value): string => (string) $value)
            ->callbackExport('action', static fn ($value): string => (string) $value)
            ->callbackExport('type', static fn ($value): string => (string) $value)
            ->defaultOrder('id', 'desc');

        if (! Authorization::current()->can('activity-log.network')) {
            $crud->hideFields(['ip_address', 'user_agent']);
        }

        return $crud;
    }

    /**
     * Values of a column present in the log (filter options), limited to the user's entries
     * on "My activity".
     *
     * @return array<string, string>
     */
    private function distinct(string $column, bool $mine): array
    {
        $builder = db_connect()->table('activity_log')->distinct()->select($column)->where($column . ' !=', '')->orderBy($column);

        if ($mine) {
            $builder->where('user_id', (int) auth()->id());
        }

        $values = array_map('strval', array_column($builder->get()->getResultArray(), $column));

        return array_combine($values, $values);
    }

    /**
     * @return array<string, string> action code => label
     */
    private function actionOptions(bool $mine): array
    {
        $builder = db_connect()->table('activity_log')->distinct()->select('module, action')->orderBy('action');

        if ($mine) {
            $builder->where('user_id', (int) auth()->id());
        }

        $options = [];

        foreach ($builder->get()->getResultArray() as $row) {
            $options[(string) $row['action']] ??= self::actionLabel((string) $row['module'], (string) $row['action']);
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    private static function value(mixed $value, bool $present): string
    {
        if (! $present) {
            return '';
        }

        if ($value === null) {
            return '<span class="text-body-secondary">null</span>';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return '<code>' . esc((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</code>';
        }

        return nl2br(esc((string) $value));
    }
}
