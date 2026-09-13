<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Pocket']);
});

function onPhone(Browser $browser): Browser
{
    return $browser->resize(390, 844);
}

it('shows the list first and switches to the editor and back', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'On the go']);

    $this->browse(fn (Browser $browser) => onPhone($browser)
        ->loginAs($this->user)
        ->visit("/notebook/{$this->notebook->id}")
        ->waitFor('@note-'.$note->id)
        ->assertVisible('@notes-sidebar')
        ->assertMissing('@editor-pane')
        ->assertMissing('@show-note-list')
        ->click('@note-'.$note->id)
        ->waitFor('@editor-pane')
        ->assertMissing('@notes-sidebar')
        ->assertInputValue('@note-title', 'On the go')
        ->assertSeeIn('@show-note-list', 'Ver listado')
        ->click('@show-note-list')
        ->waitFor('@notes-sidebar')
        ->assertMissing('@show-note-list')
    );
});

it('switches to the desktop layout when the window grows', function () {
    Note::factory()->for($this->notebook)->create();

    $this->browse(fn (Browser $browser) => onPhone($browser)
        ->loginAs($this->user)
        ->visit("/notebook/{$this->notebook->id}")
        ->waitFor('@notes-sidebar')
        ->assertMissing('@editor-pane')
        ->resize(1400, 900)
        ->waitFor('@editor-pane')
        ->assertVisible('@notes-sidebar')
    );
});

it('opens the mobile menu and navigates to the notebooks', function () {
    $this->browse(fn (Browser $browser) => onPhone($browser)
        ->loginAs($this->user)
        ->visit('/import')
        ->waitFor('@mobile-menu-button')
        ->assertMissing('@nav-trash')
        ->assertMissing('@mobile-menu')
        ->click('@mobile-menu-button')
        ->waitFor('@mobile-menu')
        ->assertSeeIn('@mobile-menu', $this->user->email)
        ->click('@mobile-nav-notebooks')
        ->waitForLocation('/notebooks')
    );
});

it('logs out from the mobile menu', function () {
    $this->browse(fn (Browser $browser) => onPhone($browser)
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@mobile-menu-button')
        ->click('@mobile-menu-button')
        ->waitFor('@mobile-logout')
        ->click('@mobile-logout')
        ->waitForLocation('/login')
    );
});

it('creates a notebook on a phone', function () {
    $this->browse(fn (Browser $browser) => onPhone($browser)
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->type('@new-notebook-name', 'From my phone')
        ->click('@create-notebook')
        ->waitForTextIn('@notebooks', 'From my phone')
    );
});

// BUG-12
it('reaches the trash and import from the mobile menu', function () {
    $this->browse(fn (Browser $browser) => onPhone($browser)
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@mobile-menu-button')
        ->click('@mobile-menu-button')
        ->waitFor('@mobile-menu')
        ->assertSeeIn('@mobile-menu', 'Trash')
        ->assertSeeIn('@mobile-menu', 'Import')
        ->click('@mobile-nav-trash')
        ->waitForLocation('/notebooks/trash')
        ->click('@mobile-menu-button')
        ->waitFor('@mobile-nav-import')
        ->click('@mobile-nav-import')
        ->waitForLocation('/import')
    );
});

afterEach(function () {
    $this->browse(fn (Browser $browser) => $browser->resize(1920, 1080));
});
