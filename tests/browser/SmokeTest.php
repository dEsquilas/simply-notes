<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

it('shows the Google login to guests', function () {
    visit('/')
        ->assertPathIs('/login')
        ->assertVisible('@google-login')
        ->assertSeeIn('@google-login', 'Sign up with Google')
        ->assertNoJavaScriptErrors();
});

it('opens the notebooks list for a logged in user', function () {
    $this->actingAs(User::factory()->create());

    visit('/notebooks')
        ->assertVisible('@create-notebook')
        ->assertSee('Create a new notebook')
        ->assertNoJavaScriptErrors();
});

it('opens a notebook with its notes and editor', function () {
    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();
    Note::factory()->for($notebook)->create(['title' => 'Hello', 'content' => '<p>World</p>']);
    $this->actingAs($user);

    visit("/notebook/{$notebook->id}")
        ->assertValue('@note-title', 'Hello')
        ->assertSeeIn('@note-body', 'World')
        ->assertNoJavaScriptErrors();
});
