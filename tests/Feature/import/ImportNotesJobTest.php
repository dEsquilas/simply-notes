<?php

use App\Jobs\ImportNotes;
use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\EvernoteExport;

/** Stores the zip where uploads go and returns a pending import job for it. */
function pendingImport(array $files): ImportJob
{
    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();
    $filePath = 'import/'.Str::random(20).'.zip';

    File::ensureDirectoryExists(storage_path('app/import'));
    copy(EvernoteExport::zip($files)->getPathname(), storage_path('app/'.$filePath));

    return ImportJob::factory()->create(['user_id' => $user->id, 'notebook_id' => $notebook->id, 'file_path' => $filePath]);
}

function runEvernoteImport(array $files): ImportJob
{
    $job = pendingImport($files);
    ImportNotes::dispatchSync($job);

    return $job->fresh();
}

/** Imports a single note with this body and returns the stored note. */
function importNoteBody(string $body, string $title = 'Title', array $meta = []): Note
{
    $job = runEvernoteImport(['note.html' => EvernoteExport::html($title, $body, $meta)]);

    return Note::where('notebook_id', $job->notebook_id)->sole();
}

describe('job lifecycle', function () {
    it('finishes with progress counters and removes the upload and extracted files', function () {
        $job = pendingImport([
            'a.html' => EvernoteExport::html('A', '<div class="para">a</div>'),
            'b.html' => EvernoteExport::html('B', '<div class="para">b</div>'),
        ]);
        $extracted = storage_path('app/import/'.pathinfo($job->file_path, PATHINFO_FILENAME));

        ImportNotes::dispatchSync($job);

        $job->refresh();
        expect($job->status)->toBe('finished')
            ->and((int) $job->total_files)->toBe(2)
            ->and((int) $job->processed_files)->toBe(2)
            ->and(Note::where('notebook_id', $job->notebook_id)->count())->toBe(2)
            ->and(storage_path('app/'.$job->file_path))->not->toBeFile()
            ->and($extracted)->not->toBeDirectory();
    });

    it('finishes with zero notes when the export has no HTML files', function () {
        $job = runEvernoteImport(['readme.txt' => 'nothing to import']);

        expect($job->status)->toBe('finished')
            ->and((int) $job->total_files)->toBe(0)
            ->and(Note::count())->toBe(0);
    });

    it('only imports HTML files at the top level of the export', function () {
        $job = runEvernoteImport([
            'top.html' => EvernoteExport::html('Top', '<div class="para">top</div>'),
            'folder/nested.html' => EvernoteExport::html('Nested', '<div class="para">nested</div>'),
        ]);

        expect(Note::where('notebook_id', $job->notebook_id)->pluck('title')->all())->toBe(['Top']);
    });

    it('fails and cleans up when the zip cannot be opened', function () {
        File::ensureDirectoryExists(storage_path('app/import'));
        file_put_contents(storage_path('app/import/corrupt.zip'), 'not a zip');
        $job = ImportJob::factory()->create(['file_path' => 'import/corrupt.zip']);

        expect(fn () => ImportNotes::dispatchSync($job))->toThrow(RuntimeException::class, 'not a valid zip');

        expect($job->fresh()->status)->toBe('failed')
            ->and(storage_path('app/import/corrupt.zip'))->not->toBeFile();
    });

    it('fails when the export has more files than allowed', function () {
        config(['import.max_files' => 2]);
        $job = pendingImport(['a.html' => 'a', 'b.html' => 'b', 'c.html' => 'c']);

        expect(fn () => ImportNotes::dispatchSync($job))->toThrow(RuntimeException::class, 'too many files');

        expect($job->fresh()->status)->toBe('failed')
            ->and(storage_path('app/'.$job->file_path))->not->toBeFile()
            ->and(Note::count())->toBe(0);
    });

    it('fails when the export is too large once extracted', function () {
        config(['import.max_uncompressed_bytes' => 100]);
        $job = pendingImport(['big.html' => str_repeat('x', 101)]);

        expect(fn () => ImportNotes::dispatchSync($job))->toThrow(RuntimeException::class, 'too large');

        expect($job->fresh()->status)->toBe('failed')
            ->and(Note::count())->toBe(0);
    });

    it('accepts exports right at the limits', function () {
        config(['import.max_files' => 1, 'import.max_uncompressed_bytes' => 1000]);

        $job = runEvernoteImport(['note.html' => EvernoteExport::html('Fits', '<div class="para">ok</div>')]);

        expect($job->status)->toBe('finished');
    });

    it('cleans up without errors when the files are already gone', function () {
        $job = ImportJob::factory()->create(['file_path' => 'import/missing.zip']);

        (new ImportNotes($job))->failed(new RuntimeException('boom'));

        expect($job->fresh()->status)->toBe('failed');
    });
});

