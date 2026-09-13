<?php

use App\Models\User;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('feature');

// Browser tests serve the app from this same process, so they share the in-memory database and use the built assets
// The browser plugin only starts for tests that call visit() in their own body (or live in a capitalized
// tests/Browser folder): every test here must call it directly, see tests/arch/BrowserTestsTest.php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('browser');

// Autosave and imports take a few seconds: assertions retry for up to 10 s
pest()->browser()->timeout(10_000);

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
 * The page's wait() keeps serving the browser's requests meanwhile.
 */
function waitForDatabase($page, callable $condition, float $seconds = 8)
{
    $deadline = microtime(true) + $seconds;

    while (! $condition() && microtime(true) < $deadline) {
        $page->wait(0.1);
    }

    expect((bool) $condition())->toBeTrue('The database never reached the expected state');

    return $page;
}

/**
 * Right-clicks an element and chooses "Eliminar" in its context menu.
 */
function deleteFromContextMenu($page, string $testId)
{
    return $page->rightClick('@'.$testId)
        ->assertSee('Eliminar')
        ->click('.mx-context-menu-item');
}

/**
 * Selects all the text in the note editor so a toolbar button applies to it.
 */
function selectAllInEditor($page)
{
    $page->script(<<<'JS'
        () => {
            const editor = document.querySelector('[data-test="note-body"] .ql-editor');
            const range = document.createRange();
            range.selectNodeContents(editor);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            document.dispatchEvent(new Event('selectionchange'));
        }
    JS);

    return $page->wait(0.2);
}

/**
 * Answers the page's confirm() and prompt() dialogs, which the browser would otherwise dismiss,
 * and records their messages in window.__dialogs.
 */
function answerDialogs($page, bool $confirm = true, ?string $prompt = null)
{
    $page->script('() => {
        window.__dialogs = [];
        window.confirm = (message) => { window.__dialogs.push(message); return '.json_encode($confirm).'; };
        window.prompt = (message) => { window.__dialogs.push(message); return '.json_encode($prompt).'; };
    }');

    return $page;
}

/**
 * Types like a user, key by key: the note title only autosaves on keydown.
 */
function typeLikeAUser($page, string $selector, string $text, bool $replace = true)
{
    if ($replace) {
        $page->clear($selector);
    }

    return $page->typeSlowly($selector, $text, 10);
}

/**
 * Waits for the note editor to be ready.
 */
function readyToEdit($page)
{
    return $page->assertVisible('@note-title');
}

/**
 * Signs in with a real session cookie in this page's browser context (routes/web.php, testing only),
 * so several pages can be signed in as different users at the same time.
 */
function signIn($page, User $user)
{
    // The app serves every page from this one process: after each request (its session is already saved), forget
    // the resolved user and the session data, or the next request would inherit them whatever its cookie says
    if (! app()->bound('testing.isolated-sessions')) {
        Event::listen(RequestHandled::class, function () {
            auth()->forgetGuards();
            app('session')->driver()->flush();
        });
        app()->instance('testing.isolated-sessions', true);
    }

    return $page->navigate("/testing/login/{$user->id}")->assertPathIs('/notebooks');
}
