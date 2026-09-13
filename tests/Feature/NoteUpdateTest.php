<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->note = Note::factory()
        ->for(Notebook::factory()->ownedBy($this->user))
        ->create(['title' => '', 'content' => '']);
});

it('updates a note with sanitized content', function () {
    $this->actingAs($this->user)
        ->postJson('/notes/update/'.$this->note->id, [
            'title' => 'Title',
            'content' => '<p>Hi <strong>there</strong></p><img src="x" onerror="alert(1)"><script>alert(2)</script>',
        ])
        ->assertOk()
        ->assertJsonPath('note.content', '<p>Hi <strong>there</strong></p><img />');

    $this->note->refresh();
    expect($this->note->title)->toBe('Title')
        ->and($this->note->content)->toBe('<p>Hi <strong>there</strong></p><img />');
});

it('stores title and content encrypted at rest', function () {
    $this->actingAs($this->user)
        ->postJson('/notes/update/'.$this->note->id, ['title' => 'secret title', 'content' => '<p>secret body</p>'])
        ->assertOk();

    $row = DB::table('notes')->where('id', $this->note->id)->first();
    expect($row->title)->not->toContain('secret')
        ->and($row->content)->not->toContain('secret');
});

it('cannot update someone else\'s note', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/notes/update/'.$this->note->id, ['title' => 'hacked'])
        ->assertForbidden();

    expect($this->note->fresh()->title)->toBe('');
});
