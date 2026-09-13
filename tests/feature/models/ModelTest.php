<?php

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('encrypts note titles and contents in the database', function () {
    $note = Note::factory()->create(['title' => 'Plain title', 'content' => '<p>Plain body</p>']);

    $row = DB::table('notes')->where('id', $note->id)->first();
    expect($row->title)->not->toBe('Plain title')
        ->and($row->content)->not->toContain('Plain body')
        ->and($note->fresh()->title)->toBe('Plain title')
        ->and($note->fresh()->content)->toBe('<p>Plain body</p>');
});

it('stores empty note fields as null without encrypting them', function () {
    $note = Note::factory()->create(['title' => null, 'content' => null]);

    $row = DB::table('notes')->where('id', $note->id)->first();
    expect($row->title)->toBeNull()
        ->and($row->content)->toBeNull();
});

it('cannot read notes encrypted with another key', function () {
    $note = Note::factory()->create(['title' => 'Secret']);

    Note::encryptUsing(new Encrypter(str_repeat('k', 32), 'AES-256-CBC'));

    try {
        expect(fn () => $note->fresh()->title)->toThrow(DecryptException::class);
    } finally {
        Note::encryptUsing(null);
    }
});

it('links notes and notebooks', function () {
    $notebook = Notebook::factory()->create();
    $active = Note::factory()->for($notebook)->create();
    $trashed = Note::factory()->for($notebook)->trashed()->create();

    expect($active->notebook->is($notebook))->toBeTrue()
        // The default relation excludes trashed notes, same as everywhere else in the app
        ->and($notebook->notes->pluck('id')->all())->toBe([$active->id])
        ->and($notebook->notes()->withTrashed()->pluck('id')->sort()->values()->all())->toBe([$active->id, $trashed->id]);
});

it('returns no notebook for notes and jobs whose notebook was deleted', function () {
    $notebook = Notebook::factory()->create();
    $note = Note::factory()->for($notebook)->create();
    $job = ImportJob::factory()->create(['notebook_id' => $notebook->id]);

    $notebook->delete();

    expect($note->fresh()->notebook)->toBeNull()
        ->and($job->fresh()->notebook)->toBeNull();
});

it('links import jobs to their notebook', function () {
    $job = ImportJob::factory()->create();

    expect($job->notebook)->toBeInstanceOf(Notebook::class)
        ->and($job->notebook->owner)->toBe($job->user_id);
});

it('hashes user passwords', function () {
    $user = User::factory()->create(['password' => 'plain-password']);

    expect($user->password)->not->toBe('plain-password')
        ->and(Hash::check('plain-password', $user->password))->toBeTrue();
});

it('never serializes user secrets', function () {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

it('only mass assigns name, email and password on users', function () {
    $user = new User(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret', 'remember_token' => 'x']);

    expect($user->name)->toBe('Jane')
        ->and($user->email)->toBe('jane@example.com')
        ->and($user->remember_token)->toBeNull();
});
