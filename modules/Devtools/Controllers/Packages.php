<?php

declare(strict_types=1);

namespace Modules\Devtools\Controllers;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Packages\PackageInfo;
use Modules\Core\Packages\PackageValidator;
use Modules\Devtools\Packages\PackageBuilder;
use Modules\Devtools\Packages\PackageBuildFailed;
use Modules\Devtools\Packages\PackageSigner;
use Modules\Devtools\Packages\PackageSource;
use Modules\Devtools\Packages\PackageStore;
use Modules\Devtools\Packages\SigningCleanup;
use Modules\Devtools\Packages\SigningFailed;
use Modules\Devtools\Packages\VersionBump;

/**
 * Devtools › Packages (`backend/devtools/packages`, session 43, D-43-01): build the installable
 * ZIP of a module or theme (validated as the installer validates it), download it with its
 * `.sha256` and `.minisig` (served from `writable/devtools/packages/`, never from `public/`), and
 * sign it from a local form.
 *
 * The signing form's safeguards (D-43-01, each one tested): the secret key and its password are
 * read into variables and the request's POST data, raw body and uploaded files are emptied
 * before anything else (the debug toolbar's *after* filter collects the POST data of every
 * request), never written to disk, session, flash data or logs, wiped with `sodium_memzero`;
 * every exception is caught and answered with a generic message (no development error page);
 * `Cache-Control: no-store`. The POST answers a redirect to `packages/signed`, which runs the
 * cleanup (debug history, logs, caches, flash data) and ends the session.
 */
final class Packages extends BackendController
{
    /** Largest secret key accepted (a minisign key file is about 350 bytes). */
    private const MAX_KEY = 4096;

    public function index(): ResponseInterface
    {
        $this->breadcrumb(lang('Devtools.title'), backend_url('devtools'))->breadcrumb(lang('Devtools.packages.title'));

        $built = PackageStore::packages();

        return $this->noStore($this->response->setBody($this->render('Modules\Devtools\Views\packages\index', [
            'title'    => lang('Devtools.packages.title'),
            'icon'     => 'bi bi-box-seam',
            'sources'  => PackageSource::all(),
            'packages' => $built,
            'result'   => session()->getFlashdata('devtools.packages.result'),
            'canWrite' => $this->can('devtools.write'),
        ])));
    }

    public function build(): ResponseInterface
    {
        $type = $this->request->getPost('type') === PackageInfo::THEME ? PackageInfo::THEME : PackageInfo::MODULE;
        $name = (string) $this->request->getPost('name');
        $bump = (string) $this->request->getPost('bump');

        try {
            $source = PackageSource::find($type, $name);

            if ($bump !== '' && $bump !== 'none') {
                $version = (new VersionBump())->raise($source, $bump);
                activity_log('Devtools', 'version_raised', ['resource' => 'devtools', 'summary' => $source->name . ' ' . $source->version . ' → ' . $version]);
                $source = PackageSource::find($type, $name);
            }

            $result = (new PackageBuilder())->build($source);
        } catch (PackageBuildFailed $e) {
            return redirect()->to(backend_url('devtools/packages'))->with('error', $e->getMessage());
        }

        activity_log('Devtools', 'packaged', ['resource' => 'devtools', 'summary' => $result->file()]);

        return redirect()->to(backend_url('devtools/packages'))
            ->with('devtools.packages.result', $result->toArray())
            ->with('success', lang('Devtools.packages.builtMessage', [$result->file()]));
    }

