<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Menus\Services\UrlValidator;

/**
 * Addresses of menu items and custom links: https/http URLs and internal paths only.
 *
 * @internal
 */
final class UrlValidatorTest extends CIUnitTestCase
{
    public function testAcceptedAddresses(): void
    {
        foreach (['https://example.com', 'http://example.com/a?b=c#d', 'HTTPS://Example.com/x', '/', '/backend/users', '/qui-som?x=1#y'] as $url) {
            $this->assertSame($url, UrlValidator::check($url), $url);
        }

        $this->assertSame('/a', UrlValidator::check('  /a  '), 'Trimmed.');
    }

    public function testRefusedAddresses(): void
    {
        $refused = [
            '', 'javascript:alert(1)', 'JavaScript:alert(1)', ' javascript:alert(1)', 'java' . "\t" . 'script:alert(1)',
            'data:text/html;base64,PHNjcmlwdD4=', 'vbscript:msgbox(1)', 'mailto:a@example.com', 'ftp://example.com',
            '//evil.example.com', '/\\evil.example.com', '\\\\evil.example.com', 'https://user:pass@example.com', 'https://',
            'http:/example.com', 'example.com', 'qui-som', '/a b', "/a\nb", 'https://' . str_repeat('a', 500) . '.com',
        ];

        foreach ($refused as $url) {
            $this->assertNull(UrlValidator::check($url), json_encode($url) ?: '');
        }
    }

    public function testInternalPaths(): void
    {
        $this->assertTrue(UrlValidator::isInternal('/backend'));
        $this->assertFalse(UrlValidator::isInternal('//backend'));
        $this->assertSame('backend/users', UrlValidator::path('/backend/users/?page=2#top'));
        $this->assertSame('', UrlValidator::path('/'));
    }
}
