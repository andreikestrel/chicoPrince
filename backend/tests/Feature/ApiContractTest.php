<?php

namespace Tests\Feature;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_api_route_returns_json_without_accept_header(): void
    {
        $this->get('/api/does-not-exist')->assertNotFound()->assertExactJson([
            'error' => ['code' => 'not_found', 'message' => 'Recurso não encontrado.', 'details' => []],
        ]);
    }

    public function test_validation_errors_keep_field_messages(): void
    {
        Route::post('/api/_contract/validation', fn () => throw ValidationException::withMessages(['name' => ['Nome obrigatório.']]));

        $this->postJson('/api/_contract/validation')->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.name.0', 'Nome obrigatório.');
    }

    public function test_authentication_error_does_not_redirect_to_login(): void
    {
        Route::get('/api/_contract/auth', fn () => throw new AuthenticationException);

        $this->get('/api/_contract/auth')->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_server_errors_hide_internal_details_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/_contract/error', fn () => throw new \RuntimeException('private-internal-detail'));

        $this->getJson('/api/_contract/error')->assertStatus(500)->assertExactJson([
            'error' => ['code' => 'server_error', 'message' => 'Serviço temporariamente indisponível.', 'details' => []],
        ])->assertDontSee('private-internal-detail');
    }

    public function test_method_error_preserves_allow_header(): void
    {
        $this->postJson('/api/health')->assertStatus(405)->assertHeader('Allow', 'GET, HEAD')
            ->assertJsonPath('error.code', 'method_not_allowed');
    }

    public function test_forbidden_error_uses_the_same_contract(): void
    {
        Route::get('/api/_contract/forbidden', fn () => abort(403, 'internal authorization detail'));
        $this->getJson('/api/_contract/forbidden')->assertForbidden()->assertExactJson([
            'error' => ['code' => 'forbidden', 'message' => 'Acesso não permitido.', 'details' => []],
        ]);
    }

    public function test_resource_wraps_product_and_paginated_collection(): void
    {
        $product = Product::factory()->create(['price' => '19.90'])->fresh();
        Route::get('/api/_contract/product', fn () => new ProductResource($product));
        Route::get('/api/_contract/products', fn () => ProductResource::collection(Product::paginate(15)));

        $this->getJson('/api/_contract/product')->assertOk()->assertJsonPath('data.price', '19.90')
            ->assertJsonStructure(['data' => ['id', 'name', 'description', 'image', 'price', 'category', 'stock', 'created_at', 'updated_at']]);
        $this->getJson('/api/_contract/products')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);
    }
}
