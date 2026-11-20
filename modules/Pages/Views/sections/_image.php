<?php
/**
 * An image of a section (`ImageField` resolved): its size, alternative text (empty when decorative)
 * and lazy loading unless it is in the first section.
 *
 * @var array{url: string, width: int|null, height: int|null, alt: string}|null $image
 * @var string|null                                                          $imageClass
 * @var bool|null                                                            $eager
 */
?>
<?php if (is_array($image ?? null)): ?>
    <img class="<?= esc($imageClass ?? 'img-fluid', 'attr') ?>" src="<?= esc($image['url'], 'attr') ?>" alt="<?= esc($image['alt'], 'attr') ?>"<?php if ($image['width'] !== null && $image['height'] !== null): ?> width="<?= (int) $image['width'] ?>" height="<?= (int) $image['height'] ?>"<?php endif ?> loading="<?= ! empty($eager) ? 'eager' : 'lazy' ?>" decoding="async">
<?php endif ?>
