<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\StagedPackages;
use Modules\Dashboard\Tests\DashboardTestCase;
use ZipArchive;

/**
 * The Modules screen (`backend/modules`, docs/installing-modules.md): permissions and forged POSTs,
 * re-authentication for uploads and uninstall requests, enable/disable with dependencies
 * (acceptance criterion 3: a disabled module's routes answer 404 and come back when enabled),
 * the packages tab, the themes tab and nothing written into the code folders.
 *
 * @internal
 */
final class ModulesScreenTest extends DashboardTestCase
{
    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    public function testEveryActionNeedsItsPermission(): void
    {
        $this->loginWith();

        foreach (['backend/modules', 'backend/modules/packages', 'backend/modules/themes', 'backend/modules/packages/upload', 'backend/modules/uninstall/News', 'backend/modules/themes/uninstall/default'] as $path) {
            $this->send('GET', $path)->assertStatus(403);
        }

        foreach (['backend/modules/disable/News', 'backend/modules/enable/News', 'backend/modules/sync', 'backend/modules/demo/News', 'backend/modules/packages/upload', 'backend/modules/packages/1/approve', 'backend/modules/packages/1/discard', 'backend/modules/uninstall/News', 'backend/modules/themes/activate', 'backend/modules/themes/uninstall/default'] as $path) {
            $this->send('POST', $path, ['action' => 'load', 'area' => 'public', 'theme' => 'default'])->assertStatus(403);
        }

        $this->assertSame(1, (int) db_connect()->table('modules')->where('name', 'News')->get()->getRow()->enabled, 'Nothing changed.');
    }

    public function testAnAdministratorManagesModulesButCannotUploadNorUninstall(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $page = (string) $this->send('GET', 'backend/modules')->getBody();
        $this->assertStringContainsString('data-module="News"', $page);
        $this->assertStringContainsString('backend/modules/disable/News', $page);
        $this->assertStringNotContainsString('backend/modules/disable/Users', $page, 'Core modules cannot be disabled.');
        $this->assertStringNotContainsString('backend/modules/uninstall/', $page);
        $this->assertStringContainsString('backend/modules/sync', $page);

        $this->assertStringNotContainsString('backend/modules/packages/upload', (string) $this->send('GET', 'backend/modules/packages')->getBody());
        $this->send('GET', 'backend/modules/packages/upload')->assertStatus(403);
        $this->send('POST', 'backend/modules/uninstall/News', ['keep_data' => '1'])->assertStatus(403);
        $this->send('POST', 'backend/modules/demo/News', ['action' => 'remove'])->assertStatus(403);
        $this->assertSame(0, db_connect()->table('module_packages')->countAllResults());
    }

    public function testDisablingHidesTheModuleAndEnablingRestoresIt(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');
        $this->send('GET', 'backend/updates')->assertStatus(200);

        $this->send('POST', 'backend/modules/disable/Backup')->assertRedirectTo(site_url('backend/modules'));
        $this->assertSame(1, $this->enabled('Backup'), 'Updates depends on Backup.');
        $this->assertStringContainsString(lang('Core.toggle.hasDependants', ['Backup', 'Updates']), (string) session()->getFlashdata('error'));

        $this->send('POST', 'backend/modules/disable/Updates');
        $this->send('POST', 'backend/modules/disable/Backup');
        $this->assertSame(0, $this->enabled('Updates'));
        $this->assertNotFound('GET', 'backend/updates');
        $this->assertStringNotContainsString('backend/updates', (string) $this->send('GET', 'backend/modules')->getBody(), 'Its menu entry is gone.');

        // Enabling Updates offers to enable Backup too.
        $this->send('POST', 'backend/modules/enable/Updates');
        $this->assertSame(0, $this->enabled('Updates'));
        $page = (string) $this->send('GET', 'backend/modules')->getBody();
        $this->assertStringContainsString('data-enable-dependencies', $page);
        $this->assertStringContainsString(lang('Core.toggle.enableDependencies', ['Backup']), $page);

        $this->send('POST', 'backend/modules/enable/Updates', ['dependencies' => '1']);
        $this->assertSame(1, $this->enabled('Updates'));
        $this->assertSame(1, $this->enabled('Backup'));
        $this->send('GET', 'backend/updates')->assertStatus(200);
        $this->assertSame(2, db_connect()->table('activity_log')->where('action', 'module_disabled')->countAllResults());

        $this->send('POST', 'backend/modules/disable/Users');
        $this->assertSame(1, $this->enabled('Users'), 'A core module stays enabled even with a forged POST.');
    }

