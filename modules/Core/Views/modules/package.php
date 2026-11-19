<?php
/**
 * One staged package (`backend/modules/packages/<id>`): what it contains (read without running
 * it), the checks of the install plan, the approval button and the log of the run.
 *
 * @var string                                                                      $layout
 * @var array<string, mixed>                                                        $row
 * @var \Modules\Core\Packages\PackageInfo|null                                     $info
 * @var array{action: string, from: string|null, problems: list<string>}|null      $plan
 * @var array<string, bool>                                                         $can
 */
$list = static fn (array $items): string => $items === [] ? '—' : esc(implode(', ', $items));
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-7">
        <?php ob_start() ?>
        <dl class="row small mb-0" data-package-details>
            <dt class="col-sm-4"><?= esc(lang('Core.packages.operation')) ?></dt>
            <dd class="col-sm-8"><?= esc(lang('Core.packages.actions.' . ($row['action'] === 'uninstall' ? 'uninstall' : 'install'))) ?> · <?= esc(lang('Core.packages.types.' . ($row['type'] === 'theme' ? 'theme' : 'module'))) ?></dd>
            <dt class="col-sm-4"><?= esc(lang('Core.modules.status')) ?></dt>
            <dd class="col-sm-8"><?= esc(lang('Core.packages.statuses.' . $row['status'])) ?></dd>
            <?php if ($info !== null): ?>
                <dt class="col-sm-4"><?= esc(lang('Core.packages.author')) ?></dt>
                <dd class="col-sm-8"><?= esc($info->author !== '' ? $info->author : '—') ?></dd>
                <dt class="col-sm-4"><?= esc(lang('Core.packages.description')) ?></dt>
                <dd class="col-sm-8"><?= esc($info->description !== '' ? lang($info->description) : '—') ?></dd>
                <dt class="col-sm-4"><?= esc(lang('Core.packages.signed')) ?></dt>
                <dd class="col-sm-8"><?= $info->signed ? ui_badge(lang('Core.list.yes'), 'success') : ui_badge(lang('Core.list.no'), 'secondary') ?></dd>
                <dt class="col-sm-4">SHA-256</dt>
                <dd class="col-sm-8 text-break"><code><?= esc($info->sha256) ?></code></dd>
                <?php if ($info->isModule()): ?>
                    <dt class="col-sm-4"><?= esc(lang('Core.packages.requires')) ?></dt>
                    <dd class="col-sm-8"><?= $list(array_map(static fn (string $m, string $c): string => $m . ' ' . $c, array_keys($info->requires), $info->requires)) ?></dd>
                    <dt class="col-sm-4"><?= esc(lang('Core.packages.permissions')) ?></dt>
                    <dd class="col-sm-8" data-package-permissions><?= $list($info->permissions) ?></dd>
                    <dt class="col-sm-4"><?= esc(lang('Core.packages.menus')) ?></dt>
                    <dd class="col-sm-8"><?= $list(array_map(static fn (string $label): string => lang($label), $info->menus)) ?></dd>
                    <dt class="col-sm-4"><?= esc(lang('Core.packages.widgets')) ?></dt>
                    <dd class="col-sm-8 text-break"><?= $list($info->widgets) ?></dd>
                    <dt class="col-sm-4"><?= esc(lang('Core.packages.tasks')) ?></dt>
                    <dd class="col-sm-8 text-break"><?= $list($info->tasks) ?></dd>
                <?php else: ?>
                    <dt class="col-sm-4"><?= esc(lang('Core.packages.parent')) ?></dt>
                    <dd class="col-sm-8"><?= esc($info->parent ?? '—') ?></dd>
                <?php endif ?>
            <?php elseif ((int) $row['keep_data'] === 1): ?>
                <dt class="col-sm-4"><?= esc(lang('Core.packages.keepData')) ?></dt>
                <dd class="col-sm-8"><?= esc(lang('Core.list.yes')) ?></dd>
            <?php endif ?>
        </dl>
        <?php if ($plan !== null): ?>
            <hr>
            <?php if ($plan['problems'] === []): ?>
                <p class="small text-success mb-2"><i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= esc(lang('Core.packages.plan.' . $plan['action'], [$row['name'], $row['version'], $plan['from'] ?? ''])) ?></p>
            <?php else: ?>
                <div class="alert alert-danger small" role="alert" data-package-problems>
                    <?php foreach ($plan['problems'] as $problem): ?><div><?= esc($problem) ?></div><?php endforeach ?>
                </div>
            <?php endif ?>
        <?php endif ?>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php if ($row['status'] === 'uploaded' && $can['upload'] && $plan !== null && $plan['problems'] === []): ?>
                <form action="<?= esc(backend_url('modules/packages/' . $row['id'] . '/approve'), 'attr') ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary" data-confirm="<?= esc(lang('Core.packages.confirmApprove'), 'attr') ?>" data-confirm-variant="primary"><i class="bi bi-check2 me-1" aria-hidden="true"></i><?= esc(lang('Core.packages.approve')) ?></button>
                </form>
            <?php endif ?>
            <?php if (in_array($row['status'], ['uploaded', 'pending'], true) && ($row['action'] === 'uninstall' ? $can['uninstall'] : $can['upload'])): ?>
                <form action="<?= esc(backend_url('modules/packages/' . $row['id'] . '/discard'), 'attr') ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger" data-confirm="<?= esc(lang('Core.packages.confirmDiscard'), 'attr') ?>"><?= esc(lang('Core.packages.discard')) ?></button>
                </form>
            <?php endif ?>
            <a class="btn btn-link" href="<?= esc(backend_url('modules/packages'), 'attr') ?>"><?= esc(lang('Core.packages.back')) ?></a>
        </div>
        <?php if ($row['status'] === 'pending'): ?>
            <p class="small mt-3 mb-0"><?= esc(lang('Core.packages.howToRun')) ?> <code>php spark modules:install --pending</code></p>
        <?php endif ?>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Core.packages.manifest'), 'icon' => 'bi bi-card-checklist', 'body' => $body]) ?>
    </div>
    <div class="col-lg-5">
        <?php if ($info !== null): ?>
            <?php ob_start() ?>
            <p class="small text-body-secondary"><?= esc(lang('Core.packages.fileCount', [count($info->files), number_format($info->totalSize())])) ?></p>
            <ul class="small list-unstyled mb-0 package-files" data-package-files>
                <?php foreach (array_slice($info->files, 0, 300, true) as $file => $size): ?>
                    <li class="text-break"><code><?= esc((string) $file) ?></code> <span class="text-body-secondary"><?= number_format((int) $size) ?> B</span></li>
                <?php endforeach ?>
            </ul>
            <?php $body = (string) ob_get_clean() ?>
            <?= theme_component('card', ['title' => lang('Core.packages.files'), 'icon' => 'bi bi-files', 'body' => $body]) ?>
        <?php endif ?>
        <?php if (($row['log'] ?? '') !== ''): ?>
            <?= theme_component('card', ['title' => lang('Core.packages.log'), 'icon' => 'bi bi-terminal', 'body' => '<pre class="small mb-0 text-wrap" data-package-log>' . esc((string) $row['log']) . '</pre>']) ?>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
