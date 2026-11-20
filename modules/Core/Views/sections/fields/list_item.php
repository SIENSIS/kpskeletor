<?php
/**
 * One item of a list field.
 *
 * @var \Modules\Core\Sections\Fields\ListField $field
 * @var string                                  $name   the item's input name (`f[items][3]`)
 * @var string                                  $id     the item's id prefix
 * @var string                                  $path   the item's path (`items.3`)
 * @var array<string, mixed>                    $item
 * @var int                                     $number 1, 2… (0 in the template)
 * @var \Modules\Core\Sections\SectionFormView  $view
 */
$title = lang('Sections.form.item', [$number > 0 ? $number : '']);
?>
<li class="kp-list-item card mb-2" data-list-item>
    <div class="card-header d-flex flex-wrap align-items-center gap-2 py-1">
        <strong class="me-auto small" data-list-title><?= esc($title) ?></strong>
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= esc(lang('Sections.form.itemActions'), 'attr') ?>">
            <button type="button" class="btn btn-outline-secondary" data-list-move="up" title="<?= esc(lang('Sections.form.moveUp'), 'attr') ?>" aria-label="<?= esc(lang('Sections.form.moveUp'), 'attr') ?>" hidden><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-outline-secondary" data-list-move="down" title="<?= esc(lang('Sections.form.moveDown'), 'attr') ?>" aria-label="<?= esc(lang('Sections.form.moveDown'), 'attr') ?>" hidden><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-outline-danger" data-list-remove title="<?= esc(lang('Sections.form.removeItem'), 'attr') ?>" aria-label="<?= esc(lang('Sections.form.removeItem'), 'attr') ?>" hidden><i class="bi bi-trash" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="card-body pb-1">
        <?php foreach ($field->getFields() as $sub => $subField): ?>
            <?= $view->field($subField, $name . '[' . $sub . ']', $id . '-' . $sub, $path . '.' . $sub, $item[$sub] ?? null) ?>
        <?php endforeach ?>
    </div>
</li>
