<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Log\Handlers\FileHandler;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Logger;

/**
 * Security defaults set at bootstrap (docs/decisions.md D-00-39, D-00-51).
 *
 * @internal
 */
final class SecurityDefaultsTest extends CIUnitTestCase
{
    public function testLogFilesAreNotWorldReadable(): void
    {
        $handlers = config(Logger::class)->handlers;

        $this->assertArrayHasKey(FileHandler::class, $handlers);
        $this->assertSame(0640, $handlers[FileHandler::class]['filePermissions']);
    }

    public function testPublicUploadsAreIgnoredByGit(): void
    {
        $gitignore = (string) file_get_contents(ROOTPATH . '.gitignore');

        $this->assertStringContainsString("/public/uploads/*\n", $gitignore);
        $this->assertStringContainsString("!/public/uploads/index.html\n", $gitignore);
        $this->assertFileExists(ROOTPATH . 'public/uploads/index.html');
    }

    public function testNothingRunsInThePublicUploadFolder(): void
    {
        $gitignore = (string) file_get_contents(ROOTPATH . '.gitignore');
        $htaccess  = (string) file_get_contents(ROOTPATH . 'public/uploads/.htaccess');

        $this->assertStringContainsString("!/public/uploads/.htaccess\n", $gitignore);
        $this->assertStringContainsString('php_flag engine off', $htaccess);
        $this->assertStringContainsString('-ExecCGI', $htaccess);
        $this->assertStringContainsString('X-Content-Type-Options "nosniff"', $htaccess);
        $this->assertStringContainsString('sandbox', $htaccess);
        $this->assertMatchesRegularExpression('/FilesMatch "[^"]*php[^"]*html\?[^"]*">\s*Require all denied/', $htaccess);
    }
}