    public function download(string $file = ''): ResponseInterface
    {
        $path = PackageStore::path($file);

        if ($path === null) {
            return $this->response->setStatusCode(404)->setBody(lang('Devtools.packages.notFound'));
        }

        $response = $this->response->download($path, null)->setFileName($file);
        $response->setContentType(str_ends_with($file, '.zip') ? 'application/zip' : 'text/plain');

        return $response->setHeader('Cache-Control', 'no-store')->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function signForm(): ResponseInterface
    {
        $file = (string) $this->request->getGet('package');

        if (PackageStore::path($file) === null || ! str_ends_with($file, '.zip')) {
            return redirect()->to(backend_url('devtools/packages'))->with('error', lang('Devtools.packages.notFound'));
        }

        $this->breadcrumb(lang('Devtools.title'), backend_url('devtools'))
            ->breadcrumb(lang('Devtools.packages.title'), backend_url('devtools/packages'))
            ->breadcrumb(lang('Devtools.packages.sign.title'));

        return $this->noStore($this->response->setBody($this->render('Modules\Devtools\Views\packages\sign', [
            'title' => lang('Devtools.packages.sign.title'),
            'icon'  => 'bi bi-pen',
            'file'  => $file,
        ])));
    }

    public function sign(): ResponseInterface
    {
        // Read the secret values, then empty the request before anything can collect it.
        $file     = (string) $this->request->getPost('package');
        $keyText  = $this->request->getPost('secret_key');
        $keyText  = is_string($keyText) ? $keyText : '';
        $password = $this->request->getPost('password');
        $password = is_string($password) ? $password : '';
        $upload   = $this->uploadedKey();
        self::scrub($this->request);

        if ($upload !== '') {
            if ($keyText !== '') {
                sodium_memzero($keyText);
            }

            $keyText = $upload;
        }

        $back = backend_url('devtools/packages/sign') . '?package=' . rawurlencode($file);

        try {
            $zip = PackageStore::path($file);

            if ($zip === null || ! str_ends_with($file, '.zip')) {
                throw new SigningFailed('package');
            }

            if ($keyText === '' || strlen($keyText) > self::MAX_KEY) {
                throw new SigningFailed('format');
            }

            $info   = (new PackageValidator(null))->validate($zip, null, null, $file);
            $result = (new PackageSigner())->sign($zip, $info->name . ' ' . $info->version, $keyText, $password);
        } catch (SigningFailed $e) {
            return $this->noStore(redirect()->to($back)->with('error', $e->userMessage()));
        } catch (\Throwable) {
            // Never the development error page: it would show the request.
            return $this->noStore(redirect()->to($back)->with('error', lang('Devtools.packages.sign.errors.failed')));
        } finally {
            if ($keyText !== '') {
                sodium_memzero($keyText);
            }

            if ($password !== '') {
                sodium_memzero($password);
            }
        }

        activity_log('Devtools', 'signed', ['resource' => 'devtools', 'summary' => lang('Devtools.packages.sign.summary', [$result->file, $result->keyId])]);

        return $this->noStore(redirect()->to(backend_url('devtools/packages/signed') . '?package=' . rawurlencode($result->file)));
    }

    /**
     * After a signature: the cleanup (debug history, logs, caches, flash data, the session) and
     * a page with the public facts of the signature (no session any more: the user signs in again).
     */
    public function signed(): ResponseInterface
    {
        $file   = (string) $this->request->getGet('package');
        $zip    = PackageStore::path($file);
        $info   = $zip !== null ? PackageStore::readInfo($zip) : null;
        $userId = auth('session')->id();

        $done = (new SigningCleanup())->run();

        activity_log('Devtools', 'cleanup', ['user_id' => $userId, 'resource' => 'devtools', 'summary' => implode(', ', $done)]);

        return $this->noStore($this->response->setBody(theme_view('Modules\Devtools\Views\packages\signed', [
            'layout' => theme_layout('auth'),
            'title'  => lang('Devtools.packages.signed.title'),
            'info'   => $info,
            'done'   => $done,
        ])));
    }

    /**
     * The uploaded key file, read in memory (PHP's own temporary upload file is removed by PHP at
     * the end of the request; it is never moved or stored).
     */
    private function uploadedKey(): string
    {
        $file = $this->request->getFile('secret_key_file');

        if ($file === null || ! $file->isValid() || $file->getSize() > self::MAX_KEY) {
            return '';
        }

        $contents = @file_get_contents($file->getTempName());

        return is_string($contents) ? $contents : '';
    }

    /**
     * Empties everything the request carries from the form: POST data, `$_REQUEST`, uploaded
     * files and the raw body (the debug toolbar collects the POST data of every request).
     */
    public static function scrub(IncomingRequest $request): void
    {
        $request->setGlobal('post', []);
        $request->setGlobal('request', []);
        service('superglobals')->setPostArray([])->setRequestArray([])->setFilesArray([]);
        $request->setBody('');
    }

    private function noStore(ResponseInterface $response): ResponseInterface
    {
        return $response->setHeader('Cache-Control', 'no-store, private')->setHeader('Pragma', 'no-cache');
    }
}
