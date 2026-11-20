<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use CodeIgniter\Debug\Toolbar;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\TestResponse;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\PackageValidator;
use Modules\Core\Security\Minisign;
use Modules\Devtools\Controllers\Packages;
use Modules\Devtools\Packages\PackageBuilder;
use Modules\Devtools\Packages\PackageSource;
use Modules\Devtools\Packages\PackageStore;

/**
 * Devtools › Packages (session 43, D-43-01): the screen, building, downloads (only stored files,
 * never a path), and the local signing form with its safeguards — the key and the password leave
 * no trace under `writable/`, in the session, the logs, the activity log or the debug toolbar's
 * data; failures answer a generic message (never the error page); `no-store`; and the cleanup
 * afterwards (debug history, logs, caches emptied, the session ended). Every Devtools lock
 * still applies.
 *
 * @internal
 */
final class PackagesScreenTest extends DevtoolsTestCase
{
    private string $folder = '';

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        $this->withDevtoolsRoutes();
        $this->fromLocalhost();
        $this->folder             = WRITEPATH . 'tests/devtools-packages-' . bin2hex(random_bytes(4));
        PackageStore::$testFolder = $this->folder;
    }

    protected function tearDown(): void
    {
        PackageStore::$testFolder = null;
        PackageValidator::removeFolder($this->folder);
        parent::tearDown();
    }

    public function testTheLocksStillApply(): void
    {
        $this->send('GET', backend_path('devtools/packages'))->assertRedirectTo(site_url('login'));

        $this->createUser('admin@example.com', 'admin');
        $this->login('admin@example.com');
        $this->fromLocalhost();
        $this->send('GET', backend_path('devtools/packages'))->assertStatus(403);
        $this->send('POST', backend_path('devtools/packages/build'), ['type' => 'theme', 'name' => 'default'])->assertStatus(403);
        $this->send('POST', backend_path('devtools/packages/sign'), ['package' => 'x-1.0.0.zip'])->assertStatus(403);

        // devtools.view alone: the screen, but no build or sign.
        $this->loginWith('devtools.view');
        $this->fromLocalhost();
        $this->send('GET', backend_path('devtools/packages'))->assertOK();
        $this->send('POST', backend_path('devtools/packages/build'), ['type' => 'theme', 'name' => 'default'])->assertStatus(403);
        $this->send('POST', backend_path('devtools/packages/sign'), ['package' => 'x-1.0.0.zip'])->assertStatus(403);

        $this->fromNetwork();
        $this->assertNotFound('GET', backend_path('devtools/packages'));
        $this->assertNotFound('POST', backend_path('devtools/packages/sign'));
    }

    public function testBuildingAndSigningAskForThePasswordAgain(): void
    {
        $this->loginSuperadmin();
        Time::setTestNow(Time::now()->addMinutes(16));
        $this->fromLocalhost();
        $this->send('POST', backend_path('devtools/packages/build'), ['type' => 'theme', 'name' => 'default'])->assertRedirectTo(site_url('auth/reauth'));
        $this->send('POST', backend_path('devtools/packages/sign'), ['package' => 'x-1.0.0.zip', 'secret_key' => 'k'])->assertRedirectTo(site_url('auth/reauth'));
        Time::setTestNow();
        $this->assertDirectoryDoesNotExist($this->folder);
    }

    public function testTheScreenBuildsAndServesPackages(): void
    {
        $this->loginSuperadmin();
        $this->fromLocalhost();

        $page = $this->send('GET', backend_path('devtools/packages'));
        $page->assertOK();
        $page->assertSee('data-package-source="Tasks"', null);
        $page->assertSee('data-package-source="default"', null);
        $page->assertDontSee('data-package-source="Auth"', null);
        $page->assertSee(esc(lang('Devtools.packages.built.none')));
        $this->assertStringContainsString('no-store', $page->response()->getHeaderLine('Cache-Control'));

        $build = $this->send('POST', backend_path('devtools/packages/build'), ['type' => 'theme', 'name' => 'default', 'bump' => 'none']);
        $build->assertRedirectTo(backend_url('devtools/packages'));
        $version = PackageSource::find(PackageInfo::THEME, 'default')->version;
        $file    = 'default-' . $version . '.zip';
        $this->assertFileExists($this->folder . '/' . $file);
        $this->assertFileExists($this->folder . '/' . $file . '.sha256');
        $this->assertSame(1, db_connect()->table('activity_log')->where(['module' => 'Devtools', 'action' => 'packaged'])->countAllResults());

        $this->fromLocalhost();
        $page = $this->send('GET', backend_path('devtools/packages'));
        $page->assertSee('data-package-result', null);
        $page->assertSee('data-built-package="' . $file . '"', null);
        $page->assertSee("minisign -Sm {$file} -t 'default {$version}'", null);

        $this->fromLocalhost();
        $download = $this->send('GET', backend_path('devtools/packages/download/' . $file . '.sha256'));
        $this->assertSame(200, $download->response()->getStatusCode());
        $this->assertInstanceOf(\CodeIgniter\HTTP\DownloadResponse::class, $download->response());

        // Only stored package files: no traversal, no other names.
        foreach (['..%2F..%2F.env', '.env', 'default-' . $version . '.zip.json', 'x.php', 'Nope-1.0.0.zip'] as $name) {
            $this->fromLocalhost();
            $this->assertNotFound('GET', backend_path('devtools/packages/download/' . $name));
        }

        // An unknown or Core module is refused with a message.
        $this->fromLocalhost();
        $this->send('POST', backend_path('devtools/packages/build'), ['type' => 'module', 'name' => 'Auth'])->assertSessionHas('error', lang('Devtools.packages.errors.noSource', ['Auth']));
    }

    public function testTheSigningFormKeepsNothing(): void
    {
        $file = $this->build();
        $this->loginSuperadmin();
        $this->fromLocalhost();

        $form = $this->send('GET', backend_path('devtools/packages/sign') . '?package=' . $file);
        $form->assertOK();
        $html = (string) $form->response()->getBody();
        $this->assertMatchesRegularExpression('/<form[^>]+autocomplete="off"/', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]+name="secret_key"[^>]+autocomplete="off"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+type="password"[^>]+name="password"[^>]+autocomplete="off"/', $html);
        $this->assertStringContainsString(esc(lang('Devtools.packages.sign.warning')), $html);
        $this->assertStringContainsString('no-store', $form->response()->getHeaderLine('Cache-Control'));

        [$keyText, $publicKey] = PackageSigningTest::makeKey('Sup3r-Secret-Passphrase');
        $this->fromLocalhost();
        $response = $this->send('POST', backend_path('devtools/packages/sign'), ['package' => $file, 'secret_key' => $keyText, 'password' => 'Sup3r-Secret-Passphrase']);

        $response->assertRedirectTo(backend_url('devtools/packages/signed') . '?package=' . $file);
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
        (new Minisign([$publicKey]))->verify($this->folder . '/' . $file, (string) file_get_contents($this->folder . '/' . $file . '.minisig'));

        $secrets = [trim(explode("\n", $keyText)[1]), substr(trim(explode("\n", $keyText)[1]), 20, 40), 'Sup3r-Secret-Passphrase'];
        $this->assertNoTrace($secrets, $response);

        // The activity log records only the file and the key id.
        $row = db_connect()->table('activity_log')->where(['module' => 'Devtools', 'action' => 'signed'])->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame(lang('Devtools.packages.sign.summary', [$file, PackageStore::readInfo($this->folder . '/' . $file)['keyId'] ?? '']), $row['summary']);
    }

    public function testFailuresAnswerAGenericMessageAndKeepNothing(): void
    {
        $file = $this->build();
        $this->loginSuperadmin();
        [$keyText] = PackageSigningTest::makeKey('Right-Passphrase-1');
        $back      = backend_url('devtools/packages/sign') . '?package=' . $file;

        // Wrong password.
        $this->fromLocalhost();
        $response = $this->send('POST', backend_path('devtools/packages/sign'), ['package' => $file, 'secret_key' => $keyText, 'password' => 'Wrong-Passphrase-2']);
        $response->assertRedirectTo($back);
        $response->assertSessionHas('error', lang('Devtools.packages.sign.errors.password'));
        $this->assertNoTrace([trim(explode("\n", $keyText)[1]), 'Wrong-Passphrase-2'], $response);
        $this->assertSame([], session()->get('_ci_old_input') ?? [], 'No old input.');

        // An unexpected failure (a corrupted package): the generic message, never the error page.
        file_put_contents($this->folder . '/' . $file, 'not a zip');
        $this->fromLocalhost();
        $response = $this->send('POST', backend_path('devtools/packages/sign'), ['package' => $file, 'secret_key' => $keyText, 'password' => 'Right-Passphrase-1']);
        $response->assertRedirectTo($back);
        $response->assertSessionHas('error', lang('Devtools.packages.sign.errors.failed'));
        $this->assertNoTrace([trim(explode("\n", $keyText)[1]), 'Right-Passphrase-1'], $response);
        $this->assertFileDoesNotExist($this->folder . '/' . $file . '.minisig');

        // An unknown package.
        $this->fromLocalhost();
        $this->send('POST', backend_path('devtools/packages/sign'), ['package' => '../../.env', 'secret_key' => $keyText])->assertSessionHas('error', lang('Devtools.packages.sign.errors.package'));
        $this->assertSame(0, db_connect()->table('activity_log')->where(['module' => 'Devtools', 'action' => 'signed'])->countAllResults());
    }

    public function testTheCleanupAfterSigningEmptiesTheHistoryAndEndsTheSession(): void
    {
        $file = $this->build();
        $user = $this->loginSuperadmin();
        [$keyText] = PackageSigningTest::makeKey('');
        $this->fromLocalhost();
        $this->send('POST', backend_path('devtools/packages/sign'), ['package' => $file, 'secret_key' => $keyText])->assertRedirect();

        // What a development request would have left: toolbar history, a log, caches.
        foreach (['debugbar/debugbar_1.json', 'logs/log-2026-11-20.log'] as $path) {
            @mkdir(dirname(WRITEPATH . $path), 0777, true);
            file_put_contents(WRITEPATH . $path, 'history');
        }

        cache()->save('devtools-test', 'cached', 600);
        session()->setFlashdata('leftover', 'flash');
        session()->set('_ci_old_input', ['post' => ['x' => 'y']]);

        $this->fromLocalhost();
        $page = $this->send('GET', backend_path('devtools/packages/signed') . '?package=' . $file);
        $page->assertOK();
        $page->assertSee('data-signed-result', null);
        $page->assertSee(PackageStore::readInfo($this->folder . '/' . $file)['publicKey'] ?? 'missing');
        $this->assertStringContainsString('no-store', $page->response()->getHeaderLine('Cache-Control'));

        $this->assertFileDoesNotExist(WRITEPATH . 'debugbar/debugbar_1.json');
        $this->assertFileDoesNotExist(WRITEPATH . 'logs/log-2026-11-20.log');
        $this->assertNull(cache('devtools-test'));
        $this->assertFalse(auth('session')->loggedIn(), 'The session ended.');
        $this->assertArrayNotHasKey('leftover', $_SESSION ?? []);
        $this->assertArrayNotHasKey('_ci_old_input', $_SESSION ?? []);

        $row = db_connect()->table('activity_log')->where(['module' => 'Devtools', 'action' => 'cleanup'])->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame((int) $user->id, (int) $row['user_id']);
        $this->assertSame('debugbar:clear, logs:clear, cache:clear, app:cache-clear, session', $row['summary']);

        // Signed out: the next request asks to sign in.
        $this->fromLocalhost();
        $this->send('GET', backend_path('devtools/packages'), [], [])->assertRedirectTo(site_url('login'));
    }

    public function testScrubEmptiesTheRequest(): void
    {
        $request = service('request');
        $request->setGlobal('post', ['secret_key' => 'k', 'password' => 'p']);
        $request->setBody('secret_key=k&password=p');
        service('superglobals')->setFilesArray(['secret_key_file' => ['name' => 'minisign.key']]);

        Packages::scrub($request);

        $this->assertSame([], $request->getPost());
        $this->assertSame([], $request->getVar() ?? []);
        $this->assertSame('', (string) $request->getBody());
        $this->assertSame([], service('superglobals')->getFilesArray());
    }

    /**
     * No trace of the secrets: in any file under writable/, in the session, in the activity log,
     * in the request (what the debug toolbar collects) or in the toolbar's own data.
     *
     * @param list<string> $secrets
     */
    private function assertNoTrace(array $secrets, TestResponse $response): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(WRITEPATH, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if (! $item->isFile() || $item->getSize() > 5_000_000) {
                continue;
            }

            $contents = (string) file_get_contents($item->getPathname());

            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $contents, 'Found in ' . $item->getPathname());
            }
        }

        $session = serialize($_SESSION ?? []);
        $logs    = json_encode(db_connect()->table('activity_log')->get()->getResultArray());
        // The request the feature test sent (FeatureTestTrait injects it as the service).
        $request = service('request');
        $this->assertInstanceOf(\CodeIgniter\HTTP\IncomingRequest::class, $request);
        $this->assertSame([], $request->getPost());
        // What the debug toolbar stores for a request (Toolbar::run()): POST, GET, headers,
        // cookies, the session and its collectors' "Vars" data.
        $collect = new \ReflectionMethod(Toolbar::class, 'collectVarData');
        $toolbar = print_r([
            $request->getPost(),
            $request->getGet(),
            array_map(static fn ($header): string => is_array($header) ? implode(',', array_map(static fn ($h): string => $h->getValueLine(), $header)) : $header->getValueLine(), $request->headers()),
            $request->getCookie(),
            $collect->invoke(new Toolbar(config('Toolbar'))),
        ], true);

        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $session, 'Found in the session.');
            $this->assertStringNotContainsString($secret, (string) $logs, 'Found in the activity log.');
            $this->assertStringNotContainsString($secret, (string) $request->getBody(), 'Found in the request body.');
            $this->assertStringNotContainsString($secret, $toolbar, 'Found in the debug toolbar data.');
        }
    }

    private function build(): string
    {
        return basename((new PackageBuilder(null, false))->build(PackageSource::find(PackageInfo::THEME, 'default'))->zip);
    }
}