    public function testUploadAndUninstallNeedARecentReauthentication(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');

        $this->send('GET', 'backend/modules/packages/upload')->assertStatus(200);
        $page = (string) $this->send('POST', 'backend/modules/packages/upload')->getBody();
        $this->assertStringContainsString(lang('Core.packages.uploadInvalid'), $page, 'No file: the form again.');

        $this->send('GET', 'backend/modules/uninstall/News')->assertStatus(200);

        Time::setTestNow(Time::now()->addMinutes(16));
        $this->send('GET', 'backend/modules/packages/upload')->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', 'backend/modules/packages/upload')->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', 'backend/modules/uninstall/News')->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', 'backend/modules/packages/1/approve')->assertRedirectTo(site_url('auth/reauth'));
        $this->assertSame(0, db_connect()->table('module_packages')->countAllResults());
    }

    public function testAnUninstallRequestBecomesPendingAndNothingIsDeleted(): void
    {
        $this->createUser('root@example.com', 'superadmin');
        $this->login('root@example.com');

        // Core modules and modules others require are refused.
        $this->assertStringContainsString(lang('Core.packages.uninstallCore', ['Users']), (string) $this->send('POST', 'backend/modules/uninstall/Users')->getBody());
        $this->assertStringContainsString(lang('Core.packages.uninstallDependants', ['Backup', 'Updates']), (string) $this->send('POST', 'backend/modules/uninstall/Backup')->getBody());

        $this->send('POST', 'backend/modules/uninstall/News', ['keep_data' => '1'])->assertRedirectTo(site_url('backend/modules/packages'));
        $row = db_connect()->table('module_packages')->get()->getRowArray();
        $this->assertSame(['uninstall', 'News', '1', 'pending'], [$row['action'], $row['name'], (string) $row['keep_data'], $row['status']]);
        $this->assertDirectoryExists(ROOTPATH . 'modules/News', 'The web server never deletes code.');

        $list = (string) $this->send('GET', 'backend/modules')->getBody();
        $this->assertStringContainsString(lang('Core.modules.statuses.pendingUninstall'), $list);
        $this->assertStringContainsString('php spark modules:install --pending', (string) $this->send('GET', 'backend/modules/packages/' . $row['id'])->getBody());

        // Only modules.uninstall may discard it.
        $_SESSION      = [];
        $this->cookies = [];
        $this->loginWith('modules.list', 'modules.upload');
        $this->send('POST', 'backend/modules/packages/' . $row['id'] . '/discard');
        $this->assertSame('pending', db_connect()->table('module_packages')->where('id', $row['id'])->get()->getRow()->status);
    }

    public function testAnUploadedPackageIsShownAndApproved(): void
    {
        $root   = $this->createUser('root@example.com', 'superadmin');
        $staged = new StagedPackages();
        $zip    = $this->modulePackage('Kpstaged');

        try {
            $row = $staged->upload($zip, 'kpstaged.zip', null, (int) $root->id);
        } finally {
            @unlink($zip);
        }

        $this->login('root@example.com');
        $page = (string) $this->send('GET', 'backend/modules/packages/' . $row['id'])->getBody();
        $this->assertStringContainsString('kpstaged-items.list', $page, 'The permissions it will add.');
        $this->assertStringContainsString('Config/Module.php', $page, 'Its files.');
        $this->assertStringContainsString(lang('Core.packages.plan.install', ['Kpstaged', '1.0.0', '']), $page);

        $this->send('POST', 'backend/modules/packages/' . $row['id'] . '/approve')->assertRedirectTo(site_url('backend/modules/packages/' . $row['id']));
        $this->assertSame('pending', $staged->find((int) $row['id'])['status']);
        $this->assertDirectoryDoesNotExist(ROOTPATH . 'modules/Kpstaged');

        $this->send('POST', 'backend/modules/packages/' . $row['id'] . '/discard');
        $this->assertSame('discarded', $staged->find((int) $row['id'])['status']);
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'package_approved')->countAllResults());
    }

    public function testThemesTab(): void
    {
        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');

        $page = (string) $this->send('GET', 'backend/modules/themes')->getBody();
        $this->assertStringContainsString('data-theme="default"', $page);
        $this->assertStringContainsString(lang('Core.themes.activeBackend'), $page);
        $this->assertStringNotContainsString('backend/modules/themes/uninstall/default', $page, 'The active theme cannot be uninstalled.');

        $this->send('POST', 'backend/modules/themes/activate', ['area' => 'public', 'theme' => 'nowhere']);
        $this->assertSame(lang('Core.themes.invalid'), session()->getFlashdata('error'));
        $this->send('POST', 'backend/modules/themes/activate', ['area' => 'public', 'theme' => 'default'])->assertRedirectTo(site_url('backend/modules/themes'));
        $this->assertSame('default', setting('Theme.public'));
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'theme_activated')->countAllResults());

        $this->createUser('root@example.com', 'superadmin');
        $_SESSION      = [];
        $this->cookies = [];
        $this->login('root@example.com');
        $this->assertStringContainsString(lang('Core.packages.themeActive', ['default']), (string) $this->send('POST', 'backend/modules/themes/uninstall/default')->getBody());
        $this->assertSame(0, db_connect()->table('module_packages')->where('type', PackageInfo::THEME)->countAllResults());
    }

    private function enabled(string $module): int
    {
        return (int) db_connect()->table('modules')->where('name', $module)->get()->getRow()->enabled;
    }

    private function modulePackage(string $name): string
    {
        $lower = strtolower($name);
        $file  = sys_get_temp_dir() . '/kps-' . bin2hex(random_bytes(6)) . '.zip';
        $zip   = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE);
        $zip->addFromString($name . '/Config/Module.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace Modules\\{$name}\\Config;

            final class Module extends \\Modules\\Core\\Config\\ModuleManifest
            {
                public string \$name    = '{$name}';
                public string \$version = '1.0.0';
                public array \$requires = ['Core' => '^0.1'];
                public array \$resources = ['items' => ['label' => 'Items', 'type' => 'crud']];
            }
            PHP);
        $zip->addFromString($name . '/Config/Routes.php', "<?php\n");
        $zip->close();
        unset($lower);

        return $file;
    }
}
