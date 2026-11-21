<?php
/**
 * Section `events.list`: the next public events as a list or as cards (a date badge, the title,
 * when and where), each linking to its public page. `when` is the HTML of `ui_date()` (escaped
 * there). Override: `themes/<slug>/Views/overrides/Events/sections/list.php`.
 *
 * @var array<string, mixed>                                                                                      $values heading, intro, categories, limit, days, columns, more
 * @var string                                                                                                    $style  list | cards
 * @var list<array{title: string, when: string, day: string, month: string, date: string, location: string, url: string}> $items
 */
$heading  = (string) $values['heading'];
$titleTag = $heading !== '' ? 'h3' : 'h2';
$columns  = max(2, min(4, (int) $values['columns']));
?>
<?= theme_view('Modules\Core\Views\sections\heading', ['heading' => $heading, 'intro' => (string) $values['intro'], 'centered' => false]) ?>
<?php if ($items === []): ?>
    <p class="text-body-secondary mb-0"><?= esc(lang('Events.sections.list.empty')) ?></p>
<?php elseif ($style === 'cards'): ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-<?= $columns ?> g-4">
        <?php foreach ($items as $item): ?>
            <div class="col">
                <article class="card kp-card kp-event-card h-100 shadow-sm">
                    <div class="card-body d-flex gap-3">
                        <time class="kp-event-date text-center flex-shrink-0" datetime="<?= esc($item['date'], 'attr') ?>">
                            <span class="kp-event-day d-block"><?= esc($item['day']) ?></span>
                            <span class="kp-event-month d-block text-uppercase small"><?= esc($item['month']) ?></span>
                        </time>
                        <div class="min-w-0">
                            <<?= $titleTag ?> class="h6 card-title mb-1"><a class="stretched-link text-break" href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['title']) ?></a></<?= $titleTag ?>>
                            <div class="small text-body-secondary"><?= $item['when'] ?></div>
                            <?php if ($item['location'] !== ''): ?>
                                <div class="small text-body-secondary"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= esc($item['location']) ?></div>
                            <?php endif ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach ?>
    </div>
<?php else: ?>
    <ul class="list-unstyled kp-event-rows mb-0">
        <?php foreach ($items as $item): ?>
            <li class="d-flex gap-3 py-3 border-bottom">
                <time class="kp-event-date text-center flex-shrink-0" datetime="<?= esc($item['date'], 'attr') ?>">
                    <span class="kp-event-day d-block"><?= esc($item['day']) ?></span>
                    <span class="kp-event-month d-block text-uppercase small"><?= esc($item['month']) ?></span>
                </time>
                <div class="min-w-0">
                    <<?= $titleTag ?> class="h6 mb-1"><a class="text-break" href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['title']) ?></a></<?= $titleTag ?>>
                    <div class="small text-body-secondary"><?= $item['when'] ?><?php if ($item['location'] !== ''): ?> · <i class="bi bi-geo-alt" aria-hidden="true"></i> <?= esc($item['location']) ?><?php endif ?></div>
                </div>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
<?php if (is_array($values['more'])): ?>
    <p class="mt-4 mb-0"><a class="btn btn-outline-primary" href="<?= esc((string) $values['more']['href'], 'attr') ?>"><?= esc((string) $values['more']['label'] !== '' ? (string) $values['more']['label'] : lang('Events.sections.list.moreDefault')) ?></a></p>
<?php endif ?>
