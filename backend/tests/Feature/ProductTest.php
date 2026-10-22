<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_sin_token_no_puede_ver_productos(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    public function test_un_usuario_autenticado_puede_listar_productos(): void
    {
        $user = User::factory()->create();
        Product::factory()->count(3)->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_un_usuario_autenticado_puede_crear_un_producto(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/products', [
            'name' => 'Laptop Lenovo',
            'description' => 'Laptop para desarrollo',
            'price' => 650.00,
            'stock' => 10,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Laptop Lenovo']);

        $this->assertDatabaseHas('products', [
            'name' => 'Laptop Lenovo',
        ]);
    }

    public function test_un_usuario_autenticado_puede_actualizar_un_producto(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/products/{$product->id}", [
            'price' => 199.99,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['price' => 199.99]);
    }

    public function test_un_usuario_autenticado_puede_eliminar_un_producto(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }
}