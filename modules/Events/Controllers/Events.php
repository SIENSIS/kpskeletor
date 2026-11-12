<?php

declare(strict_types=1);

namespace Modules\Events\Controllers;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\RawSql;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Crud;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Crud\AuditedCrud;
use Modules\Core\Frontend\RichTextEditor;
use Modules\Core\Security\HtmlSanitizer;
use Modules\Events\Services\AssignableGroups;
use Modules\Events\Services\EventRepository;
use Modules\Events\Services\EventVisibility;
use Modules\Events\Services\Recurrence;

/**
 * Administration of the events (`backend/events`, resource `events-events`; docs/events.md):
 *
 * - translated title and description (Catalan required; the description is rich text sanitised
 *   with HTMLPurifier), dates in the site's time zone, recurrence, category, colour, location;
 * - visibility `public` / `private` / `restricted`; for `restricted`, the audience: groups (only
 *   those the user may assign, `AssignableGroups`, checked again on save) and users, both in
 *   `event_audience` (`setRelationNN()` on its `group_id` and `user_id` columns, D-24-03);
 * - **scope:** with `events-events.all` every event, with a filter "visible to me / all";
 *   without it, only the events of the user's audience or created by them (`where(Closure)`,
 *   the same rule as the calendar, `EventVisibility`);
 * - filters by category, dates, visibility and status; CSV export and import (translated
 *   columns `title:ca`…, kpcrudy ≥ 1.1, D-24-06); the `duplicate` operation;
 * - events published by another module (`source_module`, session 25) cannot be edited here.
 *
 * Writes and operations are logged by the Core (`AuditedCrud`).
 */
