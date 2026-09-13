<?php

use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->note = Note::factory()
        ->for(Notebook::factory()->ownedBy($this->user))
        ->create(['title' => 'Current title', 'content' => '<p>Current content</p>']);
    $this->actingAs($this->user);
});

// index

it('lists versions without their content', function () {
    NoteVersion::factory()->for($this->note)->reason('manual')->create(['label' => 'Milestone']);

    $this->getJson("/notes/{$this->note->id}/versions")
        ->assertOk()
        ->assertJsonCount(1, 'versions')
        ->assertJsonStructure(['versions' => [['id', 'reason', 'label', 'pinned', 'created_at']]])
        ->assertJsonMissingPath('versions.0.content')
        ->assertJsonMissingPath('versions.0.title');
});

it('lists versions of another note as empty, never mixing notes', function () {
    $otherNote = Note::factory()->for($this->note->notebook)->create();
    NoteVersion::factory()->for($otherNote)->create();

    $this->getJson("/notes/{$this->note->id}/versions")->assertOk()->assertJsonCount(0, 'versions');
});

it('does not list versions of a note owned by another user', function () {
    $foreign = Note::factory()->create();

    $this->getJson("/notes/{$foreign->id}/versions")->assertForbidden();
});

// show

it('shows the sanitized title and content of a version', function () {
    $version = NoteVersion::factory()->for($this->note)->create([
        'title' => 'Old title',
        'content' => '<p>Old</p><script>alert(1)</script>',
    ]);

    $this->getJson("/notes/{$this->note->id}/versions/{$version->id}")
        ->assertOk()
        ->assertJson(['version' => [
            'id' => $version->id,
            'title' => 'Old title',
            'content' => '<p>Old</p>',
        ]]);
});

it('returns 404 for a version that belongs to another note', function () {
    $otherNote = Note::factory()->for($this->note->notebook)->create();
    $version = NoteVersion::factory()->for($otherNote)->create();

    $this->getJson("/notes/{$this->note->id}/versions/{$version->id}")->assertNotFound();
});

it('returns 404 for a version id that does not exist', function () {
    $this->getJson("/notes/{$this->note->id}/versions/999999")->assertNotFound();
});

it('does not show a version of a note owned by another user', function () {
    $foreign = Note::factory()->create();
    $version = NoteVersion::factory()->for($foreign)->create();

    $this->getJson("/notes/{$foreign->id}/versions/{$version->id}")->assertForbidden();
});

// store

it('creates a pinned manual version with a label', function () {
    $this->postJson("/notes/{$this->note->id}/versions", ['reason' => 'manual', 'label' => 'Before rewrite'])
        ->assertCreated()
        ->assertJsonPath('version.reason', 'manual')
        ->assertJsonPath('version.label', 'Before rewrite')
        ->assertJsonPath('version.pinned', true);

    expect($this->note->versions()->count())->toBe(1);
});

it('creates an unpinned session_end version without a label', function () {
    $this->postJson("/notes/{$this->note->id}/versions", ['reason' => 'session_end'])
        ->assertCreated()
        ->assertJsonPath('version.reason', 'session_end')
        ->assertJsonPath('version.pinned', false);
});

it('rejects an unknown reason', function () {
    $this->postJson("/notes/{$this->note->id}/versions", ['reason' => 'bogus'])->assertStatus(422);
});

it('pins the existing latest version instead of duplicating it when nothing changed', function () {
    // An unpinned version (e.g. from a session_end) already captures the note's current state
    $existing = NoteVersion::factory()->for($this->note)->reason('session_end')->create([
        'title' => $this->note->title,
        'content' => $this->note->content,
        'content_hash' => \App\Services\NoteVersionService::hash($this->note->title, $this->note->content),
        'pinned' => false,
    ]);

    $this->postJson("/notes/{$this->note->id}/versions", ['reason' => 'manual', 'label' => 'Keep this'])
        ->assertOk()
        ->assertJsonPath('version.id', $existing->id)
        ->assertJsonPath('version.pinned', true)
        ->assertJsonPath('version.label', 'Keep this');

    expect($this->note->versions()->count())->toBe(1);
    expect($existing->fresh()->pinned)->toBeTrue()
        ->and($existing->fresh()->label)->toBe('Keep this');
});

