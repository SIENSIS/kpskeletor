<?php
/**
 * Problems of a section that only editors see (the preview, the editor): `SectionType::warnings()`.
 *
 * @var list<array{message: string, link?: array{url: string, label: string}}> $warnings
 */
?>
<?php foreach ($warnings as $warning): ?>
    <div class="alert alert-warning kp-section-warning" role="alert">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc($warning['message']) ?>
        <?php if (isset($warning['link'])): ?>
            <a class="alert-link ms-1" href="<?= esc($warning['link']['url'], 'attr') ?>"><?= esc($warning['link']['label']) ?></a>
        <?php endif ?>
    </div>
<?php endforeach ?>
