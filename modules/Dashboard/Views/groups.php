<?php
/**
 * Dashboard › Group layouts: the widgets of one group (enabled, position, size, options).
 *
 * @var string                                                                                                       $layout
 * @var array<string, array{id: int, name: string, title: string, description: string, reserved: bool, two_factor: string}> $groups
 * @var string                                                                                                       $group
 * @var list<array<string, mixed>>                                                                                   $rows
 * @var string                                                                                                       $formAction
 */
use Modules\Core\Widgets\WidgetSizes;

$old = old('widgets');
$old = is_array($old) ? $old : null;
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<nav aria-label="<?= esc(lang('Dashboard.groups.groups'), 'attr') ?>" class="mb-3">
    <ul class="nav nav-pills flex-wrap gap-1">
        <?php foreach ($groups as $name => $info): ?>
            <li class="nav-item">
                <a class="nav-link<?= $name === $group ? ' active' : '' ?>" href="<?= esc(site_url('backend/dashboard/groups/' . $name), 'attr') ?>"<?= $name === $group ? ' aria-current="page"' : '' ?>><?= esc($info['title']) ?></a>
            </li>
        <?php endforeach ?>
    </ul>
</nav>
<p class="text-body-secondary small"><?= esc(lang('Dashboard.groups.help', [$groups[$group]['title']])) ?></p>
<?php if ($rows === []): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-grid-1x2', 'title' => lang('Dashboard.groups.none')]) ?>
<?php else: ?>
<form method="post" action="<?= esc($formAction, 'attr') ?>" data-dirty-guard>
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php foreach ($rows as $row): ?>
            <?php
            $field = 'widgets[' . $row['id'] . ']';
            $key   = 'g-' . str_replace('.', '-', (string) $row['id']);
            $prev  = is_array($old[$row['id']] ?? null) ? $old[$row['id']] : null;
            $on    = $prev !== null ? ($prev['enabled'] ?? '0') === '1' : $row['enabled'];
            ?>
            <div class="col-12 col-xl-6">
                <section class="card shadow-sm h-100" aria-labelledby="<?= esc($key, 'attr') ?>-title">
                    <div class="card-header d-flex align-items-center gap-2">
                        <div class="form-check form-switch mb-0 flex-grow-1">
                            <input type="hidden" name="<?= esc($field, 'attr') ?>[enabled]" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="<?= esc($key, 'attr') ?>-enabled" name="<?= esc($field, 'attr') ?>[enabled]" value="1"<?= $on ? ' checked' : '' ?>>
                            <label class="form-check-label fw-semibold" id="<?= esc($key, 'attr') ?>-title" for="<?= esc($key, 'attr') ?>-enabled">
                                <i class="<?= esc(ui_icon((string) $row['icon']) ?: 'bi bi-grid', 'attr') ?> me-1" aria-hidden="true"></i><?= esc((string) $row['title']) ?>
                            </label>
                        </div>
                        <span class="badge text-bg-light border"><?= esc((string) $row['module']) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (! $row['holds']): ?>
                            <p class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc(lang('Dashboard.groups.noPermission', [(string) $row['permission']])) ?></p>
                        <?php elseif ($row['permission'] !== null): ?>
                            <p class="small text-body-secondary"><?= esc(lang('Dashboard.groups.permission')) ?> <code><?= esc((string) $row['permission']) ?></code></p>
                        <?php endif ?>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small" for="<?= esc($key, 'attr') ?>-position"><?= esc(lang('Dashboard.position')) ?></label>
                                <input type="number" class="form-control form-control-sm" id="<?= esc($key, 'attr') ?>-position" name="<?= esc($field, 'attr') ?>[position]" value="<?= esc((string) ($prev['position'] ?? $row['position']), 'attr') ?>" min="0" max="9999" step="1" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="<?= esc($key, 'attr') ?>-size"><?= esc(lang('Dashboard.size')) ?></label>
                                <select class="form-select form-select-sm" id="<?= esc($key, 'attr') ?>-size" name="<?= esc($field, 'attr') ?>[size]">
                                    <?php foreach ($row['sizes'] as $size): ?>
                                        <option value="<?= esc($size, 'attr') ?>"<?= $size === ($prev['size'] ?? $row['size']) ? ' selected' : '' ?>><?= esc(WidgetSizes::label($size)) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <?php foreach ($row['fields'] as $name => $definition): ?>
                                <?php
                                $input = $field . '[options][' . $name . ']';
                                $id    = $key . '-o-' . $name;
                                $value = $prev['options'][$name] ?? $definition['value'];
                                $type  = (string) ($definition['type'] ?? 'string');
                                ?>
                                <div class="col-12">
                                    <?php if ($type === 'bool'): ?>
                                        <div class="form-check">
                                            <input type="hidden" name="<?= esc($input, 'attr') ?>" value="0">
                                            <input class="form-check-input" type="checkbox" id="<?= esc($id, 'attr') ?>" name="<?= esc($input, 'attr') ?>" value="1"<?= in_array($value, [true, '1'], true) ? ' checked' : '' ?>>
                                            <label class="form-check-label small" for="<?= esc($id, 'attr') ?>"><?= esc(lang((string) $definition['label'])) ?></label>
                                        </div>
                                    <?php else: ?>
                                        <label class="form-label small" for="<?= esc($id, 'attr') ?>"><?= esc(lang((string) $definition['label'])) ?></label>
                                        <?php if ($type === 'select'): ?>
                                            <select class="form-select form-select-sm" id="<?= esc($id, 'attr') ?>" name="<?= esc($input, 'attr') ?>">
                                                <?php foreach ($definition['choices'] as $choice => $text): ?>
                                                    <option value="<?= esc((string) $choice, 'attr') ?>"<?= (string) $choice === (string) $value ? ' selected' : '' ?>><?= esc($text) ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        <?php elseif ($type === 'links'): ?>
                                            <?= view('Modules\Dashboard\Views\links_editor', [
                                                'name'    => $input,
                                                'id'      => $id,
                                                'choices' => $definition['choices'],
                                                'items'   => is_array($value) ? $value : [],
                                                'old'     => is_array($prev['options'][$name] ?? null),
                                            ]) ?>
                                        <?php elseif ($type === 'menu'): ?>
                                            <?php $selected = is_array($value) ? $value : []; ?>
                                            <select class="form-select form-select-sm" id="<?= esc($id, 'attr') ?>" name="<?= esc($input, 'attr') ?>[]" multiple size="6">
                                                <?php foreach ($definition['choices'] as $choice => $text): ?>
                                                    <option value="<?= esc((string) $choice, 'attr') ?>"<?= in_array((string) $choice, $selected, true) ? ' selected' : '' ?>><?= esc($text) ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="<?= $type === 'int' ? 'number' : 'text' ?>" class="form-control form-control-sm" id="<?= esc($id, 'attr') ?>" name="<?= esc($input, 'attr') ?>" value="<?= esc(is_scalar($value) ? (string) $value : '', 'attr') ?>"<?php if ($type === 'int'): ?><?= isset($definition['min']) ? ' min="' . (int) $definition['min'] . '"' : '' ?><?= isset($definition['max']) ? ' max="' . (int) $definition['max'] . '"' : '' ?> step="1"<?php else: ?> maxlength="255"<?php endif ?>>
                                        <?php endif ?>
                                    <?php endif ?>
                                    <?php if (! empty($definition['help'])): ?>
                                        <div class="form-text"><?= esc(lang((string) $definition['help'])) ?></div>
                                    <?php endif ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </section>
            </div>
        <?php endforeach ?>
    </div>
    <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Dashboard.save')) ?></button>
    </div>
</form>
<?php endif ?>
<?= $this->endSection() ?>
