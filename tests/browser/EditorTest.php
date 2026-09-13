<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Tests\Support\EvernoteExport;

const EDITOR = '[data-test="note-body"] .tiptap-content';

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
    $this->note = Note::factory()->for($this->notebook)->create(['title' => 'Formatting', 'content' => '<p>Some text</p>']);
    $this->url = "/notebook/{$this->notebook->id}";
    $this->actingAs($this->user);
});

/** Waits for the toolbar and past the first second, during which edits are ignored as a note switch. */
function openEditor($page)
{
    return readyToEdit($page->assertVisible('[data-test="toolbar"]'));
}

function toolbar(string $dataTest): string
{
    return '[data-test="note-body"] [data-test="'.$dataTest.'"]';
}

/** Answers the editor's link/video URL popover, opened by the link and video buttons. */
function enterUrlInPopover($page, string $kind, string $url)
{
    $input = '[data-test="'.$kind.'-input"]';

    return $page->assertVisible($input)
        ->type($input, $url)
        ->keys($input, 'Enter');
}

it('applies toolbar formats and saves them', function (string $dataTest, string $expectedHtml) {
    $page = visit($this->url);
    selectAllInEditor(openEditor($page))->click(toolbar($dataTest));

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, $expectedHtml));
})->with([
    'bold' => ['toolbar-bold', '<strong>Some text</strong>'],
    'italic' => ['toolbar-italic', '<em>Some text</em>'],
    'underline' => ['toolbar-underline', '<u>Some text</u>'],
    'strike' => ['toolbar-strike', '<s>Some text</s>'],
    'quote' => ['toolbar-blockquote', '<blockquote><p>Some text</p></blockquote>'],
    'numbered list' => ['toolbar-ordered-list', '<ol><li><p>Some text</p></li></ol>'],
    'bullet list' => ['toolbar-bullet-list', '<ul><li><p>Some text</p></li></ul>'],
    'task list' => ['toolbar-task-list', 'data-type="taskList"'],
]);

it('indents and outdents list items', function () {
    $this->note->forceFill(['content' => '<ul><li><p>One</p></li><li><p>Two</p></li></ul>'])->save();

    $page = visit($this->url);
    // Clicking directly on the second item's text places the caret there, like a user would
    openEditor($page)->click(EDITOR.' li:nth-of-type(2)')->click(toolbar('toolbar-indent'));
    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<li><p>One</p><ul><li><p>Two</p></li></ul></li>'));

    $page->click(EDITOR.' li ul li p')->click(toolbar('toolbar-outdent'));
    waitForDatabase($page, fn () => ! str_contains((string) $this->note->fresh()->content, '<ul><li><p>One</p><ul>'));
});

it('changes the paragraph to a heading', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR)->select(toolbar('toolbar-heading'), '1');

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<h1>Some text</h1>'));
});

it('removes formatting', function () {
    $this->note->forceFill(['content' => '<p><strong>Bold</strong></p>'])->save();

    $page = visit($this->url);
    selectAllInEditor(openEditor($page))->click(toolbar('toolbar-clean'));

    waitForDatabase($page, fn () => ! str_contains((string) $this->note->fresh()->content, '<strong>'));
});

it('adds a link', function () {
    $page = visit($this->url);
    selectAllInEditor(openEditor($page))->click(toolbar('toolbar-link'));
    enterUrlInPopover($page, 'link', 'https://example.com/page');

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, 'href="https://example.com/page"'));
});

it('embeds https videos', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR)->click(toolbar('toolbar-video'));
    enterUrlInPopover($page, 'video', 'https://player.vimeo.com/video/76979871');

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));

    // Only YouTube links are rewritten: other embeddable URLs are stored as given
    expect(html_entity_decode($this->note->fresh()->content))->toContain('src="https://player.vimeo.com/video/76979871"');
});

// BUG-36: the video button kept YouTube "watch" URLs, which YouTube refuses to show inside an iframe
it('turns YouTube links into embeddable videos', function (string $url) {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR)->click(toolbar('toolbar-video'));
    enterUrlInPopover($page, 'video', $url);

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));

    expect(html_entity_decode($this->note->fresh()->content))->toContain('src="https://www.youtube.com/embed/dQw4w9WgXcQ');
})->with([
    'watch' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'watch with other parameters' => 'https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42',
    'short link' => 'https://youtu.be/dQw4w9WgXcQ',
    'shorts' => 'https://youtube.com/shorts/dQw4w9WgXcQ',
    'mobile' => 'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
]);

it('saves insecure http videos without a source', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR)->click(toolbar('toolbar-video'));
    enterUrlInPopover($page, 'video', 'http://example.com/video.mp4');

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));

    expect($this->note->fresh()->content)->not->toContain('http://example.com/video.mp4');
});

