<?php

use App\Jobs\ImportNotes;
use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\Support\EvernoteExport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create();
});

function importInto(Notebook $notebook, UploadedFile $file)
{
    return test()->postJson('/import', ['file' => $file, 'notebook' => $notebook->id]);
}

it('imports an Evernote export into an owned notebook', function () {
    $html = EvernoteExport::html('My note', '<div class="para">Hello <b>world</b></div><div><img src="image.png"></div>');

    $this->actingAs($this->user);
    importInto($this->notebook, EvernoteExport::zip(['note.html' => $html, 'image.png' => EvernoteExport::png()]))
        ->assertOk();

    $note = Note::where('notebook_id', $this->notebook->id)->sole();
    expect($note->title)->toBe('My note')
        ->and($note->content)->toContain('<p>Hello <b>world</b></p>')
        ->and(html_entity_decode($note->content))->toContain('data:image/png;base64,'.EvernoteExport::PNG);

    $job = ImportJob::sole();
    expect($job->status)->toBe('finished')
        ->and(storage_path('app/'.$job->file_path))->not->toBeFile();
});

it('does not embed files from outside the export', function () {
    // The export is extracted to storage/app/import/<name>/, so ../../ points at storage/app
    $secret = storage_path('app/outside-secret.png');
    file_put_contents($secret, EvernoteExport::png());

    try {
        $html = EvernoteExport::html('t', '<div><img src="../../outside-secret.png"></div><div><img src="../../../../.env"></div>');
        $this->actingAs($this->user);
        importInto($this->notebook, EvernoteExport::zip(['note.html' => $html]))->assertOk();
    } finally {
        File::delete($secret);
    }

    expect(Note::where('notebook_id', $this->notebook->id)->sole()->content)->not->toContain('base64');
});

it('does not embed non-image files from the export', function () {
    $html = EvernoteExport::html('t', '<div><img src="notes.txt"></div>');

    $this->actingAs($this->user);
    importInto($this->notebook, EvernoteExport::zip(['note.html' => $html, 'notes.txt' => 'plain text']))->assertOk();

    expect(Note::where('notebook_id', $this->notebook->id)->sole()->content)->not->toContain('base64');
});

it('strips scripts and event handlers from imported HTML', function () {
    $html = EvernoteExport::html('t', '<div><img src="https://example.com/a.png" onerror="alert(1)"></div><script>alert(2)</script>');

    $this->actingAs($this->user);
    importInto($this->notebook, EvernoteExport::zip(['note.html' => $html]))->assertOk();

    expect(Note::where('notebook_id', $this->notebook->id)->sole()->content)
        ->toContain('https://example.com/a.png')
        ->not->toContain('onerror')
        ->not->toContain('<script');
});

it('rejects files that are not zip', function () {
    $this->actingAs($this->user);
    importInto($this->notebook, UploadedFile::fake()->create('evil.php', 10))
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    expect(ImportJob::count())->toBe(0);
});

it('marks the job as failed when the zip is corrupt', function () {
    File::ensureDirectoryExists(storage_path('app/import'));
    file_put_contents(storage_path('app/import/corrupt.zip'), 'not a zip');
    $job = ImportJob::factory()->create([
        'user_id' => $this->user->id,
        'notebook_id' => $this->notebook->id,
        'file_path' => 'import/corrupt.zip',
    ]);

    expect(fn () => ImportNotes::dispatchSync($job))->toThrow(RuntimeException::class);

    expect($job->fresh()->status)->toBe('failed')
        ->and(storage_path('app/import/corrupt.zip'))->not->toBeFile();
});

it('cannot import into someone else\'s notebook', function () {
    $foreign = Notebook::factory()->create();

    $this->actingAs($this->user);
    importInto($foreign, EvernoteExport::zip(['note.html' => EvernoteExport::html('t', '')]))
        ->assertForbidden();

    expect(ImportJob::count())->toBe(0);
});
