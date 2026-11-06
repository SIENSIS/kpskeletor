<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Storage\PathNormalizer;
use Modules\Core\Storage\StorageException;
use Modules\Core\Storage\SvgSanitizer;
use Modules\Core\Storage\UploadRefused;
use Modules\Core\Storage\UploadRules;
use Modules\Core\Storage\UploadValidator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Upload rules of the Storage service: paths that leave the disk, forbidden and disallowed types,
 * MIME checks from the contents, sizes, SVG refused by default and sanitised when allowed, and
 * images re-encoded without their metadata.
 *
 * @internal
 */
final class UploadRulesTest extends CIUnitTestCase
{
    use StorageFixturesTrait;

    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafePaths(): iterable
    {
        yield 'parent' => ['../secret.txt'];
        yield 'nested parent' => ['users/1/../../../.env'];
        yield 'current' => ['./a.txt'];
        yield 'absolute' => ['/etc/passwd'];
        yield 'backslashes' => ['users\\..\\..\\app'];
        yield 'drive' => ['C:/Windows/win.ini'];
        yield 'stream wrapper' => ['php://filter/resource=index.php'];
        yield 'null byte' => ["a.php\0.jpg"];
        yield 'control character' => ["a\nb.txt"];
        yield 'htaccess' => ['.htaccess'];
        yield 'hidden segment' => ['users/.ssh/id_rsa'];
        yield 'empty' => [''];
        yield 'only slashes' => ['//'];
        yield 'padded segment' => ['users/ 1/a.txt'];
    }

    #[DataProvider('unsafePaths')]
    public function testUnsafePathsAreRefused(string $path): void
    {
        $this->expectException(StorageException::class);
        PathNormalizer::normalize($path);
    }

    public function testPathsAreNormalised(): void
    {
        $this->assertSame('users/7/avatar.png', PathNormalizer::normalize('users//7/avatar.png/'));
        $this->assertSame('', PathNormalizer::normalize('', true));
        $this->assertSame('modules/news/a.pdf', PathNormalizer::join('modules/news/', 'a.pdf'));
    }

