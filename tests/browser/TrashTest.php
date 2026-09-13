<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function trashAction(Notebook $notebook, string $action): string
{
    return '[data-test="trashed-notebook-'.$notebook->id.'"] .test-'.$action.'-notebook';
}

it('shows an empty trash', function () {
    visit('/notebooks/trash')->assertSeeIn('@trash-empty', 'Nothing to show here');
});

it('lists only trashed notebooks', function () {
    $trashed = Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'Binned']);
    Notebook::factory()->ownedBy($this->user)->create(['name' => 'Active']);

    visit('/notebooks/trash')
        ->assertSeeIn('@trashed-notebook-'.$trashed->id, 'Binned')
        ->assertDontSee('Active')
        ->assertMissing('@trash-empty');
});

it('restores a notebook after confirming', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'Back again']);

    $page = visit('/notebooks/trash')->assertVisible('@trashed-notebook-'.$notebook->id);
    answerDialogs($page)
        ->click(trashAction($notebook, 'restore'))
        ->assertScript('window.__dialogs[0]', 'Are you sure you want to restore this notebook?')
        ->assertMissing('@trashed-notebook-'.$notebook->id)
        ->assertVisible('@trash-empty');

    waitForDatabase($page, fn () => $notebook->fresh()->status === 0);

    $page->navigate('/notebooks')->assertSee('Back again');
});

it('keeps the notebook in the trash when restoring is cancelled', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-notebook-'.$notebook->id);
    answerDialogs($page, confirm: false)
        ->click(trashAction($notebook, 'restore'))
        ->wait(0.3)
        ->assertVisible('@trashed-notebook-'.$notebook->id);

    expect($notebook->fresh()->status)->toBe(1);
});

it('deletes a notebook and its notes forever after confirming', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    Note::factory()->count(2)->for($notebook)->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-notebook-'.$notebook->id);
    answerDialogs($page)
        ->click(trashAction($notebook, 'delete'))
        ->assertScript('window.__dialogs[0]', 'Are you sure you want to delete this notebook?')
        ->assertMissing('@trashed-notebook-'.$notebook->id);

    waitForDatabase($page, fn () => Notebook::find($notebook->id) === null);

    expect(Note::count())->toBe(0);
});

it('keeps the notebook when deleting is cancelled', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-notebook-'.$notebook->id);
    answerDialogs($page, confirm: false)
        ->click(trashAction($notebook, 'delete'))
        ->wait(0.3)
        ->assertVisible('@trashed-notebook-'.$notebook->id);

    expect($notebook->fresh())->not->toBeNull();
});

// BUG-11
it('tells the user when deleting fails and keeps the notebook listed', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-notebook-'.$notebook->id);
    answerDialogs($page);
    $notebook->forceFill(['owner' => User::factory()->create()->id])->save();

    $page->click(trashAction($notebook, 'delete'))
        ->assertSee('Failed to delete notebook')
        ->assertVisible('@trashed-notebook-'.$notebook->id);
});

// BUG-11
it('tells the user when restoring fails and keeps the notebook listed', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-notebook-'.$notebook->id);
    answerDialogs($page);
    $notebook->forceFill(['owner' => User::factory()->create()->id])->save();

    $page->click(trashAction($notebook, 'restore'))
        ->assertSee('Failed to restore notebook')
        ->assertVisible('@trashed-notebook-'.$notebook->id);
});
