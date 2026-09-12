<?php

namespace Tests\Unit;

use App\Services\NoteHtmlSanitizer;
use PHPUnit\Framework\TestCase;

class NoteHtmlSanitizerTest extends TestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function sanitize(?string $html): ?string
    {
        return (new NoteHtmlSanitizer())->sanitize($html);
    }

    public function test_keeps_the_markup_quill_produces(): void
    {
        $html = '<p>Plain <strong>bold</strong> <em>it</em> <u>u</u> <s>s</s> <span class="ql-size-large">big</span></p>'
            .'<blockquote>quote</blockquote>'
            .'<ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>item</li>'
            .'<li data-list="ordered" class="ql-indent-1"><span class="ql-ui" contenteditable="false"></span>sub</li></ol>'
            .'<p><a href="https://example.com" rel="noopener noreferrer" target="_blank">link</a></p>'
            .'<p><img src="'.self::PNG.'" width="120" /></p>'
            .'<iframe class="ql-video" frameborder="0" allowfullscreen="true" src="https://www.youtube.com/embed/abc"></iframe>'
            .'<table><tbody><tr><td data-row="row-1">cell</td></tr></tbody></table>';

        // "=" inside attributes comes back as the equivalent entity &#61;, which browsers decode
        $this->assertSame(str_replace('==', '&#61;&#61;', $html), $this->sanitize($html));
    }

    public function test_removes_scripts_event_handlers_and_dangerous_urls(): void
    {
        $html = '<p onclick="alert(1)" style="position:fixed">x</p><script>alert(2)</script>'
            .'<a href="javascript:alert(3)">l</a>'
            .'<iframe src="data:text/html,<script>alert(4)</script>"></iframe>'
            .'<iframe src="http://example.com"></iframe>';

        $clean = $this->sanitize($html);

        foreach (['onclick', 'style', '<script', 'javascript:', 'data:text/html', 'http://example.com'] as $needle) {
            $this->assertStringNotContainsString($needle, $clean);
        }
        $this->assertStringContainsString('<p>x</p>', $clean);
    }

    public function test_does_not_truncate_large_notes(): void
    {
        $html = '<p><img src="data:image/png;base64,'.str_repeat('A', 3_000_000).'" /></p>';

        $this->assertSame($html, $this->sanitize($html));
    }

    public function test_null_content_stays_null(): void
    {
        $this->assertNull($this->sanitize(null));
    }
}
