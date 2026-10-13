<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exposes_only_public_user_fields_and_logout_ends_session(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertExactJson(['data' => $user->only('id', 'name', 'email')]);
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_bad_credentials_return_generic_401(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->assertGuest();
    }

    public function test_login_validates_input_and_user_endpoint_requires_session(): void
    {
        $this->postJson('/api/login', ['email' => 'invalid'])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['email', 'password']]]);
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'absent@example.test', 'password' => 'wrong'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => 'absent@example.test', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('error.code', 'too_many_requests');
    }

    public function test_bearer_token_cannot_replace_a_session(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')->getJson('/api/user')
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }
}
