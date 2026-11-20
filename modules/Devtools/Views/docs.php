<?php
/**
 * `backend/devtools/docs/<name>`: a page of `docs/` as escaped plain text with its headings
 * (ids for the recipes' anchors).
 *
 * @var string                                                              $layout
 * @var list<array{level: int, title: string, id: string, text: string}>   $sections
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Devtools\Views\_banner') ?>
<article class="devtools-doc">
    <?php foreach ($sections as $section): ?>
        <?php if ($section['level'] > 0): ?>
            <?php $tag = 'h' . min(6, $section['level'] + 1); ?>
            <<?= $tag ?> class="mt-4" id="<?= esc($section['id'], 'attr') ?>"><?= esc($section['title']) ?></<?= $tag ?>>
        <?php endif ?>
        <?php if ($section['text'] !== ''): ?>
            <pre class="small mb-2"><?= esc($section['text']) ?></pre>
        <?php endif ?>
    <?php endforeach ?>
</article>
<?= $this->endSection() ?>
