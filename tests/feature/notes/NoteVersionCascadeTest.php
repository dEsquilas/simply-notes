<?php

use App\Models\Note;
use App\Models\NoteVersion;

it('deletes a note\'s versions when the note is force deleted', function () {
    $note = Note::factory()->create();
    NoteVersion::factory()->for($note)->count(3)->create();

    expect(NoteVersion::where('note_id', $note->id)->count())->toBe(3);

    $note->forceDelete();

    expect(NoteVersion::where('note_id', $note->id)->count())->toBe(0);
});

it('does not delete versions belonging to other notes', function () {
    $note = Note::factory()->create();
    $other = Note::factory()->create();
    NoteVersion::factory()->for($note)->create();
    NoteVersion::factory()->for($other)->create();

    $note->delete();

    expect(NoteVersion::where('note_id', $other->id)->count())->toBe(1);
});
