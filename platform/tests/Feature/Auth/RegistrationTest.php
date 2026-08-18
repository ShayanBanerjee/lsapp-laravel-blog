<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        // New accounts land on the notice, not the dashboard — the inbox they
        // just typed is still open, which it will not be an hour from now.
        $response->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_registering_sends_a_verification_email()
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Only fires because User implements MustVerifyEmail; the whole
        // scaffolding is inert without the contract.
        Notification::assertSentTo(User::whereEmail('test@example.com')->firstOrFail(), VerifyEmail::class);
    }
}
