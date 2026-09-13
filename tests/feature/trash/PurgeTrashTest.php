<?php

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('keeps a note trashed for less than the retention period', function () {
    $this->travel(-29)->days();
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create();
    $this->travelBack();

    Artisan::call('trash:purge');

    expect(Note::withTrashed()->find($note->id))->not->toBeNull();
});

it('purges a note trashed for longer than the retention period', function () {
    $this->travel(-31)->days();
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create();
    $this->travelBack();

    Artisan::call('trash:purge');

    expect(Note::withTrashed()->find($note->id))->toBeNull();
});

it('keeps a notebook trashed for less than the retention period', function () {
    $this->travel(-29)->days();
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    $this->travelBack();

    Artisan::call('trash:purge');

    expect(Notebook::withTrashed()->find($notebook->id))->not->toBeNull();
});

it('purges a notebook trashed for longer than the retention period, with its notes and import jobs', function () {
    $this->travel(-31)->days();
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    $this->travelBack();

    $note = Note::factory()->for($notebook)->create();
    $trashedNote = Note::factory()->for($notebook)->trashed()->create();
    $job = ImportJob::factory()->create(['user_id' => $this->user->id, 'notebook_id' => $notebook->id]);

    Artisan::call('trash:purge');

    expect(Notebook::withTrashed()->find($notebook->id))->toBeNull()
        ->and(Note::withTrashed()->find($note->id))->toBeNull()
        ->and(Note::withTrashed()->find($trashedNote->id))->toBeNull()
        ->and(ImportJob::find($job->id))->toBeNull();
});

it('purges an independently trashed note whose notebook is still active, without touching the notebook', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create();

    $this->travel(-31)->days();
    $note = Note::factory()->for($notebook)->trashed()->create();
    $this->travelBack();

    Artisan::call('trash:purge');

    expect(Note::withTrashed()->find($note->id))->toBeNull()
        ->and(Notebook::find($notebook->id))->not->toBeNull();
});

it('uses the configured retention period', function () {
    config(['trash.retention_days' => 5]);

    $this->travel(-6)->days();
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create();
    $this->travelBack();

    Artisan::call('trash:purge');

    expect(Note::withTrashed()->find($note->id))->toBeNull();
});
