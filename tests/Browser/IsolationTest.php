<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->alice = User::factory()->create(['name' => 'Alice']);
    $this->bob = User::factory()->create(['name' => 'Bob']);
    $this->aliceNotebook = Notebook::factory()->ownedBy($this->alice)->create(['name' => 'Alice private']);
    $this->bobNotebook = Notebook::factory()->ownedBy($this->bob)->create(['name' => 'Bob private']);
});

it('shows each user only their own notebooks', function () {
    $this->browse(fn (Browser $alice, Browser $bob) => [
        $alice->loginAs($this->alice)->visit('/notebooks')->waitForText('Alice private')->assertDontSee('Bob private'),
        $bob->loginAs($this->bob)->visit('/notebooks')->waitForText('Bob private')->assertDontSee('Alice private'),
    ]);
});

it('blocks a user from opening another user\'s notebook by URL', function () {
    $this->browse(fn (Browser $alice, Browser $bob) => [
        $alice->loginAs($this->alice)->visit("/notebook/{$this->aliceNotebook->id}")->waitFor('@notes-sidebar'),
        $bob->loginAs($this->bob)->visit("/notebook/{$this->aliceNotebook->id}")->assertSee('403')->assertDontSee('Alice private'),
    ]);
});

it('keeps two users\' simultaneous edits apart', function () {
    $aliceNote = Note::factory()->for($this->aliceNotebook)->create(['title' => 'Alice note']);
    $bobNote = Note::factory()->for($this->bobNotebook)->create(['title' => 'Bob note']);

    $this->browse(function (Browser $alice, Browser $bob) use ($aliceNote, $bobNote) {
        openNotebookAs($alice, $this->alice, $this->aliceNotebook)->waitFor('@note-title');
        openNotebookAs($bob, $this->bob, $this->bobNotebook)->waitFor('@note-title');
        $alice->pause(1100)->type('@note-title', 'Written by Alice');
        $bob->type('@note-title', 'Written by Bob');

        waitForDatabase($alice, fn () => $aliceNote->fresh()->title === 'Written by Alice' && $bobNote->fresh()->title === 'Written by Bob');
    });
});

it('does not show another user\'s import jobs', function () {
    \App\Models\ImportJob::factory()->finished()->create(['user_id' => $this->alice->id, 'notebook_id' => $this->aliceNotebook->id]);

    $this->browse(fn (Browser $bob) => $bob
        ->loginAs($this->bob)
        ->visit('/import')
        ->waitFor('@no-finished-jobs')
        ->assertSeeIn('@no-finished-jobs', 'There are no jobs completed.')
        ->assertDontSee('Alice private')
    );
});
