<?php

use App\Models\ImportJob;
use App\Models\Notebook;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Evernote']);
    $this->actingAs($this->user);
});

function importJobFor(User $user, Notebook $notebook, string $state = 'pending'): ImportJob
{
    $factory = ImportJob::factory();
    $factory = match ($state) {
        'processing' => $factory->processing(),
        'finished' => $factory->finished(),
        'failed' => $factory->failed(),
        default => $factory,
    };

    return $factory->create(['user_id' => $user->id, 'notebook_id' => $notebook->id]);
}

it('renders the import page with the user\'s notebooks', function () {
    $this->get('/import')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Import')
            ->has('notebooks', 1)
            ->where('notebooks.0.name', 'Evernote')
            ->has('runningJobs', 0)
            ->has('finishedJobs', 0)
        );
});

it('splits running and finished jobs, each with its notebook', function () {
    $pending = importJobFor($this->user, $this->notebook, 'pending');
    $processing = importJobFor($this->user, $this->notebook, 'processing');
    $finished = importJobFor($this->user, $this->notebook, 'finished');

    $this->get('/import')
        ->assertInertia(fn (Assert $page) => $page
            ->has('runningJobs', 2)
            ->where('runningJobs.0.id', $pending->id)
            ->where('runningJobs.1.id', $processing->id)
            ->where('runningJobs.0.notebook.name', 'Evernote')
            ->has('finishedJobs', 1)
            ->where('finishedJobs.0.id', $finished->id)
            ->where('finishedJobs.0.notebook.name', 'Evernote')
        );
});

it('reports the same split when polling', function () {
    $pending = importJobFor($this->user, $this->notebook, 'pending');
    $finished = importJobFor($this->user, $this->notebook, 'finished');

    $this->getJson('/import/polling')
        ->assertOk()
        ->assertJsonCount(1, 'runningJobs')
        ->assertJsonPath('runningJobs.0.id', $pending->id)
        ->assertJsonPath('runningJobs.0.notebook.name', 'Evernote')
        ->assertJsonCount(1, 'finishedJobs')
        ->assertJsonPath('finishedJobs.0.id', $finished->id);
});

it('still renders when a job\'s notebook was deleted', function () {
    $job = importJobFor($this->user, $this->notebook, 'finished');
    $this->notebook->delete();

    $this->get('/import')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('finishedJobs.0.id', $job->id)
            ->where('finishedJobs.0.notebook', null)
        );
});

// BUG-10
it('shows failed imports', function () {
    importJobFor($this->user, $this->notebook, 'failed');

    $this->get('/import')
        ->assertInertia(fn (Assert $page) => $page->has('failedJobs', 1));
    $this->getJson('/import/polling')->assertJsonCount(1, 'failedJobs');
});

// BUG-26
it('does not offer trashed notebooks as import destination', function () {
    Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $this->get('/import')
        ->assertInertia(fn (Assert $page) => $page->has('notebooks', 1));
});

it('reports failed imports with their notebook when polling', function () {
    $failed = importJobFor($this->user, $this->notebook, 'failed');

    $this->getJson('/import/polling')
        ->assertJsonPath('failedJobs.0.id', $failed->id)
        ->assertJsonPath('failedJobs.0.notebook.name', 'Evernote')
        ->assertJsonCount(0, 'runningJobs')
        ->assertJsonCount(0, 'finishedJobs');
});
