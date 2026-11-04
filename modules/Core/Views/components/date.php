<?php
/**
 * @var string|null $iso   ISO 8601 date (null: no date)
 * @var string      $text  formatted for the current locale
 * @var string|null $title full date shown on hover (relative dates)
 */
?>
<?php if (empty($iso)): ?><span class="text-body-tertiary"><?= esc($text) ?></span><?php else: ?><time datetime="<?= esc($iso) ?>"<?= ! empty($title) ? ' title="' . esc($title) . '"' : '' ?>><?= esc($text) ?></time><?php endif ?>
