<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

const NOTE_EDITOR = '[data-test="note-body"] .ql-editor';

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Work']);
    $this->url = "/notebook/{$this->notebook->id}";
    $this->actingAs($this->user);
});

it('shows an empty notebook without an editor', function () {
    visit($this->url)
        ->assertSeeIn('@no-notes', 'No hay notas')
        ->assertMissing('@note-title');
});

it('opens the last updated note first', function () {
    Note::factory()->for($this->notebook)->create(['title' => 'Older', 'updated_at' => now()->subDay()]);
    Note::factory()->for($this->notebook)->create(['title' => 'Latest', 'updated_at' => now()]);

    visit($this->url)
        ->assertValue('@note-title', 'Latest')
        ->assertScript('[...document.querySelectorAll(\'[data-test="note-title-preview"]\')].map(el => el.textContent.trim()).join("|")', 'Latest|Older');
});

it('shows note previews without HTML and a placeholder for untitled notes', function () {
    Note::factory()->for($this->notebook)->create(['title' => '', 'content' => '<p>Hello <strong>World</strong></p>', 'created_at' => '2024-02-03 10:00:00']);

    visit($this->url)
        ->assertSeeIn('@note-title-preview', 'Nueva nota')
        ->assertSeeIn('@note-content-preview', 'Hello')
        ->assertSeeIn('@note-content-preview', 'World')
        ->assertDontSeeIn('@note-content-preview', '<strong>')
        ->assertSeeIn('@note-date', '3 Feb 2024');
});

it('creates a note and opens it', function () {
    Note::factory()->for($this->notebook)->create(['title' => 'Existing']);

    $page = visit($this->url)->click('.test-new-note');

    waitForDatabase($page, fn () => Note::count() === 2);
    $created = Note::latest('id')->first();

    $page->assertVisible('@note-'.$created->id)
        ->assertValue('@note-title', '')
        ->assertAttribute('@note-title', 'placeholder', 'Nueva nota')
        ->assertScript('document.querySelector(\'[data-test="note-list"] li\').dataset.test', 'note-'.$created->id)
        ->assertVisible('.test-new-note');
});

// BUG-17
it('lets the user try again when creating a note fails', function () {
    $page = visit($this->url)->assertVisible('.test-new-note');
    $this->notebook->forceFill(['owner' => User::factory()->create()->id])->save();

    $page->click('.test-new-note')
        ->assertSee('The note could not be created')
        ->assertVisible('.test-new-note')
        ->assertMissing('.test-creating-note');
});

it('autosaves the title', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Draft']);

    $page = visit($this->url);
    typeLikeAUser(readyToEdit($page), '@note-title', 'Final title');

    waitForDatabase($page, fn () => $note->fresh()->title === 'Final title');

    $page->assertSeeIn('@note-'.$note->id, 'Final title');
});

it('autosaves the content', function () {
    $note = Note::factory()->for($this->notebook)->create(['content' => '']);

    $page = visit($this->url);
    readyToEdit($page)
        ->click(NOTE_EDITOR)
        ->typeSlowly(NOTE_EDITOR, 'Typed in the editor', 10);

    waitForDatabase($page, fn () => str_contains((string) $note->fresh()->content, 'Typed in the editor'));
});

it('does not save while the user keeps typing', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Start']);

    $page = visit($this->url);
    readyToEdit($page)->click('@note-title')->keys('@note-title', 'End');

    foreach (str_split('abcdef') as $letter) {
        typeLikeAUser($page, '@note-title', $letter, replace: false)->wait(0.4);
    }

    expect($note->fresh()->title)->toBe('Start');

    waitForDatabase($page, fn () => $note->fresh()->title === 'Startabcdef');
});

it('keeps saved changes after reloading', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Before']);

    $page = visit($this->url);
    typeLikeAUser(readyToEdit($page), '@note-title', 'After reload');

    waitForDatabase($page, fn () => $note->fresh()->title === 'After reload');

    $page->refresh()->assertValue('@note-title', 'After reload');
});

