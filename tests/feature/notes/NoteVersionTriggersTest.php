<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->note = Note::factory()
        ->for(Notebook::factory()->ownedBy($this->user))
        ->create(['title' => 'Old title', 'content' => '<p>'.str_repeat('a', 1000).'</p>']);
    $this->actingAs($this->user);
});

it('does not snapshot a version on a normal edit right after the last save', function () {
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<p>'.str_repeat('a', 999).'</p>'])
        ->assertOk();

    expect($this->note->versions()->count())->toBe(0);
});

// config/versions.php: inactivity_minutes = 5
it('snapshots the old state when editing after the inactivity threshold', function () {
    $this->note->forceFill(['updated_at' => now()->subMinutes(6)])->save();

    // Content barely changes: this is a session_start snapshot, not a substantial_change one
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<p>'.str_repeat('a', 999).'</p>'])->assertOk();

    expect($this->note->versions()->count())->toBe(1);
    $version = $this->note->versions()->first();
    expect($version->reason)->toBe('session_start')
        ->and($version->title)->toBe('Old title')
        ->and($version->content)->toBe('<p>'.str_repeat('a', 1000).'</p>')
        ->and($version->pinned)->toBeFalse();
});

it('does not snapshot exactly at the inactivity threshold', function () {
    // Freeze time at whole-second precision: SQLite datetimes drop fractional seconds, and any
    // leftover fraction would make the stored timestamp round down past the exact boundary
    $this->travelTo(now()->startOfSecond());
    $this->note->forceFill(['updated_at' => now()->subMinutes(5)])->save();

    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<p>'.str_repeat('a', 999).'</p>'])->assertOk();

    expect($this->note->versions()->count())->toBe(0);
});

it('snapshots and pins the old state on a substantial content loss', function () {
    // Loses far more than 20% and 500 characters
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<p>x</p>'])->assertOk();

    expect($this->note->versions()->count())->toBe(1);
    $version = $this->note->versions()->first();
    expect($version->reason)->toBe('substantial_change')
        ->and($version->pinned)->toBeTrue()
        ->and($version->content)->toBe('<p>'.str_repeat('a', 1000).'</p>');
});

it('snapshots a substantial change when the number of images decreases', function () {
    $this->note->forceFill(['content' => '<p>t</p><img src="a"><img src="b">'])->save();

    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<p>t</p><img src="a">'])->assertOk();

    $version = $this->note->versions()->first();
    expect($version->reason)->toBe('substantial_change')
        ->and($version->pinned)->toBeTrue();
});

// Regression: deleting a single word from a short note used to pin a version just because the
// percentage lost was high, even though very few characters actually disappeared
it('does not snapshot a small edit to a short note, even if it loses a big percentage', function () {
    $this->note->forceFill(['content' => '<p>'.str_repeat('a', 40).'</p>'])->save();

    // Loses 10 of 40 characters (25%), well under min_chars_for_percent (100)
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<p>'.str_repeat('a', 30).'</p>'])->assertOk();

    expect($this->note->versions()->count())->toBe(0);
});

it('snapshots a substantial change when the number of tables decreases', function () {
    $this->note->forceFill(['content' => '<table></table><table></table>'])->save();

    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<table></table>'])->assertOk();

    $version = $this->note->versions()->first();
    expect($version->reason)->toBe('substantial_change')
        ->and($version->pinned)->toBeTrue();
});

it('creates at most one version per update, substantial_change winning over session_start', function () {
    $this->note->forceFill(['updated_at' => now()->subMinutes(10)])->save();

    // Also a substantial loss, so both triggers would fire independently
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<p>x</p>'])->assertOk();

    expect($this->note->versions()->count())->toBe(1)
        ->and($this->note->versions()->first()->reason)->toBe('substantial_change');
});

it('never creates a version whose content hash matches the note\'s latest version', function () {
    // A version already captures the note's exact current stored state
    $this->note->versions()->create([
        'title' => $this->note->title,
        'content' => $this->note->content,
        'content_hash' => \App\Services\NoteVersionService::hash($this->note->title, $this->note->content),
        'reason' => 'manual',
        'pinned' => true,
    ]);

    $this->note->forceFill(['updated_at' => now()->subMinutes(10)])->save();

    // A stale edit would normally snapshot the old state (session_start), but it is identical
    // to the latest version already on file: no duplicate is created
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => 'x'])->assertOk();

    expect($this->note->versions()->count())->toBe(1);
});

it('pins the already stored state when a pinning snapshot finds it duplicated', function () {
    $service = app(\App\Services\NoteVersionService::class);
    $unpinned = $service->snapshot($this->note, 'session_end');

    // e.g. a substantial change or a restore right after a session_end of the same state
    expect($service->snapshot($this->note, 'substantial_change', pinned: true))->toBeNull()
        ->and($unpinned->fresh()->pinned)->toBeTrue()
        ->and($this->note->versions()->count())->toBe(1);
});
