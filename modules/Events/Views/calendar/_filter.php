<?php
/**
 * Category filter and calendar component, shared by the back office and the public calendar.
 *
 * @var list<array{id: int, slug: string, name: string, color: string|null}> $categories
 * @var int                                                                   $selected
 * @var array<string, mixed>                                                  $options
 */
?>
<?php if ($categories !== []): ?>
    <div class="row g-2 align-items-end mb-3 events-filters">
        <div class="col-12 col-sm-6 col-lg-4">
            <label class="form-label small mb-1" for="events-category"><?= esc(lang('Events.fields.category_id')) ?></label>
            <select class="form-select form-select-sm" id="events-category">
                <option value=""><?= esc(lang('Events.allCategories')) ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"<?= $selected === $category['id'] ? ' selected' : '' ?>><?= esc($category['name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
<?php endif ?>
<div class="card shadow-sm">
    <div class="card-body p-2 p-sm-3" data-component="events/calendar" data-options="<?= esc((string) json_encode($options, JSON_UNESCAPED_SLASHES), 'attr') ?>">
        <p class="alert alert-warning visually-hidden mb-2" role="alert" data-calendar-status></p>
        <noscript><p class="alert alert-info mb-0"><?= esc(lang('Events.noScript')) ?></p></noscript>
    </div>
</div>
