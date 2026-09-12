<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NoteUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function noteOf(User $user): Note
    {
        $notebook = new Notebook();
        $notebook->owner = $user->id;
        $notebook->name = 'nb';
        $notebook->save();

        $note = new Note();
        $note->notebook_id = $notebook->id;
        $note->title = '';
        $note->content = '';
        $note->save();

        return $note;
    }

    public function test_owner_updates_a_note_with_sanitized_content(): void
    {
        $user = User::factory()->create();
        $note = $this->noteOf($user);

        $response = $this->actingAs($user)->postJson('/notes/update/'.$note->id, [
            'title' => 'Title',
            'content' => '<p>Hi <strong>there</strong></p><img src="x" onerror="alert(1)"><script>alert(2)</script>',
        ]);

        $response->assertOk()->assertJsonPath('note.content', '<p>Hi <strong>there</strong></p><img />');
        $note->refresh();
        $this->assertSame('Title', $note->title);
        $this->assertSame('<p>Hi <strong>there</strong></p><img />', $note->content);
    }

    public function test_title_and_content_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $note = $this->noteOf($user);

        $this->actingAs($user)->postJson('/notes/update/'.$note->id, ['title' => 'secret title', 'content' => '<p>secret body</p>'])
            ->assertOk();

        $row = DB::table('notes')->where('id', $note->id)->first();
        $this->assertStringNotContainsString('secret', $row->title);
        $this->assertStringNotContainsString('secret', $row->content);
    }

    public function test_cannot_update_someone_elses_note(): void
    {
        [$user, $other] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteOf($other);

        $this->actingAs($user)->postJson('/notes/update/'.$note->id, ['title' => 'hacked'])->assertForbidden();

        $this->assertSame('', $note->fresh()->title);
    }
}