it('inserts a table', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR)->click(toolbar('toolbar-table'));

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<table'));
});

it('edits table rows and columns', function () {
    $this->note->forceFill(['content' => '<table><tbody><tr><td><p>a</p></td><td><p>b</p></td></tr></tbody></table>'])->save();

    $page = visit($this->url);
    openEditor($page)->click(EDITOR.' table td:first-of-type')->click(toolbar('table-add-column'));

    waitForDatabase($page, fn () => substr_count((string) $this->note->fresh()->content, '<td') === 3);
});

it('uploads an image from the toolbar', function () {
    $png = EvernoteExport::PNG;
    $page = visit($this->url);
    openEditor($page)->click(EDITOR);

    // The toolbar creates a file input that never joins the page: capture it and give it a file.
    // The change event is async, like a real file dialog, because the handler is attached after click().
    $page->script(<<<JS
        () => {
            const originalClick = HTMLInputElement.prototype.click;
            HTMLInputElement.prototype.click = function () {
                if (this.type !== 'file') { return originalClick.call(this); }
                const bytes = Uint8Array.from(atob('{$png}'), c => c.charCodeAt(0));
                const transfer = new DataTransfer();
                transfer.items.add(new File([bytes], 'pixel.png', { type: 'image/png' }));
                this.files = transfer.files;
                setTimeout(() => this.dispatchEvent(new Event('change')), 50);
            };
        }
    JS);

    $page->click(toolbar('toolbar-image'));

    waitForDatabase($page, fn () => str_contains(html_entity_decode((string) $this->note->fresh()->content), 'data:image/png;base64,'.$png));
});

it('does not insert an image when no file is chosen', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR);

    $page->script(<<<'JS'
        () => {
            HTMLInputElement.prototype.click = function () {
                if (this.type === 'file') { setTimeout(() => this.dispatchEvent(new Event('change')), 50); }
            };
        }
    JS);

    // The fake file dialog answers after 50 ms
    $page->click(toolbar('toolbar-image'))
        ->wait(0.3)
        ->assertScript('document.querySelectorAll(\'[data-test="note-body"] .tiptap-content img\').length', 0);

    expect($this->note->fresh()->content)->toBe('<p>Some text</p>');
});

it('never runs scripts pasted into a note', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR);

    $page->script(<<<'JS'
        () => {
            const data = new DataTransfer();
            data.setData('text/html', '<p>pasted</p><img src="x" onerror="window.__pastedScriptRan = true">');
            document.querySelector('[data-test="note-body"] .tiptap-content')
                .dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true, cancelable: true }));
        }
    JS);

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, 'pasted'));

    $page->refresh()
        ->assertVisible(EDITOR)
        ->wait(0.3)
        ->assertScript('window.__pastedScriptRan === true', false);

    expect($this->note->fresh()->content)->not->toContain('onerror');
});

// BUG-35 regression: notes stored before the sanitizer must not run their scripts when opened
it('never runs scripts stored in notes saved before sanitizing existed', function () {
    // Written straight to the database, the way notes saved before the hotfix may still be
    $this->note->forceFill(['content' => '<p>legacy</p><img src="x" onerror="window.__legacyScriptRan = true">'])->save();

    $page = visit($this->url);

    openEditor($page)
        ->wait(0.3)
        ->assertScript('window.__legacyScriptRan === true', false);
});

it('shows the next note after switching away from a note with a table', function () {
    $this->note->forceFill(['content' => '<p>Before</p><table><tbody><tr><td><p>A1</p></td><td><p>B1</p></td></tr></tbody></table>'])->save();
    $other = Note::factory()->for($this->notebook)->create([
        'title' => 'Other',
        'content' => '<p>Other body text</p>',
        'updated_at' => now()->subHour(),
    ]);

    $page = visit($this->url);
    openEditor($page)->assertVisible(EDITOR.' table');

    // With Quill's table plugin the editor came up empty after this switch
    $page->click('@note-'.$other->id)
        ->assertValue('@note-title', 'Other')
        ->assertSeeIn(EDITOR, 'Other body text')
        ->click('@note-'.$this->note->id)
        ->assertValue('@note-title', 'Formatting')
        ->assertVisible(EDITOR.' table')
        ->assertNoSmoke();
});

it('keeps the spaces typed inside a table cell', function () {
    $this->note->forceFill(['content' => '<table><tbody><tr><td><p>x</p></td><td><p></p></td></tr></tbody></table>'])->save();

    $page = visit($this->url);
    openEditor($page)->click(EDITOR.' table tr:first-child td:first-child p')
        ->keys(EDITOR, 'End')
        ->typeSlowly(EDITOR, ' uno dos tres', 20);

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, 'x uno dos tres'));
});
