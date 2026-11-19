<?php
/**
 * `backend/devtools`: the development tools.
 *
 * @var string                                                                $layout
 * @var list<array{url: string, icon: string, title: string, text: string}>  $tools
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Devtools\Views\_banner') ?>
<p class="text-body-secondary"><?= esc(lang('Devtools.intro')) ?></p>
<div class="row g-3 mb-4">
    <?php foreach ($tools as $tool): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <a class="card h-100 text-decoration-none" href="<?= esc($tool['url'], 'attr') ?>">
                <div class="card-body">
                    <h2 class="h5 card-title"><i class="<?= esc($tool['icon'], 'attr') ?> me-2" aria-hidden="true"></i><?= esc($tool['title']) ?></h2>
                    <p class="card-text text-body-secondary mb-0"><?= esc($tool['text']) ?></p>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>
<h2 class="h5"><?= esc(lang('Devtools.locks.title')) ?></h2>
<ol class="small">
    <?php foreach (['environment', 'manifest', 'check', 'local', 'permission', 'packaging'] as $lock): ?>
        <li><?= esc(lang('Devtools.locks.' . $lock)) ?></li>
    <?php endforeach ?>
</ol>
<?= $this->endSection() ?>
