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

    expect($notebook->fresh()->trashed())->toBeTrue();
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

    expect($notebook->fresh()->trashed())->toBeTrue();
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

    expect($notebook->fresh()->trashed())->toBeFalse();
    $this->get('/notebooks')->assertInertia(fn (Assert $page) => $page->where('notebooks.0.id', $notebook->id));
});

it('restores an active notebook without errors', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create();

    $this->postJson("/notebooks/trash/restore/{$notebook->id}")->assertOk();

    expect($notebook->fresh()->trashed())->toBeFalse();
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
});

// BUG-09
it('removes the import jobs of a permanently deleted notebook', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    ImportJob::factory()->finished()->create(['user_id' => $this->user->id, 'notebook_id' => $notebook->id]);

    $this->postJson("/notebooks/trash/delete/{$notebook->id}")->assertOk();

    expect(ImportJob::count())->toBe(0);
});

it('lists the trashed notes of the user\'s active notebooks on the trash page', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Work']);
    $trashed = Note::factory()->for($notebook)->trashed()->create(['title' => 'Old idea']);
    Note::factory()->for($notebook)->create(['title' => 'Active']);
    Note::factory()->for(Notebook::factory()->ownedBy($this->user)->trashed())->trashed()->create();
    Note::factory()->for(Notebook::factory())->trashed()->create();

    $this->get('/notebooks/trash')
        ->assertInertia(fn (Assert $page) => $page
            ->has('notes', 1)
            ->where('notes.0.id', $trashed->id)
            ->where('notes.0.title', 'Old idea')
            ->where('notes.0.notebook.name', 'Work')
            ->missing('notes.0.content')
        );
});

it('keeps the import jobs of other notebooks when deleting one permanently', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    $kept = ImportJob::factory()->finished()->create([
        'user_id' => $this->user->id,
        'notebook_id' => Notebook::factory()->ownedBy($this->user)->create()->id,
    ]);

    $this->postJson("/notebooks/trash/delete/{$notebook->id}")->assertOk();

    expect(ImportJob::sole()->id)->toBe($kept->id);
});

it('shows when each trashed item was deleted and the retention period', function () {
    Notebook::factory()->ownedBy($this->user)->trashed()->create();
    Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create();

    $this->get('/notebooks/trash')
        ->assertInertia(fn (Assert $page) => $page
            ->where('retentionDays', config('trash.retention_days'))
            ->where('notebooks.0.deleted_at', fn ($value) => $value !== null)
            ->where('notes.0.deleted_at', fn ($value) => $value !== null)
        );
});

it('empties the trash: permanently deletes trashed notebooks with their notes and import jobs, and independently trashed notes', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    $notebookNote = Note::factory()->for($notebook)->create();
    ImportJob::factory()->finished()->create(['user_id' => $this->user->id, 'notebook_id' => $notebook->id]);

    $activeNotebook = Notebook::factory()->ownedBy($this->user)->create();
    $trashedNote = Note::factory()->for($activeNotebook)->trashed()->create();

    $this->postJson('/notebooks/trash/empty')
        ->assertOk()
        ->assertExactJson(['message' => 'Trash emptied successfully']);

    expect(Notebook::withTrashed()->find($notebook->id))->toBeNull()
        ->and(Note::withTrashed()->find($notebookNote->id))->toBeNull()
        ->and(ImportJob::where('notebook_id', $notebook->id)->count())->toBe(0)
        ->and(Note::withTrashed()->find($trashedNote->id))->toBeNull()
        ->and(Notebook::find($activeNotebook->id))->not->toBeNull();
});

it('does not empty another user\'s trash', function () {
    $foreignNotebook = Notebook::factory()->trashed()->create();
    $foreignNote = Note::factory()->for(Notebook::factory())->trashed()->create();

    $this->postJson('/notebooks/trash/empty')->assertOk();

    expect(Notebook::withTrashed()->find($foreignNotebook->id))->not->toBeNull()
        ->and(Note::withTrashed()->find($foreignNote->id))->not->toBeNull();
});

it('does nothing when emptying an already empty trash', function () {
    $this->postJson('/notebooks/trash/empty')->assertOk();
});
