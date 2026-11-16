<?php
/**
 * Updates page (`backend/updates`): installed and latest versions, release notes, modules with
 * their own repository, and the "check now" button. Updating itself is a console command.
 *
 * @var string                              $layout
 * @var string                              $installed
 * @var string                              $repository
 * @var bool                                $signed
 * @var array<string, mixed>|null           $latest
 * @var string                              $notes      safe HTML (ReleaseNotes)
 * @var array<string, string>               $newer
 * @var array<string, array<string, mixed>> $modules
 * @var string|null                         $checkedAt
 * @var string|null                         $error
 * @var bool                                $canCheck
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-5">
        <?php ob_start() ?>
        <dl class="row mb-3 small">
            <dt class="col-sm-5"><?= esc(lang('Updates.installed')) ?></dt>
            <dd class="col-sm-7" data-installed><?= esc($installed) ?></dd>
            <dt class="col-sm-5"><?= esc(lang('Updates.latest')) ?></dt>
            <dd class="col-sm-7" data-latest>
                <?php if ($latest === null): ?>
                    —
                <?php else: ?>
                    <?= esc((string) $latest['version']) ?>
                    <?php if (isset($newer['Core'])): ?> <?= ui_badge(lang('Updates.new'), 'warning') ?><?php endif ?>
                <?php endif ?>
            </dd>
            <dt class="col-sm-5"><?= esc(lang('Updates.repository')) ?></dt>
            <dd class="col-sm-7 text-break"><?= $repository === '' ? esc(lang('Updates.notConfigured')) : '<code>' . esc($repository) . '</code>' ?></dd>
            <dt class="col-sm-5"><?= esc(lang('Updates.signing')) ?></dt>
            <dd class="col-sm-7"><?= $signed ? ui_badge(lang('Updates.keyConfigured'), 'success') : ui_badge(lang('Updates.keyMissing'), 'danger') ?></dd>
            <dt class="col-sm-5"><?= esc(lang('Updates.checkedAt')) ?></dt>
            <dd class="col-sm-7"><?= $checkedAt !== null ? ui_date($checkedAt . ' UTC', 'datetime') : esc(lang('Updates.never')) ?></dd>
        </dl>
        <?php if ($error !== null): ?>
            <div class="alert alert-danger small" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc(lang('Updates.msg.checkFailed', [$error])) ?></div>
        <?php endif ?>
        <?php if (isset($newer['Core'])): ?>
            <div class="alert alert-info small" role="note"><i class="bi bi-terminal me-1" aria-hidden="true"></i><?= esc(lang('Updates.howTo')) ?> <code>php spark app:update --dry-run</code></div>
        <?php endif ?>
        <?php if ($canCheck): ?>
            <form action="<?= esc(site_url('backend/updates/check'), 'attr') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><?= esc(lang('Updates.checkNow')) ?></button>
            </form>
        <?php endif ?>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Updates.application'), 'icon' => 'bi bi-box-seam', 'body' => $body]) ?>
    </div>
    <div class="col-lg-7">
        <?php ob_start() ?>
        <?php if ($latest === null): ?>
            <p class="text-body-secondary mb-0"><?= esc(lang('Updates.noRelease')) ?></p>
        <?php else: ?>
            <h3 class="h6"><?= esc((string) (($latest['name'] ?? '') !== '' ? $latest['name'] : $latest['tag'])) ?>
                <?php if (($latest['publishedAt'] ?? '') !== ''): ?><small class="text-body-secondary fw-normal">· <?= ui_date((string) $latest['publishedAt']) ?></small><?php endif ?>
            </h3>
            <div class="release-notes small" data-release-notes><?= $notes !== '' ? $notes : '<p class="text-body-secondary">' . esc(lang('Updates.noNotes')) . '</p>' ?></div>
            <?php if (($latest['url'] ?? '') !== '' && str_starts_with((string) $latest['url'], 'https://')): ?>
                <a class="small" href="<?= esc((string) $latest['url'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Updates.openRelease')) ?> <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
            <?php endif ?>
        <?php endif ?>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Updates.notes'), 'icon' => 'bi bi-journal-text', 'body' => $body]) ?>
    </div>
    <?php if ($modules !== []): ?>
        <div class="col-12">
            <?php ob_start() ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col"><?= esc(lang('Updates.module')) ?></th>
                        <th scope="col"><?= esc(lang('Updates.installed')) ?></th>
                        <th scope="col"><?= esc(lang('Updates.latest')) ?></th>
                        <th scope="col"><?= esc(lang('Updates.repository')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($modules as $name => $module): ?>
                        <tr>
                            <td><?= esc((string) $name) ?></td>
                            <td><?= esc((string) ($module['installed'] ?? '')) ?></td>
                            <td>
                                <?php if (is_string($module['error'] ?? null)): ?>
                                    <span class="text-danger small"><?= esc($module['error']) ?></span>
                                <?php else: ?>
                                    <?= esc(is_array($module['latest'] ?? null) ? (string) ($module['latest']['version'] ?? '') : '—') ?>
                                    <?php if (isset($newer[$name])): ?> <?= ui_badge(lang('Updates.new'), 'warning') ?><?php endif ?>
                                <?php endif ?>
                            </td>
                            <td class="text-break"><code><?= esc((string) ($module['repository'] ?? '')) ?></code></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <?php $body = (string) ob_get_clean() ?>
            <?= theme_component('card', ['title' => lang('Updates.modules'), 'icon' => 'bi bi-boxes', 'body' => $body]) ?>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
