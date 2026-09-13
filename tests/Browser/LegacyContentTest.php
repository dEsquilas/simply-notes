<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;

/*
 * Markup found in production notes imported from Evernote before the sanitizer existed.
 * Opening and editing those notes in the app must not hide or lose their content.
 */

function legacyNote(string $html): array
{
    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();
    $note = Note::factory()->for($notebook)->create(['title' => 'Legacy']);
    $note->forceFill(['content' => $html])->save();

    return [$user, $notebook, $note];
}

dataset('legacy markup outside Quill blocks', [
    'text inside a div' => ['<div>Text in a div</div>', 'Text in a div'],
    'code block' => ['<div><en-codeblock><div>SELECT * FROM notes;</div></en-codeblock></div>', 'SELECT * FROM notes;'],
    'to-do checkbox' => ['<div><input type="checkbox" checked>Buy milk</div>', 'Buy milk'],
    'svg icon next to text' => ['<div><svg width="10" height="10"><circle r="4"></circle></svg>Visible text</div>', 'Visible text'],
]);

it('shows paragraph content in the editor', function () {
    [$user, $notebook] = legacyNote('<p>Plain paragraph</p>');

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $user, $notebook)
        ->waitFor('@note-body .ql-editor')
        ->waitForTextIn('@note-body', 'Plain paragraph')
    );
});

// BUG-37: Quill only renders known blocks; imported notes wrapped in <div> open as an empty editor
it('shows legacy content in the editor', function (string $html, string $text) {
    [$user, $notebook] = legacyNote($html);

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $user, $notebook)
        ->waitFor('@note-body .ql-editor')
        ->waitForTextIn('@note-body', $text)
    );
})->with('legacy markup outside Quill blocks')->todo();

it('keeps legacy text in the database when only the title is edited', function (string $html, string $text) {
    [$user, $notebook, $note] = legacyNote($html);

    $this->browse(function (Browser $browser) use ($user, $notebook, $note) {
        openNotebookAs($browser, $user, $notebook)->waitFor('@note-title')->pause(1100)
            ->type('@note-title', 'Legacy edited');

        waitForDatabase($browser, fn () => $note->fresh()->title === 'Legacy edited');
    });

    expect(html_entity_decode((string) $note->fresh()->content))->toContain($text);
})->with([
    'paragraph' => ['<p>Plain paragraph</p>', 'Plain paragraph'],
    'text inside a div' => ['<div>Text in a div</div>', 'Text in a div'],
    'to-do checkbox' => ['<div><input type="checkbox" checked>Buy milk</div>', 'Buy milk'],
    'svg icon next to text' => ['<div><svg width="10" height="10"><circle r="4"></circle></svg>Visible text</div>', 'Visible text'],
]);

// BUG-38: the sanitizer drops <en-codeblock> together with its text, so saving the note deletes the code
it('keeps code block text when only the title is edited', function () {
    [$user, $notebook, $note] = legacyNote('<div><en-codeblock><div>SELECT * FROM notes;</div></en-codeblock></div>');

    $this->browse(function (Browser $browser) use ($user, $notebook, $note) {
        openNotebookAs($browser, $user, $notebook)->waitFor('@note-title')->pause(1100)
            ->type('@note-title', 'Legacy edited');

        waitForDatabase($browser, fn () => $note->fresh()->title === 'Legacy edited');
    });

    expect(html_entity_decode((string) $note->fresh()->content))->toContain('SELECT * FROM notes;');
})->todo();

// BUG-38: checkboxes and svg icons of imported to-do lists are removed when the note is saved
it('keeps to-do checkboxes when the note is saved', function () {
    [$user, $notebook, $note] = legacyNote('<div><input type="checkbox" checked>Buy milk</div>');

    $this->browse(function (Browser $browser) use ($user, $notebook, $note) {
        openNotebookAs($browser, $user, $notebook)->waitFor('@note-title')->pause(1100)
            ->type('@note-title', 'Legacy edited');

        waitForDatabase($browser, fn () => $note->fresh()->title === 'Legacy edited');
    });

    expect((string) $note->fresh()->content)->toContain('type="checkbox"');
})->todo();
