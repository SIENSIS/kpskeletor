<?php
/**
 * A user's groups and individual permissions. Checkboxes the current user may not change are
 * disabled (the server applies the same escalation rules).
 *
 * @var string                                                                                                                       $layout
 * @var \CodeIgniter\Shield\Entities\User                                                                                            $target
 * @var string                                                                                                                       $email
 * @var array<string, array{title: string, member: bool, editable: bool}>                                                            $groups
 * @var array<string, array<string, array{label: string, operations: array<string, array{name: string, label: string, held: bool, editable: bool}>}>> $permissions
 * @var string                                                                                                                       $formAction
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<form action="<?= esc($formAction, 'attr') ?>" method="post" data-dirty-guard>
    <?= csrf_field() ?>
    <p class="text-body-secondary"><?= esc((string) $target->username) ?> · <?= esc($email) ?></p>

    <section class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-diagram-3 me-1" aria-hidden="true"></i><?= esc(lang('Users.access.groups')) ?></div>
        <div class="card-body">
            <?php foreach ($groups as $name => $group): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="access-group-<?= esc($name, 'attr') ?>" value="<?= esc($name, 'attr') ?>"
                        <?= $group['editable'] ? 'name="groups[]"' : 'disabled' ?><?= $group['member'] ? ' checked' : '' ?>>
                    <?php if (! $group['editable'] && $group['member']): ?>
                        <input type="hidden" name="groups[]" value="<?= esc($name, 'attr') ?>">
                    <?php endif ?>
                    <label class="form-check-label" for="access-group-<?= esc($name, 'attr') ?>"><?= esc($group['title']) ?> <span class="text-body-secondary small font-monospace"><?= esc($name) ?></span></label>
                </div>
            <?php endforeach ?>
            <p class="small text-body-secondary mt-2 mb-0"><?= esc(lang('Users.access.groupsHelp')) ?></p>
        </div>
    </section>

    <section class="card shadow-sm mb-4">
        <div class="card-header fw-semibold"><i class="bi bi-key me-1" aria-hidden="true"></i><?= esc(lang('Users.access.permissions')) ?></div>
        <div class="card-body">
            <p class="small text-body-secondary"><?= esc(lang('Users.access.permissionsHelp')) ?></p>
            <?php foreach ($permissions as $module => $resources): ?>
                <h3 class="h6 mt-3"><?= esc($module) ?></h3>
                <?php foreach ($resources as $prefix => $resource): ?>
                    <div class="mb-2">
                        <span class="fw-semibold small"><?= esc($resource['label']) ?></span>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($resource['operations'] as $operation => $entry): ?>
                                <?php $id = 'access-perm-' . str_replace('.', '_', $entry['name']) ?>
                                <div class="form-check form-check-inline me-0">
                                    <input class="form-check-input" type="checkbox" id="<?= esc($id, 'attr') ?>" value="<?= esc($entry['name'], 'attr') ?>"
                                        <?= $entry['editable'] ? 'name="permissions[]"' : 'disabled' ?><?= $entry['held'] ? ' checked' : '' ?>>
                                    <?php if (! $entry['editable'] && $entry['held']): ?>
                                        <input type="hidden" name="permissions[]" value="<?= esc($entry['name'], 'attr') ?>">
                                    <?php endif ?>
                                    <label class="form-check-label small" for="<?= esc($id, 'attr') ?>"><?= esc(lang($entry['label'])) ?></label>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                <?php endforeach ?>
            <?php endforeach ?>
        </div>
    </section>

    <div class="d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="<?= esc(site_url('backend/users'), 'attr') ?>"><?= esc(lang('Core.ui.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Core.ui.save')) ?></button>
    </div>
</form>
<?= $this->endSection() ?>
