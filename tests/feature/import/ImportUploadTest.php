<?php

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\Support\EvernoteExport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Target']);
    $this->actingAs($this->user);
});

function validExport(): UploadedFile
{
    return EvernoteExport::zip([
        'one.html' => EvernoteExport::html('One', '<div class="para">First</div>'),
        'two.html' => EvernoteExport::html('Two', '<div class="para">Second</div>'),
    ]);
}

it('imports every note of the export and reports the job', function () {
    $this->postJson('/import', ['file' => validExport(), 'notebook' => $this->notebook->id])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('job.notebook.name', 'Target')
        ->assertJsonPath('job.status', 'finished');

    $job = ImportJob::sole();
    expect($job->user_id)->toBe($this->user->id)
        ->and($job->notebook_id)->toBe($this->notebook->id)
        ->and((int) $job->total_files)->toBe(2)
        ->and((int) $job->processed_files)->toBe(2)
        ->and(Note::where('notebook_id', $this->notebook->id)->pluck('title')->sort()->values()->all())->toBe(['One', 'Two']);
});

it('accepts the notebook id as a form string', function () {
    $this->post('/import', ['file' => validExport(), 'notebook' => (string) $this->notebook->id], ['Accept' => 'application/json'])
        ->assertOk();

    expect(Note::where('notebook_id', $this->notebook->id)->count())->toBe(2);
});

it('imports into a new notebook', function (int|string $newNotebook) {
    $this->postJson('/import', ['file' => validExport(), 'notebook' => $newNotebook, 'notebookName' => 'From Evernote'])
        ->assertOk()
        ->assertJsonPath('job.notebook.name', 'From Evernote');

    $created = Notebook::where('name', 'From Evernote')->sole();
    expect($created->owner)->toBe($this->user->id)
        ->and(Note::where('notebook_id', $created->id)->count())->toBe(2);
})->with(['number' => -1, 'form string' => '-1']);

it('requires a file', function () {
    $this->postJson('/import', ['notebook' => $this->notebook->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('requires a notebook', function () {
    $this->postJson('/import', ['file' => validExport()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notebook');
});

it('requires a name for a new notebook', function (?string $name) {
    $this->postJson('/import', ['file' => validExport(), 'notebook' => -1, 'notebookName' => $name])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notebookName');

    expect(Notebook::count())->toBe(1)
        ->and(ImportJob::count())->toBe(0);
})->with(['missing' => null, 'empty' => '']);

it('rejects files larger than 1 GB', function () {
    $this->postJson('/import', ['file' => UploadedFile::fake()->create('huge.zip', 1_048_577), 'notebook' => $this->notebook->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file' => 'greater than 1048576 kilobytes']);
});

it('answers 404 when the notebook does not exist', function (string|int $notebook) {
    $this->postJson('/import', ['file' => validExport(), 'notebook' => $notebook])
        ->assertNotFound()
        ->assertExactJson(['error' => 'Notebook not found']);

    expect(ImportJob::count())->toBe(0);
})->with(['unknown id' => 999999, 'not a number' => 'abc']);

it('answers 422 and marks the job failed when the zip cannot be opened', function () {
    // Starts like a zip so it passes validation, but is not a readable archive
    $file = EvernoteExport::corruptZip();

    $this->postJson('/import', ['file' => $file, 'notebook' => $this->notebook->id])
        ->assertStatus(422)
        ->assertExactJson(['error' => 'The file could not be imported']);

    $job = ImportJob::sole();
    expect($job->status)->toBe('failed')
        ->and(storage_path('app/'.$job->file_path))->not->toBeFile();
});

// BUG-28
it('does not leave an empty new notebook when the import fails', function () {
    $file = EvernoteExport::corruptZip();

    $this->postJson('/import', ['file' => $file, 'notebook' => -1, 'notebookName' => 'Should not stay'])
        ->assertStatus(422);

    expect(Notebook::where('name', 'Should not stay')->exists())->toBeFalse();
});

// BUG-26
it('does not import into a trashed notebook', function () {
    $this->notebook->delete();

    $this->postJson('/import', ['file' => validExport(), 'notebook' => $this->notebook->id])
        ->assertStatus(422);

    expect(Note::count())->toBe(0);
});

it('keeps the chosen notebook when the import fails', function () {
    $this->postJson('/import', ['file' => EvernoteExport::corruptZip(), 'notebook' => $this->notebook->id])
        ->assertStatus(422);

    expect($this->notebook->fresh())->not->toBeNull();
});

it('does not leave a failed job pointing to the removed new notebook', function () {
    $this->postJson('/import', ['file' => EvernoteExport::corruptZip(), 'notebook' => -1, 'notebookName' => 'Gone'])
        ->assertStatus(422);

    expect(ImportJob::count())->toBe(0);
});

it('rejects a notebook that is not a single value', function () {
    $this->postJson('/import', ['file' => validExport(), 'notebook' => [$this->notebook->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notebook');

    expect(ImportJob::count())->toBe(0);
});

it('rejects a new notebook name that is not text or is too long', function (mixed $name) {
    $this->postJson('/import', ['file' => validExport(), 'notebook' => -1, 'notebookName' => $name])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notebookName');

    expect(Notebook::count())->toBe(1);
})->with(['array' => [['a', 'b']], 'too long' => str_repeat('n', 256)]);
