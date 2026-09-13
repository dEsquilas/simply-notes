<?php

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->notebook = Notebook::factory()->ownedBy($this->user)->create(['name' => 'Desk']);
    Note::factory()->for($this->notebook)->create(['title' => 'Desktop note']);
    $this->url = "/notebook/{$this->notebook->id}";
    $this->actingAs($this->user);
});

it('hides and shows the notes list on desktop, expanding the editor', function () {
    $page = visit($this->url)->resize(1400, 900);

    $page->assertVisible('@notes-sidebar')
        ->assertVisible('@editor-pane')
        ->assertVisible('.test-hide-sidebar')
        ->click('.test-hide-sidebar')
        ->assertMissing('@notes-sidebar')
        ->assertVisible('@editor-pane')
        ->assertVisible('.test-show-sidebar')
        ->click('.test-show-sidebar')
        ->assertVisible('@notes-sidebar')
        ->assertVisible('@editor-pane')
        ->assertMissing('.test-show-sidebar');
});

it('persists the hidden desktop sidebar state after reloading', function () {
    $page = visit($this->url)->resize(1400, 900);

    $page->click('.test-hide-sidebar')
        ->assertMissing('@notes-sidebar')
        ->assertScript("window.localStorage.getItem('notebooks.desktopSidebarVisible')", 'false')
        ->refresh()
        ->assertMissing('@notes-sidebar')
        ->assertVisible('@editor-pane')
        ->assertVisible('.test-show-sidebar');
});

it('shows the notes list by default on desktop', function () {
    $page = visit($this->url)->resize(1400, 900);

    $page->assertVisible('@notes-sidebar')
        ->assertScript("window.localStorage.getItem('notebooks.desktopSidebarVisible') === null", true);
});

it('does not affect the mobile sidebar toggle', function () {
    $note = Note::factory()->for($this->notebook)->create(['title' => 'On the go']);

    $page = visit($this->url)->resize(390, 844);

    $page->assertVisible('@notes-sidebar')
        ->assertMissing('@editor-pane')
        ->assertMissing('.test-hide-sidebar')
        ->click('@note-'.$note->id)
        ->assertVisible('@editor-pane')
        ->assertMissing('@notes-sidebar')
        ->assertMissing('.test-show-sidebar')
        ->click('@show-note-list')
        ->assertVisible('@notes-sidebar');
});
