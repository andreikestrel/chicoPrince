<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\CatalogCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_are_distinct_and_slug_filters_preserve_pagination(): void
    {
        Product::factory()->count(3)->create(['category' => 'Calças', 'price' => '49.90']);
        Product::factory()->create(['category' => 'Camisetas', 'price' => '39.90']);
        $this->getJson('/api/categories')->assertOk()->assertJsonCount(2, 'data');
        $first = $this->getJson('/api/products?category_slug=calcas&per_page=2&sort=price&direction=desc')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.category', 'Calças');
        $this->assertStringContainsString('category_slug=calcas', $first->json('links.next'));
        $this->getJson($first->json('links.next'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'Calças');
        $this->getJson('/api/products?category_slug=missing')->assertNotFound();
        $this->getJson('/api/products?category_slug=calcas&category=Camisetas')->assertUnprocessable();
        $this->getJson('/api/products?category_slug[]=x')->assertUnprocessable();
    }

    public function test_category_slug_collisions_and_reserved_paths_have_unique_targets(): void
    {
        foreach (['A B', 'A-B', 'Login'] as $category) {
            Product::factory()->create(['category' => $category]);
        }
        $categories = CatalogCategory::all();
        $this->assertCount(3, $categories->pluck('slug')->unique());
        foreach ($categories as $category) {
            $this->assertNotSame('login', $category['slug']);
            $this->getJson('/api/products?category_slug='.$category['slug'])->assertOk()
                ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.category', $category['name']);
        }
    }

    public function test_only_active_banners_with_existing_categories_are_public_and_ordered(): void
    {
        Product::factory()->create(['category' => 'Calças']);
        $later = Banner::create($this->banner(['position' => 2]));
        $first = Banner::create($this->banner(['position' => 1]));
        Banner::create($this->banner(['active' => false]));
        Banner::create($this->banner(['category' => 'Missing']));
        $this->getJson('/api/banners')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $later->id);
        $this->getJson('/api/admin/banners')->assertUnauthorized();
        $this->postJson('/api/admin/banners', $this->banner())->assertUnauthorized();
        $this->putJson('/api/admin/banners/'.$first->id, $this->banner())->assertUnauthorized();
        $this->deleteJson('/api/admin/banners/'.$first->id)->assertUnauthorized();
    }

    public function test_authenticated_banner_crud_validates_image_and_category(): void
    {
        Product::factory()->create(['category' => 'Calças']);
        $this->actingAs(User::factory()->create(), 'web');
        $created = $this->postJson('/api/admin/banners', $this->banner(['category' => 'calças']))->assertCreated()->assertJsonPath('data.category', 'Calças');
        $id = $created->json('data.id');
        foreach ([
            ['image' => 'javascript:alert(1)'], ['category' => 'Missing'], ['position' => -1], ['title' => ''],
        ] as $invalid) {
            $this->putJson('/api/admin/banners/'.$id, array_replace($this->banner(), $invalid))->assertUnprocessable();
        }
        $this->putJson('/api/admin/banners/'.$id, $this->banner(['active' => false]))->assertOk()->assertJsonPath('data.active', false);
        $this->getJson('/api/banners')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/banners')->assertOk()->assertJsonPath('meta.total', 1);
        $this->deleteJson('/api/admin/banners/'.$id)->assertNoContent();
        $this->assertDatabaseCount('banners', 0);
    }

    public function test_public_banner_limit_and_management_pagination(): void
    {
        Product::factory()->create(['category' => 'Calças']);
        for ($i = 0; $i < 21; $i++) {
            Banner::create($this->banner(['position' => $i]));
        }
        $this->getJson('/api/banners')->assertOk()->assertJsonCount(20, 'data');
        $this->actingAs(User::factory()->create(), 'web');
        $this->getJson('/api/admin/banners?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 21);
    }

    private function banner(array $override = []): array
    {
        return array_replace(['title' => 'Promoções de calças', 'image' => 'https://example.test/banner.jpg', 'category' => 'Calças', 'position' => 0, 'active' => true], $override);
    }
}
