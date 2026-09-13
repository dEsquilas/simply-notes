<?php

use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates an active notebook owned by the user', function () {
    $response = $this->postJson('/notebooks/create', ['name' => 'Recipes'])->assertOk();

    $notebook = Notebook::sole();
    expect($notebook->name)->toBe('Recipes')
        ->and($notebook->owner)->toBe($this->user->id)
        ->and($notebook->fresh()->trashed())->toBeFalse();
    $response->assertJsonPath('notebook.id', $notebook->id)
        ->assertJsonPath('notebook.name', 'Recipes');
});

it('trims spaces around the name', function () {
    $this->postJson('/notebooks/create', ['name' => '  Recipes  '])->assertOk();

    expect(Notebook::sole()->name)->toBe('Recipes');
});

it('accepts unicode names', function () {
    $this->postJson('/notebooks/create', ['name' => 'Recetas 日本語 📓'])->assertOk();

    expect(Notebook::sole()->name)->toBe('Recetas 日本語 📓');
});

it('rejects a missing, empty or blank name', function (array $payload) {
    $this->postJson('/notebooks/create', $payload)
        ->assertStatus(422)
        ->assertExactJson(['message' => 'Please provide a name for the notebook']);

    expect(Notebook::count())->toBe(0);
})->with([
    'missing' => [[]],
    'empty' => [['name' => '']],
    'only spaces' => [['name' => '   ']],
]);

it('creates two notebooks when asked twice with the same name', function () {
    $this->postJson('/notebooks/create', ['name' => 'Twice'])->assertOk();
    $this->postJson('/notebooks/create', ['name' => 'Twice'])->assertOk();

    expect(Notebook::where('name', 'Twice')->count())->toBe(2);
});

// BUG-24
it('accepts a notebook called "0"', function () {
    $this->postJson('/notebooks/create', ['name' => '0'])->assertOk();

    expect(Notebook::sole()->name)->toBe('0');
});

// BUG-29: no input validation, an array name currently returns a 500
it('rejects a name that is not text', function () {
    $this->postJson('/notebooks/create', ['name' => ['not', 'text']])->assertStatus(422);

    expect(Notebook::count())->toBe(0);
});

it('rejects names longer than 255 characters', function () {
    $this->postJson('/notebooks/create', ['name' => str_repeat('n', 256)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    expect(Notebook::count())->toBe(0);
});

it('accepts names of exactly 255 characters', function () {
    $this->postJson('/notebooks/create', ['name' => str_repeat('n', 255)])->assertOk();

    expect(Notebook::sole()->name)->toBe(str_repeat('n', 255));
});
