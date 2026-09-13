<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
});

it('shows the notebook with its active notes, last updated first', function () {
    $older = Note::factory()->for($this->notebook)->create(['updated_at' => now()->subHour()]);
    $newer = Note::factory()->for($this->notebook)->create(['updated_at' => now()]);
    Note::factory()->for($this->notebook)->trashed()->create();
    Note::factory()->create();

    $this->actingAs($this->user)
        ->get("/notebook/{$this->notebook->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notebooks/View')
            ->where('inNotebook.id', $this->notebook->id)
            ->has('inNotes', 2)
            ->where('inNotes.0.id', $newer->id)
            ->where('inNotes.1.id', $older->id)
            ->missing('currentNote')
        );
});

it('decrypts note titles and contents for the page', function () {
    Note::factory()->for($this->notebook)->create(['title' => 'Shopping', 'content' => '<p>Milk</p>']);

    $this->actingAs($this->user)
        ->get("/notebook/{$this->notebook->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('inNotes.0.title', 'Shopping')
            ->where('inNotes.0.content', '<p>Milk</p>')
        );
});

it('shows an empty notebook', function () {
    $this->actingAs($this->user)
        ->get("/notebook/{$this->notebook->id}")
        ->assertInertia(fn (Assert $page) => $page->has('inNotes', 0));
});

it('redirects to the notebooks list when the notebook is in the trash', function () {
    $this->notebook->delete();

    $this->actingAs($this->user)
        ->get("/notebook/{$this->notebook->id}")
        ->assertRedirect(route('notebooks.index'));
});
