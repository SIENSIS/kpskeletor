<?php
/**
 * Scripts of the web installer's pages, when the Core's scripts are published: the page
 * configuration, the import map (no theme yet) and `core/app.js`, which mounts the components
 * (`core/install/progress`) and the show/hide button of the password fields. Every page works
 * without them (the installation then runs inside the request).
 *
 * @var bool|null $scripts
 */
?>
<?php if ($scripts ?? false) : ?>
    <script type="application/json" id="kp-config"><?= page_config_json() ?></script>
    <script type="importmap" <?= csp_script_nonce() ?>><?= (new Modules\Core\Frontend\ImportMap(service('assets')))->json() ?></script>
    <script type="module" src="<?= esc(asset('core::js/app.js')) ?>" <?= csp_script_nonce() ?>></script>
<?php endif ?>
