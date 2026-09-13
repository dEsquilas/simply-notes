<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Support\EvernoteExport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
    $this->note = Note::factory()->for($this->notebook)->create(['title' => 'Formatting', 'content' => '<p>Some text</p>']);
});

function openEditor(Browser $browser, $test): Browser
{
    return openNotebookAs($browser, $test->user, $test->notebook)
        ->waitFor('@note-body .ql-toolbar')
        ->pause(1100);
}

it('applies toolbar formats and saves them', function (string $button, string $expectedHtml) {
    $this->browse(function (Browser $browser) use ($button, $expectedHtml) {
        selectAllInEditor(openEditor($browser, $this))->click('@note-body '.$button);

        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, $expectedHtml));
    });
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

    $this->browse(function (Browser $browser) {
        selectAllInEditor(openEditor($browser, $this))->click('@note-body .ql-indent[value="+1"]');
        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, 'ql-indent-1'));

        selectAllInEditor($browser)->click('@note-body .ql-indent[value="-1"]');
        waitForDatabase($browser, fn () => ! str_contains((string) $this->note->fresh()->content, 'ql-indent-1'));
    });
});

it('changes the text size', function () {
    $this->browse(function (Browser $browser) {
        selectAllInEditor(openEditor($browser, $this))
            ->click('@note-body .ql-size .ql-picker-label')
            ->waitFor('@note-body .ql-size .ql-picker-item[data-value="large"]')
            ->click('@note-body .ql-size .ql-picker-item[data-value="large"]');

        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, 'ql-size-large'));
    });
});

it('removes formatting', function () {
    $this->note->forceFill(['content' => '<p><strong>Bold</strong></p>'])->save();

    $this->browse(function (Browser $browser) {
        selectAllInEditor(openEditor($browser, $this))->click('@note-body .ql-clean');

        waitForDatabase($browser, fn () => ! str_contains((string) $this->note->fresh()->content, '<strong>'));
    });
});

it('adds a link', function () {
    $this->browse(function (Browser $browser) {
        // The link button asks for the URL with the browser's prompt dialog
        selectAllInEditor(openEditor($browser, $this))
            ->click('@note-body .ql-link')
            ->waitForDialog()
            ->assertDialogOpened('Enter link URL:')
            ->typeInDialog('https://example.com/page')
            ->acceptDialog();

        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, 'href="https://example.com/page"'));
    });
});

it('embeds https videos', function () {
    $this->browse(function (Browser $browser) {
        openEditor($browser, $this)
            ->click('@note-body .ql-editor')
            ->click('@note-body .ql-video')
            ->waitForDialog()
            ->typeInDialog('https://player.vimeo.com/video/76979871')
            ->acceptDialog();

        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));
    });

    // Only YouTube links are rewritten: other embeddable URLs are stored as given
    expect(html_entity_decode($this->note->fresh()->content))->toContain('src="https://player.vimeo.com/video/76979871"');
});

// BUG-36: the video prompt keeps YouTube "watch" URLs, which YouTube refuses to show inside an iframe
it('turns YouTube links into embeddable videos', function (string $url) {
    $this->browse(function (Browser $browser) use ($url) {
        openEditor($browser, $this)
            ->click('@note-body .ql-editor')
            ->click('@note-body .ql-video')
            ->waitForDialog()
            ->typeInDialog($url)
            ->acceptDialog();

        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));
    });

    expect(html_entity_decode($this->note->fresh()->content))->toContain('src="https://www.youtube.com/embed/dQw4w9WgXcQ"');
})->with([
    'watch' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'watch with other parameters' => 'https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42',
    'short link' => 'https://youtu.be/dQw4w9WgXcQ',
    'shorts' => 'https://youtube.com/shorts/dQw4w9WgXcQ',
    'mobile' => 'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
]);

it('saves insecure http videos without a source', function () {
    $this->browse(function (Browser $browser) {
        openEditor($browser, $this)
            ->click('@note-body .ql-editor')
            ->click('@note-body .ql-video')
            ->waitForDialog()
            ->typeInDialog('http://example.com/video.mp4')
            ->acceptDialog();

        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, '<iframe'));
    });

    expect($this->note->fresh()->content)->not->toContain('http://example.com/video.mp4');
});

it('inserts a table', function () {
    $this->browse(function (Browser $browser) {
        openEditor($browser, $this)
            ->click('@note-body .ql-editor')
            ->click('@note-body .ql-table');

        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, '<table'));
    });
});

it('uploads an image from the toolbar', function () {
    $png = EvernoteExport::PNG;

    $this->browse(function (Browser $browser) use ($png) {
        openEditor($browser, $this)->click('@note-body .ql-editor');

        // The toolbar creates a file input that never joins the page: capture it and give it a file.
        // The change event is async, like a real file dialog, because the handler is attached after click().
        $browser->script(<<<JS
            const originalClick = HTMLInputElement.prototype.click;
            HTMLInputElement.prototype.click = function () {
                if (this.type !== 'file') { return originalClick.call(this); }
                const bytes = Uint8Array.from(atob('{$png}'), c => c.charCodeAt(0));
                const transfer = new DataTransfer();
                transfer.items.add(new File([bytes], 'pixel.png', { type: 'image/png' }));
                this.files = transfer.files;
                setTimeout(() => this.dispatchEvent(new Event('change')), 50);
            };
        JS);

        $browser->click('@note-body .ql-image');

        waitForDatabase($browser, fn () => str_contains(html_entity_decode((string) $this->note->fresh()->content), 'data:image/png;base64,'.$png));
    });
});

it('does not insert an image when no file is chosen', function () {
    $this->browse(function (Browser $browser) {
        openEditor($browser, $this)->click('@note-body .ql-editor');

        $browser->script(<<<'JS'
            HTMLInputElement.prototype.click = function () {
                if (this.type === 'file') { setTimeout(() => this.dispatchEvent(new Event('change')), 50); }
            };
        JS);

        $browser->click('@note-body .ql-image')->pause(3500);
    });

    expect($this->note->fresh()->content)->toBe('<p>Some text</p>');
});

it('never runs scripts pasted into a note', function () {
    $this->browse(function (Browser $browser) {
        openEditor($browser, $this)->click('@note-body .ql-editor');

        $browser->script(<<<'JS'
            const data = new DataTransfer();
            data.setData('text/html', '<p>pasted</p><img src="x" onerror="window.__pastedScriptRan = true">');
            document.querySelector('[dusk="note-body"] .ql-editor')
                .dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true }));
        JS);

        waitForDatabase($browser, fn () => str_contains((string) $this->note->fresh()->content, 'pasted'));

        $browser->refresh()->waitFor('@note-body .ql-editor')->pause(500)
            ->assertScript('window.__pastedScriptRan === true', false);
    });

    expect($this->note->fresh()->content)->not->toContain('onerror');
});

// BUG-35 regression: notes stored before the sanitizer must not run their scripts when opened
it('never runs scripts stored in notes saved before sanitizing existed', function () {
    // Written straight to the database, the way notes saved before the hotfix may still be
    $this->note->forceFill(['content' => '<p>legacy</p><img src="x" onerror="window.__legacyScriptRan = true">'])->save();

    $this->browse(fn (Browser $browser) => openEditor($browser, $this)
        ->pause(500)
        ->assertScript('window.__legacyScriptRan === true', false)
    );
});
