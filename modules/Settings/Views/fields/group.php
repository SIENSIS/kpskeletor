<?php
/**
 * A group of generated settings fields under an optional heading.
 *
 * @var string $heading already translated ('' for none)
 * @var string $body    HTML of the fields (already escaped)
 */
?>
<fieldset class="settings-group mb-4">
    <?php if ($heading !== ''): ?>
        <legend class="h6 border-bottom pb-2 mb-3"><?= esc($heading) ?></legend>
    <?php endif ?>
    <div class="row g-3"><?= $body ?></div>
</fieldset>
