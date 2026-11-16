<?php
/**
 * Themes tab (`backend/modules/themes`): installed themes with a preview, the active theme of
 * each area (one-click switch) and the uninstall request.
 *
 * @var string                              $layout
 * @var string                              $tab
 * @var array<string, array<string, mixed>> $themes
 * @var array<string, bool>                 $can
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Core\Views\modules\_tabs') ?>
<p class="small text-body-secondary"><?= esc(lang('Core.themes.intro')) ?> <code>php spark themes:install &lt;file.zip&gt;</code></p>
<div class="row g-3" data-themes>
    <?php foreach ($themes as $slug => $theme): ?>
        <div class="col-sm-6 col-xl-4">
            <div class="card h-100" data-theme="<?= esc($slug, 'attr') ?>">
                <?php if ($theme['screenshot'] !== null): ?>
                    <img src="<?= esc($theme['screenshot'], 'attr') ?>" class="card-img-top" alt="<?= esc(lang('Core.themes.preview', [$theme['name']]), 'attr') ?>" loading="lazy">
                <?php endif ?>
                <div class="card-body">
                    <h2 class="h6 card-title mb-1"><?= esc($theme['name']) ?> <span class="text-body-secondary fw-normal small"><?= esc($theme['version']) ?></span></h2>
                    <p class="small text-body-secondary mb-2">
                        <code><?= esc($slug) ?></code><?php if ($theme['author'] !== ''): ?> · <?= esc($theme['author']) ?><?php endif ?>
                        <?php if ($theme['parent'] !== null): ?> · <?= esc(lang('Core.themes.childOf', [$theme['parent']])) ?><?php endif ?>
                    </p>
                    <?php if ($theme['colors'] !== []): ?>
                        <div class="d-flex gap-1 mb-2" aria-label="<?= esc(lang('Core.themes.colors'), 'attr') ?>">
                            <?php foreach ($theme['colors'] as $color): ?>
                                <input type="color" class="form-control form-control-color form-control-sm" value="<?= esc($color, 'attr') ?>" disabled title="<?= esc($color, 'attr') ?>">
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                    <div class="mb-2">
                        <?php if ($theme['backend']): ?><?= ui_badge(lang('Core.themes.activeBackend'), 'success') ?><?php endif ?>
                        <?php if ($theme['public']): ?><?= ui_badge(lang('Core.themes.activePublic'), 'success') ?><?php endif ?>
                        <?php if ($theme['fallback']): ?><?= ui_badge(lang('Core.themes.fallback'), 'secondary') ?><?php endif ?>
                    </div>
                    <?php if ($theme['error'] !== null): ?><p class="small text-danger"><?= esc($theme['error']) ?></p><?php endif ?>
                </div>
                <div class="card-footer d-flex flex-wrap gap-1">
                    <?php if ($can['toggle'] && $theme['error'] === null): ?>
                        <?php foreach (['backend', 'public'] as $area): ?>
                            <?php if (! $theme[$area]): ?>
                                <form action="<?= esc(site_url('backend/modules/themes/activate'), 'attr') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="area" value="<?= esc($area, 'attr') ?>">
                                    <input type="hidden" name="theme" value="<?= esc($slug, 'attr') ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" data-confirm="<?= esc(lang('Core.themes.confirmActivate', [$theme['name'], lang('Core.themes.areas.' . $area)]), 'attr') ?>"><?= esc(lang('Core.themes.use.' . $area)) ?></button>
                                </form>
                            <?php endif ?>
                        <?php endforeach ?>
                    <?php endif ?>
                    <?php if ($can['options'] && $theme['error'] === null): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= esc(site_url('backend/settings/theme') . '?theme=' . rawurlencode($slug), 'attr') ?>"><?= esc(lang('Core.themes.options')) ?></a>
                    <?php endif ?>
                    <?php if ($can['uninstall'] && ! $theme['backend'] && ! $theme['public'] && ! $theme['fallback']): ?>
                        <a class="btn btn-sm btn-outline-danger" href="<?= esc(site_url('backend/modules/themes/uninstall/' . $slug), 'attr') ?>"><?= esc(lang('Core.modules.uninstall')) ?></a>
                    <?php endif ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
