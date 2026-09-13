<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
});

it('opens the note from its URL and scrolls the list to it', function () {
    $notes = Note::factory()->count(12)->for($this->notebook)->sequence(fn ($sequence) => [
        'title' => 'Note '.$sequence->index,
        'updated_at' => now()->subMinutes($sequence->index),
    ])->create();
    $target = $notes->last();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit("/notebook/{$this->notebook->id}/note/{$target->id}")
        ->waitFor('@note-title')
        ->assertInputValue('@note-title', $target->title)
        ->assertScript('document.querySelector(\'[dusk="note-list"]\').scrollTop > 0')
    );
});

it('redirects to the notebooks list when the note belongs to another of the user\'s notebooks', function () {
    $other = Notebook::factory()->ownedBy($this->user)->create();
    $note = Note::factory()->for($other)->create();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit("/notebook/{$this->notebook->id}/note/{$note->id}")
        ->waitForLocation('/notebooks')
    );
});

it('redirects to the notebooks list when opening a trashed notebook', function () {
    $this->notebook->forceFill(['status' => 1])->save();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit("/notebook/{$this->notebook->id}")
        ->waitForLocation('/notebooks')
    );
});

it('shows a forbidden page for another user\'s notebook and note', function () {
    $foreignNote = Note::factory()->create();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit("/notebook/{$foreignNote->notebook_id}")
        ->assertSee('403')
        ->visit("/notebook/{$foreignNote->notebook_id}/note/{$foreignNote->id}")
        ->assertSee('403')
    );
});

it('shows a not found page for a note that does not exist', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit("/notebook/{$this->notebook->id}/note/999999")
        ->assertSee('404')
    );
});

// BUG-02
it('does not open a trashed note from its URL', function () {
    $note = Note::factory()->for($this->notebook)->trashed()->create();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit("/notebook/{$this->notebook->id}/note/{$note->id}")
        ->waitForLocation("/notebook/{$this->notebook->id}")
    );
})->todo();

// BUG-03
it('does not open notes of a trashed notebook from their URL', function () {
    $note = Note::factory()->for($this->notebook)->create();
    $this->notebook->forceFill(['status' => 1])->save();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit("/notebook/{$this->notebook->id}/note/{$note->id}")
        ->waitForLocation('/notebooks')
    );
})->todo();
