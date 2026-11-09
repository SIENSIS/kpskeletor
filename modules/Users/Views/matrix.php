<?php
/**
 * The permission matrix: one card per module, one row per resource, one column per group; each
 * cell holds the atomic operations and "all" (the resource wildcard), plus the quick selections
 * "read only" and "none" (component `users/matrix`; they only tick checkboxes).
 *
 * @var string                                                                                                       $layout
 * @var array<string, array<string, array{label: string, operations: array<string, array{name: string, label: string}>}>> $blocks
 * @var array<string, array{id: int, name: string, title: string, description: string, reserved: bool, two_factor: string}> $groups
 * @var array<string, list<string>>                                                                                   $grants
 * @var array<string, bool>                                                                                           $editable
 * @var bool                                                                                                          $canSave
 * @var string                                                                                                        $formAction
 */
$shown = [];

foreach ($blocks as $resources) {
    foreach ($resources as $prefix => $resource) {
        $shown[$prefix . '.*'] = true;

        foreach ($resource['operations'] as $operation) {
            $shown[$operation['name']] = true;
        }
    }
}
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<form action="<?= esc($formAction, 'attr') ?>" method="post" data-component="users/matrix" data-dirty-guard>
    <?= csrf_field() ?>
    <?php foreach ($editable as $name => $canEdit): ?>
        <?php if ($canEdit && $name !== 'superadmin'): ?><input type="hidden" name="grants[<?= esc($name, 'attr') ?>][]" value=""><?php endif ?>
    <?php endforeach ?>
    <p class="text-body-secondary small"><?= esc(lang('Users.matrix.help')) ?></p>
    <?php foreach ($blocks as $module => $resources): ?>
        <section class="card shadow-sm mb-4 matrix-block">
            <div class="card-header fw-semibold"><?= esc($module) ?></div>
            <div class="table-responsive">
                <table class="table table-sm align-top mb-0 matrix-table">
                    <thead>
                        <tr>
                            <th scope="col" class="matrix-resource"><?= esc(lang('Users.matrix.resource')) ?></th>
                            <?php foreach ($groups as $name => $group): ?>
                                <th scope="col"><?= esc($group['title']) ?><?php if (! $editable[$name]): ?> <i class="bi bi-lock text-body-secondary" title="<?= esc(lang('Users.matrix.readOnly'), 'attr') ?>" aria-label="<?= esc(lang('Users.matrix.readOnly'), 'attr') ?>"></i><?php endif ?></th>
                            <?php endforeach ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $prefix => $resource): ?>
                            <tr>
                                <th scope="row" class="matrix-resource">
                                    <?= esc($resource['label']) ?>
                                    <div class="small text-body-secondary font-monospace"><?= esc($prefix) ?></div>
                                </th>
                                <?php foreach ($groups as $name => $group): ?>
                                    <?php
                                    $held     = array_flip($grants[$name] ?? []);
                                    $wildcard = isset($held[$prefix . '.*']) || $name === 'superadmin';
                                    $canEdit  = $editable[$name];
                                    $cell     = 'm-' . $name . '-' . str_replace('.', '_', $prefix);
                                    ?>
                                    <td data-matrix-cell>
                                        <div class="form-check form-check-sm fw-semibold">
                                            <input class="form-check-input" type="checkbox" id="<?= esc($cell, 'attr') ?>-all" value="<?= esc($prefix . '.*', 'attr') ?>"
                                                <?= $canEdit && $name !== 'superadmin' ? 'name="grants[' . esc($name, 'attr') . '][]"' : 'disabled' ?>
                                                data-matrix-all<?= $wildcard ? ' checked' : '' ?>>
                                            <label class="form-check-label" for="<?= esc($cell, 'attr') ?>-all"><?= esc(lang('Users.matrix.all')) ?></label>
                                        </div>
                                        <?php foreach ($resource['operations'] as $operation => $entry): ?>
                                            <div class="form-check form-check-sm">
                                                <input class="form-check-input" type="checkbox" id="<?= esc($cell . '-' . $operation, 'attr') ?>" value="<?= esc($entry['name'], 'attr') ?>"
                                                    <?= $canEdit && ! $wildcard ? 'name="grants[' . esc($name, 'attr') . '][]"' : 'disabled' ?>
                                                    data-matrix-op="<?= esc($operation, 'attr') ?>" data-field="grants[<?= esc($name, 'attr') ?>][]"<?= $wildcard || isset($held[$entry['name']]) ? ' checked' : '' ?><?= $canEdit ? '' : ' data-matrix-locked' ?>>
                                                <label class="form-check-label" for="<?= esc($cell . '-' . $operation, 'attr') ?>"><?= esc(lang($entry['label'])) ?></label>
                                            </div>
                                        <?php endforeach ?>
                                        <?php if ($canEdit && $name !== 'superadmin'): ?>
                                            <div class="btn-group btn-group-sm mt-1" role="group" aria-label="<?= esc(lang('Users.matrix.quick'), 'attr') ?>">
                                                <button type="button" class="btn btn-outline-secondary" data-matrix-quick="read"><?= esc(lang('Users.matrix.readOnlyQuick')) ?></button>
                                                <button type="button" class="btn btn-outline-secondary" data-matrix-quick="none"><?= esc(lang('Users.matrix.none')) ?></button>
                                            </div>
                                        <?php endif ?>
                                    </td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach ?>
    <?php foreach ($groups as $name => $group): ?>
        <?php $other = array_values(array_filter($grants[$name] ?? [], static fn (string $grant): bool => ! isset($shown[$grant]))) ?>
        <?php if ($other !== []): ?>
            <p class="small text-body-secondary mb-1"><?= esc(lang('Users.matrix.otherGrants', [$group['title']])) ?> <code><?= esc(implode(', ', $other)) ?></code></p>
        <?php endif ?>
    <?php endforeach ?>
    <p class="small text-body-secondary"><?= esc(lang('Users.matrix.superadmin')) ?></p>
    <?php if ($canSave): ?>
        <div class="d-flex justify-content-end gap-2 sticky-bottom bg-body py-2 border-top">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Users.matrix.save')) ?></button>
        </div>
    <?php endif ?>
</form>
<?= $this->endSection() ?>
