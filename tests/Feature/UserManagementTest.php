<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('お名前')
            ->assertSee('メールアドレス')
            ->assertSee('パスワード');
    }

    public function test_users_can_register_with_valid_input(): void
    {
        $this->post('/register', [
            'name' => '山田 太郎',
            'email' => 'taro@example.com',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => '山田 太郎',
            'email' => 'taro@example.com',
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taro@example.com']);

        $this->from('/register')->post('/register', [
            'name' => '山田 太郎',
            'email' => 'taro@example.com',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors('email');
    }

    public function test_registration_rejects_invalid_email_format(): void
    {
        $this->from('/register')->post('/register', [
            'name' => '山田 太郎',
            'email' => 'invalid-email',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'name' => '山田 太郎',
            'email' => 'invalid-email',
        ]);
    }

    public function test_registration_validation_errors_can_be_seen_in_japanese(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => '',
            'password' => 'secret',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect('/register')
            ->assertSessionHasErrors(['name', 'email', 'password']);

        $this->followRedirects($response)
            ->assertSee('お名前は必ず入力してください。')
            ->assertSee('メールアドレスは必ず入力してください。')
            ->assertSee('パスワードが確認用と一致しません。');
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('メールアドレス')
            ->assertSee('パスワード');
    }

    public function test_users_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret'),
        ]);

        $this->post('/login', [
            'email' => 'taro@example.com',
            'password' => 'secret',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret'),
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'taro@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_error_message_can_be_seen_in_japanese(): void
    {
        User::factory()->create([
            'email' => 'taro@example.com',
            'password' => Hash::make('secret'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'taro@example.com',
            'password' => 'wrong-password',
        ]);

        $this->followRedirects($response)
            ->assertSee('メールアドレスまたはパスワードが正しくありません。');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login_when_accessing_authenticated_screen(): void
    {
        $this->get('/books/create')
            ->assertRedirect('/login');
    }

    public function test_authenticated_users_are_redirected_home_from_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect('/');
    }

    public function test_authenticated_users_are_redirected_home_from_register(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/register')
            ->assertRedirect('/');
    }

    public function test_password_is_stored_hashed(): void
    {
        $this->post('/register', [
            'name' => '山田 太郎',
            'email' => 'taro@example.com',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]);

        $password = User::where('email', 'taro@example.com')->value('password');

        $this->assertNotSame('secret', $password);
        $this->assertTrue(Hash::check('secret', $password));
    }
}
