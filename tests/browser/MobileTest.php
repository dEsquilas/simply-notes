<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Pocket']);
    $this->actingAs($this->user);
});

function onPhone($page)
{
    return $page->resize(390, 844);
}

it('shows the list first and switches to the editor and back', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'On the go']);

    $page = visit("/notebook/{$this->notebook->id}");

    onPhone($page)
        ->assertVisible('@notes-sidebar')
        ->assertMissing('@editor-pane')
        ->assertMissing('@show-note-list')
        ->click('@note-'.$note->id)
        ->assertVisible('@editor-pane')
        ->assertMissing('@notes-sidebar')
        ->assertValue('@note-title', 'On the go')
        ->assertSeeIn('@show-note-list', 'Ver listado')
        ->click('@show-note-list')
        ->assertVisible('@notes-sidebar')
        ->assertMissing('@show-note-list');
});

it('switches to the desktop layout when the window grows', function () {
    Note::factory()->for($this->notebook)->create();

    $page = visit("/notebook/{$this->notebook->id}");

    onPhone($page)
        ->assertVisible('@notes-sidebar')
        ->assertMissing('@editor-pane')
        ->resize(1400, 900)
        ->assertVisible('@editor-pane')
        ->assertVisible('@notes-sidebar');
});

it('opens the mobile menu and navigates to the notebooks', function () {
    $page = visit('/import');

    onPhone($page)
        ->assertMissing('@nav-trash')
        ->assertMissing('@mobile-menu')
        ->click('@mobile-menu-button')
        ->assertSeeIn('@mobile-menu', $this->user->email)
        ->click('@mobile-nav-notebooks')
        ->assertPathIs('/notebooks');
});

it('logs out from the mobile menu', function () {
    $page = visit('/notebooks');

    onPhone($page)
        ->click('@mobile-menu-button')
        ->click('@mobile-logout')
        ->assertPathIs('/login');
});

it('creates a notebook on a phone', function () {
    $page = visit('/notebooks');

    onPhone($page)
        ->type('@new-notebook-name', 'From my phone')
        ->click('@create-notebook')
        ->assertSeeIn('@notebooks', 'From my phone');
});

// BUG-12
it('reaches the trash and import from the mobile menu', function () {
    $page = visit('/notebooks');

    onPhone($page)
        ->click('@mobile-menu-button')
        ->assertSeeIn('@mobile-menu', 'Trash')
        ->assertSeeIn('@mobile-menu', 'Import')
        ->click('@mobile-nav-trash')
        ->assertPathIs('/notebooks/trash')
        ->click('@mobile-menu-button')
        ->click('@mobile-nav-import')
        ->assertPathIs('/import');
});
