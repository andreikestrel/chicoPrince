<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os dados de demonstração são exclusivos do ambiente local ou de testes.');
        }

        $password = config('seeding.password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Configure SEED_USER_PASSWORD com pelo menos 12 caracteres. Execute scripts/init-env.php para gerar a configuração local.');
        }

        DB::transaction(function () use ($password): void {
            User::firstOrCreate(['email' => config('seeding.email')], [
                'name' => 'Usuário de demonstração',
                'password' => Hash::make($password),
            ]);

            $this->call(ProductSeeder::class);
        });
    }
}
