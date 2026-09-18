<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_national_code(): void
    {
        $user = User::factory()->create([
            'national_code' => '1234567890',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'username' => '1234567890',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'national_code' => '1234567890',
        ]);

        $this->post('/login', [
            'username' => '1234567890',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_national_code_must_be_exactly_10_digits(): void
    {
        $response = $this->post('/login', [
            'username' => '123456789', // 9 digits
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