describe('note fields', function () {
    it('uses the first heading as title, trimmed and on one line', function () {
        $job = runEvernoteImport(['note.html' => '<html><body><h1>  Line one'.PHP_EOL.'line two  </h1><h1>Second heading</h1>'
            .'<en-note class="peso" style="white-space: inherit;"><div class="para">x</div></en-note></body></html>']);

        expect(Note::where('notebook_id', $job->notebook_id)->sole()->title)->toBe('Line oneline two');
    });

    it('leaves the title empty when there is no heading', function () {
        $job = runEvernoteImport(['note.html' => '<html><body><en-note class="peso" style="white-space: inherit;"><div class="para">x</div></en-note></body></html>']);

        expect(Note::where('notebook_id', $job->notebook_id)->sole()->title)->toBe('');
    });

    it('keeps the creation and update dates from the export', function () {
        $note = importNoteBody('<div class="para">x</div>', meta: ['created' => '2021-03-04 05:06:07', 'updated' => '2022-08-09 10:11:12']);

        expect($note->created_at->format('Y-m-d H:i:s'))->toBe('2021-03-04 05:06:07')
            ->and($note->updated_at->format('Y-m-d H:i:s'))->toBe('2022-08-09 10:11:12');
    });

    it('uses the import time when the export has no dates', function () {
        // The job uses PHP's date(), so time cannot be frozen: compare against the real clock
        $note = importNoteBody('<div class="para">x</div>');

        expect(abs($note->created_at->diffInSeconds(now())))->toBeLessThan(5)
            ->and(abs($note->updated_at->diffInSeconds(now())))->toBeLessThan(5);
    });

    it('stores notes encrypted and sanitized in the job\'s notebook', function () {
        $note = importNoteBody('<div class="para">Hello</div><script>alert(1)</script>');

        expect($note->content)->toBe('<p>Hello</p>')
            ->and(\DB::table('notes')->where('id', $note->id)->value('content'))->not->toContain('Hello');
    });
});

describe('HTML conversion', function () {
    it('converts paragraphs', function () {
        expect(importNoteBody('<div class="para">Hello <b>world</b></div>')->content)->toBe('<p>Hello <b>world</b></p>');
    });

    it('removes Evernote icons, metadata and note attributes from the body', function () {
        $content = importNoteBody('<icons><img src="icon.png"></icons><note-attributes>author</note-attributes><div class="para">Body</div>')->content;

        expect($content)->toBe('<p>Body</p>');
    });

    it('turns 24px text into Quill large text', function () {
        expect(importNoteBody('<div class="para"><span style="font-size: 24px;" data-fontsize="24">Big</span></div>')->content)
            ->toBe('<p><span class="ql-size-large">Big</span></p>');
    });

    it('turns bullet lists into Quill lists', function () {
        expect(importNoteBody('<ul><li>One</li><li>Two</li></ul>')->content)
            ->toBe('<ol><li data-list="bullet">One</li><li data-list="bullet">Two</li></ol>');
    });

    it('removes empty lists', function () {
        expect(importNoteBody('<div class="para">Before</div><ul></ul>')->content)->toBe('<p>Before</p>');
    });

    it('keeps nested list items as indented items', function () {
        $content = importNoteBody('<ul><li>Parent</li><ul><li>Child</li></ul></ul>')->content;

        expect($content)->toContain('Parent')
            ->toContain('Child')
            ->toContain('ql-indent-1');
    });

    it('keeps the text of Evernote code blocks', function () {
        expect(importNoteBody('<div><en-codeblock><div>SELECT 1;</div></en-codeblock></div>')->content)
            ->toBe('<div><div>SELECT 1;</div></div>');
    });

    it('collapses whitespace', function () {
        expect(importNoteBody("<div class=\"para\">a \n\n\t   b</div>")->content)->toBe('<p>a b</p>');
    });

    it('cleans tables, keeping only the text of each cell', function () {
        $content = importNoteBody('<div><en-table><table style="border:1px" width="100"><tbody>'
            .'<tr style="height:2px" height="2"><td width="50" valign="top" align="left" data-colwidth="50"><div><p>Cell</p></div></td></tr>'
            .'</tbody></table></en-table></div>')->content;

        expect($content)->toBe('<table><tbody><tr><td data-row="row-0">Cell</td></tr></tbody></table>');
    });

    it('numbers table rows', function () {
        $content = importNoteBody('<table><tbody><tr><td>a</td></tr><tr><td>b</td></tr></tbody></table>')->content;

        expect($content)->toContain('<td data-row="row-0">a</td>')
            ->toContain('<td data-row="row-1">b</td>');
    });

    // BUG-33
    it('starts row numbers again in each table', function () {
        $content = importNoteBody('<table><tbody><tr><td>a</td></tr></tbody></table><table><tbody><tr><td>b</td></tr></tbody></table>')->content;

        expect($content)->toContain('<td data-row="row-0">b</td>');
    })->todo();

    // BUG-31
    it('imports the body when en-note has different attributes', function () {
        $job = runEvernoteImport(['note.html' => '<html><body><h1>t</h1><en-note class="peso"><div class="para">Body</div></en-note></body></html>']);

        expect(Note::where('notebook_id', $job->notebook_id)->sole()->content)->toBe('<p>Body</p>');
    })->todo();

    it('imports tagged content that sits next to loose text', function () {
        expect(importNoteBody('loose text <div class="para">Kept</div>')->content)->toContain('<p>Kept</p>');
    });

    // BUG-32
    it('imports bodies that are plain text', function () {
        expect(importNoteBody('Just text')->content)->toBe('Just text');
    })->todo();
});

