<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class ProductLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_writes_log_only_action_and_identifiers(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->with('products')->times(3)->andReturn($logger);
        $logger->shouldReceive('info')->once()->with('product.created', Mockery::on(fn ($context) => array_keys($context) === ['product_id', 'user_id'] && $context['user_id'] === $user->id));
        $this->actingAs($user, 'web');
        $created = $this->postJson('/api/products', ['name' => 'Teste', 'description' => 'Não registrar este conteúdo', 'price' => '20.00', 'category' => 'Teste'])->assertCreated();
        $id = $created->json('data.id');
        $logger->shouldReceive('info')->once()->with('product.updated', ['product_id' => $id, 'user_id' => $user->id]);
        $logger->shouldReceive('info')->once()->with('product.deleted', ['product_id' => $id, 'user_id' => $user->id]);
        $this->patchJson('/api/products/'.$id, ['stock' => 1])->assertOk();
        $this->deleteJson('/api/products/'.$id)->assertNoContent();
    }

    public function test_rejected_writes_do_not_log_success(): void
    {
        Log::shouldReceive('channel')->with('products')->never();
        $product = Product::factory()->create();
        $this->deleteJson('/api/products/'.$product->id)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'web');
        $this->patchJson('/api/products/'.$product->id, ['stock' => -1])->assertUnprocessable();
        $this->deleteJson('/api/products/999999')->assertNotFound();
    }
}
