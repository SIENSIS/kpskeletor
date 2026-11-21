<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\SectionForm;
use Modules\Core\Sections\SectionFormView;
use Modules\Core\Sections\SectionOptions;
use Modules\Core\Sections\SectionRegistry;
use Modules\Core\Sections\SectionType;
use Modules\Core\Sections\SectionValidationException;
use Modules\Pages\Services\PageSections;
use RuntimeException;

/**
 * The section editor shared by pages (`Sections`, `backend/pages/<id>/sections`, D-45-01) and
 * reusable blocks (`BlockSections`, `backend/pages/blocks/<id>/sections`, D-47-01): the same
 * list (drag and drop or keyboard reordering, `pages/section-list`; the up/down buttons also work
 * without JavaScript), gallery, forms generated from the types' schemas and validated on the
 * server (`SectionForm`; forged posts are refused with the field's message), duplicate,
 * hide/show and delete.
 *
 * A subclass gives the owner: who may edit it (checked on every request), its storage
 * (`PageSections` for its owner type), URLs, breadcrumbs and what to record after a change
 * (a revision and an activity entry).
 */
abstract class SectionEditor extends BackendController
{
    /**
     * The owner (a page or a block row) when the user may edit it; otherwise a 404.
     *
     * @return array<string, mixed>
     */
    abstract protected function owner(int $id): array;

    abstract protected function storage(): PageSections;

    abstract protected function baseUrl(int $id): string;

    /**
     * @param array<string, mixed> $owner
     */
    abstract protected function ownerTitle(array $owner): string;

    /**
     * @param array<string, mixed> $owner
     */
    abstract protected function crumbs(array $owner, ?string $last = null): void;

    /**
     * After a change: a revision and an activity entry.
     *
     * @param array<string, mixed> $owner
     */
    abstract protected function changed(int $id, string $action, array $owner, string $detail = ''): void;

    /**
     * Extra variables of the list view (toolbar links, the page's layout switch).
     *
     * @param array<string, mixed> $owner
     *
     * @return array<string, mixed>
     */
    abstract protected function listExtras(array $owner): array;

    public function index(int $id): string
    {
        $owner    = $this->owner($id);
        $registry = new SectionRegistry();
        $locale   = $this->request->getLocale();
        $rows     = [];

        foreach ($this->storage()->all($id) as $section) {
            $type   = $registry->get($section->type);
            $rows[] = [
                'section'  => $section,
                'type'     => $type,
                'summary'  => $type?->summary($section, $locale) ?? '',
                'warnings' => $type?->warnings($section, auth()->user()) ?? [],
            ];
        }

        $this->crumbs($owner);

        return $this->render('Modules\Pages\Views\editor\list', [
            'title'   => lang('Pages.sections.titleOf', [$this->ownerTitle($owner)]),
            'icon'    => 'bi bi-layout-text-window-reverse',
            'rows'    => $rows,
            'baseUrl' => $this->baseUrl($id),
            'full'    => count($rows) >= PageSections::MAX_SECTIONS,
        ] + $this->listExtras($owner));
    }

    public function gallery(int $id): string
    {
        $owner = $this->owner($id);
        $this->crumbs($owner, lang('Pages.sections.add'));

        return $this->render('Modules\Pages\Views\editor\gallery', [
            'title'   => lang('Pages.sections.addTo', [$this->ownerTitle($owner)]),
            'icon'    => 'bi bi-plus-square',
            'types'   => array_filter((new SectionRegistry())->all(), fn (SectionType $type): bool => $this->storage()->accepts($type->key())),
            'newUrl'  => $this->baseUrl($id) . '/new',
            'backUrl' => $this->baseUrl($id),
        ]);
    }

    public function create(int $id, string $key): string
    {
        $owner = $this->owner($id);
        $type  = $this->type($key);

        return $this->form($owner, $type, null, (new SectionForm($type, FieldContext::current('Pages')))->formValues(null), SectionOptions::defaults($type), false);
    }

    public function edit(int $id, int $sectionId): string
    {
        $owner   = $this->owner($id);
        $section = $this->storage()->find($id, $sectionId) ?? throw PageNotFoundException::forPageNotFound();
        $type    = $this->type($section->type);
        $form    = new SectionForm($type, FieldContext::current('Pages', $type->fileKeys($section)));

        return $this->form($owner, $type, $sectionId, $form->formValues($section), $section->options, $section->hidden, null, $type->warnings($section, auth()->user()));
    }

