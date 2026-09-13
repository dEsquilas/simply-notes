<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Dusk\Browser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\DuskTestCase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

// Browser tests run with `php artisan dusk` (phpunit.dusk.xml), never with the normal suite
uses(DuskTestCase::class)->in('Browser');

/**
 * Makes the Google callback return this account without calling Google.
 */
function fakeGoogleUser(string $email, ?string $name = 'Jane Doe'): void
{
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn((new GoogleUser())->map(['email' => $email, 'name' => $name]));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

/**
 * Autosave fires between 1.5 and 3 seconds after the last keystroke.
 */
function waitForAutosave(Browser $browser): Browser
{
    return $browser->pause(3200);
}
