<?php

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

/*
 * Every protected route, checked for guests, other users and missing records.
 * {notebook} and {note} are replaced with real ids in each test.
 */

dataset('routes requiring login', [
    'notebooks list' => ['get', '/notebooks'],
    'create notebook' => ['post', '/notebooks/create'],
    'trash list' => ['get', '/notebooks/trash'],
    'import page' => ['get', '/import'],
    'import upload' => ['post', '/import'],
    'import polling' => ['get', '/import/polling'],
    'view notebook' => ['get', '/notebook/1'],
    'view note' => ['get', '/notebook/1/note/1'],
    'create note' => ['post', '/notes/create/1'],
    'update note' => ['post', '/notes/update/1'],
    'trash note' => ['post', '/notes/trash/1'],
    'trash notebook' => ['post', '/notebooks/trash/1'],
    'restore notebook' => ['post', '/notebooks/trash/restore/1'],
    'delete notebook' => ['post', '/notebooks/trash/delete/1'],
    'logout' => ['post', '/logout'],
]);

dataset('notebook routes', [
    'view notebook' => ['get', '/notebook/{notebook}'],
    'create note' => ['post', '/notes/create/{notebook}'],
    'trash notebook' => ['post', '/notebooks/trash/{notebook}'],
    'restore notebook' => ['post', '/notebooks/trash/restore/{notebook}'],
    'delete notebook' => ['post', '/notebooks/trash/delete/{notebook}'],
]);

dataset('note routes', [
    'view note' => ['get', '/notebook/{notebook}/note/{note}'],
    'update note' => ['post', '/notes/update/{note}'],
    'trash note' => ['post', '/notes/trash/{note}'],
]);

function routeFor(string $uri, ?Notebook $notebook = null, ?Note $note = null): string
{
    return str_replace(['{notebook}', '{note}'], [$notebook?->id ?? 999999, $note?->id ?? 999999], $uri);
}

it('redirects guests to login', function (string $method, string $uri) {
    $this->{$method}($uri)->assertRedirect('/login');
})->with('routes requiring login');

it('answers 401 to guest JSON requests', function (string $method, string $uri) {
    $this->{$method.'Json'}($uri)->assertUnauthorized();
})->with('routes requiring login');

it('forbids notebook routes on another user\'s notebook', function (string $method, string $uri) {
    $notebook = Notebook::factory()->create();
    $note = Note::factory()->for($notebook)->create();

    $this->actingAs(User::factory()->create())
        ->{$method.'Json'}(routeFor($uri, $notebook))
        ->assertForbidden();

    expect($notebook->fresh())->not->toBeNull()
        ->and($notebook->fresh()->status)->toBe(0)
        ->and($note->fresh())->not->toBeNull()
        ->and(Note::count())->toBe(1);
})->with('notebook routes');

it('forbids note routes on another user\'s note', function (string $method, string $uri) {
    $note = Note::factory()->create(['title' => 'Original']);

    $this->actingAs(User::factory()->create())
        ->{$method.'Json'}(routeFor($uri, $note->notebook, $note), ['title' => 'hacked'])
        ->assertForbidden();

    expect($note->fresh()->title)->toBe('Original')
        ->and($note->fresh()->status)->toBe(0);
})->with('note routes');

it('forbids opening another user\'s note through your own notebook', function () {
    $user = User::factory()->create();
    $ownNotebook = Notebook::factory()->ownedBy($user)->create();
    $foreignNote = Note::factory()->create();

    $this->actingAs($user)
        ->get("/notebook/{$ownNotebook->id}/note/{$foreignNote->id}")
        ->assertForbidden();
});

it('answers 404 for notes that do not exist', function (string $method, string $uri) {
    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();

    $this->actingAs($user)
        ->{$method.'Json'}(routeFor($uri, $notebook))
        ->assertNotFound();
})->with('note routes');

it('answers 403 for notebooks that do not exist', function (string $method, string $uri) {
    $this->actingAs(User::factory()->create())
        ->{$method.'Json'}(routeFor($uri))
        ->assertForbidden();
})->with('notebook routes');

// BUG-05
it('answers 404 for notebooks that do not exist', function (string $method, string $uri) {
    $this->actingAs(User::factory()->create())
        ->{$method.'Json'}(routeFor($uri))
        ->assertNotFound();
})->with('notebook routes')->todo();

it('treats non-numeric ids as missing records', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson('/notebook/abc')->assertForbidden();
    $this->postJson('/notes/update/abc')->assertNotFound();
});

it('never lists another user\'s data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Notebook::factory()->ownedBy($other)->create(['name' => 'Foreign active']);
    Notebook::factory()->ownedBy($other)->trashed()->create(['name' => 'Foreign trashed']);
    ImportJob::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user);

    $this->get('/notebooks')->assertDontSee('Foreign active');
    $this->get('/notebooks/trash')->assertDontSee('Foreign trashed');
    $this->get('/import')->assertDontSee('Foreign active');
    $this->getJson('/import/polling')->assertExactJson(['runningJobs' => [], 'finishedJobs' => []]);
});
