<?php
/**
 * Security status page (`backend/security`): summary, results by group, the last external probe,
 * and the effective Content-Security-Policy and security headers (read-only).
 *
 * @var string                                          $layout
 * @var Modules\Core\Security\Check\SecurityReport      $report
 * @var Modules\Core\Security\Check\SecurityReport|null $probe
 * @var array{name: string, value: string}              $csp
 * @var array<string, string>                           $headers
 */
$counts   = $report->counts();
$status   = $report->status();
$variants = ['ok' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-12">
        <?php ob_start() ?>
        <div class="d-flex flex-wrap align-items-center gap-3 mb-2" data-security-summary="<?= esc($status, 'attr') ?>">
            <span class="fs-5"><?= ui_badge(lang('SecurityCheck.overall.' . $status), $variants[$status], 'bi bi-shield') ?></span>
            <span><?= esc(lang('SecurityCheck.summary', [$counts['ok'], $counts['info'], $counts['warning'], $counts['error']])) ?></span>
        </div>
        <p class="small text-body-secondary mb-3"><?= esc(lang('SecurityCheck.checkedAt')) ?>: <?= $report->checkedAt !== '' ? ui_date($report->checkedAt . ' UTC', 'datetime') : '—' ?> · <?= esc(lang('SecurityCheck.console')) ?> <code>php spark security:check</code></p>
        <div class="d-flex flex-wrap gap-2">
            <form action="<?= esc(site_url('backend/security/refresh'), 'attr') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><?= esc(lang('SecurityCheck.refresh')) ?></button>
            </form>
            <a class="btn btn-outline-secondary" href="<?= esc(site_url('backend/security/probe'), 'attr') ?>"><i class="bi bi-broadcast me-1" aria-hidden="true"></i><?= esc(lang('SecurityCheck.probe.run')) ?></a>
        </div>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('SecurityCheck.summaryTitle'), 'icon' => 'bi bi-clipboard-check', 'body' => $body]) ?>
    </div>
    <?php foreach ($report->grouped() as $group => $results): ?>
        <div class="col-12" data-group="<?= esc($group, 'attr') ?>">
            <?= theme_component('card', [
                'title' => lang('SecurityCheck.group.' . $group) === 'SecurityCheck.group.' . $group ? ucfirst($group) : lang('SecurityCheck.group.' . $group),
                'icon'  => 'bi bi-list-check',
                'body'  => $this->setVar('results', $results)->include('Modules\Core\Views\security\_results'),
            ]) ?>
        </div>
    <?php endforeach ?>
    <div class="col-12" data-group="probe">
        <?php if ($probe === null || $probe->results() === []): ?>
            <?php $body = '<p class="text-body-secondary mb-0">' . esc(lang('SecurityCheck.probe.never')) . '</p>' ?>
        <?php else: ?>
            <?php $body = '<p class="small text-body-secondary">' . esc(lang('SecurityCheck.checkedAt')) . ': ' . ui_date($probe->checkedAt . ' UTC', 'datetime') . '</p>' . $this->setVar('results', $probe->results())->include('Modules\Core\Views\security\_results') ?>
        <?php endif ?>
        <?= theme_component('card', ['title' => lang('SecurityCheck.group.probe'), 'icon' => 'bi bi-broadcast', 'body' => $body]) ?>
    </div>
    <div class="col-12">
        <?php ob_start() ?>
        <h3 class="h6"><?= esc($csp['name']) ?></h3>
        <?php if ($csp['value'] === ''): ?>
            <p class="text-danger"><?= esc(lang('SecurityCheck.csp.disabled')) ?></p>
        <?php else: ?>
            <ul class="list-unstyled small font-monospace text-break mb-3" data-effective-csp>
                <?php foreach (Modules\Core\Security\Check\EffectivePolicy::directives($csp['value']) as $directive => $sources): ?>
                    <li><strong><?= esc($directive) ?></strong> <?= esc(implode(' ', $sources)) ?></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
        <h3 class="h6"><?= esc(lang('SecurityCheck.otherHeaders')) ?></h3>
        <dl class="row small mb-2" data-effective-headers>
            <?php foreach ($headers as $name => $value): ?>
                <dt class="col-md-4 text-break"><?= esc($name) ?></dt>
                <dd class="col-md-8 font-monospace text-break"><?= esc($value) ?></dd>
            <?php endforeach ?>
        </dl>
        <p class="small text-body-secondary mb-0"><?= esc(lang('SecurityCheck.headersHelp')) ?></p>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('SecurityCheck.effective'), 'icon' => 'bi bi-file-earmark-lock', 'body' => $body]) ?>
    </div>
</div>
<?= $this->endSection() ?>
