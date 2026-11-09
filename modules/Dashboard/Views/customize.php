<?php
/**
 * "Customise the dashboard" without JavaScript: position, size and visibility of every widget
 * the user may see.
 *
 * @var string                                                                                                   $layout
 * @var list<array{id: string, title: string, position: int, size: string, sizes: list<string>, hidden: bool}> $rows
 * @var string                                                                                                   $saveUrl
 * @var string                                                                                                   $resetUrl
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php if ($rows === []): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-speedometer2', 'title' => lang('Dashboard.empty'), 'message' => lang('Dashboard.emptyHelp')]) ?>
<?php else: ?>
    <form method="post" action="<?= esc($saveUrl, 'attr') ?>" class="card shadow-sm" data-dirty-guard>
        <?= csrf_field() ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?= esc(lang('Dashboard.widget')) ?></th>
                        <th scope="col" class="dashboard-col-number"><?= esc(lang('Dashboard.position')) ?></th>
                        <th scope="col"><?= esc(lang('Dashboard.size')) ?></th>
                        <th scope="col" class="text-center"><?= esc(lang('Dashboard.visible')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $field = 'widgets[' . $row['id'] . ']'; $key = 'c-' . str_replace('.', '-', $row['id']); ?>
                        <tr>
                            <th scope="row" class="fw-semibold"><label for="<?= esc($key, 'attr') ?>-position"><?= esc($row['title']) ?></label></th>
                            <td><input type="number" class="form-control form-control-sm" id="<?= esc($key, 'attr') ?>-position" name="<?= esc($field, 'attr') ?>[position]" value="<?= (int) $row['position'] ?>" min="0" max="999999" step="1"></td>
                            <td>
                                <select class="form-select form-select-sm" name="<?= esc($field, 'attr') ?>[size]" aria-label="<?= esc(lang('Dashboard.size') . ': ' . $row['title'], 'attr') ?>">
                                    <?php foreach ($row['sizes'] as $size): ?>
                                        <option value="<?= esc($size, 'attr') ?>"<?= $size === $row['size'] ? ' selected' : '' ?>><?= esc(\Modules\Core\Widgets\WidgetSizes::label($size)) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="<?= esc($field, 'attr') ?>[visible]" value="0">
                                <input type="checkbox" class="form-check-input" name="<?= esc($field, 'attr') ?>[visible]" value="1"<?= $row['hidden'] ? '' : ' checked' ?> aria-label="<?= esc(lang('Dashboard.visible') . ': ' . $row['title'], 'attr') ?>">
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a class="btn btn-outline-secondary" href="<?= esc(site_url('backend'), 'attr') ?>"><?= esc(lang('Dashboard.cancel')) ?></a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Dashboard.save')) ?></button>
        </div>
    </form>
    <form method="post" action="<?= esc($resetUrl, 'attr') ?>" class="mt-3" data-confirm="<?= esc(lang('Dashboard.restoreConfirm'), 'attr') ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-link px-0"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= esc(lang('Dashboard.restore')) ?></button>
    </form>
<?php endif ?>
<?= $this->endSection() ?>