final class Events extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $this->sanitizePostedDescription();

        $user       = $this->currentUserOrNobody();
        $me         = (int) $user->id;
        $crud       = $this->crud('events-events');
        $assignable = (new AssignableGroups())->ids($user);

        $crud->setTable('events')
            ->setSubject(lang('Events.event'), lang('Events.events'))
            ->setTranslations('event_translations', 'event_id', 'locale', ['title', 'description'], ContentTranslations::LOCALES, [
                'required'  => ['ca'],
                'types'     => ['title' => 'text', 'description' => 'textarea'],
                'rules'     => ['title' => 'required|max_length[190]', 'description' => 'permit_empty|max_length[100000]'],
                'fallbacks' => ['*' => ['ca']],
                'attrs'     => ['description' => RichTextEditor::attrs()],
            ])
            ->setRelationNN('groups', 'event_audience', 'groups', 'event_id', 'group_id', 'title', [
                // Only the groups the user may assign are offered (and saved); links to other
                // groups set by someone else are kept (kpcrudy only rewrites offered options).
                'where' => new RawSql('id IN (' . implode(',', $assignable === [] ? [0] : array_map('intval', $assignable)) . ')'),
                'order' => 'title',
            ])
            ->setRelationNN('users', 'event_audience', 'users', 'event_id', 'user_id', 'username', [
                'where' => ['active' => 1, 'deleted_at' => null],
                'order' => 'username',
            ])
            ->fieldType('starts_at', 'datetime')
            ->fieldType('ends_at', 'datetime')
            ->fieldType('all_day', 'boolean')
            ->fieldType('color', 'color')
            ->fieldType('url', 'url')
            ->fieldType('category_id', 'select', ['options' => self::categoryOptions()])
            ->fieldType('visibility', 'select', ['options' => $this->options('visibilities', EventVisibility::VISIBILITIES)])
            ->fieldType('status', 'select', ['options' => $this->options('statuses', EventVisibility::STATUSES)])
            ->fieldType('recur_freq', 'select', ['options' => $this->options('frequencies', Recurrence::FREQUENCIES)])
            ->fieldType('recur_days', 'multiselect', ['options' => $this->options('days', array_keys(Recurrence::DAYS)), 'storage' => 'csv'])
            ->fieldType('recur_interval', 'number', ['attrs' => ['min' => 1, 'max' => 99]])
            ->fieldType('recur_count', 'number', ['attrs' => ['min' => 1, 'max' => 999]])
            ->fieldType('recur_until', 'date')
            ->fieldType('recur_exceptions', 'textarea', ['attrs' => ['rows' => 3, 'placeholder' => 'AAAA-MM-DD']])
            ->columns(['title', 'starts_at', 'category_id', 'visibility', 'status', 'recur_freq'])
            ->fields([
                'title', 'description', 'starts_at', 'ends_at', 'all_day', 'location', 'organiser', 'category_id', 'color', 'url', 'status',
                'visibility', 'groups', 'users',
                'recur_freq', 'recur_interval', 'recur_days', 'recur_until', 'recur_count', 'recur_exceptions',
            ])
            ->showFields([
                'title', 'description', 'starts_at', 'ends_at', 'all_day', 'location', 'organiser', 'category_id', 'color', 'url', 'status',
                'visibility', 'groups', 'users',
                'recur_freq', 'recur_interval', 'recur_days', 'recur_until', 'recur_count', 'recur_exceptions',
                'source_module', 'created_at', 'updated_at',
            ])
            ->exportColumns([
                'title', 'description', 'starts_at', 'ends_at', 'all_day', 'location', 'organiser', 'category_id', 'color', 'url', 'status',
                'visibility', 'groups', 'users', 'recur_freq', 'recur_interval', 'recur_days', 'recur_until', 'recur_count', 'recur_exceptions',
            ])
            ->exportTranslations()
            // No `description` in the import: kpcrudy stores imported translations as they come,
            // and rich text must be sanitised before it is stored (the form's clean-up only sees
            // the posted form; library request in docs/library-requests.md).
            ->enableImport([
                'fields' => [
                    'title', 'starts_at', 'ends_at', 'all_day', 'location', 'organiser', 'category_id', 'color', 'url', 'status',
                    'visibility', 'groups', 'users', 'recur_freq', 'recur_interval', 'recur_days', 'recur_until', 'recur_count', 'recur_exceptions',
                ],
            ])
            ->searchFields(['title', 'location', 'organiser'])
            ->filters(['category_id', 'starts_at', 'visibility', 'status', 'groups'])
            ->setRules([
                'starts_at'        => 'required|valid_date',
                'ends_at'          => 'permit_empty|valid_date',
                'location'         => 'permit_empty|max_length[190]',
                'organiser'        => 'permit_empty|max_length[190]',
                'color'            => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
                'url'              => 'permit_empty|max_length[500]|valid_url_strict[https,http]',
                'recur_interval'   => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[99]',
                'recur_count'      => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[999]',
                'recur_until'      => 'permit_empty|valid_date[Y-m-d]',
                'recur_exceptions' => 'permit_empty|max_length[5000]',
            ])
            ->setDefault('visibility', 'private')
            ->setDefault('status', 'confirmed')
            ->setDefault('recur_interval', 1)
            ->setHelp('visibility', lang('Events.help.visibility'))
            ->setHelp('groups', lang('Events.help.groups'))
            ->setHelp('users', lang('Events.help.users'))
            ->setHelp('ends_at', lang('Events.help.endsAt'))
            ->setHelp('recur_freq', lang('Events.help.recurFreq'))
            ->setHelp('recur_days', lang('Events.help.recurDays'))
            ->setHelp('recur_until', lang('Events.help.recurUntil'))
            ->setHelp('recur_exceptions', lang('Events.help.recurExceptions'))
            ->displayAs($this->labels())
            ->callbackColumn('visibility', static fn ($value): string => self::visibilityBadge((string) $value))
            ->callbackColumn('status', static fn ($value): string => self::statusBadge((string) $value))
            ->callbackColumn('recur_freq', static fn ($value): string => $value === null || $value === '' ? '' : esc(lang('Events.frequencies.' . (in_array($value, Recurrence::FREQUENCIES, true) ? $value : 'daily'))))
            ->callbackColumn('source_module', static fn ($value): string => $value === null || $value === '' ? '' : ui_badge(lang('Events.managedBy', [(string) $value]), 'info', 'bi bi-lock'))
            ->callbackExport('visibility', static fn ($value): string => (string) $value)
            ->callbackExport('status', static fn ($value): string => (string) $value)
            ->callbackExport('recur_freq', static fn ($value): string => (string) $value)
            ->beforeInsert(fn (array $data): array|string => $this->beforeSave($data, $user, $assignable, null))
            ->beforeUpdate(fn (array $data, string $id, array $old): array|string => $this->beforeSave($data, $user, $assignable, (int) $id, $old))
            ->afterWrite(static function (string $operation, string $id, array $data): void {
                if ($operation !== 'delete') {
                    self::clearUnusedAudience((int) $id);
                }
            })
            ->defaultOrder('starts_at', 'desc');

        // Events published by another module are managed there (session 25: calendar API).
        $local = static fn (?array $row): bool => $row === null || ($row['source_module'] ?? null) === null || $row['source_module'] === '';

        foreach (['edit', 'delete', 'trash', 'duplicate'] as $operation) {
            $crud->setPermission($operation, $local);
        }

        if ($this->can('events-events.all')) {
            $crud->addFilter('scope', lang('Events.scope.label'), static function (BaseBuilder $builder, string $value) use ($user, $me): void {
                if ($value === 'mine') {
                    EventVisibility::audience($builder, $user, 'events', false, $me);
                }
            }, ['options' => ['mine' => lang('Events.scope.mine'), 'all' => lang('Events.scope.all')]]);
        } else {
            $crud->where(static fn (BaseBuilder $builder) => EventVisibility::audience($builder, $user, 'events', false, $me));
        }

        $crud->addOperation('duplicate', fn (string $id, array $row, Crud $crud): ResponseInterface => $this->duplicate((int) $id, $row, $crud, $me), [
            'label'      => lang('Events.duplicate'),
            'icon'       => 'bi bi-copy',
            'confirm'    => lang('Events.duplicateConfirm'),
            'permission' => 'duplicate',
            'visible'    => fn (?array $row): bool => $this->can('events-events.add') && $local($row),
        ]);

        return $this->renderCrud($crud);
    }

    /**
     * Options of the category field: every category (inactive ones too), by name.
     *
     * @return array<int, string>
     */
    public static function categoryOptions(): array
    {
        $options = [];

        foreach ((new EventRepository())->categories(false) as $category) {
            $options[$category['id']] = $category['name'];
        }

        return $options;
    }

    /**
     * Checks the audience and the dates, normalises the recurrence, sets the creator of new
     * events and bumps the iCal sequence of changed ones.
     *
     * @param array<string, mixed> $data
     * @param list<int>            $assignable
     * @param array<string, mixed> $old
     *
     * @return array<string, mixed>|string
     */
    private function beforeSave(array $data, User $user, array $assignable, ?int $id, array $old = []): array|string
    {
        // Forged or forbidden groups are refused (kpcrudy would only drop them silently).
        $groups = $this->postedIds('groups');

        if (array_diff($groups, $assignable) !== []) {
            security_log('Events', 'forbidden_audience', [
                'resource' => 'events-events',
                'summary'  => lang('Events.activity.forbidden_audience'),
                'changes'  => ['groups' => array_values(array_diff($groups, $assignable))],
            ]);

            return lang('Events.errors.forbiddenGroups');
        }

        $visibility = (string) ($data['visibility'] ?? $old['visibility'] ?? 'private');
        // Only the form posts the audience lists (a CSV import checks its groups against the
        // offered options itself).
        $isForm = is_array($this->request->getPost('kp_tr'));

        if ($isForm && $visibility === 'restricted' && $groups === [] && $this->postedIds('users') === [] && ! $this->hasLockedAudience($id, $assignable)) {
            return lang('Events.errors.emptyAudience');
        }

        foreach (['ends_at', 'recur_until', 'recur_count', 'category_id', 'color', 'url', 'location', 'organiser'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $start = $data['starts_at'] ?? $old['starts_at'] ?? null;
        $end   = array_key_exists('ends_at', $data) ? $data['ends_at'] : ($old['ends_at'] ?? null);

        if (is_string($start) && is_string($end) && strtotime($end) < strtotime($start)) {
            return lang('Events.errors.endBeforeStart');
        }

        if (array_key_exists('recur_freq', $data)) {
            $data['recur_freq'] = in_array($data['recur_freq'], Recurrence::FREQUENCIES, true) ? $data['recur_freq'] : null;
        }

        if (array_key_exists('recur_interval', $data)) {
            $data['recur_interval'] = max(1, (int) $data['recur_interval']);
        }

        if (array_key_exists('recur_exceptions', $data)) {
            $data['recur_exceptions'] = Recurrence::normaliseExceptions((string) $data['recur_exceptions']);
        }

        if (array_key_exists('color', $data)) {
            $data['color'] = EventRepository::color($data['color']);
        }

        if ($id === null) {
            $data['created_by'] = (int) $user->id;
            $data['sequence']   = 0;
        } else {
            $data['sequence'] = (int) ($old['sequence'] ?? 0) + 1;
        }

        return $data;
    }

    /**
     * Whether the event already has audience rows the user cannot change (groups set by someone
     * who may assign them): kpcrudy keeps them, so the audience is not empty.
     *
     * @param list<int> $assignable
     */
    private function hasLockedAudience(?int $id, array $assignable): bool
    {
        if ($id === null) {
            return false;
        }

        $builder = db_connect()->table('event_audience')->where('event_id', $id)->where('group_id IS NOT NULL', null, false);

        if ($assignable !== []) {
            $builder->whereNotIn('group_id', $assignable);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * An event that is not `restricted` keeps no audience rows.
     */
    private static function clearUnusedAudience(int $id): void
    {
        $row = db_connect()->table('events')->select('visibility')->where('id', $id)->get()->getRowArray();

        if (is_array($row) && $row['visibility'] !== 'restricted') {
            db_connect()->table('event_audience')->where('event_id', $id)->delete();
        }
    }

    /**
     * Copies an event (texts and audience) and opens the copy's form.
     *
     * @param array<string, mixed> $row
     */
    private function duplicate(int $id, array $row, Crud $crud, int $me): ResponseInterface
    {
        $db  = db_connect();
        $now = Time::now()->toDateTimeString();

        $copy = array_diff_key($row, array_flip(['id', 'deleted_at', 'source_module', 'source_key', 'source_url', 'calendar']));
        $copy = array_intersect_key($copy, array_flip($db->getFieldNames('events')));
        $copy['created_by'] = $me;
        $copy['sequence']   = 0;
        $copy['created_at'] = $now;
        $copy['updated_at'] = $now;

        $db->transStart();
        $db->table('events')->insert($copy);
        $newId = (int) $db->insertID();

        foreach ($db->table('event_translations')->where('event_id', $id)->get()->getResultArray() as $text) {
            $db->table('event_translations')->insert([
                'event_id'    => $newId,
                'locale'      => $text['locale'],
                'title'       => mb_substr(lang('Events.copyOf', [(string) $text['title']]), 0, 190),
                'description' => $text['description'],
            ]);
        }

        foreach ($db->table('event_audience')->where('event_id', $id)->get()->getResultArray() as $audience) {
            $db->table('event_audience')->insert(['event_id' => $newId, 'group_id' => $audience['group_id'], 'user_id' => $audience['user_id']]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return $crud->backToList('danger', lang('Events.errors.duplicate'));
        }

        session()->setFlashdata('success', lang('Events.duplicated'));

        return redirect()->to($crud->url('edit', (string) $newId));
    }

    /**
     * Ids posted for an N-N field (`groups[]`, `users[]`).
     *
     * @return list<int>
     */
    private function postedIds(string $field): array
    {
        $values = $this->request->getPost($field);

        if (! is_array($values)) {
            return [];
        }

        $ids = [];

        foreach ($values as $value) {
            if (is_scalar($value) && ctype_digit((string) $value)) {
                $ids[] = (int) $value;
            } elseif (is_scalar($value) && (string) $value !== '') {
                $ids[] = -1; // not an id: never assignable
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Sanitises the posted descriptions (`kp_tr[<locale>][description]`) with HTMLPurifier before
     * kpcrudy reads the form (its hooks only receive the main table's data, D-17-02).
     */
    private function sanitizePostedDescription(): void
    {
        $post = $this->request->getPost();

        if (! is_array($post['kp_tr'] ?? null)) {
            return;
        }

        foreach ($post['kp_tr'] as $locale => $fields) {
            if (is_array($fields) && array_key_exists('description', $fields)) {
                $post['kp_tr'][$locale]['description'] = is_string($fields['description']) ? HtmlSanitizer::clean($fields['description']) : '';
            }
        }

        $this->request->setGlobal('post', $post);
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, string>
     */
    private function options(string $group, array $values): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = lang('Events.' . $group . '.' . $value);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function labels(): array
    {
        $labels = [];

        foreach ([
            'title', 'description', 'starts_at', 'ends_at', 'all_day', 'location', 'organiser', 'category_id', 'color', 'url', 'status',
            'visibility', 'groups', 'users', 'recur_freq', 'recur_interval', 'recur_days', 'recur_until', 'recur_count', 'recur_exceptions',
            'source_module', 'created_at', 'updated_at',
        ] as $field) {
            $labels[$field] = lang('Events.fields.' . $field);
        }

        return $labels;
    }

    public static function visibilityBadge(string $visibility): string
    {
        $icons = ['public' => 'bi bi-globe', 'private' => 'bi bi-lock', 'restricted' => 'bi bi-people'];

        return isset($icons[$visibility]) ? ui_badge(lang('Events.visibilities.' . $visibility), 'light', $icons[$visibility]) : esc($visibility);
    }

    public static function statusBadge(string $status): string
    {
        $variants = ['confirmed' => 'success', 'tentative' => 'warning', 'cancelled' => 'secondary'];

        return isset($variants[$status]) ? ui_badge(lang('Events.statuses.' . $status), $variants[$status]) : esc($status);
    }
}
