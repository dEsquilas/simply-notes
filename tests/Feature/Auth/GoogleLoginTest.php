<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;

it('creates and logs in a new Google user', function () {
    fakeGoogleUser('jane@example.com');

    $this->get('/google/callback')->assertRedirect(RouteServiceProvider::HOME);

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Jane Doe');
});

it('logs in an existing user without creating a duplicate', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    fakeGoogleUser('jane@example.com');

    $this->get('/google/callback')->assertRedirect(RouteServiceProvider::HOME);

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

it('does not log in when Google returns an error', function () {
    $this->get('/google/callback?error=access_denied')->assertRedirect(RouteServiceProvider::HOME);

    $this->assertGuest();
});
