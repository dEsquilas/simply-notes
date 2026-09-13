<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Work']);
});

it('shows an empty notebook without an editor', function () {
    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $this->user, $this->notebook)
        ->assertSeeIn('@no-notes', 'No hay notas')
        ->assertMissing('@note-title')
    );
});

it('opens the last updated note first', function () {
    Note::factory()->for($this->notebook)->create(['title' => 'Older', 'updated_at' => now()->subDay()]);
    Note::factory()->for($this->notebook)->create(['title' => 'Latest', 'updated_at' => now()]);

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $this->user, $this->notebook)
        ->waitFor('@note-title')
        ->assertInputValue('@note-title', 'Latest')
        ->assertScript('[...document.querySelectorAll(\'[dusk="note-title-preview"]\')].map(el => el.textContent.trim()).join("|")', 'Latest|Older')
    );
});

it('shows note previews without HTML and a placeholder for untitled notes', function () {
    Note::factory()->for($this->notebook)->create(['title' => '', 'content' => '<p>Hello <strong>World</strong></p>', 'created_at' => '2024-02-03 10:00:00']);

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $this->user, $this->notebook)
        ->waitFor('@note-title-preview')
        ->assertSeeIn('@note-title-preview', 'Nueva nota')
        ->assertSeeIn('@note-content-preview', 'Hello')
        ->assertSeeIn('@note-content-preview', 'World')
        ->assertDontSeeIn('@note-content-preview', '<strong>')
        ->assertSeeIn('@note-date', '3 Feb 2024')
    );
});

it('creates a note and opens it', function () {
    Note::factory()->for($this->notebook)->create(['title' => 'Existing']);

    $this->browse(function (Browser $browser) {
        openNotebookAs($browser, $this->user, $this->notebook)
            ->waitFor('.dusk-new-note')
            ->click('.dusk-new-note');

        waitForDatabase($browser, fn () => Note::count() === 2);
        $created = Note::latest('id')->first();

        $browser->waitFor('@note-'.$created->id)
            ->assertInputValue('@note-title', '')
            ->assertAttribute('@note-title', 'placeholder', 'Nueva nota')
            ->assertScript('document.querySelector(\'[dusk="note-list"] li\').getAttribute("dusk")', 'note-'.$created->id)
            ->assertVisible('.dusk-new-note');
    });
});

// BUG-17
it('lets the user try again when creating a note fails', function () {
    $this->browse(function (Browser $browser) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('.dusk-new-note');
        $this->notebook->forceFill(['owner' => User::factory()->create()->id])->save();

        $browser->click('.dusk-new-note')
            ->pause(1500)
            ->assertVisible('.dusk-new-note')
            ->assertMissing('.dusk-creating-note');
    });
});

it('autosaves the title', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Draft']);

    $this->browse(function (Browser $browser) use ($note) {
        openNotebookAs($browser, $this->user, $this->notebook)
            ->waitFor('@note-title')
            ->pause(1100)
            ->type('@note-title', 'Final title');

        waitForDatabase($browser, fn () => $note->fresh()->title === 'Final title');

        $browser->waitForTextIn('@note-'.$note->id, 'Final title');
    });
});

it('autosaves the content', function () {
    $note = Note::factory()->for($this->notebook)->create(['content' => '']);

    $this->browse(function (Browser $browser) use ($note) {
        openNotebookAs($browser, $this->user, $this->notebook)
            ->waitFor('@note-body .ql-editor')
            ->pause(1100)
            ->click('@note-body .ql-editor')
            ->keys('@note-body .ql-editor', 'Typed in the editor');

        waitForDatabase($browser, fn () => str_contains((string) $note->fresh()->content, 'Typed in the editor'));
    });
});

it('does not save while the user keeps typing', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Start']);

    $this->browse(function (Browser $browser) use ($note) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-title')->pause(1100);

        foreach (str_split('abcdef') as $letter) {
            $browser->append('@note-title', $letter)->pause(400);
        }

        expect($note->fresh()->title)->toBe('Start');

        waitForDatabase($browser, fn () => $note->fresh()->title === 'Startabcdef');
    });
});

it('keeps saved changes after reloading', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Before']);

    $this->browse(function (Browser $browser) use ($note) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-title')->pause(1100)
            ->type('@note-title', 'After reload');

        waitForDatabase($browser, fn () => $note->fresh()->title === 'After reload');

        $browser->refresh()->waitFor('@note-title')->assertInputValue('@note-title', 'After reload');
    });
});

