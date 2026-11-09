<?php
/**
 * Widget "Users summary" (`Modules\Users\Widgets\UsersSummary`), rendered from its data.
 *
 * @var int    $active
 * @var int    $online
 * @var int    $withoutTwoFactor
 * @var int    $onlineMinutes
 * @var string $url
 */
?>
<div class="row g-2 text-center mb-2">
    <div class="col-4">
        <div class="dashboard-stat"><?= esc(number_format($active)) ?></div>
        <div class="small text-body-secondary"><?= esc(lang('Users.widget.active')) ?></div>
    </div>
    <div class="col-4">
        <div class="dashboard-stat text-success-emphasis"><?= esc(number_format($online)) ?></div>
        <div class="small text-body-secondary"><?= esc(lang('Users.widget.online', [$onlineMinutes])) ?></div>
    </div>
    <div class="col-4">
        <div class="dashboard-stat<?= $withoutTwoFactor > 0 ? ' text-warning-emphasis' : '' ?>"><?= esc(number_format($withoutTwoFactor)) ?></div>
        <div class="small text-body-secondary"><?= esc(lang('Users.widget.withoutTwoFactor')) ?></div>
    </div>
</div>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Users.widget.open')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
