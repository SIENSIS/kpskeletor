<?php
/**
 * Widget "New contact messages" (`Modules\Contact\Widgets\NewMessages`).
 *
 * @var list<array<string, mixed>> $items
 * @var int                        $total
 * @var string                     $url
 */
?>
<ul class="list-group list-group-flush mb-2">
    <?php foreach ($items as $item): ?>
        <li class="list-group-item px-0">
            <a class="fw-semibold text-break" href="<?= esc(backend_url('contact/messages/show/' . \Kpcrudy\Crud::maskId((int) $item['id'], 'contact_messages')), 'attr') ?>"><?= esc((string) $item['subject']) ?></a>
            <div class="small text-body-secondary"><?= esc((string) $item['name']) ?> · <?= esc((string) $item['recipient_name']) ?> · <?= ui_date((string) $item['created_at']) ?></div>
        </li>
    <?php endforeach ?>
</ul>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Contact.widgets.new.all', [$total])) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
