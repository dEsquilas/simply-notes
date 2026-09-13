<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
    $this->actingAs($this->user);
});

it('opens the note from its URL and scrolls the list to it', function () {
    $notes = Note::factory()->count(12)->for($this->notebook)->sequence(fn ($sequence) => [
        'title' => 'Note '.$sequence->index,
        'updated_at' => now()->subMinutes($sequence->index),
    ])->create();
    $target = $notes->last();

    visit("/notebook/{$this->notebook->id}/note/{$target->id}")
        ->assertValue('@note-title', $target->title)
        ->assertScript('document.querySelector(\'[data-test="note-list"]\').scrollTop > 0');
});

it('redirects to the notebooks list when the note belongs to another of the user\'s notebooks', function () {
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->create();

    visit("/notebook/{$this->notebook->id}/note/{$note->id}")->assertPathIs('/notebooks');
});

it('redirects to the notebooks list when opening a trashed notebook', function () {
    $this->notebook->forceFill(['status' => 1])->save();

    visit("/notebook/{$this->notebook->id}")->assertPathIs('/notebooks');
});

it('shows a forbidden page for another user\'s notebook and note', function () {
    $foreignNote = Note::factory()->create();

    visit("/notebook/{$foreignNote->notebook_id}")
        ->assertSee('403')
        ->navigate("/notebook/{$foreignNote->notebook_id}/note/{$foreignNote->id}")
        ->assertSee('403');
});

it('shows a not found page for a note that does not exist', function () {
    visit("/notebook/{$this->notebook->id}/note/999999")->assertSee('404');
});

// BUG-02
it('does not open a trashed note from its URL', function () {
    $note = Note::factory()->for($this->notebook)->trashed()->create();

    visit("/notebook/{$this->notebook->id}/note/{$note->id}")->assertPathIs("/notebook/{$this->notebook->id}");
});

// BUG-03
it('does not open notes of a trashed notebook from their URL', function () {
    $note = Note::factory()->for($this->notebook)->create();
    $this->notebook->forceFill(['status' => 1])->save();

    visit("/notebook/{$this->notebook->id}/note/{$note->id}")->assertPathIs('/notebooks');
});
