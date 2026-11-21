<?php
/**
 * Section `pages.embed`: an allowed page of another site in a sandboxed iframe (D-46-01). With
 * "click to load" the iframe is built by `pages/embed` only after the visitor clicks (no request
 * to the other site before); without JavaScript, a link opens it.
 * Override: `themes/<slug>/Views/overrides/Pages/sections/embed.php`.
 *
 * @var array<string, mixed>                 $values heading, intro, url, title, ratio, click_to_load, fullscreen
 * @var \Modules\Core\Sections\RenderContext $context
 */
$url   = trim((string) $values['url']);
$title = (string) $values['title'];
$ratio = in_array($values['ratio'], \Modules\Pages\Sections\EmbedSection::RATIOS, true) ? (string) $values['ratio'] : '16x9';
$host  = (string) \Modules\Pages\Embeds\EmbedHosts::host($url);
$allow = $values['fullscreen'] === true ? 'fullscreen' : '';
?>
<?= theme_view('Modules\Core\Views\sections\heading', ['heading' => (string) $values['heading'], 'intro' => (string) $values['intro'], 'centered' => false]) ?>
<div class="kp-embed ratio ratio-<?= esc($ratio, 'attr') ?> rounded-3 overflow-hidden"
     data-component="pages/embed" data-src="<?= esc($url, 'attr') ?>" data-title="<?= esc($title, 'attr') ?>"
     data-sandbox="<?= esc(\Modules\Pages\Sections\EmbedSection::SANDBOX, 'attr') ?>" data-allow="<?= esc($allow, 'attr') ?>">
    <?php if ($values['click_to_load'] === true): ?>
        <div class="kp-embed-placeholder d-flex flex-column align-items-center justify-content-center text-center p-3" data-embed-placeholder>
            <i class="bi bi-play-circle display-4 mb-2" aria-hidden="true"></i>
            <p class="fw-semibold mb-1 text-break"><?= esc($title) ?></p>
            <p class="small mb-3"><?= esc(lang('Pages.embed.consent', [$host])) ?></p>
            <button type="button" class="btn btn-primary" data-embed-load><?= esc(lang('Pages.embed.load')) ?></button>
            <a class="small mt-2 link-light" href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(lang('Pages.embed.openThere', [$host])) ?></a>
        </div>
    <?php else: ?>
        <iframe src="<?= esc($url, 'attr') ?>" title="<?= esc($title, 'attr') ?>" loading="lazy" sandbox="<?= esc(\Modules\Pages\Sections\EmbedSection::SANDBOX, 'attr') ?>" referrerpolicy="strict-origin-when-cross-origin"<?= $allow !== '' ? ' allow="' . esc($allow, 'attr') . '" allowfullscreen' : '' ?>></iframe>
    <?php endif ?>
</div>
