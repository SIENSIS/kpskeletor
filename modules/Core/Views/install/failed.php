<?php
/**
 * Web installer: an installation that stopped (session 39): the failing step, the reason and
 * what to do, as the form showed it before.
 *
 * @var string                $base
 * @var string                $step    Label of the failing step
 * @var string                $message
 * @var array<string, string> $errors  Invalid options (field => message)
 */
?>
<?= view('Modules\Core\Views\install\_head', ['base' => $base, 'title' => lang('Install.web.failedTitle')]) ?>
<main class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
            <h1 class="h3 mb-3"><i class="bi bi-x-octagon text-danger me-2" aria-hidden="true"></i><?= esc(lang('Install.web.failedTitle')) ?></h1>

            <div class="alert alert-danger" role="alert">
                <p class="mb-1"><strong><?= esc(lang('Install.web.failedStep', [$step])) ?></strong></p>
                <?php if ($message !== '') : ?>
                    <p class="mb-0 text-break"><?= esc($message) ?></p>
                <?php endif ?>
                <?php if ($errors !== []) : ?>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $field => $error) : ?>
                            <li><?= esc($field) ?>: <?= esc($error) ?></li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>

            <p><?= esc(lang('Install.web.failedWhat')) ?></p>
            <a href="<?= esc(site_url('install'), 'attr') ?>" class="btn btn-primary"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= esc(lang('Install.web.retry')) ?></a>
        </div>
    </div>
</main>
</body>
</html>
