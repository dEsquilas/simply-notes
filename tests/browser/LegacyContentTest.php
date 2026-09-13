<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;

/*
 * Markup found in production notes imported from Evernote before the sanitizer existed.
 * Opening and editing those notes in the app must not hide or lose their content (BUG-37, BUG-38).
 */

function legacyNote(string $html): array
{
    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();
    $note = Note::factory()->for($notebook)->create(['title' => 'Legacy']);
    $note->forceFill(['content' => $html])->save();

    return [$user, $notebook, $note];
}

dataset('legacy markup', [
    'paragraph' => ['<p>Plain paragraph</p>', 'Plain paragraph'],
    'text inside a div' => ['<div>Text in a div</div>', 'Text in a div'],
    'code block' => ['<div><en-codeblock><div>SELECT * FROM notes;</div><div>WHERE id = 1</div></en-codeblock></div>', 'SELECT * FROM notes;'],
    'to-do checkbox' => ['<div><input type="checkbox" checked>Buy milk</div>', 'Buy milk'],
    'svg icon next to text' => ['<div><svg width="10" height="10"><circle r="4"></circle></svg>Visible text</div>', 'Visible text'],
]);

it('shows legacy content in the editor', function (string $html, string $text) {
    [$user, $notebook] = legacyNote($html);

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $user, $notebook)
        ->waitFor('@note-body .ql-editor')
        ->waitForTextIn('@note-body', $text)
    );
})->with('legacy markup');

it('does not save a legacy note just by opening it', function () {
    [$user, $notebook, $note] = legacyNote('<div>Text in a div</div>');
    $before = $note->fresh()->updated_at;

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $user, $notebook)
        ->waitForTextIn('@note-body', 'Text in a div')
        ->pause(4000)
    );

    expect($note->fresh()->updated_at->equalTo($before))->toBeTrue()
        ->and($note->fresh()->content)->toBe('<div>Text in a div</div>');
});

it('keeps legacy text when only the title is edited', function (string $html, string $text) {
    [$user, $notebook, $note] = legacyNote($html);

    $this->browse(function (Browser $browser) use ($user, $notebook, $note) {
        openNotebookAs($browser, $user, $notebook)->waitFor('@note-title')->pause(1100)
            ->type('@note-title', 'Legacy edited');

        waitForDatabase($browser, fn () => $note->fresh()->title === 'Legacy edited');
    });

    expect(html_entity_decode((string) $note->fresh()->content))->toContain($text);
})->with('legacy markup');

it('keeps legacy text when writing in the note body', function (string $html, string $text) {
    [$user, $notebook, $note] = legacyNote($html);

    $this->browse(function (Browser $browser) use ($user, $notebook, $note, $text) {
        openNotebookAs($browser, $user, $notebook)
            ->waitForTextIn('@note-body', $text)
            ->pause(1100)
            ->click('@note-body .ql-editor')
            ->keys('@note-body .ql-editor', '{end}', ' added later');

        waitForDatabase($browser, fn () => str_contains((string) $note->fresh()->content, 'added later'));
    });

    expect(html_entity_decode((string) $note->fresh()->content))->toContain($text);
})->with('legacy markup');

it('saves imported to-dos as Quill checklist items', function () {
    [$user, $notebook, $note] = legacyNote('<div><input type="checkbox" checked>Buy milk</div><div><input type="checkbox">Buy eggs</div>');

    $this->browse(function (Browser $browser) use ($user, $notebook, $note) {
        openNotebookAs($browser, $user, $notebook)->waitForTextIn('@note-body', 'Buy eggs')->pause(1100)
            ->type('@note-title', 'To-dos');

        waitForDatabase($browser, fn () => $note->fresh()->title === 'To-dos');
    });

    expect((string) $note->fresh()->content)
        ->toContain('<li data-list="checked">')
        ->toContain('<li data-list="unchecked">')
        ->toContain('Buy milk')
        ->toContain('Buy eggs');
});
