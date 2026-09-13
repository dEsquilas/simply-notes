<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares the logged in user without secrets', function () {
    $user = User::factory()->create(['name' => 'Jane']);

    $this->actingAs($user)
        ->get('/notebooks')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', 'Jane')
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
        );
});

it('shares no user for guests', function () {
    $this->get('/login')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

it('shares the current URL with the route helper', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/notebooks')
        ->assertInertia(fn (Assert $page) => $page->where('ziggy.location', url('/notebooks')));
    $this->get('/notebooks/trash')
        ->assertInertia(fn (Assert $page) => $page->where('ziggy.location', url('/notebooks/trash')));
});