it('saves immediately with Ctrl+S in the editor', function () {
    $note = Note::factory()->for($this->notebook)->create(['content' => '']);

    $page = visit($this->url);
    readyToEdit($page)
        ->click(NOTE_EDITOR)
        ->typeSlowly(NOTE_EDITOR, 'Saved now', 10)
        ->keys(NOTE_EDITOR, 'Control+s');

    waitForDatabase($page, fn () => str_contains((string) $note->fresh()->content, 'Saved now'), seconds: 1);
});

// BUG-21
it('saves immediately with Ctrl+S in the title', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Old']);

    $page = visit($this->url);
    typeLikeAUser(readyToEdit($page), '@note-title', 'Quick')->keys('@note-title', 'Control+s');

    waitForDatabase($page, fn () => $note->fresh()->title === 'Quick', seconds: 1);
});

it('switches between notes and updates the URL', function () {
    $first = Note::factory()->for($this->notebook)->create(['title' => 'First', 'updated_at' => now()]);
    $second = Note::factory()->for($this->notebook)->create(['title' => 'Second', 'updated_at' => now()->subHour()]);

    visit($this->url)
        ->assertValue('@note-title', 'First')
        ->click('@note-'.$second->id)
        ->assertValue('@note-title', 'Second')
        ->assertPathIs("/notebook/{$this->notebook->id}/note/{$second->id}")
        ->click('@note-'.$first->id)
        ->assertValue('@note-title', 'First');
});

it('does not overwrite a note just by opening it', function () {
    Note::factory()->for($this->notebook)->create(['title' => 'First', 'updated_at' => now()]);
    $second = Note::factory()->for($this->notebook)->create(['title' => 'Second', 'updated_at' => now()->subHour()]);
    $secondUpdatedAt = $second->fresh()->updated_at;

    visit($this->url)
        ->click('@note-'.$second->id)
        ->assertValue('@note-title', 'Second')
        // Autosave would have fired within 3 s
        ->wait(3.1);

    expect($second->fresh()->updated_at->equalTo($secondUpdatedAt))->toBeTrue();
});

// BUG-06
it('keeps the edits of a note when switching to another before autosave', function () {
    $first = Note::factory()->for($this->notebook)->create(['title' => 'First', 'updated_at' => now()]);
    $second = Note::factory()->for($this->notebook)->create(['title' => 'Second', 'updated_at' => now()->subHour()]);

    $page = visit($this->url);
    typeLikeAUser(readyToEdit($page), '@note-title', 'First edited')->click('@note-'.$second->id);

    waitForDatabase($page, fn () => $first->fresh()->title === 'First edited');

    expect($second->fresh()->title)->toBe('Second');
});

// BUG-07
it('tells the user when a note could not be saved', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Will fail']);

    $page = visit($this->url);
    readyToEdit($page);
    $note->delete();

    typeLikeAUser($page, '@note-title', 'Lost change')->assertSee('The note could not be saved');
});

// BUG-07
it('warns the user when the session expired before saving', function () {
    Note::factory()->for($this->notebook)->create(['title' => 'Session']);

    $page = visit($this->url);
    readyToEdit($page);
    auth()->logout();

    typeLikeAUser($page, '@note-title', 'After expiry')->assertSee('Your session has expired');
});

