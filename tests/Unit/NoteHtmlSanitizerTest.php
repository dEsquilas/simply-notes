<?php

use App\Services\NoteHtmlSanitizer;
use Tests\Support\EvernoteExport;

beforeEach(function () {
    $this->sanitizer = new NoteHtmlSanitizer();
});

it('keeps the markup Quill produces', function () {
    $html = '<p>Plain <strong>bold</strong> <em>it</em> <u>u</u> <s>s</s> <span class="ql-size-large">big</span></p>'
        .'<blockquote>quote</blockquote>'
        .'<ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>item</li>'
        .'<li data-list="ordered" class="ql-indent-1"><span class="ql-ui" contenteditable="false"></span>sub</li></ol>'
        .'<p><a href="https://example.com" rel="noopener noreferrer" target="_blank">link</a></p>'
        .'<p><img src="data:image/png;base64,'.EvernoteExport::PNG.'" width="120" /></p>'
        .'<iframe class="ql-video" frameborder="0" allowfullscreen="true" src="https://www.youtube.com/embed/abc"></iframe>'
        .'<table><tbody><tr><td data-row="row-1">cell</td></tr></tbody></table>';

    // "=" inside attributes comes back as the equivalent entity &#61;, which browsers decode
    expect($this->sanitizer->sanitize($html))->toBe(str_replace('==', '&#61;&#61;', $html));
});

it('removes scripts, event handlers and dangerous URLs', function () {
    $clean = $this->sanitizer->sanitize(
        '<p onclick="alert(1)" style="position:fixed">x</p><script>alert(2)</script>'
        .'<a href="javascript:alert(3)">l</a>'
        .'<iframe src="data:text/html,<script>alert(4)</script>"></iframe>'
        .'<iframe src="http://example.com"></iframe>'
    );

    expect($clean)->toContain('<p>x</p>');
    foreach (['onclick', 'style', '<script', 'javascript:', 'data:text/html', 'http://example.com'] as $needle) {
        expect($clean)->not->toContain($needle);
    }
});

it('allows https iframes regardless of case and surrounding spaces', function (string $src) {
    expect($this->sanitizer->sanitize('<iframe src="'.$src.'"></iframe>'))->toContain('src=');
})->with([
    'lowercase' => 'https://www.youtube.com/embed/abc',
    'uppercase scheme' => 'HTTPS://www.youtube.com/embed/abc',
]);

it('keeps an iframe without a source', function () {
    expect($this->sanitizer->sanitize('<iframe class="ql-video"></iframe>'))->toBe('<iframe class="ql-video"></iframe>');
});

it('allows http images while iframes need https', function () {
    expect($this->sanitizer->sanitize('<img src="http://example.com/a.png" />'))->toBe('<img src="http://example.com/a.png" />')
        ->and($this->sanitizer->sanitize('<iframe src="http://example.com"></iframe>'))->toBe('<iframe></iframe>');
});

it('allows mailto links', function () {
    // "@" comes back as the equivalent entity &#64;, which browsers decode
    expect($this->sanitizer->sanitize('<a href="mailto:jane@example.com">mail</a>'))->toBe('<a href="mailto:jane&#64;example.com">mail</a>');
});

it('drops Quill attributes on elements where Quill never puts them', function (string $html, string $expected) {
    expect($this->sanitizer->sanitize($html))->toBe($expected);
})->with([
    'data-list on a paragraph' => ['<p data-list="bullet">x</p>', '<p>x</p>'],
    'contenteditable on a div' => ['<div contenteditable="true">x</div>', '<div>x</div>'],
    'data-row on a div' => ['<div data-row="row-1">x</div>', '<div>x</div>'],
]);

it('removes dangerous elements', function (string $html) {
    $clean = $this->sanitizer->sanitize('<p>keep</p>'.$html);

    expect($clean)->toBe('<p>keep</p>');
})->with([
    'script' => '<script>alert(1)</script>',
    'style' => '<style>body{display:none}</style>',
    'object' => '<object data="evil.swf"></object>',
    'embed' => '<embed src="evil.swf">',
    'form' => '<form action="https://evil.example"><input name="password"></form>',
]);

it('does not truncate large notes', function () {
    $html = '<p><img src="data:image/png;base64,'.str_repeat('A', 3_000_000).'" /></p>';

    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('keeps null content as null', function () {
    expect($this->sanitizer->sanitize(null))->toBeNull();
});
