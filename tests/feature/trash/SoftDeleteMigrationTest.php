<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('converts status=1 rows to deleted_at set to the migration run time, and status=0 rows to null', function () {
    // Roll back the note versions migration and the soft-deletes migration to get the old `status` column back
    Artisan::call('migrate:rollback', ['--step' => 2]);

    // Old, so a wrong implementation that copied updated_at into deleted_at would be easy to spot
    $oldTimestamp = now()->subYears(2)->startOfSecond();

    $activeNotebookId = DB::table('notebooks')->insertGetId([
        'name' => 'Active notebook', 'owner' => 1, 'status' => 0,
        'created_at' => $oldTimestamp, 'updated_at' => $oldTimestamp,
    ]);
    $trashedNotebookId = DB::table('notebooks')->insertGetId([
        'name' => 'Trashed notebook', 'owner' => 1, 'status' => 1,
        'created_at' => $oldTimestamp, 'updated_at' => $oldTimestamp,
    ]);
    $activeNoteId = DB::table('notes')->insertGetId([
        'notebook_id' => $activeNotebookId, 'status' => 0,
        'created_at' => $oldTimestamp, 'updated_at' => $oldTimestamp,
    ]);
    $trashedNoteId = DB::table('notes')->insertGetId([
        'notebook_id' => $activeNotebookId, 'status' => 1,
        'created_at' => $oldTimestamp, 'updated_at' => $oldTimestamp,
    ]);

    // Re-run the soft-deletes migration
    Artisan::call('migrate');

    $activeNotebook = DB::table('notebooks')->where('id', $activeNotebookId)->first();
    $trashedNotebook = DB::table('notebooks')->where('id', $trashedNotebookId)->first();
    $activeNote = DB::table('notes')->where('id', $activeNoteId)->first();
    $trashedNote = DB::table('notes')->where('id', $trashedNoteId)->first();

    expect($activeNotebook->deleted_at)->toBeNull()
        ->and($activeNote->deleted_at)->toBeNull()
        ->and($trashedNotebook->deleted_at)->not->toBeNull()
        ->and($trashedNote->deleted_at)->not->toBeNull()
        // Set to "now" (the migration's run time), not copied from the two-year-old updated_at
        ->and(\Carbon\Carbon::parse($trashedNotebook->deleted_at)->diffInMinutes(now()))->toBeLessThan(1)
        ->and(\Carbon\Carbon::parse($trashedNote->deleted_at)->diffInMinutes(now()))->toBeLessThan(1);

    expect(Schema::hasColumn('notes', 'status'))->toBeFalse()
        ->and(Schema::hasColumn('notebooks', 'status'))->toBeFalse();
});

it('restores the status column when rolling back, deriving it from deleted_at', function () {
    $trashedId = DB::table('notes')->insertGetId([
        'notebook_id' => DB::table('notebooks')->insertGetId(['name' => 'N', 'owner' => 1, 'created_at' => now(), 'updated_at' => now()]),
        'deleted_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $activeId = DB::table('notes')->insertGetId([
        'notebook_id' => DB::table('notebooks')->insertGetId(['name' => 'N2', 'owner' => 1, 'created_at' => now(), 'updated_at' => now()]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Artisan::call('migrate:rollback', ['--step' => 2]);

    $trashed = DB::table('notes')->where('id', $trashedId)->first();
    $active = DB::table('notes')->where('id', $activeId)->first();

    expect($trashed->status)->toBe(1)
        ->and($active->status)->toBe(0);

    // Leave the schema as every other test expects it
    Artisan::call('migrate');
});
