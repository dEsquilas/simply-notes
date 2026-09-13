<?php

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $this->bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $this->aliceNotebook = Notebook::factory()->ownedBy($this->alice)->create(['name' => 'Alice private']);
    $this->bobNotebook = Notebook::factory()->ownedBy($this->bob)->create(['name' => 'Bob private']);
});

it('shows each user only their own notebooks', function () {
    $alice = visit('/login');
    $bob = visit('/login');
    signIn($alice, $this->alice);
    signIn($bob, $this->bob);

    $alice->assertSee('Alice private')->assertDontSee('Bob private');
    $bob->assertSee('Bob private')->assertDontSee('Alice private');
});

it('blocks a user from opening another user\'s notebook by URL', function () {
    $alice = visit('/login');
    $bob = visit('/login');
    signIn($alice, $this->alice)->navigate("/notebook/{$this->aliceNotebook->id}");
    signIn($bob, $this->bob)->navigate("/notebook/{$this->aliceNotebook->id}");

    $alice->assertVisible('@notes-sidebar');
    $bob->assertSee('403')->assertDontSee('Alice private');
});

it('keeps two users\' simultaneous edits apart', function () {
    $aliceNote = Note::factory()->for($this->aliceNotebook)->create(['title' => 'Alice note']);
    $bobNote = Note::factory()->for($this->bobNotebook)->create(['title' => 'Bob note']);
    $alice = visit('/login');
    $bob = visit('/login');

    signIn($alice, $this->alice)->navigate("/notebook/{$this->aliceNotebook->id}")->assertValue('@note-title', 'Alice note');
    signIn($bob, $this->bob)->navigate("/notebook/{$this->bobNotebook->id}")->assertValue('@note-title', 'Bob note');

    typeLikeAUser($alice, '@note-title', 'Written by Alice');
    typeLikeAUser($bob, '@note-title', 'Written by Bob');

    waitForDatabase($alice, fn () => $aliceNote->fresh()->title === 'Written by Alice' && $bobNote->fresh()->title === 'Written by Bob');
});

it('does not show another user\'s import jobs', function () {
    ImportJob::factory()->finished()->create(['user_id' => $this->alice->id, 'notebook_id' => $this->aliceNotebook->id]);
    $this->actingAs($this->bob);

    visit('/import')
        ->assertSeeIn('@no-finished-jobs', 'There are no jobs completed.')
        ->assertDontSee('Alice private');
});
