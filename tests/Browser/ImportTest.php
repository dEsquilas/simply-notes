<?php

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\Support\EvernoteExport;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Evernote']);
});

function exportZipPath(array $files = []): string
{
    $files = $files ?: [
        'one.html' => EvernoteExport::html('One', '<div class="para">First</div>'),
        'two.html' => EvernoteExport::html('Two', '<div class="para">Second</div>'),
    ];
    $source = EvernoteExport::zip($files)->getPathname();
    $path = sys_get_temp_dir().'/export-'.uniqid().'.zip';
    copy($source, $path);

    return $path;
}

it('shows the empty import page', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@import-submit')
        ->assertSeeIn('@no-running-jobs', 'There are no jobs running.')
        ->assertSeeIn('@no-finished-jobs', 'There are no jobs completed.')
        ->assertSelectHasOptions('@import-notebook', ['', '-1', (string) $this->notebook->id])
        ->assertMissing('@import-new-notebook-name')
    );
});

it('does not send anything without a file and a notebook', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@import-submit')
        ->click('@import-submit')
        ->pause(800)
        ->assertVisible('@import-submit')
    );

    expect(ImportJob::count())->toBe(0);
});

// BUG-19
it('tells the user to choose a file and a notebook', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@import-submit')
        ->click('@import-submit')
        ->waitForText('You should select a file and a notebook.')
        ->pause(1500)
        ->assertSee('You should select a file and a notebook.')
    );
})->todo();

it('imports an export into an existing notebook', function () {
    $zip = exportZipPath();

    $this->browse(function (Browser $browser) use ($zip) {
        $browser->loginAs($this->user)
            ->visit('/import')
            ->waitFor('@import-submit')
            ->attach('@import-file', $zip)
            ->select('@import-notebook', (string) $this->notebook->id)
            ->click('@import-submit');

        waitForDatabase($browser, fn () => ImportJob::where('status', 'finished')->exists(), seconds: 10);
        $job = ImportJob::sole();

        $browser->waitFor('@finished-job-'.$job->id, 5)
            ->assertSeeIn('@finished-job-'.$job->id, 'Evernote')
            ->assertSeeIn('@finished-job-'.$job->id, '2')
            ->assertSeeIn('@no-running-jobs', 'There are no jobs running.')
            ->assertSelected('@import-notebook', '');
    });

    expect(Note::where('notebook_id', $this->notebook->id)->pluck('title')->sort()->values()->all())->toBe(['One', 'Two']);
});

it('imports an export into a new notebook', function () {
    $zip = exportZipPath();

    $this->browse(function (Browser $browser) use ($zip) {
        $browser->loginAs($this->user)
            ->visit('/import')
            ->waitFor('@import-submit')
            ->attach('@import-file', $zip)
            ->select('@import-notebook', '-1')
            ->waitFor('@import-new-notebook-name')
            ->type('@import-new-notebook-name', 'Imported notes')
            ->click('@import-submit');

        waitForDatabase($browser, fn () => Notebook::where('name', 'Imported notes')->exists(), seconds: 10);

        $browser->waitForText('Imported notes', 5);
    });

    $created = Notebook::where('name', 'Imported notes')->sole();
    expect(Note::where('notebook_id', $created->id)->count())->toBe(2);
});

it('opens the destination notebook from a finished job', function () {
    $job = ImportJob::factory()->finished(4)->create(['user_id' => $this->user->id, 'notebook_id' => $this->notebook->id]);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@finished-job-'.$job->id)
        ->assertSeeIn('@finished-job-'.$job->id, '4')
        ->clickLink('Evernote')
        ->waitForLocation('/notebook/'.$this->notebook->id)
    );
});

it('shows running jobs with their progress', function () {
    $pending = ImportJob::factory()->create(['user_id' => $this->user->id, 'notebook_id' => $this->notebook->id]);
    $processing = ImportJob::factory()->processing(10, 3)->create(['user_id' => $this->user->id, 'notebook_id' => $this->notebook->id]);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@running-job-'.$pending->id)
        ->assertSeeIn('@running-job-'.$pending->id, 'Calculating...')
        ->assertSeeIn('@running-job-'.$pending->id, 'Pending')
        ->assertSeeIn('@running-job-'.$processing->id, '3 / 10')
    );
});

it('moves a job to finished while the page is open', function () {
    $job = ImportJob::factory()->processing(2, 1)->create(['user_id' => $this->user->id, 'notebook_id' => $this->notebook->id]);

    $this->browse(function (Browser $browser) use ($job) {
        $browser->loginAs($this->user)->visit('/import')->waitFor('@running-job-'.$job->id);

        $job->forceFill(['status' => 'finished', 'processed_files' => 2])->save();

        $browser->waitFor('@finished-job-'.$job->id, 5)
            ->assertMissing('@running-job-'.$job->id);
    });
});

// BUG-08
it('labels jobs that are being processed', function () {
    $job = ImportJob::factory()->processing()->create(['user_id' => $this->user->id, 'notebook_id' => $this->notebook->id]);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@running-job-'.$job->id)
        ->assertSeeIn('@running-job-'.$job->id, 'Processing')
    );
})->todo();

// BUG-09
it('keeps working when a job\'s notebook was deleted', function () {
    ImportJob::factory()->finished()->create(['user_id' => $this->user->id, 'notebook_id' => $this->notebook->id]);
    $this->notebook->delete();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@import-submit', 5)
        ->assertSee('Finished jobs')
    );
})->todo();

// BUG-10
it('shows imports that failed', function () {
    $zip = exportZipPath();
    file_put_contents($zip, "PK\x03\x04".str_repeat("\0", 64));

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@import-submit')
        ->attach('@import-file', $zip)
        ->select('@import-notebook', (string) $this->notebook->id)
        ->click('@import-submit')
        ->waitForText('failed', 6)
    );
})->todo();

// BUG-20
it('explains why an import was rejected', function (string $case) {
    $path = $case === 'not a zip' ? tap(sys_get_temp_dir().'/notes-'.uniqid().'.txt', fn ($p) => file_put_contents($p, 'text')) : exportZipPath();

    $this->browse(function (Browser $browser) use ($path, $case) {
        $browser->loginAs($this->user)->visit('/import')->waitFor('@import-submit')->attach('@import-file', $path);

        if ($case === 'new notebook without name') {
            $browser->select('@import-notebook', '-1');
        } else {
            $browser->select('@import-notebook', (string) $this->notebook->id);
        }

        $browser->click('@import-submit')->waitForText('The', 6)->assertSee($case === 'not a zip' ? 'zip' : 'name');
    });
})->with(['not a zip', 'new notebook without name'])->todo();

// BUG-22
it('focuses the fields when clicking their labels', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@import-submit')
        ->clickAtXPath("//label[contains(., 'Select the notebook')]")
        ->assertFocused('@import-notebook')
    );
})->todo();

// BUG-26
it('does not offer trashed notebooks as destination', function () {
    $trashed = Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'Binned']);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@import-notebook')
        ->assertSelectMissingOption('@import-notebook', (string) $trashed->id)
    );
})->todo();

// BUG-34
it('shows the finish date in the user\'s time zone and format', function () {
    $job = ImportJob::factory()->finished()->create([
        'user_id' => $this->user->id,
        'notebook_id' => $this->notebook->id,
        'updated_at' => '2024-02-03 23:30:00',
    ]);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@finished-job-'.$job->id)
        ->assertSeeIn('@finished-job-'.$job->id, '3 de febrero de 2024')
    );
})->todo();
