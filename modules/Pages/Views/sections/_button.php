<?php
/**
 * A button of a section: a resolved link (`LinkField`) with a Bootstrap button style; links to
 * other sites open in the same tab but say so to screen readers.
 *
 * @var array{href: string, label: string, external: bool}|null $link
 * @var string                                                 $buttonStyle e.g. `primary`, `outline-light`
 * @var string|null                                            $buttonClass extra classes
 */
$allowed = array_keys(\Modules\Pages\Sections\PagesSectionType::BUTTON_STYLES);
$variant = in_array($buttonStyle ?? '', $allowed, true) ? $buttonStyle : 'primary';
?>
<?php if (is_array($link ?? null) && $link['label'] !== ''): ?>
    <a class="btn btn-<?= esc($variant, 'attr') ?> <?= esc($buttonClass ?? '', 'attr') ?>" href="<?= esc($link['href'], 'attr') ?>"<?= $link['external'] ? ' rel="noopener"' : '' ?>><?= esc($link['label']) ?><?php if ($link['external']): ?><i class="bi bi-box-arrow-up-right ms-1 small" aria-hidden="true"></i><span class="visually-hidden"> <?= esc(lang('Pages.sectionTypes.externalLink')) ?></span><?php endif ?></a>
<?php endif ?>
