<?php

use App\Models\User;
use Laravel\Dusk\Browser;

it('shows the Google login to guests', function () {
    $this->browse(fn (Browser $browser) => $browser
        ->visit('/')
        ->assertPathIs('/login')
        ->assertVisible('@google-login')
        ->assertSeeIn('@google-login', 'Sign up with Google')
    );
});

it('opens the notebooks list for a logged in user', function () {
    $user = User::factory()->create();

    $this->browse(fn (Browser $browser) => $browser
        ->loginAs($user)
        ->visit('/notebooks')
        ->waitFor('@create-notebook')
        ->assertSee('Create a new notebook')
    );
});
