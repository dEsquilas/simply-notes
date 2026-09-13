<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Tests\Support\EvernoteExport;

const EDITOR = '[data-test="note-body"] .ql-editor';

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
    return readyToEdit($page->assertVisible('[data-test="note-body"] .ql-toolbar'));
}

function toolbar(string $button): string
{
    return '[data-test="note-body"] '.$button;
}

/** Answers the editor's URL tooltip, opened by the link and video buttons. */
function enterUrlInTooltip($page, string $mode, string $url)
{
    $input = '[data-test="note-body"] .ql-tooltip.ql-editing input[type="text"]';

    return $page->assertAttribute('[data-test="note-body"] .ql-tooltip', 'data-mode', $mode)
        ->type($input, $url)
        ->keys($input, 'Enter');
}

it('applies toolbar formats and saves them', function (string $button, string $expectedHtml) {
    $page = visit($this->url);
    selectAllInEditor(openEditor($page))->click(toolbar($button));

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, $expectedHtml));
})->with([
    'bold' => ['.ql-bold', '<strong>Some text</strong>'],
    'italic' => ['.ql-italic', '<em>Some text</em>'],
    'underline' => ['.ql-underline', '<u>Some text</u>'],
    'strike' => ['.ql-strike', '<s>Some text</s>'],
    'quote' => ['.ql-blockquote', '<blockquote>Some text</blockquote>'],
    'numbered list' => ['.ql-list[value="ordered"]', 'data-list="ordered"'],
    'bullet list' => ['.ql-list[value="bullet"]', 'data-list="bullet"'],
]);

it('indents and outdents list items', function () {
    $this->note->forceFill(['content' => '<ol><li data-list="bullet">Item</li></ol>'])->save();

    $page = visit($this->url);
    selectAllInEditor(openEditor($page))->click(toolbar('.ql-indent[value="+1"]'));
    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, 'ql-indent-1'));

    selectAllInEditor($page)->click(toolbar('.ql-indent[value="-1"]'));
    waitForDatabase($page, fn () => ! str_contains((string) $this->note->fresh()->content, 'ql-indent-1'));
});

it('changes the text size', function () {
    $page = visit($this->url);
    selectAllInEditor(openEditor($page))
        ->click(toolbar('.ql-size .ql-picker-label'))
        ->click(toolbar('.ql-size .ql-picker-item[data-value="large"]'));

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, 'ql-size-large'));
});

it('removes formatting', function () {
    $this->note->forceFill(['content' => '<p><strong>Bold</strong></p>'])->save();

    $page = visit($this->url);
    selectAllInEditor(openEditor($page))->click(toolbar('.ql-clean'));

    waitForDatabase($page, fn () => ! str_contains((string) $this->note->fresh()->content, '<strong>'));
});

it('adds a link', function () {
    // The link button asks for the URL in the editor's tooltip
    $page = visit($this->url);
    selectAllInEditor(openEditor($page))->click(toolbar('.ql-link'));
    enterUrlInTooltip($page, 'link', 'https://example.com/page');

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, 'href="https://example.com/page"'));
});

it('embeds https videos', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR)->click(toolbar('.ql-video'));
    enterUrlInTooltip($page, 'video', 'https://player.vimeo.com/video/76979871');

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));

    // Only YouTube links are rewritten: other embeddable URLs are stored as given
    expect(html_entity_decode($this->note->fresh()->content))->toContain('src="https://player.vimeo.com/video/76979871"');
});

// BUG-36: the video button kept YouTube "watch" URLs, which YouTube refuses to show inside an iframe
it('turns YouTube links into embeddable videos', function (string $url) {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR)->click(toolbar('.ql-video'));
    enterUrlInTooltip($page, 'video', $url);

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));

    // Quill's tooltip already rewrites some of these forms and appends ?showinfo=0; any embed URL is fine
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
    openEditor($page)->click(EDITOR)->click(toolbar('.ql-video'));
    enterUrlInTooltip($page, 'video', 'http://example.com/video.mp4');

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));

    expect($this->note->fresh()->content)->not->toContain('http://example.com/video.mp4');
});

it('inserts a table', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR)->click(toolbar('.ql-table'));

    waitForDatabase($page, fn () => str_contains((string) $this->note->fresh()->content, '<table'));
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

    $page->click(toolbar('.ql-image'));

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
    $page->click(toolbar('.ql-image'))
        ->wait(0.3)
        ->assertScript('document.querySelectorAll(\'[data-test="note-body"] .ql-editor img\').length', 0);

    expect($this->note->fresh()->content)->toBe('<p>Some text</p>');
});

it('never runs scripts pasted into a note', function () {
    $page = visit($this->url);
    openEditor($page)->click(EDITOR);

    $page->script(<<<'JS'
        () => {
            const data = new DataTransfer();
            data.setData('text/html', '<p>pasted</p><img src="x" onerror="window.__pastedScriptRan = true">');
            document.querySelector('[data-test="note-body"] .ql-editor')
                .dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true }));
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
