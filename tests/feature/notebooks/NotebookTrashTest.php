<?php

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('sends a notebook to the trash', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create();

    $this->postJson("/notebooks/trash/{$notebook->id}")
        ->assertOk()
        ->assertExactJson(['message' => 'Notebook deleted successfully']);

    expect($notebook->fresh()->status)->toBe(1);
});

it('keeps the notes of a trashed notebook', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create();
    Note::factory()->count(2)->for($notebook)->create();

    $this->postJson("/notebooks/trash/{$notebook->id}")->assertOk();

    expect(Note::where('notebook_id', $notebook->id)->count())->toBe(2);
});

it('trashes an already trashed notebook without errors', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $this->postJson("/notebooks/trash/{$notebook->id}")->assertOk();

    expect($notebook->fresh()->status)->toBe(1);
});

it('lists only the user\'s trashed notebooks on the trash page', function () {
    $trashed = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    Notebook::factory()->ownedBy($this->user)->create();

    $this->get('/notebooks/trash')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notebooks/Trash')
            ->has('notebooks', 1)
            ->where('notebooks.0.id', $trashed->id)
        );
});

it('shows an empty trash page', function () {
    $this->get('/notebooks/trash')
        ->assertInertia(fn (Assert $page) => $page->component('notebooks/Trash')->has('notebooks', 0));
});

it('restores a trashed notebook', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $this->postJson("/notebooks/trash/restore/{$notebook->id}")
        ->assertOk()
        ->assertExactJson(['message' => 'Notebook restored successfully']);

    expect($notebook->fresh()->status)->toBe(0);
    $this->get('/notebooks')->assertInertia(fn (Assert $page) => $page->where('notebooks.0.id', $notebook->id));
});

it('restores an active notebook without errors', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create();

    $this->postJson("/notebooks/trash/restore/{$notebook->id}")->assertOk();

    expect($notebook->fresh()->status)->toBe(0);
});

it('permanently deletes a trashed notebook and all its notes', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    Note::factory()->count(2)->for($notebook)->create();
    Note::factory()->trashed()->for($notebook)->create();
    $otherNote = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->create();

    $this->postJson("/notebooks/trash/delete/{$notebook->id}")
        ->assertOk()
        ->assertExactJson(['message' => 'Notebook deleted permanently']);

    expect(Notebook::find($notebook->id))->toBeNull()
        ->and(Note::where('notebook_id', $notebook->id)->count())->toBe(0)
        ->and($otherNote->fresh())->not->toBeNull();
});

// BUG-27
it('does not permanently delete a notebook that is not in the trash', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create();

    $this->postJson("/notebooks/trash/delete/{$notebook->id}")->assertStatus(422);

    expect($notebook->fresh())->not->toBeNull();
})->todo();

// BUG-09
it('removes the import jobs of a permanently deleted notebook', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    ImportJob::factory()->finished()->create(['user_id' => $this->user->id, 'notebook_id' => $notebook->id]);

    $this->postJson("/notebooks/trash/delete/{$notebook->id}")->assertOk();

    expect(ImportJob::count())->toBe(0);
})->todo();
