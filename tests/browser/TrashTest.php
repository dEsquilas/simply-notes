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

function noteTrashAction(Note $note, string $action): string
{
    return '[data-test="trashed-note-'.$note->id.'"] .test-'.$action.'-note';
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

    waitForDatabase($page, fn () => ! $notebook->fresh()->trashed());

    $page->navigate('/notebooks')->assertSee('Back again');
});

it('keeps the notebook in the trash when restoring is cancelled', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-notebook-'.$notebook->id);
    answerDialogs($page, confirm: false)
        ->click(trashAction($notebook, 'restore'))
        ->wait(0.3)
        ->assertVisible('@trashed-notebook-'.$notebook->id);

    expect($notebook->fresh()->trashed())->toBeTrue();
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

it('shows when a trashed notebook and note were deleted and when they will be purged', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create(['deleted_at' => '2024-02-01 10:00:00']);
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create(['deleted_at' => '2024-02-01 10:00:00']);

    visit('/notebooks/trash')
        ->assertSeeIn('[data-test="trashed-notebook-'.$notebook->id.'"] [data-test="trashed-notebook-deleted-at"]', '1 de febrero de 2024')
        ->assertSeeIn('[data-test="trashed-notebook-'.$notebook->id.'"] [data-test="trashed-notebook-purge-at"]', '2 de marzo de 2024')
        ->assertSeeIn('[data-test="trashed-note-'.$note->id.'"] [data-test="trashed-note-deleted-at"]', '1 de febrero de 2024')
        ->assertSeeIn('[data-test="trashed-note-'.$note->id.'"] [data-test="trashed-note-purge-at"]', '2 de marzo de 2024');
});

it('permanently deletes a trashed note after confirming', function () {
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create(['title' => 'Gone for good']);

    $page = visit('/notebooks/trash')->assertVisible('@trashed-note-'.$note->id);
    answerDialogs($page)
        ->click(noteTrashAction($note, 'delete'))
        ->assertScript('window.__dialogs[0]', 'Are you sure you want to permanently delete this note?')
        ->assertMissing('@trashed-note-'.$note->id);

    waitForDatabase($page, fn () => Note::withTrashed()->find($note->id) === null);
});

it('keeps the note when permanent deletion is cancelled', function () {
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-note-'.$note->id);
    answerDialogs($page, confirm: false)
        ->click(noteTrashAction($note, 'delete'))
        ->wait(0.3)
        ->assertVisible('@trashed-note-'.$note->id);

    expect(Note::withTrashed()->find($note->id))->not->toBeNull();
});

it('tells the user when permanently deleting a note fails and keeps it listed', function () {
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-note-'.$note->id);
    answerDialogs($page);
    $note->forceDelete();

    $page->click(noteTrashAction($note, 'delete'))
        ->assertSee('Failed to delete note')
        ->assertVisible('@trashed-note-'.$note->id);
});

it('does not show the empty trash button when the trash is empty', function () {
    visit('/notebooks/trash')->assertMissing('@empty-trash');
});

it('empties the trash after confirming', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    Note::factory()->count(2)->for($notebook)->create();
    $note = Note::factory()->for(Notebook::factory()->ownedBy($this->user))->trashed()->create();

    $page = visit('/notebooks/trash')
        ->assertVisible('@trashed-notebook-'.$notebook->id)
        ->assertVisible('@trashed-note-'.$note->id);

    answerDialogs($page)
        ->click('@empty-trash')
        ->assertScript('window.__dialogs[0]', 'Are you sure you want to permanently delete everything in the trash?')
        ->assertVisible('@trash-empty');

    waitForDatabase($page, fn () => Notebook::withTrashed()->find($notebook->id) === null);

    expect(Note::withTrashed()->find($note->id))->toBeNull()
        ->and(Note::count())->toBe(0);
});

it('keeps the trash when emptying is cancelled', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $page = visit('/notebooks/trash')->assertVisible('@trashed-notebook-'.$notebook->id);
    answerDialogs($page, confirm: false)
        ->click('@empty-trash')
        ->wait(0.3)
        ->assertVisible('@trashed-notebook-'.$notebook->id);

    expect(Notebook::withTrashed()->find($notebook->id))->not->toBeNull();
});

