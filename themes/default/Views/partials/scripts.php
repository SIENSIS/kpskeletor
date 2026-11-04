<?php
/**
 * The theme's scripts (external files only: no inline JavaScript).
 */
?>
<?php foreach (theme()->scripts() as $src): ?>
    <script src="<?= esc($src) ?>" defer></script>
<?php endforeach ?>