describe('images', function () {
    it('embeds images from the export as data URIs with their real type', function (string $file, string $mime) {
        $bytes = UploadedFile::fake()->image($file, 2, 2)->get();
        $job = runEvernoteImport([
            'note.html' => EvernoteExport::html('t', '<div><img src="res/'.$file.'"></div>'),
            'res/'.$file => $bytes,
        ]);

        expect(html_entity_decode(Note::where('notebook_id', $job->notebook_id)->sole()->content))
            ->toContain('data:'.$mime.';base64,'.base64_encode($bytes));
    })->with([
        'png' => ['pic.png', 'image/png'],
        'jpeg' => ['pic.jpg', 'image/jpeg'],
        'gif' => ['pic.gif', 'image/gif'],
    ]);

    it('embeds webp images', function () {
        $bytes = UploadedFile::fake()->image('pic.webp', 2, 2)->get();
        $job = runEvernoteImport(['note.html' => EvernoteExport::html('t', '<div><img src="pic.webp"></div>'), 'pic.webp' => $bytes]);

        expect(html_entity_decode(Note::where('notebook_id', $job->notebook_id)->sole()->content))->toContain('data:image/webp;base64,');
    })->skip(! function_exists('imagewebp'), 'GD without WebP support');

    it('understands Windows-style image paths', function () {
        $job = runEvernoteImport([
            'note.html' => EvernoteExport::html('t', '<div><img src="res\\pic.png"></div>'),
            'res/pic.png' => EvernoteExport::png(),
        ]);

        expect(Note::where('notebook_id', $job->notebook_id)->sole()->content)->toContain('data:image/png;base64,');
    });

    it('keeps external image URLs as they are', function () {
        expect(importNoteBody('<div><img src="https://example.com/pic.png"></div>')->content)
            ->toBe('<div><img src="https://example.com/pic.png" /></div>');
    });

    it('drops image sources that cannot be embedded', function (string $src, array $extraFiles) {
        $job = runEvernoteImport(['note.html' => EvernoteExport::html('t', '<div><img src="'.$src.'"></div>')] + $extraFiles);

        expect(Note::where('notebook_id', $job->notebook_id)->sole()->content)->toBe('<div><img /></div>');
    })->with([
        'missing file' => ['nope.png', []],
        'empty source' => ['', []],
        'directory' => ['res', ['res/readme.txt' => 'x']],
        'not an image' => ['notes.txt', ['notes.txt' => 'plain text']],
        'fake image extension' => ['evil.png', ['evil.png' => '<?php echo "hacked";']],
        'outside the export' => ['../../../../.env', []],
    ]);
});
