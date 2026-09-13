<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
    $this->note = Note::factory()->for($this->notebook)->create();
    $this->actingAs($this->user);
});

it('sends a note to the trash', function () {
    $this->postJson("/notes/trash/{$this->note->id}")
        ->assertOk()
        ->assertExactJson([]);

    expect($this->note->fresh()->status)->toBe(1);
});

it('hides trashed notes from the notebook', function () {
    $this->postJson("/notes/trash/{$this->note->id}");

    $this->get("/notebook/{$this->notebook->id}")
        ->assertInertia(fn (Assert $page) => $page->has('inNotes', 0));
});

it('trashes an already trashed note without errors', function () {
    $this->note->forceFill(['status' => 1])->save();

    $this->postJson("/notes/trash/{$this->note->id}")->assertOk();

    expect($this->note->fresh()->status)->toBe(1);
});

// BUG-14
it('restores a trashed note', function () {
    $this->note->forceFill(['status' => 1])->save();

    $this->postJson("/notes/trash/restore/{$this->note->id}")->assertOk();

    expect($this->note->fresh()->status)->toBe(0);
});

it('restores an active note without errors', function () {
    $this->postJson("/notes/trash/restore/{$this->note->id}")->assertOk();

    expect($this->note->fresh()->status)->toBe(0);
});

it('shows a restored note in its notebook again', function () {
    $this->note->forceFill(['status' => 1])->save();

    $this->postJson("/notes/trash/restore/{$this->note->id}")->assertOk();

    $this->get("/notebook/{$this->notebook->id}")
        ->assertInertia(fn (Assert $page) => $page->has('inNotes', 1)->where('inNotes.0.id', $this->note->id));
});

it('does not restore another user\'s trashed note', function () {
    $foreign = Note::factory()->trashed()->create();

    $this->postJson("/notes/trash/restore/{$foreign->id}")->assertForbidden();

    expect($foreign->fresh()->status)->toBe(1);
});
