<?php
/**
 * Staged packages (`backend/modules/packages`): uploaded, pending and processed installs and
 * uninstalls, with the console command that runs them.
 *
 * @var string                           $layout
 * @var string                           $tab
 * @var list<array<string, mixed>>       $rows
 * @var array<string, bool>              $can
 */
$colors = ['uploaded' => 'info', 'pending' => 'warning', 'installing' => 'warning', 'installed' => 'success', 'failed' => 'danger', 'discarded' => 'secondary', 'expired' => 'secondary'];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Core\Views\modules\_tabs') ?>
<?php ob_start() ?>
<p class="small text-body-secondary"><?= esc(lang('Core.packages.intro')) ?></p>
<p class="small"><?= esc(lang('Core.packages.howToRun')) ?> <code>php spark modules:install --pending</code></p>
<?php if ($can['upload']): ?>
    <a class="btn btn-primary btn-sm mb-3" href="<?= esc(site_url('backend/modules/packages/upload'), 'attr') ?>"><i class="bi bi-upload me-1" aria-hidden="true"></i><?= esc(lang('Core.packages.upload')) ?></a>
<?php endif ?>
<?php if ($rows === []): ?>
    <p class="text-body-secondary mb-0"><?= esc(lang('Core.packages.none')) ?></p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" data-packages-table>
            <thead>
            <tr>
                <th scope="col"><?= esc(lang('Core.packages.operation')) ?></th>
                <th scope="col"><?= esc(lang('Core.modules.status')) ?></th>
                <th scope="col" class="d-none d-md-table-cell"><?= esc(lang('Core.packages.date')) ?></th>
                <th scope="col" class="text-end"><?= esc(lang('Core.modules.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr data-package="<?= (int) $row['id'] ?>">
                    <td>
                        <?= esc(lang('Core.packages.actions.' . ($row['action'] === 'uninstall' ? 'uninstall' : 'install'))) ?>:
                        <strong><?= esc(trim($row['name'] . ' ' . $row['version'])) ?></strong>
                        <span class="small text-body-secondary">(<?= esc(lang('Core.packages.types.' . ($row['type'] === 'theme' ? 'theme' : 'module'))) ?><?= (int) $row['keep_data'] === 1 ? ', ' . esc(lang('Core.packages.keepData')) : '' ?>)</span>
                    </td>
                    <td><?= ui_badge(lang('Core.packages.statuses.' . $row['status']), $colors[$row['status']] ?? 'secondary') ?></td>
                    <td class="small d-none d-md-table-cell"><?= ui_date(($row['processed_at'] ?? $row['approved_at'] ?? $row['created_at']) . ' UTC', 'datetime') ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= esc(site_url('backend/modules/packages/' . $row['id']), 'attr') ?>"><?= esc(lang('Core.packages.details')) ?></a>
                            <?php if (in_array($row['status'], ['uploaded', 'pending'], true) && ($row['action'] === 'uninstall' ? $can['uninstall'] : $can['upload'])): ?>
                                <form action="<?= esc(site_url('backend/modules/packages/' . $row['id'] . '/discard'), 'attr') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= esc(lang('Core.packages.confirmDiscard'), 'attr') ?>"><?= esc(lang('Core.packages.discard')) ?></button>
                                </form>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $body = (string) ob_get_clean() ?>
<?= theme_component('card', ['title' => lang('Core.modules.tabs.packages'), 'icon' => 'bi bi-box-seam', 'body' => $body]) ?>
<?= $this->endSection() ?>
