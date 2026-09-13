<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

it('redirects to the Google consent screen', function () {
    $this->get('/google/redirect')
        ->assertRedirect()
        ->assertRedirectContains('accounts.google.com');
});

it('creates and logs in a new Google user', function () {
    fakeGoogleUser('jane@example.com');

    $this->get('/google/callback')->assertRedirect(RouteServiceProvider::HOME);

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Jane Doe');
});

it('gives new Google users an unguessable hashed password', function () {
    Str::createRandomStringsUsing(fn () => 'random-password-from-str-random');
    fakeGoogleUser('jane@example.com');

    $this->get('/google/callback');

    $password = User::where('email', 'jane@example.com')->value('password');
    expect($password)->not->toBe('random-password-from-str-random')
        ->and(Hash::check('random-password-from-str-random', $password))->toBeTrue();

    Str::createRandomStringsNormally();
});

it('logs in an existing user without creating a duplicate', function () {
    $user = User::factory()->create(['email' => 'jane@example.com', 'name' => 'Original Name']);
    fakeGoogleUser('jane@example.com', 'Name From Google');

    $this->get('/google/callback')->assertRedirect(RouteServiceProvider::HOME);

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1)
        ->and($user->fresh()->name)->toBe('Original Name');
});

it('regenerates the session when logging in with Google', function () {
    fakeGoogleUser('jane@example.com');
    $this->startSession();
    $sessionBefore = session()->getId();

    $this->get('/google/callback');

    expect(session()->getId())->not->toBe($sessionBefore);
});

it('does not log in when Google returns an error', function () {
    $this->get('/google/callback?error=access_denied')->assertRedirect(RouteServiceProvider::HOME);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

// BUG-30
it('sends the user back to login when Google fails', function () {
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andThrow(new RuntimeException('Google is down'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/google/callback')->assertRedirect('/login');

    $this->assertGuest();
});

// BUG-30
it('logs in a Google account that has no name', function () {
    fakeGoogleUser('noname@example.com', null);

    $this->get('/google/callback')->assertRedirect(RouteServiceProvider::HOME);

    $this->assertAuthenticated();
    expect(User::sole()->name)->toBe('noname@example.com');
});
