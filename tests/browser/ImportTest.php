<?php

use App\Models\ImportJob;
use App\Models\Notebook;
use App\Models\User;

/*
 * The browser plugin's in-process server does not receive uploaded files (multipart bodies), so the zip
 * upload itself is covered by tests/feature/import. These tests cover everything the import page shows.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Evernote']);
    $this->actingAs($this->user);
});

function jobFor($test, string $state = 'pending', array $attributes = []): ImportJob
{
    $factory = match ($state) {
        'processing' => ImportJob::factory()->processing(),
        'finished' => ImportJob::factory()->finished(),
        'failed' => ImportJob::factory()->failed(),
        default => ImportJob::factory(),
    };

    return $factory->create(['user_id' => $test->user->id, 'notebook_id' => $test->notebook->id, ...$attributes]);
}

it('shows the empty import page', function () {
    visit('/import')
        ->assertSeeIn('@no-running-jobs', 'There are no jobs running.')
        ->assertSeeIn('@no-finished-jobs', 'There are no jobs completed.')
        ->assertScript('[...document.querySelectorAll(\'[data-test="import-notebook"] option\')].map(o => o.value).join("|")', '|-1|'.$this->notebook->id)
        ->assertMissing('@import-new-notebook-name')
        ->assertDontSee('Failed jobs')
        ->assertNoJavaScriptErrors();
});

it('asks for the name of a new notebook', function () {
    visit('/import')
        ->select('@import-notebook', '-1')
        ->assertVisible('@import-new-notebook-name')
        ->select('@import-notebook', (string) $this->notebook->id)
        ->assertMissing('@import-new-notebook-name');
});

it('does not send anything without a file and a notebook', function () {
    visit('/import')
        ->click('@import-submit')
        ->assertSee('You should select a file and a notebook.')
        ->assertVisible('@import-submit');

    expect(ImportJob::count())->toBe(0);
});

// BUG-19
it('tells the user to choose a file and a notebook', function () {
    visit('/import')
        ->click('@import-submit')
        ->assertSee('You should select a file and a notebook.')
        // It used to vanish after 1 ms
        ->wait(1)
        ->assertSee('You should select a file and a notebook.');
});

// BUG-20
it('shows why the server rejected an import', function () {
    $file = tempnam(sys_get_temp_dir(), 'export').'.zip';
    file_put_contents($file, 'not really a zip');

    visit('/import')
        ->attach('@import-file', $file)
        ->select('@import-notebook', (string) $this->notebook->id)
        ->click('@import-submit')
        ->assertSee('The file field')
        ->assertVisible('@import-submit');

    expect(ImportJob::count())->toBe(0);
});

it('opens the destination notebook from a finished job', function () {
    $job = jobFor($this, 'finished', ['total_files' => 4, 'processed_files' => 4]);

    visit('/import')
        ->assertSeeIn('@finished-job-'.$job->id, '4')
        ->click('[data-test="finished-job-'.$job->id.'"] a')
        ->assertPathIs('/notebook/'.$this->notebook->id);
});

it('shows running jobs with their progress', function () {
    $pending = jobFor($this);
    $processing = ImportJob::factory()->processing(10, 3)->create(['user_id' => $this->user->id, 'notebook_id' => $this->notebook->id]);

    visit('/import')
        ->assertSeeIn('@running-job-'.$pending->id, 'Calculating...')
        ->assertSeeIn('@running-job-'.$pending->id, 'Pending')
        ->assertSeeIn('@running-job-'.$processing->id, '3 / 10');
});

it('moves a job to finished while the page is open', function () {
    $job = ImportJob::factory()->processing(2, 1)->create(['user_id' => $this->user->id, 'notebook_id' => $this->notebook->id]);

    $page = visit('/import')->assertVisible('@running-job-'.$job->id);

    $job->forceFill(['status' => 'finished', 'processed_files' => 2])->save();

    $page->assertVisible('@finished-job-'.$job->id)
        ->assertMissing('@running-job-'.$job->id);
});

it('moves a job to failed while the page is open', function () {
    $job = jobFor($this, 'processing');

    $page = visit('/import')->assertVisible('@running-job-'.$job->id);

    $job->forceFill(['status' => 'failed'])->save();

    $page->assertSeeIn('@failed-job-'.$job->id, 'Import failed')
        ->assertMissing('@running-job-'.$job->id);
});

// BUG-08
it('labels jobs that are being processed', function () {
    $job = jobFor($this, 'processing');

    visit('/import')->assertSeeIn('@running-job-'.$job->id, 'Processing');
});

// BUG-09
it('keeps working when a job\'s notebook was deleted', function () {
    $job = jobFor($this, 'finished');
    $this->notebook->delete();

    visit('/import')
        ->assertSee('Finished jobs')
        ->assertSeeIn('@finished-job-'.$job->id, 'Deleted notebook')
        ->assertNoJavaScriptErrors();
});

// BUG-10
it('shows imports that failed', function () {
    $job = jobFor($this, 'failed');

    visit('/import')
        ->assertSee('Failed jobs')
        ->assertSeeIn('@failed-job-'.$job->id, 'Evernote')
        ->assertSeeIn('@failed-job-'.$job->id, 'Import failed');
});

// BUG-22
it('focuses the fields when clicking their labels', function () {
    visit('/import')
        ->click('label[for="notebook"]')
        ->assertScript('document.activeElement.dataset.test === "import-notebook"')
        ->click('label[for="file_input"]')
        ->assertScript('document.activeElement.dataset.test === "import-file"');
});

// BUG-26
it('does not offer trashed notebooks as destination', function () {
    $trashed = Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'Binned']);

    visit('/import')
        ->assertVisible('@import-notebook')
        ->assertScript('[...document.querySelectorAll(\'[data-test="import-notebook"] option\')].some(o => o.value === "'.$trashed->id.'")', false);
});

// BUG-34
it('shows the finish date in the user\'s time zone and format', function (string $timezone, string $date) {
    $job = jobFor($this, 'finished', ['updated_at' => '2024-02-03 23:30:00']);

    visit('/import')
        ->withTimezone($timezone)
        ->assertSeeIn('@finished-job-'.$job->id, $date);
})->with([
    'Madrid, already the next day' => ['Europe/Madrid', '4 de febrero de 2024'],
    'New York, still the same day' => ['America/New_York', '3 de febrero de 2024'],
]);
