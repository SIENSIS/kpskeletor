<?php
/**
 * Sample sidebar region of the public layout in the theme preview.
 */
?>
<?= theme_component('card', [
    'title' => lang('Core.preview.sidebarTitle'),
    'icon'  => 'bi-info-circle',
    'body'  => '<p class="mb-0">' . esc(lang('Core.preview.sidebarText')) . '</p>',
]) ?>
