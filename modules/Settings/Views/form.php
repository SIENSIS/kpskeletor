<?php
/**
 * A settings tab: the generated fields of a module, of the theme or of Settings › Security.
 *
 * @var string                                                $layout
 * @var string                                                $tab
 * @var string                                                $action
 * @var string                                                $intro
 * @var bool                                                  $readOnly
 * @var string|null                                           $fields   HTML (module and theme tabs)
 * @var list<array{title: string, fields: string}>|null      $sections HTML per module (security tab)
 * @var array<string, string>|null                            $themes   slug => name (theme tab)
 * @var string|null                                           $theme
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Settings\Views\_tabs') ?>
<?php if (isset($themes) && count($themes) > 1): ?>
    <form method="get" action="<?= esc(site_url('backend/settings/theme'), 'attr') ?>" class="row g-2 align-items-end mb-3">
        <div class="col-sm-auto">
            <label for="settings-theme" class="form-label"><?= esc(lang('Settings.theme.edit')) ?></label>
            <select class="form-select" id="settings-theme" name="theme">
                <?php foreach ($themes as $slug => $name): ?>
                    <option value="<?= esc($slug, 'attr') ?>"<?= $slug === $theme ? ' selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-sm-auto"><button type="submit" class="btn btn-outline-secondary"><?= esc(lang('Settings.theme.choose')) ?></button></div>
    </form>
<?php endif ?>
<p class="text-body-secondary small"><?= esc($intro) ?></p>
<?php if ($readOnly): ?>
    <section class="card shadow-sm"><div class="card-body"><?= $fields ?? '' ?></div></section>
<?php elseif (isset($sections)): ?>
    <form action="<?= esc($action, 'attr') ?>" method="post" data-dirty-guard>
        <?= csrf_field() ?>
        <?php foreach ($sections as $section): ?>
            <div class="mb-4"><?= theme_component('card', ['title' => $section['title'], 'icon' => 'bi bi-shield-lock', 'body' => $section['fields']]) ?></div>
        <?php endforeach ?>
        <div class="d-flex justify-content-end gap-2 settings-actions">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Core.ui.save')) ?></button>
        </div>
    </form>
<?php else: ?>
    <?= theme_component('form_layout', ['action' => $action, 'fields' => $fields ?? '', 'dirtyGuard' => true, 'title' => null]) ?>
<?php endif ?>
<?= $this->endSection() ?>
