<?php
/**
 * The heading and introduction of a section (both optional).
 *
 * @var string $heading
 * @var string $intro
 * @var bool   $centered
 */
?>
<?php if ($heading !== '' || $intro !== ''): ?>
    <header class="kp-section-header mb-4<?= ! empty($centered) ? ' text-center mx-auto' : '' ?>">
        <?php if ($heading !== ''): ?>
            <h2 class="kp-section-title h2 text-break"><?= esc($heading) ?></h2>
        <?php endif ?>
        <?php if ($intro !== ''): ?>
            <p class="kp-section-intro text-body-secondary kp-pre-line mb-0"><?= esc($intro) ?></p>
        <?php endif ?>
    </header>
<?php endif ?>
