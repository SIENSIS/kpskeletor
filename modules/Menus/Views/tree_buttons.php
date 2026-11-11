<?php
/**
 * The move buttons of a tree editor row (up, down, indent, outdent). They submit the form without
 * JavaScript (one move per request); the tree editor handles them in the page instead.
 *
 * @var string $moveKey the value posted as `move` before `:<direction>`
 * @var string $label   the row's label (for the buttons' accessible names)
 */
$moves = [
    'up'      => ['bi bi-arrow-up', 'Menus.tree.up'],
    'down'    => ['bi bi-arrow-down', 'Menus.tree.down'],
    'outdent' => ['bi bi-arrow-bar-left', 'Menus.tree.outdent'],
    'indent'  => ['bi bi-arrow-bar-right', 'Menus.tree.indent'],
];
?>
<div class="btn-group btn-group-sm menu-tree-moves" role="group" aria-label="<?= esc(lang('Menus.tree.moves', [$label]), 'attr') ?>">
    <?php foreach ($moves as $move => [$icon, $text]): ?>
        <button type="submit" class="btn btn-outline-secondary" name="move" value="<?= esc($moveKey . ':' . $move, 'attr') ?>" data-move="<?= esc($move, 'attr') ?>" title="<?= esc(lang($text), 'attr') ?>" aria-label="<?= esc(lang($text) . ': ' . $label, 'attr') ?>"><i class="<?= esc($icon, 'attr') ?>" aria-hidden="true"></i></button>
    <?php endforeach ?>
</div>
