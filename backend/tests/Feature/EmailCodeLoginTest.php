<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailCodeLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_superadmin_logs_in_directly_with_token(): void
    {
        User::factory()->create([
            'email' => 'admin@test.com',
            'password' => 'secret123',
            'role' => 'superadmin',
        ]);

        $this->postJson('/api/login', ['email' => 'admin@test.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('is_superadmin', true)
            ->assertJsonStructure(['token', 'user']);

        Mail::assertNothingSent();
    }

    public function test_hardcoded_email_does_not_grant_superadmin(): void
    {
        $user = User::factory()->create([
            'email' => 'andy@test.com',
            'password' => 'secret123',
            'role' => null,
        ]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('requires_2fa', true)
            ->assertJsonMissingPath('token');
    }

    public function test_normal_user_gets_code_by_email_and_no_token(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('requires_2fa', true)
            ->assertJsonPath('is_superadmin', false)
            ->assertJsonMissingPath('token')
            ->assertJsonMissingPath('user')
            ->assertJsonMissingPath('debug_code');

        $user->refresh();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->verification_code);
        $this->assertTrue($user->verification_code_expires_at->isFuture());

        Mail::assertSent(VerificationCodeMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_wrong_password_does_not_generate_a_code(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'otra-clave'])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->verification_code);
        Mail::assertNothingSent();
    }

    public function test_correct_code_returns_token_and_hides_secrets(): void
    {
        $user = User::factory()->create(['google2fa_secret' => 'SECRETOQR']);
        $user->forceFill([
            'verification_code' => '123456',
            'verification_code_expires_at' => now()->addMinutes(5),
        ])->save();

        $this->postJson('/api/verify-code', ['email' => $user->email, 'code' => '123456'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonMissingPath('user.verification_code')
            ->assertJsonMissingPath('user.google2fa_secret');

        $this->assertNull($user->fresh()->verification_code);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'verification_code' => '123456',
            'verification_code_expires_at' => now()->addMinutes(5),
        ])->save();

        $this->postJson('/api/verify-code', ['email' => $user->email, 'code' => '654321'])
            ->assertStatus(422)
            ->assertJsonMissingPath('token');
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'verification_code' => '123456',
            'verification_code_expires_at' => now()->subMinute(),
        ])->save();

        $this->postJson('/api/verify-code', ['email' => $user->email, 'code' => '123456'])
            ->assertStatus(422)
            ->assertJsonMissingPath('token');
    }

    public function test_code_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'verification_code' => '123456',
            'verification_code_expires_at' => now()->addMinutes(5),
        ])->save();

        $datos = ['email' => $user->email, 'code' => '123456'];

        $this->postJson('/api/verify-code', $datos)->assertOk();
        $this->postJson('/api/verify-code', $datos)->assertStatus(422);
    }
}