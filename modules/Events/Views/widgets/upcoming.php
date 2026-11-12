<?php
/**
 * Widget "Upcoming events" (`Modules\Events\Widgets\UpcomingEvents`). `when` is the HTML of
 * `ui_date()` (escaped there).
 *
 * @var list<array{title: string, when: string, location: string, url: string}> $items
 * @var string                                                                   $url
 */
?>
<ul class="list-group list-group-flush mb-2">
    <?php foreach ($items as $item): ?>
        <li class="list-group-item px-0">
            <a class="fw-semibold text-break" href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['title']) ?></a>
            <div class="small text-body-secondary">
                <?= $item['when'] ?><?php if ($item['location'] !== ''): ?> · <i class="bi bi-geo-alt" aria-hidden="true"></i> <?= esc($item['location']) ?><?php endif ?>
            </div>
        </li>
    <?php endforeach ?>
</ul>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Events.widgets.upcoming.all')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
