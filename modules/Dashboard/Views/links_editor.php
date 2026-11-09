<?php
/**
 * Editor of a `links` option (Dashboard › Group layouts, D-16-06): one ordered list whose rows are
 * a back-office menu entry or a free link (label + https:// URL or internal path). Without
 * JavaScript the order is the "position" field and three empty rows allow adding links; the
 * component `dashboard/links-editor` adds drag and drop, up/down/remove buttons and "add".
 *
 * @var string                $name    Field name prefix (`widgets[<id>][options][<key>]`)
 * @var string                $id      Id prefix
 * @var array<string, string> $choices Menu entry key => label
 * @var list<mixed>           $items   Stored items ({menu} or {label, url}), or the posted rows when `old`
 * @var bool                  $old     Whether `items` are the rows posted with a validation error
 */
$rows = [];

foreach ($items as $item) {
    if (! is_array($item)) {
        continue;
    }

    if ($old) {
        $rows[] = ['ref' => (string) ($item['ref'] ?? ''), 'label' => (string) ($item['label'] ?? ''), 'url' => (string) ($item['url'] ?? '')];
    } elseif (isset($item['menu'])) {
        $rows[] = ['ref' => 'menu:' . $item['menu'], 'label' => '', 'url' => ''];
    } else {
        $rows[] = ['ref' => 'url', 'label' => (string) ($item['label'] ?? ''), 'url' => (string) ($item['url'] ?? '')];
    }
}

for ($i = 0; $i < 3; $i++) {
    $rows[] = ['ref' => '', 'label' => '', 'url' => ''];
}
?>
<div data-component="dashboard/links-editor" data-max="<?= \Modules\Core\Widgets\WidgetOptions::MAX_LINKS ?>" data-options='<?= esc(json_encode([
    'up' => lang('Dashboard.links.up'), 'down' => lang('Dashboard.links.down'), 'remove' => lang('Dashboard.links.remove'), 'move' => lang('Dashboard.links.move'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), 'attr') ?>'>
    <ol class="list-group mb-2 dashboard-links" data-links-list id="<?= esc($id, 'attr') ?>">
        <?php foreach ($rows as $i => $row): ?>
            <?php $field = $name . '[' . $i . ']'; $rid = $id . '-' . $i; ?>
            <li class="list-group-item" data-links-row>
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div class="d-flex gap-1 dashboard-links-tools" data-links-tools></div>
                    <div class="flex-grow-1 dashboard-links-ref">
                        <label class="form-label small mb-1" for="<?= esc($rid, 'attr') ?>-ref"><?= esc(lang('Dashboard.links.item')) ?></label>
                        <select class="form-select form-select-sm" id="<?= esc($rid, 'attr') ?>-ref" name="<?= esc($field, 'attr') ?>[ref]" data-links-ref>
                            <option value=""<?= $row['ref'] === '' ? ' selected' : '' ?>><?= esc(lang('Dashboard.links.none')) ?></option>
                            <option value="url"<?= $row['ref'] === 'url' ? ' selected' : '' ?>><?= esc(lang('Dashboard.links.free')) ?></option>
                            <optgroup label="<?= esc(lang('Dashboard.links.menu'), 'attr') ?>">
                                <?php foreach ($choices as $key => $label): ?>
                                    <option value="<?= esc('menu:' . $key, 'attr') ?>"<?= $row['ref'] === 'menu:' . $key ? ' selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="dashboard-links-position" data-links-position>
                        <label class="form-label small mb-1" for="<?= esc($rid, 'attr') ?>-position"><?= esc(lang('Dashboard.position')) ?></label>
                        <input type="number" class="form-control form-control-sm" id="<?= esc($rid, 'attr') ?>-position" name="<?= esc($field, 'attr') ?>[position]" value="<?= ($i + 1) * 10 ?>" min="0" max="9999" step="1">
                    </div>
                </div>
                <div class="row g-2 mt-0">
                    <div class="col-12 col-sm-5" data-links-free>
                        <label class="form-label small mb-1" for="<?= esc($rid, 'attr') ?>-label"><?= esc(lang('Dashboard.links.label')) ?></label>
                        <input type="text" class="form-control form-control-sm" id="<?= esc($rid, 'attr') ?>-label" name="<?= esc($field, 'attr') ?>[label]" value="<?= esc($row['label'], 'attr') ?>" maxlength="100">
                    </div>
                    <div class="col-12 col-sm-7" data-links-free>
                        <label class="form-label small mb-1" for="<?= esc($rid, 'attr') ?>-url"><?= esc(lang('Dashboard.links.url')) ?></label>
                        <input type="text" inputmode="url" class="form-control form-control-sm" id="<?= esc($rid, 'attr') ?>-url" name="<?= esc($field, 'attr') ?>[url]" value="<?= esc($row['url'], 'attr') ?>" maxlength="500" placeholder="https://… · /backend/…" autocomplete="off" spellcheck="false">
                    </div>
                </div>
            </li>
        <?php endforeach ?>
    </ol>
    <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-links-add><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= esc(lang('Dashboard.links.add')) ?></button>
    <div class="form-text"><?= esc(lang('Dashboard.links.help', [\Modules\Core\Widgets\WidgetOptions::MAX_LINKS])) ?></div>
</div>
