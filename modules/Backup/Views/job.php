<?php
/**
 * A restore started from the back office: progress while it runs (`backup/restore-progress`
 * polls `progress/backup/<job>` and reloads this page at the end), then the result and, once,
 * the merged `.env` to copy when the backup's secrets differ.
 *
 * @var string               $layout
 * @var array<string, mixed> $job
 * @var string|null          $env
 * @var string               $progressUrl
 * @var string               $pageUrl
 */
$status  = (string) ($job['status'] ?? 'queued');
$percent = max(0, min(100, (int) ($job['percent'] ?? 0)));
$secrets = is_array($job['secrets'] ?? null) ? $job['secrets'] : [];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8">
        <?php ob_start() ?>
        <p class="mb-2"><code class="text-break"><?= esc((string) ($job['file'] ?? '')) ?></code></p>
        <?php if ($status === 'done'): ?>
            <div class="alert alert-success" role="status"><i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= esc(lang('Backup.msg.restored')) ?></div>
            <?php if (! empty($job['safety'])): ?>
                <p class="small"><?= esc(lang('Backup.msg.safety', [(string) $job['safety']])) ?></p>
            <?php endif ?>
            <?php if ($secrets !== []): ?>
                <div class="alert alert-warning" role="note">
                    <p><i class="bi bi-key me-1" aria-hidden="true"></i><?= esc(lang('Backup.msg.secretsDiffer', [implode(', ', array_map('strval', $secrets))])) ?></p>
                    <?php if ($env !== null): ?>
                        <label for="backup-env" class="form-label"><?= esc(lang('Backup.msg.envCopy')) ?></label>
                        <textarea id="backup-env" class="form-control font-monospace small" rows="12" readonly><?= esc($env) ?></textarea>
                        <p class="small mt-2 mb-0"><?= esc(lang('Backup.msg.envOnce')) ?></p>
                    <?php else: ?>
                        <p class="mb-0 small"><?= esc(lang('Backup.msg.envShown')) ?></p>
                    <?php endif ?>
                </div>
            <?php endif ?>
            <a class="btn btn-primary" href="<?= esc(site_url('backend/backups'), 'attr') ?>"><?= esc(lang('Backup.title')) ?></a>
        <?php elseif ($status === 'failed'): ?>
            <div class="alert alert-danger mb-0" role="alert"><i class="bi bi-x-octagon me-1" aria-hidden="true"></i><?= esc((string) ($job['message'] ?? lang('Backup.error.unexpected'))) ?></div>
        <?php else: ?>
            <div data-component="backup/restore-progress" data-url="<?= esc($progressUrl, 'attr') ?>" data-page="<?= esc($pageUrl, 'attr') ?>">
                <p data-progress-label aria-live="polite"><?= esc(lang('Backup.step.' . ($job['step'] ?? 'queued'))) ?></p>
                <div class="progress" role="progressbar" aria-label="<?= esc(lang('Backup.page.job'), 'attr') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" data-progress-bar data-percent="<?= $percent ?>"></div>
                </div>
                <p class="small text-body-secondary mt-3 mb-0"><?= esc(lang('Backup.msg.keepOpen')) ?></p>
                <p class="small mt-2 mb-0 d-none" data-progress-finished><a href="<?= esc($pageUrl, 'attr') ?>"><?= esc(lang('Backup.msg.seeResult')) ?></a></p>
            </div>
        <?php endif ?>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Backup.page.' . match ($status) {
            'done' => 'jobDone', 'failed' => 'jobFailed', default => 'job'
        }), 'icon' => 'bi bi-arrow-counterclockwise', 'body' => $body]) ?>
    </div>
</div>
<?= $this->endSection() ?>
