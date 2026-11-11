<?php

declare(strict_types=1);

namespace Modules\Menus\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Menus\Services\MenuRepository;

/**
 * The public menus (`backend/menus/public`, resource `menus-public`; docs/menus.md), a kpcrudy
 * CRUD: a name and the theme location the menu is placed in (one menu per location). The items
 * are edited in the tree editor (`MenuItems`, operation "Items", permission `edit`). Deleting a
 * menu deletes its items. Writes are logged by the Core (`AuditedCrud`).
 */
final class PublicMenus extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $locations = $this->locations();
        $menus     = new MenuRepository();

        $crud = $this->crud('menus-public')
            ->setTable('menus')
            ->setSubject(lang('Menus.public.menu'), lang('Menus.public.title'))
            ->columns(['name', 'location', 'items', 'updated_at'])
            ->addFields(['name', 'location'])
            ->editFields(['name', 'location'])
            ->searchFields(['name'])
            ->unsortable(['items'])
            ->displayAs([
                'name'       => lang('Menus.public.name'),
                'location'   => lang('Menus.public.location'),
                'items'      => lang('Menus.public.items'),
                'updated_at' => lang('Menus.public.updated'),
            ])
            ->fieldType('location', 'select', ['options' => ['' => lang('Menus.public.noLocation')] + $locations])
            ->setRules([
                'name'     => 'required|max_length[100]',
                'location' => 'permit_empty|in_list[' . implode(',', array_keys($locations)) . ']|is_unique[menus.location,id,{id}]',
            ])
            ->setHelp('location', lang('Menus.public.locationHelp'))
            ->callbackColumn('location', static fn ($value): string => is_string($value) && $value !== ''
                ? ui_badge($locations[$value] ?? $value, isset($locations[$value]) ? 'primary' : 'warning', 'bi bi-geo-alt')
                : ui_badge(lang('Menus.public.noLocation'), 'light'))
            ->callbackColumn('items', static fn ($value, array $row): string => esc((string) count($menus->items((int) $row['id']))))
            ->callbackColumn('updated_at', static fn ($value): string => ui_date(is_string($value) ? $value : null, 'relative'))
            ->beforeInsert(static fn (array $data): array => self::normalise($data))
            ->beforeUpdate(static fn (array $data): array => self::normalise($data))
            ->afterInsert(static fn () => (new CacheInvalidator())->invalidate(['menus']))
            ->afterUpdate(static fn () => (new CacheInvalidator())->invalidate(['menus']))
            ->afterDelete(static fn (string $id) => $menus->deleteMenuItems((int) $id))
            ->unsetShow()
            ->unsetExport()
            ->defaultOrder('name', 'asc');

        $crud->addOperation('items', static fn (string $id): ResponseInterface => redirect()->to(site_url('backend/menus/items/' . (int) $id)), [
            'method'     => 'get',
            'permission' => 'edit',
            'label'      => lang('Menus.public.editItems'),
            'icon'       => 'bi bi-diagram-3',
            'class'      => 'btn-outline-primary',
        ]);

        return $this->renderCrud($crud);
    }

    /**
     * The menu locations of the public theme: location => translated label.
     *
     * @return array<string, string>
     */
    private function locations(): array
    {
        return array_map(static fn (string $label): string => lang($label), service('theme')->menuLocations('public'));
    }

    /**
     * An empty location is stored as null (several menus may be placed nowhere).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function normalise(array $data): array
    {
        if (array_key_exists('location', $data) && ($data['location'] === '' || $data['location'] === null)) {
            $data['location'] = null;
        }

        return $data;
    }
}
