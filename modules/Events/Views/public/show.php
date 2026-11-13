<?php
/**
 * A public event (`PublicCalendar::show`).
 *
 * @var string               $layout
 * @var string               $title
 * @var array<string, mixed> $event
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<article class="card shadow-sm">
    <div class="card-body">
        <h1 class="h3 mb-3"><?= esc($title) ?></h1>
        <?= theme_view('Modules\Events\Views\calendar\_details', ['event' => $event]) ?>
    </div>
    <div class="card-footer bg-transparent">
        <a class="btn btn-outline-secondary btn-sm" href="<?= esc(site_url('calendar'), 'attr') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('Events.backToCalendar')) ?></a>
    </div>
</article>
<?= $this->endSection() ?>
