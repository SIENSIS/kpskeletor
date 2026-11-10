<?php
/**
 * Web installer: the last page. When `.env` could not be written, its content is shown once for
 * the operator to copy (it holds the keys and the database password: the page is not cached).
 *
 * @var string                              $base
 * @var Modules\Core\Install\InstallResult  $result
 * @var string                              $envPath
 * @var string                              $loginUrl
 * @var string                              $username
 */
?>
<?= view('Modules\Core\Views\install\_head', ['base' => $base, 'title' => lang('Install.web.doneTitle')]) ?>
<main class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
            <h1 class="h3 mb-3"><i class="bi bi-check-circle text-success me-2" aria-hidden="true"></i><?= esc(lang('Install.web.doneTitle')) ?></h1>

            <?php if ($result->envWritten) : ?>
                <p><?= esc(lang('Install.web.envWritten', [$envPath])) ?></p>
            <?php else : ?>
                <div class="alert alert-warning" role="alert">
                    <strong><?= esc(lang('Install.web.envCopyTitle')) ?></strong>
                    <?= esc(lang('Install.web.envCopy', [$envPath])) ?>
                </div>
                <label for="env" class="form-label fw-semibold">.env</label>
                <textarea id="env" class="form-control font-monospace small mb-3" rows="16" readonly spellcheck="false"><?= esc($result->env) ?></textarea>
            <?php endif ?>

            <?php if ($result->demoError !== '') : ?>
                <div class="alert alert-warning" role="alert"><?= esc(lang('Install.web.demoFailed', [$result->demoError])) ?></div>
            <?php endif ?>

            <?php if ($result->demoLoaded !== []) : ?>
                <p><?= esc(lang('Install.cli.demoLoaded', [implode(', ', $result->demoLoaded)])) ?></p>
            <?php endif ?>

            <?php if ($result->demoSecrets !== []) : ?>
                <p class="mb-1"><?= esc(lang('Core.demo.secrets')) ?></p>
                <table class="table table-sm small mb-3">
                    <tbody>
                    <?php foreach ($result->demoSecrets as $label => $value) : ?>
                        <tr><td><?= esc($label) ?></td><td><code><?= esc($value) ?></code></td></tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>

            <h2 class="h5 mt-4"><?= esc(lang('Install.web.cronTitle')) ?></h2>
            <p class="mb-1"><?= esc(lang('Install.cli.cron')) ?></p>
            <pre class="bg-body border rounded p-2 small text-wrap text-break"><code><?= esc($result->cronLine) ?></code></pre>

            <h2 class="h5 mt-4"><?= esc(lang('Install.web.nextTitle')) ?></h2>
            <ul>
                <li><?= esc(lang('Install.web.nextLogin', [$username])) ?></li>
                <li><?= esc(lang('Install.web.twoFactor')) ?></li>
                <li><?= esc(lang('Install.cli.securityCheck')) ?></li>
            </ul>

            <?php if ($result->envWritten) : ?>
                <a href="<?= esc($loginUrl) ?>" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i><?= esc(lang('Install.web.login')) ?></a>
            <?php endif ?>
        </div>
    </div>
</main>
</body>
</html>
