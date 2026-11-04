<?php
/**
 * @var string      $name
 * @var string|null $url
 * @var string      $avatar HTML of ui_avatar()
 */
?>
<span class="user-link d-inline-flex align-items-center gap-2"><?= $avatar ?><?php if (! empty($url)): ?><a href="<?= esc(ui_safe_url($url)) ?>"><?= esc($name) ?></a><?php else: ?><span><?= esc($name) ?></span><?php endif ?></span>
