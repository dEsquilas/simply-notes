<?php

use App\Models\User;

it('shows guests the Google login', function () {
    visit('/')
        ->assertPathIs('/login')
        ->assertSee('Welcome to SimplyNotes')
        ->assertSee('You can login via Google account')
        ->assertAttributeContains('@google-login', 'href', '/google/redirect');
});

it('sends guests to the login screen from any page', function (string $path) {
    visit($path)
        ->assertPathIs('/login')
        ->assertVisible('@google-login');
})->with(['/notebooks', '/notebooks/trash', '/import', '/notebook/1', '/notebook/1/note/1']);

it('returns to the login screen when Google login is cancelled', function () {
    visit('/google/callback?error=access_denied')
        ->assertPathIs('/login')
        ->assertVisible('@google-login');
});

it('shows the user name in the menu', function () {
    $this->actingAs(User::factory()->create(['name' => 'Jane Doe']));

    visit('/notebooks')->assertSeeIn('@user-menu', 'Jane Doe');
});

it('logs out from the user menu', function () {
    $this->actingAs(User::factory()->create());

    visit('/notebooks')
        ->click('@user-menu')
        ->click('@logout')
        ->assertPathIs('/login')
        ->navigate('/notebooks')
        ->assertPathIs('/login');
});

it('closes the user menu with Escape', function () {
    $this->actingAs(User::factory()->create());

    visit('/notebooks')
        ->click('@user-menu')
        ->assertVisible('@logout')
        ->keys('@user-menu', 'Escape')
        ->assertMissing('@logout');
});

it('moves between sections with the top menu', function () {
    $this->actingAs(User::factory()->create());

    visit('/notebooks')
        ->click('@nav-trash')
        ->assertPathIs('/notebooks/trash')
        ->assertSee('Trash')
        ->click('@nav-import')
        ->assertPathIs('/import')
        ->assertSee('Import Evernote Content')
        ->click('@nav-notebooks')
        ->assertPathIs('/notebooks')
        ->assertVisible('@create-notebook');
});

it('takes the user home from the logo', function () {
    $this->actingAs(User::factory()->create());

    visit('/import')
        ->click('Simple Notes')
        ->assertPathIs('/notebooks');
});
