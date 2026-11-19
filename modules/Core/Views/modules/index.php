<?php
/**
 * Modules screen (`backend/modules`): every module with versions, status, dependencies and the
 * actions the user may take (enable/disable, demo data, uninstall), and the sync button.
 *
 * @var string                                                        $layout
 * @var string                                                        $tab
 * @var array<string, array<string, mixed>>                           $rows
 * @var array<string, bool>                                           $can
 * @var array{module: string, dependencies: list<string>}|null        $confirm
 * @var array<string, string>|null                                    $secrets
 */
$statusColors = [
    'enabled' => 'success', 'disabled' => 'secondary', 'new' => 'info',
    'pendingInstall' => 'warning', 'pendingUninstall' => 'warning', 'error' => 'danger',
];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Core\Views\modules\_tabs') ?>

<?php if (is_array($confirm ?? null)): ?>
    <div class="alert alert-warning" role="alert" data-enable-dependencies>
        <p class="mb-2"><?= esc(lang('Core.toggle.dependenciesDisabled', [$confirm['module'], implode(', ', $confirm['dependencies'])])) ?></p>
        <form action="<?= esc(backend_url('modules/enable/' . $confirm['module']), 'attr') ?>" method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="dependencies" value="1">
            <button type="submit" class="btn btn-sm btn-warning"><?= esc(lang('Core.toggle.enableDependencies', [implode(', ', $confirm['dependencies'])])) ?></button>
        </form>
        <a class="btn btn-sm btn-link" href="<?= esc(backend_url('modules'), 'attr') ?>"><?= esc(lang('Core.ui.cancel')) ?></a>
    </div>
<?php endif ?>

<?php if (is_array($secrets ?? null) && $secrets !== []): ?>
    <div class="alert alert-info" role="alert" data-demo-secrets>
        <p class="mb-2"><?= esc(lang('Core.demo.secrets')) ?></p>
        <ul class="mb-0 small">
            <?php foreach ($secrets as $label => $value): ?>
                <li><?= esc((string) $label) ?>: <code class="user-select-all"><?= esc($value) ?></code></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>

<?php ob_start() ?>
<?php if ($can['sync']): ?>
    <form action="<?= esc(backend_url('modules/sync'), 'attr') ?>" method="post" class="mb-3">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-primary btn-sm" data-confirm="<?= esc(lang('Core.modules.confirmSync'), 'attr') ?>"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><?= esc(lang('Core.modules.sync')) ?></button>
        <span class="small text-body-secondary ms-2"><?= esc(lang('Core.modules.syncHelp')) ?></span>
    </form>
