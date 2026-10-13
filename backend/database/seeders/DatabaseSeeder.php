<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(DemoUserSeeder::class);
            $this->call(DemoAccountsSeeder::class);
            $this->call(ProductSeeder::class);
        });
    }
}
