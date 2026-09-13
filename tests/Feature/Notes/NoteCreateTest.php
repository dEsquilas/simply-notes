<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
    $this->actingAs($this->user);
});

it('creates an empty active note in the notebook', function () {
    $response = $this->postJson("/notes/create/{$this->notebook->id}")->assertOk();

    $note = Note::sole();
    expect($note->notebook_id)->toBe($this->notebook->id)
        ->and($note->title)->toBe('')
        ->and($note->content)->toBe('')
        ->and($note->fresh()->status)->toBe(0);
    $response->assertJsonPath('note.id', $note->id)
        ->assertJsonPath('note.title', '')
        ->assertJsonPath('note.content', '');
});

it('creates a new note every time', function () {
    $this->postJson("/notes/create/{$this->notebook->id}")->assertOk();
    $this->postJson("/notes/create/{$this->notebook->id}")->assertOk();

    expect(Note::where('notebook_id', $this->notebook->id)->count())->toBe(2);
});

// BUG-04
it('does not create notes in a trashed notebook', function () {
    $this->notebook->forceFill(['status' => 1])->save();

    $this->postJson("/notes/create/{$this->notebook->id}")->assertStatus(422);

    expect(Note::count())->toBe(0);
})->todo();
