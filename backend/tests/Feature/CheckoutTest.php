<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function payload($variant, array $override = []): array
    {
        return array_replace([
            'request_key' => (string) Str::uuid(), 'coupon' => 'PRIMEIRACOMPRA',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2, 'unit_price' => '29.95']],
            'address' => ['recipient' => 'Teste', 'cep' => '01001-000', 'street' => 'Rua teste', 'number' => '1',
                'complement' => '', 'district' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP'],
        ], $override);
    }

    private function variant()
    {
        $product = Product::factory()->create(['price' => '29.95', 'stock' => 3]);

        return $product->variants()->create(['color' => 'AZUL', 'size' => 'M', 'stock' => 3]);
    }

    public function test_guest_cannot_order_and_customer_cannot_manage(): void
    {
        $variant = $this->variant();
        $this->postJson('/api/orders', $this->payload($variant))->assertUnauthorized();
        $customer = User::factory()->create();
        $this->actingAs($customer, 'web');
        $this->postJson('/api/products', [])->assertForbidden();
        $this->patchJson('/api/products/'.$variant->product_id, ['stock' => 99])->assertForbidden();
        $this->deleteJson('/api/products/'.$variant->product_id)->assertForbidden();
        $this->getJson('/api/admin/banners')->assertForbidden();
        $this->postJson('/api/admin/banners', [])->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_completion_uses_server_totals_and_replay_does_not_deduct_twice(): void
    {
        $variant = $this->variant();
        $this->actingAs(User::factory()->create(), 'web');
        $payload = $this->payload($variant);
        $first = $this->postJson('/api/orders', $payload + ['total' => 1])->assertCreated()
            ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.subtotal', 5990)
            ->assertJsonPath('data.discount', 599)->assertJsonPath('data.total', 5391);
        $this->assertSame(1, $variant->fresh()->stock);
        $this->assertSame(1, $variant->product->fresh()->stock);
        $this->postJson('/api/orders', $payload)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $variant->fresh()->stock);
        $payload['address']['number'] = '2';
        $this->postJson('/api/orders', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_stock_price_duplicate_and_address_validation_leave_no_order(): void
    {
        $variant = $this->variant();
        $this->actingAs(User::factory()->create(), 'web');
        foreach ([
            ['items' => [['variant_id' => $variant->id, 'quantity' => 4, 'unit_price' => '29.95']]],
            ['items' => [['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '0.01']]],
            ['address' => []], ['coupon' => 'INVALID'],
            ['items' => array_fill(0, 2, ['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '29.95'])],
        ] as $override) {
            $this->postJson('/api/orders', $this->payload($variant, $override))->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $variant->fresh()->stock);
    }

    public function test_coupon_only_applies_to_first_order_and_stock_cannot_be_oversold(): void
    {
        $variant = $this->variant();
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/orders', $this->payload($variant))->assertCreated();
        $second = $this->payload($variant, ['items' => [['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => '29.95']]]);
        $this->postJson('/api/orders', $second)->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['coupon']]]);
        $second['coupon'] = null;
        $this->postJson('/api/orders', $second)->assertCreated()->assertJsonPath('data.discount', 0);
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/orders', $this->payload($variant))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(0, $variant->fresh()->stock);
    }

    public function test_demo_accounts_have_requested_credentials_and_distinct_roles(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $this->assertDatabaseHas('users', ['email' => 'teste@chicoprince.com', 'is_admin' => false]);
        $this->postJson('/api/login', ['email' => 'admin@chicoprince.com', 'password' => '123456'])
            ->assertOk()->assertJsonPath('data.is_admin', true);
        $this->getJson('/api/admin/banners')->assertOk();
        $this->postJson('/api/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'teste@chicoprince.com', 'password' => '654321'])
            ->assertOk()->assertJsonPath('data.is_admin', false);
        $this->getJson('/api/admin/banners')->assertForbidden();
    }
}