it('filters notes by title and by content, and clears the search', function () {
    $groceries = Note::factory()->for($this->notebook)->create(['title' => 'Groceries', 'content' => '<p>milk</p>']);
    $meeting = Note::factory()->for($this->notebook)->create(['title' => 'Meeting', 'content' => '<p>budget review</p>']);

    visit($this->url)
        ->assertVisible('@note-'.$groceries->id)
        ->assertMissing('.test-clear-search')
        ->type('@note-search', 'groc')
        ->assertMissing('@note-'.$meeting->id)
        ->assertVisible('@note-'.$groceries->id)
        ->type('@note-search', 'BUDGET')
        ->assertMissing('@note-'.$groceries->id)
        ->assertVisible('@note-'.$meeting->id)
        ->type('@note-search', 'nothing matches')
        ->assertMissing('@note-'.$meeting->id)
        ->assertMissing('@note-'.$groceries->id)
        ->click('.test-clear-search')
        ->assertVisible('@note-'.$groceries->id)
        ->assertVisible('@note-'.$meeting->id)
        ->assertValue('@note-search', '');
});

// BUG-13
it('does not match HTML markup when searching', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Formatted', 'content' => '<p><strong>bold words</strong></p>']);

    visit($this->url)
        ->type('@note-search', 'bold')
        ->assertVisible('@note-'.$note->id)
        ->type('@note-search', 'strong')
        ->assertMissing('@note-'.$note->id);
});

it('sends the open note to the trash and opens the next one', function () {
    $open = Note::factory()->for($this->notebook)->create(['title' => 'Open', 'updated_at' => now()]);
    Note::factory()->for($this->notebook)->create(['title' => 'Next', 'updated_at' => now()->subHour()]);

    $page = visit($this->url);
    deleteFromContextMenu($page, 'note-'.$open->id)
        ->assertMissing('@note-'.$open->id)
        ->assertValue('@note-title', 'Next');

    waitForDatabase($page, fn () => $open->fresh()->status === 1);
});

it('sends a note that is not open to the trash without changing the editor', function () {
    Note::factory()->for($this->notebook)->create(['title' => 'Open', 'updated_at' => now()]);
    $other = Note::factory()->for($this->notebook)->create(['title' => 'Other', 'updated_at' => now()->subHour()]);

    $page = visit($this->url);
    deleteFromContextMenu($page, 'note-'.$other->id)
        ->assertMissing('@note-'.$other->id)
        ->assertValue('@note-title', 'Open');

    waitForDatabase($page, fn () => $other->fresh()->status === 1);
});

it('shows the empty state after trashing the last note', function () {
    $only = Note::factory()->for($this->notebook)->create(['title' => 'Only']);

    $page = visit($this->url);

    deleteFromContextMenu($page, 'note-'.$only->id)
        ->assertVisible('@no-notes')
        ->assertMissing('@note-title');
});

// BUG-18
it('keeps the note listed when sending it to the trash fails', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Stays']);

    $page = visit($this->url)->assertVisible('@note-'.$note->id);
    $note->delete();

    deleteFromContextMenu($page, 'note-'.$note->id)
        ->assertSee('The note could not be sent to the trash')
        ->assertVisible('@note-'.$note->id);
});

// BUG-14
it('lets the user restore a trashed note', function () {
    $note = Note::factory()->for($this->notebook)->trashed()->create(['title' => 'Recover me']);

    $page = visit('/notebooks/trash')
        ->assertSeeIn('@trashed-note-'.$note->id, 'Recover me')
        ->assertSeeIn('@trashed-note-'.$note->id, 'Work')
        ->assertMissing('@trash-empty')
        ->click('[data-test="trashed-note-'.$note->id.'"] .test-restore-note')
        ->assertMissing('@trashed-note-'.$note->id);

    waitForDatabase($page, fn () => $note->fresh()->status === 0);

    $page->navigate("/notebook/{$this->notebook->id}")->assertVisible('@note-'.$note->id);
});

it('keeps the note in the trash when restoring it fails', function () {
    $note = Note::factory()->for($this->notebook)->trashed()->create(['title' => 'Stuck']);

    $page = visit('/notebooks/trash')->assertVisible('@trashed-note-'.$note->id);
    $note->delete();

    $page->click('[data-test="trashed-note-'.$note->id.'"] .test-restore-note')
        ->assertSee('Failed to restore note')
        ->assertVisible('@trashed-note-'.$note->id);
});
