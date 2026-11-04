<?php
/**
 * The page's scripts, external files only (no inline JavaScript): the theme's scripts, the page
 * configuration (a JSON data block, not executable), the import map (every published JS file
 * with its hashed URL) and the core entry module `core/app.js`, all with the CSP nonce.
 */
?>
<?php foreach (theme()->scripts() as $src): ?>
    <script src="<?= esc($src) ?>" defer <?= csp_script_nonce() ?>></script>
<?php endforeach ?>
    <script type="application/json" id="kp-config"><?= page_config_json() ?></script>
    <script type="importmap" <?= csp_script_nonce() ?>><?= import_map()->json() ?></script>
    <script type="module" src="<?= esc(asset('core::js/app.js')) ?>" <?= csp_script_nonce() ?>></script>
