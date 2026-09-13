<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use App\Policies\NotebookPolicy;
use App\Policies\NotePolicy;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->owner)->create();
    $this->note = Note::factory()->for($this->notebook)->create();
});

it('maps the models to their policies', function () {
    expect(Gate::getPolicyFor(Note::class))->toBeInstanceOf(NotePolicy::class)
        ->and(Gate::getPolicyFor(Notebook::class))->toBeInstanceOf(NotebookPolicy::class);
});

it('lets only the owner act on a notebook', function (string $ability) {
    $policy = new NotebookPolicy();

    expect($policy->{$ability}($this->owner, $this->notebook))->toBeTrue()
        ->and($policy->{$ability}($this->stranger, $this->notebook))->toBeFalse();
})->with(['update', 'verifyOwnership']);

it('lets only the owner of the notebook act on a note', function () {
    $policy = new NotePolicy();

    expect($policy->verifyOwnership($this->owner, $this->note))->toBeTrue()
        ->and($policy->verifyOwnership($this->stranger, $this->note))->toBeFalse();
});

it('applies the same rules through the gate', function () {
    expect(Gate::forUser($this->owner)->allows('update', $this->notebook))->toBeTrue()
        ->and(Gate::forUser($this->stranger)->allows('update', $this->notebook))->toBeFalse()
        ->and(Gate::forUser($this->owner)->allows('verifyOwnership', $this->notebook))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('verifyOwnership', $this->note))->toBeTrue()
        ->and(Gate::forUser($this->stranger)->allows('verifyOwnership', $this->note))->toBeFalse();
});

it('denies a note whose notebook no longer exists', function () {
    $this->notebook->delete();

    expect((new NotePolicy())->verifyOwnership($this->owner, $this->note->fresh()))->toBeFalse();
});

it('still treats a trashed notebook as owned', function () {
    $this->notebook->forceFill(['status' => 1])->save();

    expect((new NotebookPolicy())->update($this->owner, $this->notebook))->toBeTrue();
});

// BUG-16 was resolved by deleting the unused, broken abilities
it('denies abilities the app does not define', function (string $ability, string $model) {
    expect(Gate::forUser($this->owner)->allows($ability, $model))->toBeFalse();
})->with([
    'note viewAny' => ['viewAny', Note::class],
    'note create' => ['create', Note::class],
    'notebook viewAny' => ['viewAny', Notebook::class],
    'notebook create' => ['create', Notebook::class],
]);
