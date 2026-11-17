<?php
/**
 * One group of security check results (a table: status, check, what it means, how to fix it).
 *
 * @var list<Modules\Core\Security\Check\CheckResult> $results
 */
$variants = ['ok' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'];
$icons    = ['ok' => 'bi bi-check-circle', 'info' => 'bi bi-info-circle', 'warning' => 'bi bi-exclamation-triangle', 'error' => 'bi bi-x-octagon'];
?>
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead>
        <tr>
            <th scope="col" class="text-nowrap"><?= esc(lang('SecurityCheck.col.status')) ?></th>
            <th scope="col"><?= esc(lang('SecurityCheck.col.check')) ?></th>
            <th scope="col"><?= esc(lang('SecurityCheck.col.result')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $result): ?>
            <tr data-check="<?= esc($result->id, 'attr') ?>" data-status="<?= esc($result->status, 'attr') ?>">
                <td class="text-nowrap"><?= ui_badge(lang('SecurityCheck.status.' . $result->status), $variants[$result->status], $icons[$result->status]) ?></td>
                <td class="fw-semibold"><?= esc($result->title) ?></td>
                <td class="text-break small">
                    <?= esc($result->message) ?>
                    <?php if ($result->fix !== '' && $result->status !== 'ok'): ?>
                        <div class="text-body-secondary mt-1"><i class="bi bi-wrench me-1" aria-hidden="true"></i><?= esc($result->fix) ?></div>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