    public function save(int $ownerId): RedirectResponse|ResponseInterface|string
    {
        $owner    = $this->owner($ownerId);
        $sections = $this->storage();
        $id       = (string) $this->request->getPost('id');
        $id       = ctype_digit($id) && $id !== '0' ? (int) $id : null;
        $current  = $id === null ? null : ($sections->find($ownerId, $id) ?? throw PageNotFoundException::forPageNotFound());
        $type     = $this->type($current !== null ? $current->type : (string) $this->request->getPost('type'));
        $context  = FieldContext::current('Pages', $current === null ? [] : $type->fileKeys($current));
        $hidden   = $this->request->getPost('hidden') === '1';
        $back     = $this->baseUrl($ownerId);

        try {
            $section = (new SectionForm($type, $context))->validate($this->request->getPost('f'), $this->request->getPost('o'), $hidden, $id);
            $saved   = $sections->save($ownerId, $section, $id);
        } catch (SectionValidationException $e) {
            $posted  = $this->request->getPost('f');
            $posted  = is_array($posted) ? $posted : [];
            $options = $this->request->getPost('o');

            $this->response->setStatusCode(422);

            return $this->form($owner, $type, $id, $posted, is_array($options) ? $options : [], $hidden, $e->toArray());
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($ownerId, $id === null ? 'section_added' : 'section_edited', $owner, $type->label());

        return redirect()->to($back . '#section-' . $saved)->with('success', lang($id === null ? 'Pages.sections.added' : 'Pages.sections.saved'));
    }

    public function order(int $ownerId): RedirectResponse
    {
        $owner = $this->owner($ownerId);
        $back  = $this->baseUrl($ownerId);
        $move  = (string) $this->request->getPost('move');

        try {
            if ($move !== '') {
                [$id, $direction] = array_pad(explode(':', $move, 2), 2, '');

                if (! ctype_digit($id)) {
                    throw new RuntimeException(lang('Pages.sections.errors.notFound'));
                }

                $this->storage()->move($ownerId, (int) $id, $direction);
            } else {
                $order = json_decode((string) $this->request->getPost('order'), true);

                if (! is_array($order) || array_filter($order, static fn (mixed $id): bool => ! is_int($id)) !== []) {
                    throw new RuntimeException(lang('Pages.sections.errors.order'));
                }

                $this->storage()->reorder($ownerId, array_values($order));
            }
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($ownerId, 'sections_reordered', $owner);

        return redirect()->to($back . ($move !== '' ? '#section-' . (int) $move : ''))->with('success', lang('Pages.sections.orderSaved'));
    }

    public function duplicate(int $ownerId, int $sectionId): RedirectResponse
    {
        $owner = $this->owner($ownerId);
        $back  = $this->baseUrl($ownerId);

        try {
            $copy = $this->storage()->duplicate($ownerId, $sectionId);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($ownerId, 'section_duplicated', $owner);

        return redirect()->to($back . '#section-' . $copy)->with('success', lang('Pages.sections.duplicated'));
    }

    public function hide(int $ownerId, int $sectionId): RedirectResponse
    {
        $owner  = $this->owner($ownerId);
        $back   = $this->baseUrl($ownerId);
        $hidden = $this->request->getPost('hidden') === '1';

        try {
            $this->storage()->setHidden($ownerId, $sectionId, $hidden);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($ownerId, $hidden ? 'section_hidden' : 'section_shown', $owner);

        return redirect()->to($back . '#section-' . $sectionId)->with('success', lang($hidden ? 'Pages.sections.hiddenOk' : 'Pages.sections.shownOk'));
    }

    public function delete(int $ownerId, int $sectionId): RedirectResponse
    {
        $owner = $this->owner($ownerId);
        $back  = $this->baseUrl($ownerId);

        try {
            $this->storage()->delete($ownerId, $sectionId);
        } catch (RuntimeException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $this->changed($ownerId, 'section_deleted', $owner);

        return redirect()->to($back)->with('success', lang('Pages.sections.deleted'));
    }

    /**
     * @param array<string, mixed>                       $owner
     * @param array<string, mixed>                       $values  field => posted shape
     * @param array<string, mixed>                       $options
     * @param array{path: string, message: string, link?: array{url: string, label: string}}|null $error
     * @param list<array{message: string, link?: array{url: string, label: string}}> $warnings
     */
    private function form(array $owner, SectionType $type, ?int $sectionId, array $values, array $options, bool $hidden, ?array $error = null, array $warnings = []): string
    {
        $id      = (int) $owner['id'];
        $context = FieldContext::current('Pages');
        $this->crumbs($owner, $type->label());

        return $this->render('Modules\Pages\Views\editor\form', [
            'title'     => lang($sectionId === null ? 'Pages.sections.new' : 'Pages.sections.edit', [$type->label()]),
            'icon'      => $type->icon(),
            'type'      => $type,
            'sectionId' => $sectionId,
            'fields'    => (new SectionFormView($type, $context, $values, $options, $hidden, $error, $warnings))->render(),
            'action'    => $this->baseUrl($id) . '/save',
            'backUrl'   => $this->baseUrl($id),
        ]);
    }

    /** A section type this owner accepts (a block never holds a block section). */
    private function type(string $key): SectionType
    {
        $type = (new SectionRegistry())->get($key);

        if ($type === null || ! $this->storage()->accepts($key)) {
            throw PageNotFoundException::forPageNotFound(lang('Sections.errors.unknownType'));
        }

        return $type;
    }
}