it('pins the existing latest version without a label when none is given', function () {
    $existing = NoteVersion::factory()->for($this->note)->reason('session_end')->create([
        'title' => $this->note->title,
        'content' => $this->note->content,
        'content_hash' => \App\Services\NoteVersionService::hash($this->note->title, $this->note->content),
        'pinned' => false,
        'label' => 'Existing label',
    ]);

    $this->postJson("/notes/{$this->note->id}/versions", ['reason' => 'manual'])
        ->assertOk()
        ->assertJsonPath('version.id', $existing->id)
        ->assertJsonPath('version.pinned', true);

    // No label was sent: the existing one is left untouched
    expect($existing->fresh()->label)->toBe('Existing label');
});

it('does not create a version for a note owned by another user', function () {
    $foreign = Note::factory()->create();

    $this->postJson("/notes/{$foreign->id}/versions", ['reason' => 'manual'])->assertForbidden();

    expect($foreign->versions()->count())->toBe(0);
});

// restore

it('restores a version onto the note and snapshots the current state first', function () {
    $version = NoteVersion::factory()->for($this->note)->create([
        'title' => 'Restored title',
        'content' => '<p>Restored</p>',
    ]);

    $this->postJson("/notes/{$this->note->id}/versions/{$version->id}/restore")
        ->assertOk()
        ->assertJsonPath('note.id', $this->note->id)
        ->assertJsonPath('note.title', 'Restored title')
        ->assertJsonPath('note.content', '<p>Restored</p>');

    $this->note->refresh();
    expect($this->note->title)->toBe('Restored title')
        ->and($this->note->content)->toBe('<p>Restored</p>');

    // The pre-restore state ("Current title" / "Current content") is preserved and pinned,
    // since it precedes a destructive change
    $restoreSnapshot = $this->note->versions()->where('reason', 'restore')->first();
    expect($restoreSnapshot)->not->toBeNull()
        ->and($restoreSnapshot->title)->toBe('Current title')
        ->and($restoreSnapshot->content)->toBe('<p>Current content</p>')
        ->and($restoreSnapshot->pinned)->toBeTrue();
});

it('sanitizes the restored content', function () {
    $version = NoteVersion::factory()->for($this->note)->create([
        'content' => '<p>Safe</p><script>alert(1)</script>',
    ]);

    $this->postJson("/notes/{$this->note->id}/versions/{$version->id}/restore")->assertOk();

    expect($this->note->fresh()->content)->toBe('<p>Safe</p>');
});

it('returns 404 restoring a version that belongs to another note', function () {
    $otherNote = Note::factory()->for($this->note->notebook)->create();
    $version = NoteVersion::factory()->for($otherNote)->create();

    $this->postJson("/notes/{$this->note->id}/versions/{$version->id}/restore")->assertNotFound();

    expect($this->note->fresh()->title)->toBe('Current title');
});

it('does not restore a version onto a note owned by another user', function () {
    $foreign = Note::factory()->create();
    $version = NoteVersion::factory()->for($foreign)->create();

    $this->postJson("/notes/{$foreign->id}/versions/{$version->id}/restore")->assertForbidden();
});

it('keeps an existing label and saves the new one as its own version when nothing changed', function () {
    $existing = NoteVersion::factory()->for($this->note)->reason('manual')->create([
        'title' => $this->note->title,
        'content' => $this->note->content,
        'content_hash' => \App\Services\NoteVersionService::hash($this->note->title, $this->note->content),
        'pinned' => true,
        'label' => 'First label',
    ]);

    $this->postJson("/notes/{$this->note->id}/versions", ['reason' => 'manual', 'label' => 'Second label'])
        ->assertCreated()
        ->assertJsonPath('version.label', 'Second label')
        ->assertJsonPath('version.pinned', true);

    expect($existing->fresh()->label)->toBe('First label')
        ->and($this->note->versions()->count())->toBe(2);
});

it('does not duplicate a version when the same label is saved again', function () {
    $existing = NoteVersion::factory()->for($this->note)->reason('manual')->create([
        'title' => $this->note->title,
        'content' => $this->note->content,
        'content_hash' => \App\Services\NoteVersionService::hash($this->note->title, $this->note->content),
        'pinned' => true,
        'label' => 'Same',
    ]);

    $this->postJson("/notes/{$this->note->id}/versions", ['reason' => 'manual', 'label' => 'Same'])
        ->assertOk()
        ->assertJsonPath('version.id', $existing->id);

    expect($this->note->versions()->count())->toBe(1);
});
