<?php
/**
 * The public footer (docs/menus.md, "Footer columns"; docs/themes.md). When the `footer` menu has
 * top-level items with children, each of them is a column: its label as the heading (a link when
 * it has an address) and its children as the links; top-level items without children go in a row
 * of links below. The theme options `footerColumns` (columns on wide screens) and `footerContact`
 * (a translated first column with the address and contact details, lines separated by `|`) shape
 * it. A footer menu without children keeps the single line of links.
 */
$items   = menu('footer');
$columns = array_values(array_filter($items, static fn ($item): bool => $item->hasChildren()));
$loose   = array_values(array_filter($items, static fn ($item): bool => ! $item->hasChildren() && $item->url !== null));
$count   = (string) theme_option('footerColumns');
$count   = in_array($count, ['2', '3', '4', '5', '6'], true) ? $count : '4';
$contact = theme_option('footerContact');
$contact = is_array($contact) ? \Modules\Core\Settings\TranslatedText::resolve(array_filter($contact, 'is_string'), [], service('request')->getLocale()) : '';
$lines   = array_values(array_filter(array_map('trim', explode('|', $contact)), static fn (string $line): bool => $line !== ''));
$newTab  = ' target="_blank" rel="noopener noreferrer"';
$notice  = (string) theme_option('footerText') !== '' ? esc((string) theme_option('footerText')) : '&copy; ' . date('Y') . ' ' . esc(site_name());
?>
<footer class="site-footer border-top py-4 mt-auto">
    <?php if ($columns === [] && $lines === []): ?>
        <div class="container d-flex flex-wrap justify-content-between gap-3 small text-body-secondary">
            <div data-menu-location="footer"><?= theme_partial('public_menu', ['location' => 'footer']) ?><?= theme_region('public', 'footer') ?></div>
            <div><?= $notice ?></div>
        </div>
    <?php else: ?>
        <div class="container small">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-<?= esc($count, 'attr') ?> g-4 site-footer-columns" data-menu-location="footer">
                <?php if ($lines !== []): ?>
                    <div class="col site-footer-column site-footer-contact">
                        <h2 class="h6 site-footer-heading"><?= esc(lang('ThemeDefault.footer.contact')) ?></h2>
                        <address class="mb-0 text-body-secondary">
                            <?php foreach ($lines as $i => $line): ?><?= $i > 0 ? '<br>' : '' ?><?= esc($line) ?><?php endforeach ?>
                        </address>
                    </div>
                <?php endif ?>
                <?php foreach ($columns as $column): ?>
                    <nav class="col site-footer-column <?= esc($column->cssClass, 'attr') ?>" aria-label="<?= esc($column->label, 'attr') ?>">
                        <h2 class="h6 site-footer-heading">
                            <?php if ($column->url !== null): ?>
                                <a class="link-body-emphasis text-decoration-none" href="<?= esc($column->url, 'attr') ?>"<?= $column->newTab() ? $newTab : '' ?>><?= esc($column->label) ?></a>
                            <?php else: ?>
                                <?= esc($column->label) ?>
                            <?php endif ?>
                        </h2>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($column->children as $link): ?>
                                <?php if ($link->url !== null): ?>
                                    <li class="mb-1 <?= esc($link->cssClass, 'attr') ?>"><a class="link-secondary<?= $link->active ? ' fw-semibold' : '' ?>" href="<?= esc($link->url, 'attr') ?>"<?= $link->active ? ' aria-current="page"' : '' ?><?= $link->newTab() ? $newTab : '' ?>><?php if ($link->icon !== ''): ?><i class="<?= esc($link->icon, 'attr') ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($link->label) ?></a></li>
                                <?php endif ?>
                            <?php endforeach ?>
                        </ul>
                    </nav>
                <?php endforeach ?>
            </div>
            <div class="d-flex flex-wrap justify-content-between gap-3 border-top mt-4 pt-3 text-body-secondary">
                <?php if ($loose !== []): ?>
                    <ul class="list-inline mb-0 public-menu public-menu-footer">
                        <?php foreach ($loose as $link): ?>
                            <li class="list-inline-item <?= esc($link->cssClass, 'attr') ?>"><a class="link-secondary<?= $link->active ? ' fw-semibold' : '' ?>" href="<?= esc((string) $link->url, 'attr') ?>"<?= $link->active ? ' aria-current="page"' : '' ?><?= $link->newTab() ? $newTab : '' ?>><?php if ($link->icon !== ''): ?><i class="<?= esc($link->icon, 'attr') ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($link->label) ?></a></li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
                <?= theme_region('public', 'footer') ?>
                <div class="ms-auto"><?= $notice ?></div>
            </div>
        </div>
    <?php endif ?>
</footer>
