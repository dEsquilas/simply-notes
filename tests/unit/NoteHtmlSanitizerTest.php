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

// BUG-38 regression
it('keeps the text of Evernote and SVG elements it does not allow', function (string $html, string $expected) {
    expect($this->sanitizer->sanitize($html))->toBe($expected);
})->with([
    'code block' => ['<div><en-codeblock><div>SELECT * FROM notes;</div></en-codeblock></div>', '<div><div>SELECT * FROM notes;</div></div>'],
    'note wrapper' => ['<en-note><div>Body text</div></en-note>', '<div>Body text</div>'],
    'svg icon' => ['<div><svg width="10"><circle r="4"></circle></svg>Visible text</div>', '<div>Visible text</div>'],
]);

it('keeps Quill checklists, which is how imported to-dos are saved', function () {
    $html = '<ol><li data-list="checked">Buy milk</li><li data-list="unchecked">Eggs</li></ol>';

    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('keeps the markup quill-table-better produces for a resized, bordered table', function () {
    $html = '<table style="width: 100%" class="ql-table-better">'
        .'<colgroup><col width="72" /></colgroup>'
        .'<tbody><tr><td data-row="row-t51p" width="72" height="24" style="border-style: solid; border-color: #ff0000; background-color: #eeeeee; text-align: center; vertical-align: middle">'
        .'<p class="ql-table-block" data-cell="cell-4ev6">cell</p></td>'
        .'<th data-row="row-t51p" colspan="2" rowspan="1"><p data-cell="cell-pvwg">head</p></th>'
        .'</tr></tbody></table>';

    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('strips anything but the known-safe CSS properties and characters from table styles', function (string $style, string $expected) {
    $clean = $this->sanitizer->sanitize('<table style="'.$style.'"></table>');

    expect($clean)->toBe($expected === '' ? '<table></table>' : '<table style="'.$expected.'"></table>');
})->with([
    'unknown property dropped, known one kept' => ['position: fixed; width: 50%', 'width: 50%'],
    'url() rejected even under an allowed property' => ['background-color: url(javascript:alert(1))', ''],
    'expression() rejected' => ['width: expression(alert(1))', ''],
    'javascript scheme rejected' => ['background-color: javascript:alert(1)', ''],
    'attempt to break out into a new rule keeps only the safe declaration before it' => ['width: 1px;}body{background:red', 'width: 1px'],
    'declaration without a colon (e.g. a trailing semicolon) is skipped' => ['width: 100px;;', 'width: 100px'],
]);

it('drops style on elements quill-table-better never puts it on', function () {
    expect($this->sanitizer->sanitize('<p style="width: 100%">x</p>'))->toBe('<p>x</p>');
});
