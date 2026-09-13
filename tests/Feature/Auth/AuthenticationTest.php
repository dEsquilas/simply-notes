<?php

use App\Models\User;

it('renders the login screen', function () {
    $this->withoutVite()->get('/login')->assertOk();
});

it('has no password login', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(405);

    $this->assertGuest();
});

it('redirects guests to login', function () {
    $this->get('/notebooks')->assertRedirect('/login');
});

it('logs users out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
