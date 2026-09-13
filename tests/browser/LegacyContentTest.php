<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

/*
 * Markup found in production notes imported from Evernote before the sanitizer existed.
 * Opening and editing those notes in the app must not hide or lose their content (BUG-37, BUG-38).
 */

const LEGACY_EDITOR = '[data-test="note-body"] .tiptap-content';
// Excludes the task item's visually-hidden accessibility label, which also contains the item's text
const LEGACY_TEXT = '[data-test="note-body"] .tiptap-content p';

/** Stores a note with this raw HTML, logs its owner in and returns the note and its notebook URL. */
function legacyNote(string $html): array
{
    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();
    $note = Note::factory()->for($notebook)->create(['title' => 'Legacy']);
    $note->forceFill(['content' => $html])->save();
    test()->actingAs($user);

    return [$note, "/notebook/{$notebook->id}"];
}

dataset('legacy markup', [
    'paragraph' => ['<p>Plain paragraph</p>', 'Plain paragraph'],
    'text inside a div' => ['<div>Text in a div</div>', 'Text in a div'],
    'code block' => ['<div><en-codeblock><div>SELECT * FROM notes;</div><div>WHERE id = 1</div></en-codeblock></div>', 'SELECT * FROM notes;'],
    'to-do checkbox' => ['<div><input type="checkbox" checked>Buy milk</div>', 'Buy milk'],
    'svg icon next to text' => ['<div><svg width="10" height="10"><circle r="4"></circle></svg>Visible text</div>', 'Visible text'],
]);

it('shows legacy content in the editor', function (string $html, string $text) {
    [, $url] = legacyNote($html);

    visit($url)->assertSeeIn(LEGACY_TEXT, $text);
})->with('legacy markup');

it('does not save a legacy note just by opening it', function () {
    [$note, $url] = legacyNote('<div>Text in a div</div>');
    $before = $note->fresh()->updated_at;

    visit($url)
        ->assertSeeIn('@note-body', 'Text in a div')
        // Autosave would have fired within 3 s
        ->wait(3.1);

    expect($note->fresh()->updated_at->equalTo($before))->toBeTrue()
        ->and($note->fresh()->content)->toBe('<div>Text in a div</div>');
});

it('keeps legacy text when only the title is edited', function (string $html, string $text) {
    [$note, $url] = legacyNote($html);

    $page = visit($url);
    typeLikeAUser(readyToEdit($page), '@note-title', 'Legacy edited');

    waitForDatabase($page, fn () => $note->fresh()->title === 'Legacy edited');

    expect(html_entity_decode((string) $note->fresh()->content))->toContain($text);
})->with('legacy markup');

it('keeps legacy text when writing in the note body', function (string $html, string $text) {
    [$note, $url] = legacyNote($html);

    $page = visit($url);
    readyToEdit($page->assertSeeIn(LEGACY_TEXT, $text))
        ->click(LEGACY_EDITOR)
        ->keys(LEGACY_EDITOR, 'Control+End')
        ->typeSlowly(LEGACY_EDITOR, ' added later', 10);

    waitForDatabase($page, fn () => str_contains((string) $note->fresh()->content, 'added later'));

    expect(html_entity_decode((string) $note->fresh()->content))->toContain($text);
})->with('legacy markup');

it('saves imported to-dos as Tiptap task list items', function () {
    [$note, $url] = legacyNote('<div><input type="checkbox" checked>Buy milk</div><div><input type="checkbox">Buy eggs</div>');

    $page = visit($url);
    typeLikeAUser(readyToEdit($page->assertSeeIn(LEGACY_TEXT, 'Buy eggs')), '@note-title', 'To-dos');

    waitForDatabase($page, fn () => $note->fresh()->title === 'To-dos');

    expect((string) $note->fresh()->content)
        ->toContain('data-checked="true"')
        ->toContain('data-checked="false"')
        ->toContain('Buy milk')
        ->toContain('Buy eggs');
});
