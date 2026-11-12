<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Security\HtmlSanitizer;

/**
 * The rich-text sanitiser (HTMLPurifier): no styles, scripts or handlers; alignment kept as the
 * classes text-center / text-right / text-justify (from `style`, `align` and the editor's
 * `ql-align-*`), no other class.
 *
 * @internal
 */
final class HtmlSanitizerTest extends CIUnitTestCase
{
    public function testAlignmentBecomesClassesAndStylesAreDropped(): void
    {
        $this->assertSame('<p class="text-center">a</p>', HtmlSanitizer::clean('<p style="text-align: center; color: red">a</p>'));
        $this->assertSame('<p class="text-right">a</p>', HtmlSanitizer::clean('<p style="TEXT-ALIGN:end">a</p>'));
        $this->assertSame('<h2 class="text-justify">a</h2>', HtmlSanitizer::clean('<h2 align="justify">a</h2>'));
        $this->assertSame('<p class="text-right">a</p>', HtmlSanitizer::clean('<p class="ql-align-right">a</p>'));
        $this->assertSame('<ul><li class="text-center">a</li></ul>', HtmlSanitizer::clean('<ul><li class="text-center">a</li></ul>'));
        $this->assertSame('<p>a</p>', HtmlSanitizer::clean('<p style="text-align:left" class="evil btn">a</p>'), 'Left is the default; other classes go.');
        $this->assertSame('<p>a</p>', HtmlSanitizer::clean('<p class="text-danger">a</p>'));
        $this->assertSame('<blockquote class="text-center">q</blockquote>', HtmlSanitizer::clean('<blockquote style="text-align:center">q</blockquote>'));
    }

    public function testDangerousMarkupIsRemoved(): void
    {
        $clean = HtmlSanitizer::clean('<p onclick="x()" style="background:url(javascript:alert(1))">t<script>alert(1)</script><a href="javascript:alert(2)">l</a><iframe src="https://evil.example"></iframe></p>');

        $this->assertSame('<p>t<a>l</a></p>', $clean);
    }

    public function testOnlyImagesOfTheSiteItselfAreKept(): void
    {
        $base = rtrim(config('App')->baseURL, '/');

        $this->assertSame('<p><img src="/uploads/modules/news/a.jpg" alt="A cat"></p>', HtmlSanitizer::clean('<p><img src="/uploads/modules/news/a.jpg" alt="A cat"></p>'));
        $this->assertSame('<p><img src="' . $base . '/uploads/x.png" alt=""></p>', HtmlSanitizer::clean('<p><img src="' . $base . '/uploads/x.png" alt=""></p>'));
        $this->assertSame('<p>t</p>', HtmlSanitizer::clean('<p>t<img src="https://tracker.example/pixel.gif" alt="x"></p>'), 'External image removed');
        $this->assertSame('<p>t</p>', HtmlSanitizer::clean('<p>t<img src="//tracker.example/p.gif"></p>'), 'Scheme-relative external image removed');
        $this->assertSame('<p>t</p>', HtmlSanitizer::clean('<p>t<img src="data:image/png;base64,iVBORw0KGgo="></p>'), 'data: images removed');
        $this->assertSame('<p><a href="https://example.org/">link</a></p>', HtmlSanitizer::clean('<p><a href="https://example.org/">link</a></p>'), 'Links to other sites are kept');
    }
}
