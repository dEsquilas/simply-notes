<?php

use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\Notebook;
use App\Models\User;

const VERSION_NOTE_EDITOR = '[data-test="note-body"] .tiptap-content';

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
    $this->actingAs($this->user);
});

it('opens the history panel and lists existing versions', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'With history']);
    NoteVersion::factory()->for($note)->reason('manual')->create(['label' => 'Checkpoint']);

    $page = visit("/notebook/{$this->notebook->id}/note/{$note->id}");
    readyToEdit($page)
        ->click('@note-history-button')
        ->assertVisible('@note-history-panel')
        ->assertSeeIn('@note-history-list', 'Checkpoint');
});

it('saves a manual version with a label', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Manual save']);

    $page = visit("/notebook/{$this->notebook->id}/note/{$note->id}");
    readyToEdit($page)->click('@note-history-button')->assertVisible('@note-history-panel');

    typeLikeAUser($page, '@note-history-label', 'Before big rewrite');
    $page->click('@note-history-save');

    waitForDatabase($page, fn () => $note->versions()->count() === 1);

    $version = $note->versions()->first();
    expect($version->reason)->toBe('manual')
        ->and($version->pinned)->toBeTrue()
        ->and($version->label)->toBe('Before big rewrite');

    $page->assertSeeIn('@note-history-list', 'Before big rewrite');
});

it('previews a version without leaving the note untouched', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Preview me', 'content' => '<p>Current</p>']);
    $version = NoteVersion::factory()->for($note)->create(['title' => 'Old snapshot', 'content' => '<p>Older content</p>']);

    $page = visit("/notebook/{$this->notebook->id}/note/{$note->id}");
    readyToEdit($page)
        ->click('@note-history-button')
        ->click('@note-history-item-'.$version->id)
        ->assertSeeIn('@note-history-preview', 'Older content');

    // Selecting a version to preview must not change the note being edited
    expect($note->fresh()->content)->toBe('<p>Current</p>');
});

it('restores a version and updates the open editor', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Will be restored', 'content' => '<p>Current content</p>']);
    $version = NoteVersion::factory()->for($note)->create(['title' => 'Older title', 'content' => '<p>Older content</p>']);

    $page = visit("/notebook/{$this->notebook->id}/note/{$note->id}");
    readyToEdit($page)
        ->click('@note-history-button')
        ->click('@note-history-item-'.$version->id)
        ->assertSeeIn('@note-history-preview', 'Older content');

    answerDialogs($page)->click('@note-history-restore');

    waitForDatabase($page, fn () => $note->fresh()->title === 'Older title');

    $page->assertMissing('@note-history-panel')
        ->assertValue('@note-title', 'Older title')
        ->assertSeeIn(VERSION_NOTE_EDITOR, 'Older content');

    expect($note->fresh()->content)->toBe('<p>Older content</p>');

    // The pre-restore state was preserved as a new version
    expect($note->versions()->where('reason', 'restore')->count())->toBe(1);
});

// Regression: a version snapshotted right after opening the history panel (or a restore) must
// never miss an edit that was still waiting for the 1.5s autosave timer
it('flushes a pending edit before opening the history panel', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Before edit']);

    $page = visit("/notebook/{$this->notebook->id}/note/{$note->id}");
    typeLikeAUser(readyToEdit($page), '@note-title', 'Edited just now');

    // Well under the 1.5s autosave delay: without an explicit flush this edit would not be saved yet
    $page->click('@note-history-button')->assertVisible('@note-history-panel');

    expect($note->fresh()->title)->toBe('Edited just now');
});

// session_end trigger
it('snapshots a session_end version when switching to another note', function () {
    $first = Note::factory()->for($this->notebook)->create(['title' => 'First', 'updated_at' => now()]);
    $second = Note::factory()->for($this->notebook)->create(['title' => 'Second', 'updated_at' => now()->subHour()]);

    $page = visit("/notebook/{$this->notebook->id}");
    readyToEdit($page)->assertValue('@note-title', 'First')->click('@note-'.$second->id);

    waitForDatabase($page, fn () => $first->versions()->where('reason', 'session_end')->count() === 1);

    $version = $first->versions()->where('reason', 'session_end')->first();
    expect($version->title)->toBe('First');
});

it('snapshots a single session_end version when the page is left', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'Leaving', 'updated_at' => now()]);

    $page = visit("/notebook/{$this->notebook->id}");
    readyToEdit($page)->assertValue('@note-title', 'Leaving');

    // Leaving a page fires both events one after the other: they must not create two versions
    $page->script('() => {
        Object.defineProperty(document, "visibilityState", { configurable: true, get: () => "hidden" });
        document.dispatchEvent(new Event("visibilitychange"));
        window.dispatchEvent(new Event("pagehide"));
    }');

    waitForDatabase($page, fn () => $note->versions()->where('reason', 'session_end')->count() >= 1);
    $page->wait(1);

    expect($note->versions()->where('reason', 'session_end')->count())->toBe(1);
});