    private function assertRefused(string $reason, UploadValidator $validator, string $contents, string $name, ?UploadRules $rules = null): void
    {
        $path         = $this->tempFile($contents);
        $this->temp[] = $path;

        try {
            $validator->validate($path, $name, $rules ?? new UploadRules());
            $this->fail("{$name} should be refused ({$reason}).");
        } catch (UploadRefused $e) {
            $this->assertSame($reason, $e->reason(), $name);
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public function testCodeAndHtmlAreNeverAccepted(): void
    {
        $validator = $this->validator($this->storageConfig(), ['jpg', 'txt', 'php', 'html', 'svg']);

        $this->assertRefused('forbidden', $validator, '<?php echo 1;', 'shell.php');
        $this->assertRefused('forbidden', $validator, '<?php echo 1;', 'shell.PHTML');
        $this->assertRefused('forbidden', $validator, '<html><script>alert(1)</script>', 'page.html');
        $this->assertRefused('forbidden', $validator, 'Deny from all', '.htaccess');
        $this->assertRefused('forbidden', $validator, 'x', 'no-extension');
        $this->assertRefused('content', $validator, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', 'notes.txt');
        $this->assertRefused('content', $validator, "Hello\n<?php system(\$_GET['c']); ?>\n", 'notes.txt');
        $this->assertRefused('content', $validator, '<?php echo 1; ?>', 'shell.php.jpg');
    }

    public function testSvgIsRefusedByDefault(): void
    {
        $validator = $this->validator($this->storageConfig(), ['png', 'svg', 'txt']);
        $svg       = '<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"/></svg>';

        $this->assertNotContains('svg', $validator->allowedTypes(new UploadRules()));
        $this->assertRefused('svg', $validator, $svg, 'logo.svg');
        $this->assertRefused('content', $validator, $svg, 'logo.txt');
    }

    public function testAllowedSvgIsSanitised(): void
    {
        $validator = $this->validator($this->storageConfig(), ['svg'], allowSvg: true);
        $svg       = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)" viewBox="0 0 10 10">'
            . '<script>alert(2)</script><style>circle{fill:url(https://evil.example/x)}</style>'
            . '<foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><iframe src="https://evil.example"/></body></foreignObject>'
            . '<a href="javascript:alert(3)"><text>x</text></a>'
            . '<use xlink:href="https://evil.example/sprite.svg#icon"/><use href="#ok"/>'
            . '<circle id="ok" r="4" fill="url(#grad)" style="fill:red" onclick="alert(4)"/>'
            . '<image href="data:image/png;base64,AAAA"/></svg>';

        $path         = $this->tempFile($svg);
        $this->temp[] = $path;
        $valid        = $validator->validate($path, 'logo.svg', new UploadRules());
        $clean        = (string) $valid->contents;

        $this->assertSame('image/svg+xml', $valid->mime);
        $this->assertStringContainsString('<circle id="ok" r="4" fill="url(#grad)"/>', $clean);
        $this->assertStringContainsString('<use href="#ok"/>', $clean);

        foreach (['script', 'alert', 'onload', 'onclick', 'style', 'foreignObject', 'iframe', 'evil.example', 'javascript', 'data:', '<a ', '<image'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $clean, $forbidden);
        }

        $this->assertRefused('svg', $validator, '<!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>', 'bomb.svg');
        $this->assertRefused('mime', $validator, '<html xmlns="http://www.w3.org/1999/xhtml"><body>x</body></html>', 'page.svg');
        $this->assertRefused('svg', $validator, '<?xml version="1.0"?><html xmlns="http://www.w3.org/1999/xhtml"/>', 'page.svg');
    }

    public function testTheSanitiserRefusesNonSvgDocuments(): void
    {
        $this->expectException(StorageException::class);
        (new SvgSanitizer())->sanitize('not xml at all');
    }

    public function testFieldsOnlyNarrowTheGlobalRules(): void
    {
        $validator = $this->validator($this->storageConfig(), ['jpg', 'png', 'pdf'], 1000);
        $rules     = UploadRules::make('png, .PDF, exe, docx', 5000);

        $this->assertSame(['png', 'pdf'], $validator->allowedTypes($rules));
        $this->assertSame(1000, $validator->maxSize($rules), 'The smaller limit wins.');
        $this->assertSame(500, $validator->maxSize(UploadRules::make('png', 500)));

        $this->assertRefused('type', $validator, self::pdf(), 'report.pdf', UploadRules::make('png'));
        $this->assertRefused('type', $validator, 'a,b', 'data.csv');
        $this->assertRefused('too_large', $validator, str_repeat('x', 1001), 'big.pdf');
        $this->assertRefused('empty', $validator, '', 'empty.pdf');
    }

    public function testTheMimeTypeComesFromTheContents(): void
    {
        $validator = $this->validator($this->storageConfig());

        $this->assertRefused('mime', $validator, self::pdf(), 'photo.png');
        $this->assertRefused('mime', $validator, self::png(), 'report.pdf');
        $this->assertRefused('mime', $validator, self::png(), 'photo.jpg');
        $this->assertRefused('image', $validator, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00" . str_repeat("\x00", 40), 'broken.jpg');

        $path         = $this->tempFile(self::pdf());
        $this->temp[] = $path;
        $valid        = $validator->validate($path, 'Informe Final.PDF', new UploadRules());
        $this->assertSame(['pdf', 'application/pdf', null], [$valid->extension, $valid->mime, $valid->contents]);

        $path         = $this->tempFile("name,age\nAna,30\n");
        $this->temp[] = $path;
        $this->assertSame('text/csv', $validator->validate($path, 'people.csv', new UploadRules())->mime, 'Stored with the canonical type.');
    }

    public function testImagesAreReencodedWithoutMetadata(): void
    {
        $validator = $this->validator($this->storageConfig());
        $jpeg      = self::jpegWithMetadata();
        $path      = $this->tempFile($jpeg);
        $this->temp[] = $path;

        $this->assertStringContainsString('SECRET-GPS', $jpeg);
        $valid = $validator->validate($path, 'photo.JPG', new UploadRules());

        $this->assertNotNull($valid->contents);
        $this->assertStringNotContainsString('SECRET-GPS', (string) $valid->contents);
        $this->assertStringNotContainsString('APPENDED-PAYLOAD', (string) $valid->contents);
        $this->assertSame([10, 10, 'image/jpeg'], [$valid->width, $valid->height, $valid->mime]);
        $this->assertNotFalse(imagecreatefromstring((string) $valid->contents));

        $kept = $validator->validate($path, 'photo.jpg', new UploadRules(reencode: false));
        $this->assertNull($kept->contents, 'A field can turn re-encoding off.');

        $png = $this->tempFile(self::png(8, 6));
        $this->temp[] = $png;
        $this->assertSame([8, 6], [$validator->validate($png, 'a.png', new UploadRules())->width, 6]);
    }

    public function testHugeImagesAreRefused(): void
    {
        $config                 = $this->storageConfig();
        $config->maxImagePixels = 40;

        $this->assertRefused('image', $this->validator($config), self::png(8, 6), 'bomb.png');
    }
}