it('saves immediately with Ctrl+S in the editor', function () {
    $note = Note::factory()->for($this->notebook)->create(['content' => '']);

    $this->browse(function (Browser $browser) use ($note) {
        openNotebookAs($browser, $this->user, $this->notebook)
            ->waitFor('@note-body .ql-editor')
            ->pause(1100)
            ->click('@note-body .ql-editor')
            ->keys('@note-body .ql-editor', 'Saved now', ['{control}', 's']);

        waitForDatabase($browser, fn () => str_contains((string) $note->fresh()->content, 'Saved now'), seconds: 1);
    });
});

// BUG-21
it('saves immediately with Ctrl+S in the title', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Old']);

    $this->browse(function (Browser $browser) use ($note) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-title')->pause(1100)
            ->type('@note-title', 'Quick')
            ->keys('@note-title', ['{control}', 's']);

        waitForDatabase($browser, fn () => $note->fresh()->title === 'Quick', seconds: 1);
    });
});

it('switches between notes and updates the URL', function () {
    $first = Note::factory()->for($this->notebook)->create(['title' => 'First', 'updated_at' => now()]);
    $second = Note::factory()->for($this->notebook)->create(['title' => 'Second', 'updated_at' => now()->subHour()]);

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $this->user, $this->notebook)
        ->waitFor('@note-'.$second->id)
        ->assertInputValue('@note-title', 'First')
        ->click('@note-'.$second->id)
        ->waitUsing(3, 100, fn () => $browser->inputValue('@note-title') === 'Second')
        ->assertPathIs("/notebook/{$this->notebook->id}/note/{$second->id}")
        ->click('@note-'.$first->id)
        ->waitUsing(3, 100, fn () => $browser->inputValue('@note-title') === 'First')
    );
});

it('does not overwrite a note just by opening it', function () {
    $first = Note::factory()->for($this->notebook)->create(['title' => 'First', 'updated_at' => now()]);
    $second = Note::factory()->for($this->notebook)->create(['title' => 'Second', 'updated_at' => now()->subHour()]);
    $secondUpdatedAt = $second->fresh()->updated_at;

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $this->user, $this->notebook)
        ->waitFor('@note-'.$second->id)
        ->click('@note-'.$second->id)
        ->pause(4000)
    );

    expect($second->fresh()->updated_at->equalTo($secondUpdatedAt))->toBeTrue();
});

// BUG-06
it('keeps the edits of a note when switching to another before autosave', function () {
    $first = Note::factory()->for($this->notebook)->create(['title' => 'First', 'updated_at' => now()]);
    $second = Note::factory()->for($this->notebook)->create(['title' => 'Second', 'updated_at' => now()->subHour()]);

    $this->browse(function (Browser $browser) use ($first, $second) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-title')->pause(1100)
            ->type('@note-title', 'First edited')
            ->click('@note-'.$second->id);

        waitForDatabase($browser, fn () => $first->fresh()->title === 'First edited');
    });
});

// BUG-07
it('tells the user when a note could not be saved', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Will fail']);

    $this->browse(function (Browser $browser) use ($note) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-title')->pause(1100);
        $note->delete();

        $browser->type('@note-title', 'Lost change')
            ->waitForText('could not be saved', 6);
    });
});

// BUG-07
it('warns the user when the session expired before saving', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Session']);

    $this->browse(function (Browser $browser) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-title')->pause(1100);
        $browser->driver->manage()->deleteAllCookies();

        $browser->type('@note-title', 'After expiry')
            ->waitForText('session', 6);
    });
});

it('filters notes by title and by content, and clears the search', function () {
    $groceries = Note::factory()->for($this->notebook)->create(['title' => 'Groceries', 'content' => '<p>milk</p>']);
    $meeting = Note::factory()->for($this->notebook)->create(['title' => 'Meeting', 'content' => '<p>budget review</p>']);

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $this->user, $this->notebook)
        ->waitFor('@note-'.$groceries->id)
        ->assertMissing('.dusk-clear-search')
        ->type('@note-search', 'groc')
        ->waitUntilMissing('@note-'.$meeting->id)
        ->assertVisible('@note-'.$groceries->id)
        ->type('@note-search', 'BUDGET')
        ->waitUntilMissing('@note-'.$groceries->id)
        ->assertVisible('@note-'.$meeting->id)
        ->type('@note-search', 'nothing matches')
        ->waitUntilMissing('@note-'.$meeting->id)
        ->assertMissing('@note-'.$groceries->id)
        ->click('.dusk-clear-search')
        ->waitFor('@note-'.$groceries->id)
        ->assertVisible('@note-'.$meeting->id)
        ->assertInputValue('@note-search', '')
    );
});

