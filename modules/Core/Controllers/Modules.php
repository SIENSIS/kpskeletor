<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Demo\DemoDataException;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Modules\ModuleOverview;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\ModuleToggleException;
use Modules\Core\Modules\ModuleToggler;
use Modules\Core\Modules\SyncFailedException;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\PackageInstaller;
use Modules\Core\Packages\PackageRefused;
use Modules\Core\Packages\StagedPackages;
use Throwable;

/**
 * The Modules screen (`backend/modules`, docs/installing-modules.md):
 *
 * - list (`modules.list`): every module with versions, status, dependencies and dependants;
 * - enable / disable (`modules.toggle`), offering to enable disabled dependencies too;
 * - demo data load / reload / remove (`modules.demo`);
 * - sync (`modules.sync`): `modules:sync` from the web (it stops when the web server may not
 *   write the generated namespaces file: then the console must run it);
 * - packages (`modules.upload` + re-authentication): upload a ZIP, approve or discard it; the
 *   install itself runs from the console (`php spark modules:install --pending`);
 * - uninstall (`modules.uninstall` + re-authentication): creates a pending uninstall.
 *
 * Nothing here writes into `modules/` or `themes/`.
 */
final class Modules extends BackendController
{
    public function index(): string
    {
        $this->breadcrumb(lang('Core.modules.title'));

        return $this->render('Modules\Core\Views\modules\index', [
            'title'   => lang('Core.modules.title'),
            'icon'    => 'bi bi-boxes',
            'tab'     => 'modules',
            'rows'    => (new ModuleOverview())->rows(),
            'can'     => $this->permissions(),
            'confirm' => session()->getFlashdata('enableDependencies'),
            'secrets' => session()->getFlashdata('demoSecrets'),
        ]);
    }

    public function enable(string $name): RedirectResponse
    {
        $withDependencies = $this->request->getPost('dependencies') === '1';

        try {
            $enabled = (new ModuleToggler())->enable($name, $withDependencies);
        } catch (ModuleToggleException $e) {
            if ($e->missingDependencies) {
                session()->setFlashdata('enableDependencies', ['module' => $name, 'dependencies' => $e->modules]);

                return $this->back();
            }

            return $this->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->back()->with('error', lang('Core.modules.enableFailed', [$name, $e->getMessage()]));
        }

        return $this->back()->with('success', $enabled === [] ? lang('Core.toggle.alreadyEnabled', [$name]) : lang('Core.toggle.enabled', [implode(', ', $enabled)]));
    }

