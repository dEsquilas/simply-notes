<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('lists the user\'s active notebooks, newest first', function () {
    $older = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Older', 'created_at' => now()->subDay()]);
    $newer = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Newer', 'created_at' => now()]);
    Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'Trashed']);

    $this->actingAs($this->user)
        ->get('/notebooks')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notebooks/List')
            ->has('notebooks', 2)
            ->where('notebooks.0.id', $newer->id)
            ->where('notebooks.1.id', $older->id)
        );
});

it('shows an empty list when the user has no notebooks', function () {
    $this->actingAs($this->user)
        ->get('/notebooks')
        ->assertInertia(fn (Assert $page) => $page->component('notebooks/List')->has('notebooks', 0));
});

it('shows how many notes each notebook has', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create();
    Note::factory()->count(3)->for($notebook)->create();

    $this->actingAs($this->user)
        ->get('/notebooks')
        ->assertInertia(fn (Assert $page) => $page->where('notebooks.0.notes_count', 3));
});

// BUG-25
it('does not count trashed notes', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create();
    Note::factory()->count(2)->for($notebook)->create();
    Note::factory()->trashed()->for($notebook)->create();

    $this->actingAs($this->user)
        ->get('/notebooks')
        ->assertInertia(fn (Assert $page) => $page->where('notebooks.0.notes_count', 2));
});
