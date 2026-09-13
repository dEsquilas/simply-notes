<?php

use App\Models\User;
use Laravel\Dusk\Browser;

it('shows guests the Google login', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->logout()
        ->visit('/')
        ->waitForLocation('/login')
        ->assertSee('Welcome to SimplyNotes')
        ->assertSee('You can login via Google account')
        ->assertAttributeContains('@google-login', 'href', '/google/redirect')
    );
});

it('sends guests to the login screen from any page', function (string $path) {
    $this->browse(fn (Browser $browser) => $browser
        ->logout()
        ->visit($path)
        ->waitForLocation('/login')
        ->assertVisible('@google-login')
    );
})->with(['/notebooks', '/notebooks/trash', '/import', '/notebook/1', '/notebook/1/note/1']);

it('returns to the login screen when Google login is cancelled', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->logout()
        ->visit('/google/callback?error=access_denied')
        ->waitForLocation('/login')
        ->assertVisible('@google-login')
    );
});

it('shows the user name in the menu', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($user)
        ->visit('/notebooks')
        ->waitFor('@user-menu')
        ->assertSeeIn('@user-menu', 'Jane Doe')
    );
});

it('logs out from the user menu', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs(User::factory()->create())
        ->visit('/notebooks')
        ->waitFor('@user-menu')
        ->click('@user-menu')
        ->waitFor('@logout')
        ->click('@logout')
        ->waitForLocation('/login')
        ->visit('/notebooks')
        ->waitForLocation('/login')
    );
});

it('closes the user menu with Escape', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs(User::factory()->create())
        ->visit('/notebooks')
        ->waitFor('@user-menu')
        ->click('@user-menu')
        ->waitFor('@logout')
        ->keys('@user-menu', '{escape}')
        ->waitUntilMissing('@logout')
    );
});

it('moves between sections with the top menu', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs(User::factory()->create())
        ->visit('/notebooks')
        ->waitFor('@nav-trash')
        ->click('@nav-trash')
        ->waitForLocation('/notebooks/trash')
        ->assertSee('Trash')
        ->click('@nav-import')
        ->waitForLocation('/import')
        ->assertSee('Import Evernote Content')
        ->click('@nav-notebooks')
        ->waitForLocation('/notebooks')
        ->assertVisible('@create-notebook')
    );
});

it('takes the user home from the logo', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->loginAs(User::factory()->create())
        ->visit('/import')
        ->waitForText('Simple Notes')
        ->clickLink('Simple Notes')
        ->waitForLocation('/notebooks')
    );
});
