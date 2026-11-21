<?php
/**
 * Placements of a reusable block in the zones of the public theme (`backend/pages/blocks/<id>/zones`):
 * the list (zone, rule, pages, order; edit and remove) and the form to add or change one.
 *
 * @var string                                  $layout
 * @var array<string, mixed>                    $block
 * @var list<array{id: int, block_id: int, zone: string, rule: string, pages: list<int>, position: int}> $placements
 * @var array{id: int, block_id: int, zone: string, rule: string, pages: list<int>, position: int}|null  $editing
 * @var array<string, string>                   $zones  zone => label
 * @var array<int, string>                      $pages  page id => title (indented)
 * @var string                                  $baseUrl
 * @var string                                  $sectionsUrl
 * @var string                                  $listUrl
 */
$old      = static fn (string $field, mixed $default): mixed => old($field) ?? $default;
$zone     = (string) $old('zone', $editing['zone'] ?? (string) array_key_first($zones));
$rule     = (string) $old('rule', $editing['rule'] ?? 'all');
$chosen   = array_map('intval', (array) $old('pages', $editing['pages'] ?? []));
$position = (int) $old('position', $editing['position'] ?? 10);
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<p class="text-body-secondary"><?= esc(lang('Pages.blocks.placementsHelp')) ?></p>
<?php if ($block['status'] !== 'published'): ?>
    <p class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc(lang('Pages.blocks.draftNotice')) ?></p>
<?php endif ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0"><?= esc(lang('Pages.blocks.placements')) ?></h2></div>
            <?php if ($zones === []): ?>
                <div class="card-body"><p class="mb-0 text-body-secondary"><?= esc(lang('Pages.blocks.noZones')) ?></p></div>
            <?php elseif ($placements === []): ?>
                <div class="card-body">
                    <?= theme_component('empty_state', ['icon' => 'bi bi-pin-map', 'title' => lang('Pages.blocks.noPlacements'), 'message' => lang('Pages.blocks.noPlacementsHelp'), 'compact' => true]) ?>
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($placements as $placement): ?>
                        <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                            <span class="flex-grow-1 min-w-0">
                                <strong class="d-block"><?= esc($zones[$placement['zone']] ?? $placement['zone']) ?></strong>
                                <span class="small text-body-secondary d-block">
                                    <?= esc(lang('Pages.blocks.rules.' . $placement['rule'])) ?>
                                    <?php if ($placement['pages'] !== []): ?>
                                        : <?= esc(implode(', ', array_map(static fn (int $page): string => trim(ltrim($pages[$page] ?? ('#' . $page), '— ')), $placement['pages']))) ?>
                                    <?php endif ?>
                                    · <?= esc(lang('Pages.blocks.fields.position')) ?> <?= (int) $placement['position'] ?>
                                </span>
                                <?php if (! isset($zones[$placement['zone']])): ?>
                                    <?= ui_badge(lang('Pages.blocks.zoneMissing'), 'warning', 'bi bi-exclamation-triangle') ?>
                                <?php endif ?>
                            </span>
                            <a class="btn btn-sm btn-outline-primary" href="<?= esc($baseUrl . '?edit=' . $placement['id'], 'attr') ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= esc(lang('Pages.sections.editShort')) ?></a>
                            <form method="post" action="<?= esc($baseUrl . '/' . $placement['id'] . '/delete', 'attr') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= esc(lang('Pages.blocks.placementDeleteConfirm'), 'attr') ?>" aria-label="<?= esc(lang('Pages.blocks.placementDelete') . ': ' . ($zones[$placement['zone']] ?? $placement['zone']), 'attr') ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </form>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
        <p class="mt-3 d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="<?= esc($sectionsUrl, 'attr') ?>"><i class="bi bi-layout-text-window-reverse me-1" aria-hidden="true"></i><?= esc(lang('Pages.sections.title')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= esc($listUrl, 'attr') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Pages.blocks.backToList')) ?></a>
        </p>
    </div>
    <?php if ($zones !== []): ?>
        <div class="col-lg-5">
            <form method="post" action="<?= esc($baseUrl . '/save', 'attr') ?>" class="card shadow-sm" data-dirty-guard>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <div class="card-header bg-transparent"><h2 class="h6 mb-0"><?= esc(lang($editing === null ? 'Pages.blocks.placementAdd' : 'Pages.blocks.placementEdit')) ?></h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="placement-zone"><?= esc(lang('Pages.blocks.fields.zone')) ?></label>
                        <select class="form-select" id="placement-zone" name="zone" required>
                            <?php foreach ($zones as $key => $label): ?>
                                <option value="<?= esc($key, 'attr') ?>"<?= $key === $zone ? ' selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6"><?= esc(lang('Pages.blocks.fields.rule')) ?></legend>
                        <?php foreach (\Modules\Pages\Services\BlockZones::RULES as $value): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="rule" id="placement-rule-<?= esc($value, 'attr') ?>" value="<?= esc($value, 'attr') ?>"<?= $value === $rule ? ' checked' : '' ?>>
                                <label class="form-check-label" for="placement-rule-<?= esc($value, 'attr') ?>"><?= esc(lang('Pages.blocks.rules.' . $value)) ?></label>
                            </div>
                        <?php endforeach ?>
                    </fieldset>
                    <div class="mb-3">
                        <label class="form-label" for="placement-pages"><?= esc(lang('Pages.blocks.fields.pages')) ?></label>
                        <select class="form-select" id="placement-pages" name="pages[]" multiple size="8" aria-describedby="placement-pages-help">
                            <?php foreach ($pages as $id => $title): ?>
                                <option value="<?= (int) $id ?>"<?= in_array((int) $id, $chosen, true) ? ' selected' : '' ?>><?= esc($title) ?></option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text" id="placement-pages-help"><?= esc(lang('Pages.blocks.help.pages')) ?></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="placement-position"><?= esc(lang('Pages.blocks.fields.position')) ?></label>
                        <input class="form-control" type="number" id="placement-position" name="position" min="0" max="9999" value="<?= $position ?>" aria-describedby="placement-position-help">
                        <div class="form-text" id="placement-position-help"><?= esc(lang('Pages.blocks.help.position')) ?></div>
                    </div>
                </div>
                <div class="card-footer form-actions d-flex flex-wrap gap-2 justify-content-end">
                    <?php if ($editing !== null): ?>
                        <a class="btn btn-outline-secondary" href="<?= esc($baseUrl, 'attr') ?>"><?= esc(lang('Core.ui.cancel')) ?></a>
                    <?php endif ?>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Core.ui.save')) ?></button>
                </div>
            </form>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
