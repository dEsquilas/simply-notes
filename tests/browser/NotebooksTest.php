<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows no notebooks for a new user', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->assertScript('document.querySelectorAll(\'[dusk="notebooks"] > *\').length', 0)
    );
});

it('lists the user\'s notebooks, newest first, with note count and date', function () {
    $older = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Older', 'created_at' => '2024-01-05 10:00:00']);
    $newer = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Newer', 'created_at' => '2024-03-20 10:00:00']);
    Note::factory()->count(3)->for($older)->create();
    Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'In the trash']);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@notebook-'.$newer->id)
        ->assertScript('[...document.querySelectorAll(\'[dusk="notebook-name"]\')].map(el => el.textContent.trim()).join("|")', 'Newer|Older')
        ->assertSeeIn('@notebook-'.$older->id, '3')
        ->assertSeeIn('@notebook-'.$older->id, '5 de enero de 2024')
        ->assertSeeIn('@notebook-'.$newer->id, '0')
        ->assertDontSee('In the trash')
    );
});

it('creates a notebook', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->type('@new-notebook-name', 'Recipes')
        ->click('@create-notebook')
        ->waitForTextIn('@notebooks', 'Recipes')
        ->assertInputValue('@new-notebook-name', '')
    );

    expect(Notebook::where('owner', $this->user->id)->pluck('name')->all())->toBe(['Recipes']);
});

it('does nothing when creating a notebook without a name', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->click('@create-notebook')
        ->pause(800)
    );

    expect(Notebook::count())->toBe(0);
});

it('creates notebooks with unicode names', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->type('@new-notebook-name', 'Recetas 日本語')
        ->click('@create-notebook')
        ->waitForTextIn('@notebooks', 'Recetas 日本語')
    );
});

// BUG-23
it('creates one notebook on a double click', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->type('@new-notebook-name', 'Once')
        ->doubleClick('@create-notebook')
        ->pause(1000)
    );

    expect(Notebook::where('name', 'Once')->count())->toBe(1);
});

// BUG-23
it('shows a new notebook first, as after reloading', function () {
    Notebook::factory()->ownedBy($this->user)->create(['name' => 'Existing', 'created_at' => now()->subDay()]);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->type('@new-notebook-name', 'Brand new')
        ->click('@create-notebook')
        ->waitForTextIn('@notebooks', 'Brand new')
        ->assertScript('document.querySelector(\'[dusk="notebook-name"]\').textContent.trim()', 'Brand new')
    );
});

// BUG-19
it('confirms visibly that the notebook was created', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->type('@new-notebook-name', 'Visible')
        ->click('@create-notebook')
        ->waitForText('Created')
        ->pause(1500)
        ->assertSee('Created')
    );
});

it('opens a notebook from its card', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Work']);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks')
        ->waitFor('@notebook-'.$notebook->id)
        ->click('@notebook-'.$notebook->id)
        ->waitForLocation('/notebook/'.$notebook->id)
        ->assertVisible('@notes-sidebar')
    );
});

it('sends a notebook to the trash from its context menu', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Old stuff']);

    $this->browse(function (Browser $browser) use ($notebook) {
        $browser->loginAs($this->user)
            ->visit('/notebooks')
            ->waitFor('@notebook-'.$notebook->id);

        deleteFromContextMenu($browser, '@notebook-'.$notebook->id)
            ->waitUntilMissing('@notebook-'.$notebook->id);

        waitForDatabase($browser, fn () => $notebook->fresh()->status === 1);

        $browser->visit('/notebooks/trash')->waitForText('Old stuff');
    });
});

// BUG-18
it('keeps the notebook on screen when sending it to the trash fails', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Stays']);

    $this->browse(function (Browser $browser) use ($notebook) {
        $browser->loginAs($this->user)->visit('/notebooks')->waitFor('@notebook-'.$notebook->id);
        $notebook->delete();

        deleteFromContextMenu($browser, '@notebook-'.$notebook->id)
            ->pause(1000)
            ->assertVisible('@notebook-'.$notebook->id);
    });
});
