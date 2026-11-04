<?php
/**
 * @var string      $name
 * @var string      $url
 * @var string      $icon Bootstrap Icons class (chosen by ui_file_link())
 * @var string|null $size human-readable size
 */
?>
<a href="<?= esc(ui_safe_url($url)) ?>" class="file-link"><i class="bi <?= esc($icon) ?> me-1" aria-hidden="true"></i><?= esc($name) ?></a><?php if (! empty($size)): ?> <small class="text-body-secondary">(<?= esc($size) ?>)</small><?php endif ?>
