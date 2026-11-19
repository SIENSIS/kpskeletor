<?php
/**
 * Web installer: the progress of a running installation (session 39). With the Core's scripts
 * (`core/install/progress.js`) it polls `progress/install/<job>`, moves the bar, adds the log
 * lines and opens the result page at the end. Without them (the result page while the job is
 * still running) the page reloads itself every few seconds.
 *
 * @var string               $base
 * @var bool                 $scripts
 * @var string               $progress  URL of the JSON progress
 * @var string               $resultUrl URL of the result page
 * @var array<string, mixed> $job
 */

use Modules\Core\Controllers\WebInstaller;

$percent = max(0, min(100, (int) ($job['percent'] ?? 0)));
$lines   = [];

foreach (is_array($job['log'] ?? null) ? $job['log'] : [] as $line) {
    if (is_array($line) && is_string($line['key'] ?? null) && preg_match('/\AInstall\.(?:step|progress)\.[A-Za-z]+\z/', $line['key']) === 1) {
        $lines[] = lang($line['key'], array_filter(is_array($line['params'] ?? null) ? $line['params'] : [], 'is_scalar'));
    }
}
?>
<?= view('Modules\Core\Views\install\_head', ['base' => $base, 'title' => lang('Install.web.progressTitle'), 'scripts' => $scripts, 'refresh' => $scripts ? null : 3]) ?>
<main class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8"
             data-component="core/install/progress"
             data-url="<?= esc($progress, 'attr') ?>"
             data-next="<?= esc($resultUrl, 'attr') ?>"
             data-lost="<?= esc(lang('Install.web.progressLost'), 'attr') ?>">
            <h1 class="h3 mb-2"><span class="spinner-border spinner-border-sm text-primary me-2 align-middle" aria-hidden="true" data-progress-spinner></span><?= esc(lang('Install.web.progressTitle')) ?></h1>
            <p class="text-body-secondary"><?= esc(lang('Install.web.progressIntro')) ?></p>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
                        <span class="fw-semibold text-break" id="install-step" data-progress-label><?= esc(WebInstaller::stepLabel($job)) ?></span>
                        <span class="text-body-secondary small text-nowrap" data-progress-percent><?= esc(lang('Install.web.percent', [$percent])) ?></span>
                    </div>
                    <div class="progress" role="progressbar" aria-labelledby="install-step" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>" data-progress>
                        <div class="progress-bar progress-bar-striped progress-bar-animated" data-progress-bar data-percent="<?= $percent ?>"></div>
                    </div>
                    <div class="alert alert-danger mt-3 mb-0 d-none" role="alert" data-progress-error></div>
                </div>
            </div>

            <h2 class="h6 text-body-secondary"><?= esc(lang('Install.web.progressLog')) ?></h2>
            <ol class="list-group list-group-numbered small mb-3" aria-live="polite" aria-relevant="additions" data-progress-log>
                <?php foreach ($lines as $line) : ?>
                    <li class="list-group-item py-1"><?= esc($line) ?></li>
                <?php endforeach ?>
            </ol>

            <p class="small text-body-secondary mb-0">
                <?= esc(lang('Install.web.progressFallback')) ?>
                <a href="<?= esc($resultUrl, 'attr') ?>"><?= esc(lang('Install.web.resultLink')) ?></a>
            </p>
        </div>
    </div>
</main>
<?= view('Modules\Core\Views\install\_scripts', ['scripts' => $scripts]) ?>
</body>
</html>
