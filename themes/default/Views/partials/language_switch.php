<?php
/**
 * Language switch of the public site (docs/i18n.md, "Choosing the language"): one small POST
 * form per available locale (works without JavaScript; CSRF), handled by the Core's
 * `language-switch` route, which stores the choice and comes back to this page. Hidden when the
 * site offers a single language or the theme option `languageSwitch` is off.
 *
 * @var string|null $switchClass
 */
use Modules\Core\I18n\Locales;

$locales = Locales::names();

if (count($locales) < 2 || ! theme_option('languageSwitch')) {
    return;
}

$current = service('request')->getLocale();
$query   = (string) service('request')->getUri()->getQuery();
$return  = '/' . ltrim(uri_string(), '/') . ($query !== '' ? '?' . $query : '');
?>
<div class="dropdown language-switch <?= esc($switchClass ?? '') ?>">
    <button class="btn btn-link nav-link text-body px-2 d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= esc(lang('Core.languageSwitch.current', [$locales[$current] ?? $current])) ?>" title="<?= esc(lang('Core.languageSwitch.label')) ?>">
        <i class="bi bi-translate" aria-hidden="true"></i><span class="small text-uppercase"><?= esc($current) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($locales as $locale => $name): ?>
            <li>
                <form method="post" action="<?= esc(site_url('language'), 'attr') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="locale" value="<?= esc($locale, 'attr') ?>">
                    <input type="hidden" name="return" value="<?= esc($return, 'attr') ?>">
                    <button type="submit" class="dropdown-item<?= $locale === $current ? ' active' : '' ?>" lang="<?= esc($locale, 'attr') ?>"<?= $locale === $current ? ' aria-current="true"' : '' ?>><?= esc($name) ?></button>
                </form>
            </li>
        <?php endforeach ?>
    </ul>
</div>
