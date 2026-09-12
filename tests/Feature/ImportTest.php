<?php

namespace Tests\Feature;

use App\Jobs\ImportNotes;
use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function notebookOf(User $user): Notebook
    {
        $notebook = new Notebook();
        $notebook->owner = $user->id;
        $notebook->name = 'Imported';
        $notebook->save();

        return $notebook;
    }

    private function evernoteHtml(string $title, string $body): string
    {
        return '<html><body><h1>'.$title.'</h1>'
            .'<en-note class="peso" style="white-space: inherit;">'.$body.'</en-note></body></html>';
    }

    /** @param  array<string, string>  $files */
    private function zip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'export.zip', 'application/zip', null, true);
    }

    private function importAs(User $user, Notebook $notebook, UploadedFile $file)
    {
        return $this->actingAs($user)->postJson('/import', ['file' => $file, 'notebook' => $notebook->id]);
    }

    public function test_imports_an_evernote_export_into_an_owned_notebook(): void
    {
        $user = User::factory()->create();
        $notebook = $this->notebookOf($user);
        $html = $this->evernoteHtml('My note', '<div class="para">Hello <b>world</b></div><div><img src="image.png"></div>');

        $this->importAs($user, $notebook, $this->zip(['note.html' => $html, 'image.png' => base64_decode(self::PNG)]))
            ->assertOk();

        $note = Note::where('notebook_id', $notebook->id)->sole();
        $this->assertSame('My note', $note->title);
        $this->assertStringContainsString('<p>Hello <b>world</b></p>', $note->content);
        $this->assertStringContainsString('data:image/png;base64,'.self::PNG, html_entity_decode($note->content));

        $job = ImportJob::sole();
        $this->assertSame('finished', $job->status);
        $this->assertFileDoesNotExist(storage_path('app/'.$job->file_path));
    }

    public function test_does_not_embed_files_from_outside_the_export(): void
    {
        $user = User::factory()->create();
        $notebook = $this->notebookOf($user);
        // The export is extracted to storage/app/import/<name>/, so ../../ points at storage/app
        $secret = storage_path('app/outside-secret.png');
        file_put_contents($secret, base64_decode(self::PNG));

        try {
            $html = $this->evernoteHtml('t', '<div><img src="../../outside-secret.png"></div><div><img src="../../../../.env"></div>');
            $this->importAs($user, $notebook, $this->zip(['note.html' => $html]))->assertOk();
        } finally {
            File::delete($secret);
        }

        $content = Note::where('notebook_id', $notebook->id)->sole()->content;
        $this->assertStringNotContainsString('base64', $content);
    }

    public function test_does_not_embed_non_image_files_from_the_export(): void
    {
        $user = User::factory()->create();
        $notebook = $this->notebookOf($user);
        $html = $this->evernoteHtml('t', '<div><img src="notes.txt"></div>');

        $this->importAs($user, $notebook, $this->zip(['note.html' => $html, 'notes.txt' => 'plain text']))->assertOk();

        $this->assertStringNotContainsString('base64', Note::where('notebook_id', $notebook->id)->sole()->content);
    }

    public function test_strips_scripts_and_event_handlers_from_imported_html(): void
    {
        $user = User::factory()->create();
        $notebook = $this->notebookOf($user);
        $html = $this->evernoteHtml('t', '<div><img src="https://example.com/a.png" onerror="alert(1)"></div><script>alert(2)</script>');

        $this->importAs($user, $notebook, $this->zip(['note.html' => $html]))->assertOk();

        $content = Note::where('notebook_id', $notebook->id)->sole()->content;
        $this->assertStringContainsString('https://example.com/a.png', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertStringNotContainsString('<script', $content);
    }

    public function test_rejects_files_that_are_not_zip(): void
    {
        $user = User::factory()->create();
        $notebook = $this->notebookOf($user);

        $this->importAs($user, $notebook, UploadedFile::fake()->create('evil.php', 10))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, ImportJob::count());
    }

    public function test_marks_the_job_as_failed_when_the_zip_is_corrupt(): void
    {
        $user = User::factory()->create();
        $notebook = $this->notebookOf($user);
        File::ensureDirectoryExists(storage_path('app/import'));
        file_put_contents(storage_path('app/import/corrupt.zip'), 'not a zip');

        $job = new ImportJob();
        $job->user_id = $user->id;
        $job->notebook_id = $notebook->id;
        $job->status = 'pending';
        $job->file_path = 'import/corrupt.zip';
        $job->save();

        try {
            ImportNotes::dispatchSync($job);
            $this->fail('A corrupt zip must throw');
        } catch (\RuntimeException) {
        }

        $this->assertSame('failed', $job->fresh()->status);
        $this->assertFileDoesNotExist(storage_path('app/import/corrupt.zip'));
    }

    public function test_cannot_import_into_someone_elses_notebook(): void
    {
        [$user, $other] = [User::factory()->create(), User::factory()->create()];

        $this->importAs($user, $this->notebookOf($other), $this->zip(['note.html' => $this->evernoteHtml('t', '')]))
            ->assertForbidden();

        $this->assertSame(0, ImportJob::count());
    }
}
