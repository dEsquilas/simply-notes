<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows an empty trash', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks/trash')
        ->waitFor('@trash-empty')
        ->assertSeeIn('@trash-empty', 'Nothing to show here')
    );
});

it('lists only trashed notebooks', function () {
    $trashed = Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'Binned']);
    Notebook::factory()->ownedBy($this->user)->create(['name' => 'Active']);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks/trash')
        ->waitFor('@trashed-notebook-'.$trashed->id)
        ->assertSeeIn('@trashed-notebook-'.$trashed->id, 'Binned')
        ->assertDontSee('Active')
        ->assertMissing('@trash-empty')
    );
});

it('restores a notebook after confirming', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create(['name' => 'Back again']);

    $this->browse(function (Browser $browser) use ($notebook) {
        $browser->loginAs($this->user)
            ->visit('/notebooks/trash')
            ->waitFor('@trashed-notebook-'.$notebook->id)
            ->click('@trashed-notebook-'.$notebook->id.' .dusk-restore-notebook')
            ->waitForDialog()
            ->assertDialogOpened('Are you sure you want to restore this notebook?')
            ->acceptDialog()
            ->waitUntilMissing('@trashed-notebook-'.$notebook->id)
            ->assertVisible('@trash-empty');

        waitForDatabase($browser, fn () => $notebook->fresh()->status === 0);

        $browser->visit('/notebooks')->waitForText('Back again');
    });
});

it('keeps the notebook in the trash when restoring is cancelled', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks/trash')
        ->waitFor('@trashed-notebook-'.$notebook->id)
        ->click('@trashed-notebook-'.$notebook->id.' .dusk-restore-notebook')
        ->waitForDialog()
        ->dismissDialog()
        ->pause(500)
        ->assertVisible('@trashed-notebook-'.$notebook->id)
    );

    expect($notebook->fresh()->status)->toBe(1);
});

it('deletes a notebook and its notes forever after confirming', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();
    Note::factory()->count(2)->for($notebook)->create();

    $this->browse(function (Browser $browser) use ($notebook) {
        $browser->loginAs($this->user)
            ->visit('/notebooks/trash')
            ->waitFor('@trashed-notebook-'.$notebook->id)
            ->click('@trashed-notebook-'.$notebook->id.' .dusk-delete-notebook')
            ->waitForDialog()
            ->assertDialogOpened('Are you sure you want to delete this notebook?')
            ->acceptDialog()
            ->waitUntilMissing('@trashed-notebook-'.$notebook->id);

        waitForDatabase($browser, fn () => Notebook::find($notebook->id) === null);
    });

    expect(Note::count())->toBe(0);
});

it('keeps the notebook when deleting is cancelled', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($this->user)
        ->visit('/notebooks/trash')
        ->waitFor('@trashed-notebook-'.$notebook->id)
        ->click('@trashed-notebook-'.$notebook->id.' .dusk-delete-notebook')
        ->waitForDialog()
        ->dismissDialog()
        ->pause(500)
        ->assertVisible('@trashed-notebook-'.$notebook->id)
    );

    expect($notebook->fresh())->not->toBeNull();
});

// BUG-11
it('tells the user when deleting fails and keeps the notebook listed', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $this->browse(function (Browser $browser) use ($notebook) {
        $browser->loginAs($this->user)->visit('/notebooks/trash')->waitFor('@trashed-notebook-'.$notebook->id);
        $notebook->forceFill(['owner' => User::factory()->create()->id])->save();

        $browser->click('@trashed-notebook-'.$notebook->id.' .dusk-delete-notebook')
            ->waitForDialog()
            ->acceptDialog()
            ->waitForText('Failed to delete notebook')
            ->assertVisible('@trashed-notebook-'.$notebook->id);
    });
})->todo();

// BUG-11
it('tells the user when restoring fails and keeps the notebook listed', function () {
    $notebook = Notebook::factory()->ownedBy($this->user)->trashed()->create();

    $this->browse(function (Browser $browser) use ($notebook) {
        $browser->loginAs($this->user)->visit('/notebooks/trash')->waitFor('@trashed-notebook-'.$notebook->id);
        $notebook->forceFill(['owner' => User::factory()->create()->id])->save();

        $browser->click('@trashed-notebook-'.$notebook->id.' .dusk-restore-notebook')
            ->waitForDialog()
            ->acceptDialog()
            ->waitForText('Failed to restore notebook')
            ->assertVisible('@trashed-notebook-'.$notebook->id);
    });
})->todo();