<?php endif ?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0" data-modules-table>
        <thead>
        <tr>
            <th scope="col"><?= esc(lang('Core.modules.module')) ?></th>
            <th scope="col"><?= esc(lang('Core.modules.version')) ?></th>
            <th scope="col"><?= esc(lang('Core.modules.status')) ?></th>
            <th scope="col" class="d-none d-md-table-cell"><?= esc(lang('Core.modules.dependencies')) ?></th>
            <th scope="col" class="text-end"><?= esc(lang('Core.modules.actions')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $name => $row): ?>
            <tr data-module="<?= esc($name, 'attr') ?>">
                <td>
                    <i class="<?= esc($row['icon'], 'attr') ?> me-1" aria-hidden="true"></i><strong><?= esc($name) ?></strong>
                    <?php if ($row['core']): ?> <?= ui_badge(lang('Core.modules.core'), 'dark') ?><?php endif ?>
                    <?php if ($row['developmentOnly']): ?> <span title="<?= esc(lang('Core.modules.developmentOnlyHelp'), 'attr') ?>"><?= ui_badge(lang('Core.modules.developmentOnly'), 'warning') ?></span><?php endif ?>
                    <?php if ($row['description'] !== ''): ?><div class="small text-body-secondary"><?= esc($row['description']) ?></div><?php endif ?>
                    <?php foreach ($row['errors'] as $error): ?><div class="small text-danger"><?= esc($error) ?></div><?php endforeach ?>
                </td>
                <td class="text-nowrap">
                    <?= esc($row['installed'] ?? $row['version']) ?>
                    <?php if ($row['installed'] !== null && $row['version'] !== '' && $row['installed'] !== $row['version']): ?>
                        <div class="small text-body-secondary"><?= esc(lang('Core.modules.onDisk', [$row['version']])) ?></div>
                    <?php endif ?>
                    <?php if ($row['latest'] !== null && $row['latest'] !== ($row['installed'] ?? $row['version'])): ?>
                        <div><?= ui_badge(lang('Core.modules.latest', [$row['latest']]), 'warning') ?></div>
                    <?php endif ?>
                </td>
                <td><?= ui_badge(lang('Core.modules.statuses.' . $row['status']), $statusColors[$row['status']] ?? 'secondary') ?>
                    <?php if ($row['demoLoaded']): ?> <?= ui_badge(lang('Core.modules.demoLoaded'), 'info') ?><?php endif ?>
                </td>
                <td class="small d-none d-md-table-cell">
                    <?php if ($row['requires'] !== []): ?>
                        <div><span class="text-body-secondary"><?= esc(lang('Core.modules.requires')) ?>:</span> <?= esc(implode(', ', array_map(static fn (string $m, string $c): string => $m . ' ' . $c, array_keys($row['requires']), $row['requires']))) ?></div>
                    <?php endif ?>
                    <?php if ($row['dependants'] !== []): ?>
                        <div><span class="text-body-secondary"><?= esc(lang('Core.modules.dependants')) ?>:</span> <?= esc(implode(', ', $row['dependants'])) ?></div>
                    <?php endif ?>
                </td>
                <td class="text-end">
                    <div class="d-inline-flex flex-nowrap gap-1 justify-content-end">
                        <?php if ($can['toggle'] && ! $row['core'] && in_array($row['status'], ['enabled', 'disabled'], true)): ?>
                            <?php if ($row['enabled']): ?>
                                <form action="<?= esc(backend_url('modules/disable/' . $name), 'attr') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" data-confirm="<?= esc(lang('Core.modules.confirmDisable', [$name]), 'attr') ?>"><?= esc(lang('Core.modules.disable')) ?></button>
                                </form>
                            <?php elseif ($row['blocked']): ?>
                                <span class="small text-body-secondary" data-module-blocked><?= esc(lang('Core.modules.developmentOnlyHelp')) ?></span>
                            <?php else: ?>
                                <form action="<?= esc(backend_url('modules/enable/' . $name), 'attr') ?>" method="post">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-primary" data-confirm="<?= esc(lang('Core.modules.confirmEnable', [$name]), 'attr') ?>"><?= esc(lang('Core.modules.enable')) ?></button>
                                </form>
                            <?php endif ?>
                        <?php endif ?>
                        <?php if ($can['demo'] && $row['demo'] && $row['enabled']): ?>
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?= esc(lang('Core.modules.demo.menu')) ?></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach ($row['demoLoaded'] ? ['reload', 'remove'] : ['load'] as $action): ?>
                                        <li>
                                            <form action="<?= esc(backend_url('modules/demo/' . $name), 'attr') ?>" method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="<?= esc($action, 'attr') ?>">
                                                <button type="submit" class="dropdown-item" data-confirm="<?= esc(lang('Core.demo.confirm.' . $action, [$name]), 'attr') ?>"><?= esc(lang('Core.modules.demo.' . $action)) ?></button>
                                            </form>
                                        </li>
                                    <?php endforeach ?>
                                </ul>
                            </div>
                        <?php endif ?>
                        <?php if ($can['uninstall'] && ! $row['core'] && $row['dependants'] === [] && in_array($row['status'], ['enabled', 'disabled', 'error'], true) && $name !== 'Core'): ?>
                            <a class="btn btn-sm btn-outline-danger" href="<?= esc(backend_url('modules/uninstall/' . $name), 'attr') ?>"><?= esc(lang('Core.modules.uninstall')) ?></a>
                        <?php endif ?>
                    </div>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php $body = (string) ob_get_clean() ?>
<?= theme_component('card', ['title' => lang('Core.modules.installed'), 'icon' => 'bi bi-boxes', 'body' => $body]) ?>
<?= $this->endSection() ?>
