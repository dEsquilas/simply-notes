<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->note = Note::factory()
        ->for(Notebook::factory()->ownedBy($this->user))
        ->create(['title' => 'Old title', 'content' => '<p>Old</p>']);
    $this->actingAs($this->user);
});

it('updates a note with sanitized content', function () {
    $this->postJson('/notes/update/'.$this->note->id, [
        'title' => 'Title',
        'content' => '<p>Hi <strong>there</strong></p><img src="x" onerror="alert(1)"><script>alert(2)</script>',
    ])
        ->assertOk()
        ->assertJsonPath('note.content', '<p>Hi <strong>there</strong></p><img />');

    $this->note->refresh();
    expect($this->note->title)->toBe('Title')
        ->and($this->note->content)->toBe('<p>Hi <strong>there</strong></p><img />');
});

it('returns the saved note', function () {
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'New', 'content' => '<p>New</p>'])
        ->assertOk()
        ->assertJsonPath('note.id', $this->note->id)
        ->assertJsonPath('note.title', 'New')
        ->assertJsonPath('note.content', '<p>New</p>');
});

it('stores title and content encrypted at rest', function () {
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'secret title', 'content' => '<p>secret body</p>'])
        ->assertOk();

    $row = DB::table('notes')->where('id', $this->note->id)->first();
    expect($row->title)->not->toContain('secret')
        ->and($row->content)->not->toContain('secret');
});

it('clears title and content that are not sent', function () {
    $this->postJson('/notes/update/'.$this->note->id, [])->assertOk();

    $this->note->refresh();
    expect($this->note->title)->toBeNull()
        ->and($this->note->content)->toBeNull();
});

it('saves an empty title and empty content', function () {
    $this->postJson('/notes/update/'.$this->note->id, ['title' => '', 'content' => ''])->assertOk();

    $this->note->refresh();
    expect($this->note->title)->toBeNull()
        ->and($this->note->content)->toBeNull();
});

it('keeps unicode titles and content', function () {
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'Café 日本語 🎉', 'content' => '<p>Ñandú 😀</p>'])->assertOk();

    $this->note->refresh();
    expect($this->note->title)->toBe('Café 日本語 🎉')
        ->and($this->note->content)->toBe('<p>Ñandú 😀</p>');
});

it('saves very long titles and multi-megabyte content without truncating', function () {
    $title = str_repeat('t', 10_000);
    $content = '<p><img src="data:image/png;base64,'.str_repeat('A', 5_000_000).'" /></p>';

    $this->postJson('/notes/update/'.$this->note->id, ['title' => $title, 'content' => $content])->assertOk();

    $this->note->refresh();
    expect($this->note->title)->toBe($title)
        ->and($this->note->content)->toBe($content);
});

it('moves the updated note to the top of its notebook', function () {
    $recent = Note::factory()->for($this->note->notebook)->create(['updated_at' => now()]);
    $this->note->forceFill(['updated_at' => now()->subDay()])->save();

    $this->travel(1)->minutes();
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'Bumped', 'content' => ''])->assertOk();

    $this->get("/notebook/{$this->note->notebook_id}")
        ->assertInertia(fn ($page) => $page
            ->where('inNotes.0.id', $this->note->id)
            ->where('inNotes.1.id', $recent->id)
        );
});

// BUG-29
it('rejects content that is not text', function () {
    $this->postJson('/notes/update/'.$this->note->id, ['title' => 'x', 'content' => ['not', 'text']])
        ->assertStatus(422);

    expect($this->note->fresh()->content)->toBe('<p>Old</p>');
})->todo();

// BUG-29: no input validation, an array title currently returns a 500
it('rejects a title that is not text', function () {
    $this->postJson('/notes/update/'.$this->note->id, ['title' => ['not', 'text'], 'content' => ''])
        ->assertStatus(422);

    expect($this->note->fresh()->title)->toBe('Old title');
})->todo();
