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
    ->in('feature');

// Browser tests run with `php artisan dusk` (phpunit.dusk.xml), never with the normal suite
uses(DuskTestCase::class)->in('browser');

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
 * Waits until the database reflects what the browser did (autosave fires 1.5–3 s after the last keystroke).
 */
function waitForDatabase(Browser $browser, callable $condition, int $seconds = 6): Browser
{
    $browser->waitUsing($seconds, 100, fn () => (bool) $condition(), 'The database never reached the expected state');
    expect((bool) $condition())->toBeTrue();

    return $browser;
}

/**
 * Right-clicks an element and chooses "Eliminar" in its context menu.
 */
function deleteFromContextMenu(Browser $browser, string $selector): Browser
{
    return $browser->rightClick($selector)
        ->waitForText('Eliminar')
        ->clickAtXPath("//*[contains(@class, 'mx-context-menu-item')][contains(., 'Eliminar')]");
}

/**
 * Selects all the text in the note editor so a toolbar button applies to it.
 */
function selectAllInEditor(Browser $browser): Browser
{
    $browser->script(<<<'JS'
        const editor = document.querySelector('[dusk="note-body"] .ql-editor');
        const range = document.createRange();
        range.selectNodeContents(editor);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        document.dispatchEvent(new Event('selectionchange'));
    JS);

    return $browser->pause(200);
}

/**
 * Opens a notebook as its owner and waits for the notes page.
 */
function openNotebookAs(Browser $browser, \App\Models\User $user, \App\Models\Notebook $notebook): Browser
{
    // Browsers are reused between tests: always start from a desktop-sized window
    return $browser->resize(1920, 1080)
        ->loginAs($user)
        ->visit("/notebook/{$notebook->id}")
        ->waitFor('@notes-sidebar');
}
