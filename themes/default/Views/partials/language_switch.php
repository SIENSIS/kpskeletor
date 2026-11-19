<?php
/**
 * Language switch of the public site (docs/i18n.md, "Language in the URL", D-37-02): links to the
 * current page in every available language (`/es/…`; Pages give each language's own slugs). Hidden
 * on non-public paths, when the site offers a single language, when the language is not part of
 * public URLs (`Config\LocaleUrls::$enabled`) or when the theme option `languageSwitch` is off.
 *
 * @var string|null $switchClass
 */
use Modules\Core\I18n\Locales;

$links = service('localeurls')->links();

if (count($links) < 2 || ! theme_option('languageSwitch')) {
    return;
}

$names   = Locales::names();
$current = service('request')->getLocale();
?>
<div class="dropdown language-switch <?= esc($switchClass ?? '') ?>">
    <button class="btn btn-link nav-link text-body px-2 d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?= esc(lang('Core.languageSwitch.label')) ?>">
        <i class="bi bi-translate" aria-hidden="true"></i><span class="small text-uppercase"><?= esc($current) ?></span><span class="visually-hidden"> — <?= esc(lang('Core.languageSwitch.current', [$names[$current] ?? $current])) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($links as $locale => $href): ?>
            <li>
                <a class="dropdown-item<?= $locale === $current ? ' active' : '' ?>" href="<?= esc(ui_safe_url($href), 'attr') ?>" hreflang="<?= esc($locale, 'attr') ?>" lang="<?= esc($locale, 'attr') ?>"<?= $locale === $current ? ' aria-current="true"' : '' ?>><?= esc($names[$locale] ?? $locale) ?></a>
            </li>
        <?php endforeach ?>
    </ul>
</div>
