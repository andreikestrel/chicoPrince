<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_is_public_and_reports_a_working_database(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['data' => ['status' => 'ok', 'database' => 'ok']]);
    }

    public function test_database_failure_returns_503_without_exception_details(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('private connection details'));

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertExactJson(['data' => ['status' => 'unavailable', 'database' => 'unavailable']]);
    }
}
