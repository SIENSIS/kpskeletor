<?php
/**
 * The account link at the end of the public navbar (theme option `showAccountLink`,
 * docs/themes.md "Account link"): "Sign in" for guests, "Administration" for users who can open
 * the back office, "My account" otherwise (`Modules\Core\Frontend\AccountLink`). Inside the
 * collapsed menu at phone width.
 */
if (! theme_option('showAccountLink')) {
    return;
}

$link = (new Modules\Core\Frontend\AccountLink())->for(auth()->loggedIn() ? auth()->user() : null);

if ($link === null) {
    return;
}
?>
<ul class="navbar-nav ms-md-auto mb-2 mb-md-0 site-account-link">
    <li class="nav-item">
        <a class="nav-link d-flex align-items-center gap-1" href="<?= esc($link['url'], 'attr') ?>" data-account-link="<?= esc($link['kind'], 'attr') ?>"><i class="<?= esc($link['icon'], 'attr') ?>" aria-hidden="true"></i><span><?= esc($link['label']) ?></span></a>
    </li>
</ul>