    public function disable(string $name): RedirectResponse
    {
        try {
            $changed = (new ModuleToggler())->disable($name);
        } catch (ModuleToggleException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        return $this->back()->with('success', lang($changed ? 'Core.toggle.disabled' : 'Core.toggle.alreadyDisabled', [$name]));
    }

    public function demo(string $name): RedirectResponse
    {
        $action  = (string) $this->request->getPost('action');
        $manager = new DemoDataManager();

        if (! in_array($action, ['load', 'reload', 'remove'], true)) {
            return $this->back();
        }

        try {
            $manager->assertAllowed();

            if ($action === 'load') {
                $message = lang($manager->load($name) ? 'Core.demo.loaded' : 'Core.demo.alreadyLoaded', [$name]);
            } else {
                $removal = $action === 'reload' ? $manager->reload($name) : $manager->remove($name);
                $message = lang('Core.demo.removed', [$name, $removal->rows, $removal->files]);

                if ($removal->kept !== []) {
                    $message .= ' ' . lang('Core.demo.kept', [implode(', ', $removal->kept)]);
                }
            }
        } catch (DemoDataException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        // Demo users' random passwords: shown once on the next page (D-19-01), never stored.
        $secrets = $manager->takeSecrets();

        if ($secrets !== []) {
            session()->setFlashdata('demoSecrets', $secrets);
        }

        return $this->back()->with('success', $message);
    }

    public function sync(): RedirectResponse
    {
        try {
            $report = (new ModuleSynchronizer())->sync();
        } catch (SyncFailedException $e) {
            return $this->back()->with('error', lang('Core.sync.failed') . ' ' . implode(' ', $e->errors));
        } catch (Throwable $e) {
            return $this->back()->with('error', lang('Core.modules.syncFailed', [$e->getMessage()]));
        }

        activity_log('Core', 'modules_synced', ['resource' => 'modules', 'summary' => implode(', ', array_keys($report->installed))]);

        return $this->back()->with('success', $report->hasChanges() ? lang('Core.sync.done') : lang('Core.sync.nothing'));
    }

    /**
     * Staged packages: upload form, waiting and processed operations.
     */
    public function packages(): string
    {
        $staged = new StagedPackages();
        $staged->expire();
        $this->breadcrumb(lang('Core.modules.title'), site_url('backend/modules'))->breadcrumb(lang('Core.modules.tabs.packages'));

        return $this->render('Modules\Core\Views\modules\packages', [
            'title'  => lang('Core.modules.title'),
            'icon'   => 'bi bi-boxes',
            'tab'    => 'packages',
            'rows'   => $staged->latest(),
            'can'    => $this->permissions(),
            'limits' => $staged->folder() !== '' ? config('Packages') : null,
        ]);
    }

    public function uploadForm(): string
    {
        return $this->uploadPage();
    }

    public function upload(): ResponseInterface|string
    {
        $file = $this->request->getFile('package');

        if ($file === null || ! $file->isValid() || $file->hasMoved() || strtolower($file->getClientExtension()) !== 'zip') {
            return $this->uploadPage(lang('Core.packages.uploadInvalid'));
        }

        $signature = null;
        $sig       = $this->request->getFile('signature');

        if ($sig !== null && $sig->isValid() && ! $sig->hasMoved()) {
            if ($sig->getSize() > 4096) {
                return $this->uploadPage(lang('Core.packages.signatureFormat'));
            }

            $signature = (string) file_get_contents($sig->getTempName());
        }

        try {
            $row = (new StagedPackages())->upload($file->getTempName(), mb_substr($file->getClientName(), 0, 120), $signature, (int) auth()->id());
        } catch (PackageRefused $e) {
            return $this->uploadPage($e->getMessage());
        }

        return redirect()->to(site_url('backend/modules/packages/' . $row['id']))->with('success', lang('Core.packages.uploaded', [$row['name'] . ' ' . $row['version']]));
    }

    public function package(int $id): string
    {
        $staged = new StagedPackages();
        $row    = $staged->find($id);

        if ($row === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $info = $staged->info($row);
        $plan = $info !== null && $row['status'] === 'uploaded' ? (new PackageInstaller())->plan($info, false, false) : null;

        $this->breadcrumb(lang('Core.modules.title'), site_url('backend/modules'))
            ->breadcrumb(lang('Core.modules.tabs.packages'), site_url('backend/modules/packages'))
            ->breadcrumb(trim($row['name'] . ' ' . $row['version']));

        return $this->render('Modules\Core\Views\modules\package', [
            'title' => lang('Core.packages.title', [trim($row['name'] . ' ' . $row['version'])]),
            'icon'  => 'bi bi-box-seam',
            'row'   => $row,
            'info'  => $info,
            'plan'  => $plan,
            'can'   => $this->permissions(),
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        try {
            (new StagedPackages())->approve($id, (int) auth()->id());
        } catch (PackageRefused $e) {
            return redirect()->to(site_url('backend/modules/packages/' . $id))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('backend/modules/packages/' . $id))->with('success', lang('Core.packages.approved'));
    }

    public function discard(int $id): RedirectResponse
    {
        $staged = new StagedPackages();
        $row    = $staged->find($id);

        // Discarding an uninstall needs modules.uninstall, an install modules.upload.
        if ($row === null || ! $this->can($row['action'] === 'uninstall' ? 'modules.uninstall' : 'modules.upload')) {
            security_log('Core', 'forbidden', ['resource' => 'modules', 'summary' => 'discard ' . $id]);

            return redirect()->to(site_url('backend/modules/packages'))->with('error', lang('Core.modules.notAllowed'));
        }

        try {
            $staged->discard($id);
        } catch (PackageRefused $e) {
            return redirect()->to(site_url('backend/modules/packages'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('backend/modules/packages'))->with('success', lang('Core.packages.discarded'));
    }

    public function uninstallForm(string $name): string
    {
        return $this->uninstallPage($name);
    }

    public function uninstall(string $name): ResponseInterface|string
    {
        try {
            (new StagedPackages())->requestUninstall(PackageInfo::MODULE, $name, $this->request->getPost('keep_data') === '1', (int) auth()->id());
        } catch (PackageRefused $e) {
            return $this->uninstallPage($name, $e->getMessage());
        }

        return redirect()->to(site_url('backend/modules/packages'))->with('success', lang('Core.packages.uninstallRequested', [$name]));
    }

    private function uploadPage(?string $error = null): string
    {
        $this->breadcrumb(lang('Core.modules.title'), site_url('backend/modules'))
            ->breadcrumb(lang('Core.modules.tabs.packages'), site_url('backend/modules/packages'))
            ->breadcrumb(lang('Core.packages.upload'));

        return $this->render('Modules\Core\Views\modules\upload', [
            'title'  => lang('Core.packages.upload'),
            'icon'   => 'bi bi-upload',
            'error'  => $error,
            'config' => config('Packages'),
        ]);
    }

    private function uninstallPage(string $name, ?string $error = null): string
    {
        $rows = (new ModuleOverview())->rows();

        if (! isset($rows[$name])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->breadcrumb(lang('Core.modules.title'), site_url('backend/modules'))->breadcrumb(lang('Core.modules.uninstallTitle', [$name]));

        return $this->render('Modules\Core\Views\modules\uninstall', [
            'title'    => lang('Core.modules.uninstallTitle', [$name]),
            'icon'     => 'bi bi-trash',
            'module'   => $rows[$name],
            'action'   => site_url('backend/modules/uninstall/' . $name),
            'cancel'   => site_url('backend/modules'),
            'keepData' => true,
            'error'    => $error,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(): array
    {
        $can = [];

        foreach (['list', 'toggle', 'sync', 'upload', 'uninstall', 'demo'] as $operation) {
            $can[$operation] = $this->can('modules.' . $operation);
        }

        return $can;
    }

    private function back(): RedirectResponse
    {
        return redirect()->to(site_url('backend/modules'));
    }
}
