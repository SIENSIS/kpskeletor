<?php
/**
 * Tabs of the Modules screen (separate pages): modules, packages, themes.
 *
 * @var string $tab
 */
$tabs = [
    'modules'  => ['url' => backend_url('modules'), 'icon' => 'bi bi-boxes'],
    'packages' => ['url' => backend_url('modules/packages'), 'icon' => 'bi bi-box-seam'],
    'themes'   => ['url' => backend_url('modules/themes'), 'icon' => 'bi bi-palette'],
];
?>
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-x-auto" data-modules-tabs>
    <?php foreach ($tabs as $key => $item): ?>
        <li class="nav-item">
            <a class="nav-link text-nowrap<?= $tab === $key ? ' active' : '' ?>" href="<?= esc($item['url'], 'attr') ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
                <i class="<?= esc($item['icon'], 'attr') ?> me-1" aria-hidden="true"></i><?= esc(lang('Core.modules.tabs.' . $key)) ?>
            </a>
        </li>
    <?php endforeach ?>
</ul>
