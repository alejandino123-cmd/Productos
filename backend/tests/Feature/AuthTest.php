<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_registrarse(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Andy Test',
            'email' => 'andy@test.com',
            'password' => '123456',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'andy@test.com',
        ]);
    }

    public function test_un_usuario_puede_iniciar_sesion(): void
    {
        $user = User::factory()->create([
            'email' => 'andy@test.com',
            'password' => Hash::make('123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'andy@test.com',
            'password' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_no_se_puede_iniciar_sesion_con_credenciales_incorrectas(): void
    {
        User::factory()->create([
            'email' => 'andy@test.com',
            'password' => Hash::make('123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'andy@test.com',
            'password' => 'password-incorrecto',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_logout(): void
{
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $this->withToken($token)->postJson('/api/logout')->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
}
}