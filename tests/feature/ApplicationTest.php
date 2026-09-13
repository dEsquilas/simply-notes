<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('registers the console commands and schedule', function () {
    $this->artisan('inspire')->assertSuccessful();
    $this->artisan('schedule:list')->assertSuccessful();
});

it('creates the tables the app uses', function () {
    expect(Schema::hasColumns('notebooks', ['id', 'name', 'owner', 'status', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasColumns('notes', ['id', 'title', 'content', 'notebook_id', 'status', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasColumns('import_jobs', ['id', 'user_id', 'notebook_id', 'status', 'total_files', 'processed_files', 'file_path']))->toBeTrue()
        ->and(Schema::hasTable('jobs'))->toBeTrue();
});

it('rolls back the notes index migration', function () {
    // Runs only against the in-memory testing database forced by phpunit.xml
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');

    Artisan::call('migrate:rollback', ['--step' => 1]);
    expect(collect(Schema::getIndexes('notes'))->pluck('columns')->flatten()->all())->not->toContain('notebook_id');

    Artisan::call('migrate');
    expect(collect(Schema::getIndexes('notes'))->pluck('columns')->flatten()->all())->toContain('notebook_id');
});

// BUG-15
it('rolls back every migration', function () {
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');

    expect(Artisan::call('migrate:reset'))->toBe(0);
});