// BUG-13
it('does not match HTML markup when searching', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Formatted', 'content' => '<p><strong>bold words</strong></p>']);

    $this->browse(fn (Browser $browser) => openNotebookAs($browser, $this->user, $this->notebook)
        ->waitFor('@note-'.$note->id)
        ->type('@note-search', 'strong')
        ->waitUntilMissing('@note-'.$note->id)
    );
});

it('sends the open note to the trash and opens the next one', function () {
    $open = Note::factory()->for($this->notebook)->create(['title' => 'Open', 'updated_at' => now()]);
    $next = Note::factory()->for($this->notebook)->create(['title' => 'Next', 'updated_at' => now()->subHour()]);

    $this->browse(function (Browser $browser) use ($open) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-'.$open->id);

        deleteFromContextMenu($browser, '@note-'.$open->id)
            ->waitUntilMissing('@note-'.$open->id)
            ->waitUsing(3, 100, fn () => $browser->inputValue('@note-title') === 'Next');

        waitForDatabase($browser, fn () => $open->fresh()->status === 1);
    });
});

it('sends a note that is not open to the trash without changing the editor', function () {
    $open = Note::factory()->for($this->notebook)->create(['title' => 'Open', 'updated_at' => now()]);
    $other = Note::factory()->for($this->notebook)->create(['title' => 'Other', 'updated_at' => now()->subHour()]);

    $this->browse(function (Browser $browser) use ($other) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-'.$other->id);

        deleteFromContextMenu($browser, '@note-'.$other->id)
            ->waitUntilMissing('@note-'.$other->id)
            ->assertInputValue('@note-title', 'Open');

        waitForDatabase($browser, fn () => $other->fresh()->status === 1);
    });
});

it('shows the empty state after trashing the last note', function () {
    $only = Note::factory()->for($this->notebook)->create(['title' => 'Only']);

    $this->browse(fn (Browser $browser) => deleteFromContextMenu(
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-'.$only->id),
        '@note-'.$only->id
    )
        ->waitFor('@no-notes')
        ->assertMissing('@note-title')
    );
});

// BUG-18
it('keeps the note listed when sending it to the trash fails', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Stays']);

    $this->browse(function (Browser $browser) use ($note) {
        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-'.$note->id);
        $note->delete();

        deleteFromContextMenu($browser, '@note-'.$note->id)
            ->pause(1000)
            ->assertVisible('@note-'.$note->id);
    });
});

// BUG-14
it('lets the user restore a trashed note', function () {
    $note = Note::factory()->for($this->notebook)->trashed()->create(['title' => 'Recover me']);

    $this->browse(function (Browser $browser) use ($note) {
        $browser->loginAs($this->user)
            ->visit('/notebooks/trash')
            ->waitFor('@trashed-note-'.$note->id)
            ->assertSeeIn('@trashed-note-'.$note->id, 'Recover me')
            ->assertSeeIn('@trashed-note-'.$note->id, 'Work')
            ->assertMissing('@trash-empty')
            ->click('@trashed-note-'.$note->id.' .dusk-restore-note')
            ->waitUntilMissing('@trashed-note-'.$note->id);

        waitForDatabase($browser, fn () => $note->fresh()->status === 0);

        openNotebookAs($browser, $this->user, $this->notebook)->waitFor('@note-'.$note->id);
    });
});

it('keeps the note in the trash when restoring it fails', function () {
    $note = Note::factory()->for($this->notebook)->trashed()->create(['title' => 'Stuck']);

    $this->browse(function (Browser $browser) use ($note) {
        $browser->loginAs($this->user)->visit('/notebooks/trash')->waitFor('@trashed-note-'.$note->id);
        $note->delete();

        $browser->click('@trashed-note-'.$note->id.' .dusk-restore-note')
            ->waitForText('Failed to restore note')
            ->assertVisible('@trashed-note-'.$note->id);
    });
});
