<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
    $this->note = Note::factory()->for($this->notebook)->create(['title' => 'Selected', 'updated_at' => now()->subDay()]);
});

it('opens a note inside its notebook', function () {
    $sibling = Note::factory()->for($this->notebook)->create(['updated_at' => now()]);
    Note::factory()->for($this->notebook)->trashed()->create();

    $this->actingAs($this->user)
        ->get("/notebook/{$this->notebook->id}/note/{$this->note->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notebooks/View')
            ->where('inNotebook.id', $this->notebook->id)
            ->where('currentNote.id', $this->note->id)
            ->where('currentNote.title', 'Selected')
            ->has('inNotes', 2)
            ->where('inNotes.0.id', $sibling->id)
            ->where('inNotes.1.id', $this->note->id)
        );
});

it('redirects when the URL mixes a note with another of the user\'s notebooks', function () {
    $otherNotebook = Notebook::factory()->ownedBy($this->user)->create();

    $this->actingAs($this->user)
        ->get("/notebook/{$otherNotebook->id}/note/{$this->note->id}")
        ->assertRedirect(route('notebooks.index'));
});

// BUG-02
it('does not open a trashed note by URL', function () {
    $this->note->forceFill(['status' => 1])->save();

    $this->actingAs($this->user)
        ->get("/notebook/{$this->notebook->id}/note/{$this->note->id}")
        ->assertRedirect(route('notebook.view', $this->notebook->id));
});

// BUG-03
it('does not open a note of a trashed notebook by URL', function () {
    $this->notebook->forceFill(['status' => 1])->save();

    $this->actingAs($this->user)
        ->get("/notebook/{$this->notebook->id}/note/{$this->note->id}")
        ->assertRedirect(route('notebooks.index'));
});
