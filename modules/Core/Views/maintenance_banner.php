<?php
/**
 * Maintenance banner (top bar of the back-office layout, added by the `maintenance` filter): a
 * superadmin keeps using the site while visitors get the maintenance page.
 */
?>
<div class="maintenance-banner alert alert-danger d-flex flex-wrap align-items-center gap-2 py-1 px-2 mb-0 small" role="status" data-maintenance>
    <i class="bi bi-cone-striped" aria-hidden="true"></i>
    <span><?= esc(lang('Core.maintenance.banner')) ?></span>
    <a href="<?= esc(site_url('backend/settings/actions'), 'attr') ?>" class="alert-link"><?= esc(lang('Core.maintenance.manage')) ?></a>
</div>
