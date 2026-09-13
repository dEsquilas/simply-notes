<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

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
