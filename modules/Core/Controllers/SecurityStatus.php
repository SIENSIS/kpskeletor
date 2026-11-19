<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Config\App;
use Modules\Core\Security\Check\EffectivePolicy;
use Modules\Core\Security\Check\Probe\SiteProbe;
use Modules\Core\Security\Check\SecurityChecker;
use Modules\Core\Security\Check\SecurityStatus as Status;

/**
 * The Security status page (`backend/security`, `security.check`): the results of the last run
 * of the checks (the daily task, `security:check` or "check again"; run on the first visit),
 * grouped, with what each means and how to fix it, the last external probe, and the effective
 * Content-Security-Policy and security headers. The probe is a deliberate action behind
 * re-authentication: requests from the server to the site itself (never to third parties).
 */
final class SecurityStatus extends BackendController
{
    public function index(): string
    {
        $status = new Status();
        $report = $status->read();

        if ($report === null) {
            $report = (new SecurityChecker())->run();
            $status->store($report);
        }

        $policy = new EffectivePolicy();

        $this->breadcrumb(lang('SecurityCheck.title'));

        return $this->render('Modules\Core\Views\security\index', [
            'title'   => lang('SecurityCheck.title'),
            'icon'    => 'bi bi-shield-check',
            'report'  => $report,
            'probe'   => $status->read('probe'),
            'csp'     => $policy->csp(),
            'headers' => $policy->headers(),
        ]);
    }

    public function refresh(): RedirectResponse
    {
        $report = (new SecurityChecker())->run();
        (new Status())->store($report);
        activity_log('Core', 'security_check', ['resource' => 'security', 'changes' => $report->counts()]);

        return redirect()->to(backend_url('security'))->with('success', lang('SecurityCheck.refreshed'));
    }

    public function probeForm(): string
    {
        $this->breadcrumb(lang('SecurityCheck.title'), backend_url('security'));
        $this->breadcrumb(lang('SecurityCheck.probe.run'));

        return $this->render('Modules\Core\Views\security\probe', [
            'title'  => lang('SecurityCheck.probe.run'),
            'icon'   => 'bi bi-broadcast',
            'action' => backend_url('security/probe'),
            'cancel' => backend_url('security'),
            'url'    => rtrim(config(App::class)->baseURL, '/') . '/',
        ]);
    }

    public function probe(): RedirectResponse
    {
        if (! is_cli()) {
            @set_time_limit(180); // a dozen requests with their timeouts (never the CLI test runner)
        }

        // Always the configured base URL: a request value never chooses where the server sends requests.
        $url    = rtrim(config(App::class)->baseURL, '/') . '/';
        $report = (new SecurityChecker([]))->run('probe', new SiteProbe($url));
        (new Status())->storeProbe($report);
        security_log('Core', 'security_probe', ['resource' => 'security', 'summary' => $url, 'changes' => $report->counts()]);

        return redirect()->to(backend_url('security'))->with($report->hasErrors() ? 'error' : 'success', lang('SecurityCheck.probe.done', [$report->counts()['error'], $report->counts()['warning']]));
    }
}
