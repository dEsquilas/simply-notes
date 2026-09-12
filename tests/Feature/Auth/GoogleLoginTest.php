<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $email, string $name = 'Jane Doe'): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn((new GoogleUser())->map(['email' => $email, 'name' => $name]));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_new_google_user_is_created_and_logged_in(): void
    {
        $this->fakeGoogleUser('jane@example.com');

        $response = $this->get('/google/callback');

        $response->assertRedirect(RouteServiceProvider::HOME);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Jane Doe', $user->name);
    }

    public function test_existing_user_logs_in_without_creating_a_duplicate(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        $this->fakeGoogleUser('jane@example.com');

        $this->get('/google/callback')->assertRedirect(RouteServiceProvider::HOME);

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_google_error_does_not_log_in(): void
    {
        $this->get('/google/callback?error=access_denied')->assertRedirect(RouteServiceProvider::HOME);

        $this->assertGuest();
    }
}
