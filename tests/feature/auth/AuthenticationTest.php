<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the login screen for guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('canResetPassword', false)
        );
});

it('redirects authenticated users away from the login screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect(RouteServiceProvider::HOME);
});

it('has no password login', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(405);

    $this->assertGuest();
});

it('redirects the root URL to the notebooks list', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('notebooks.index'));
});

it('sends guests from the root URL to the login screen', function () {
    $this->followingRedirects()
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
});

it('logs users out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

it('keeps users logged out after logging out', function () {
    $this->actingAs(User::factory()->create())->post('/logout');

    $this->get('/notebooks')->assertRedirect('/login');
});

it('regenerates the CSRF token when logging out', function () {
    $this->actingAs(User::factory()->create());
    $tokenBefore = session()->token();

    $this->post('/logout');

    expect(session()->token())->not->toBe($tokenBefore);
});

it('signs a user in through the testing-only login route', function () {
    $user = User::factory()->create();

    $this->get("/testing/login/{$user->id}")->assertRedirect(route('notebooks.index'));

    $this->assertAuthenticatedAs($user);
});
