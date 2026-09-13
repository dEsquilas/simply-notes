<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows no notebooks for a new user', function () {
    visit('/notebooks')
        ->assertVisible('@create-notebook')
        ->assertScript('document.querySelectorAll(\'[data-test="notebooks"] > *\').length', 0);
});

it('lists the user\'s notebooks, newest first, with note count and date', function () {
    $older = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Older', 'created_at' => '2024-01-05 10:00:00']);
    $newer = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Newer', 'created_at' => '2024-03-20 10:00:00']);
    Note::factory()->count(3)->for($older)->create();
    Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'In the trash']);

    visit('/notebooks')
        ->assertVisible('@notebook-'.$newer->id)
        ->assertScript('[...document.querySelectorAll(\'[data-test="notebook-name"]\')].map(el => el.textContent.trim()).join("|")', 'Newer|Older')
        ->assertSeeIn('[data-test="notebook-'.$older->id.'"] [data-test="notes-count"]', '3')
        ->assertSeeIn('[data-test="notebook-'.$older->id.'"] [data-test="notebook-date"]', '5 de enero de 2024')
        ->assertSeeIn('[data-test="notebook-'.$newer->id.'"] [data-test="notes-count"]', '0')
        ->assertDontSee('In the trash');
});

it('creates a notebook', function () {
    visit('/notebooks')
        ->type('@new-notebook-name', 'Recipes')
        ->click('@create-notebook')
        ->assertSeeIn('@notebooks', 'Recipes')
        ->assertValue('@new-notebook-name', '');

    expect(Notebook::where('owner', $this->user->id)->pluck('name')->all())->toBe(['Recipes']);
});

it('does nothing when creating a notebook without a name', function () {
    visit('/notebooks')
        ->click('@create-notebook')
        ->wait(0.3);

    expect(Notebook::count())->toBe(0);
});

it('creates notebooks with unicode names', function () {
    visit('/notebooks')
        ->type('@new-notebook-name', 'Recetas 日本語')
        ->click('@create-notebook')
        ->assertSeeIn('@notebooks', 'Recetas 日本語');
});

// BUG-23
it('creates one notebook on a double click', function () {
    $page = visit('/notebooks')->type('@new-notebook-name', 'Once');

    $page->page()->locator('[data-test="create-notebook"]')->dblclick();
    $page->assertSeeIn('@notebooks', 'Once')->wait(0.5);

    expect(Notebook::where('name', 'Once')->count())->toBe(1);
});

// BUG-23
it('shows a new notebook first, as after reloading', function () {
    Notebook::factory()->ownedBy($this->user)->create(['name' => 'Existing', 'created_at' => now()->subDay()]);

    visit('/notebooks')
        ->type('@new-notebook-name', 'Brand new')
        ->click('@create-notebook')
        ->assertSeeIn('@notebooks', 'Brand new')
        ->assertScript('document.querySelector(\'[data-test="notebook-name"]\').textContent.trim()', 'Brand new');
});

// BUG-19
it('confirms visibly that the notebook was created', function () {
    visit('/notebooks')
        ->type('@new-notebook-name', 'Visible')
        ->click('@create-notebook')
        ->assertSee('Created')
        // It used to vanish after 1 ms
        ->wait(1)
        ->assertSee('Created');
});

it('opens a notebook from its card', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Work']);

    visit('/notebooks')
        ->click('@notebook-'.$notebook->id)
        ->assertPathIs('/notebook/'.$notebook->id)
        ->assertVisible('@notes-sidebar');
});

it('sends a notebook to the trash from its context menu', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Old stuff']);

    $page = visit('/notebooks')->assertVisible('@notebook-'.$notebook->id);
    deleteFromContextMenu(answerDialogs($page), 'notebook-'.$notebook->id)
        ->assertScript('window.__dialogs[0]', 'Are you sure you want to send this notebook to the trash?')
        ->assertMissing('@notebook-'.$notebook->id);

    waitForDatabase($page, fn () => $notebook->fresh()->trashed());

    $page->navigate('/notebooks/trash')->assertSee('Old stuff');
});

it('keeps the notebook when sending it to the trash is cancelled', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Not yet']);

    $page = visit('/notebooks')->assertVisible('@notebook-'.$notebook->id);
    deleteFromContextMenu(answerDialogs($page, confirm: false), 'notebook-'.$notebook->id)
        ->wait(0.3)
        ->assertVisible('@notebook-'.$notebook->id);

    expect($notebook->fresh()->trashed())->toBeFalse();
});

// BUG-18
it('keeps the notebook on screen when sending it to the trash fails', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Stays']);

    $page = visit('/notebooks')->assertVisible('@notebook-'.$notebook->id);
    $notebook->forceDelete();

    deleteFromContextMenu(answerDialogs($page), 'notebook-'.$notebook->id)
        ->assertSee('The notebook could not be sent to the trash')
        ->assertVisible('@notebook-'.$notebook->id);
});
