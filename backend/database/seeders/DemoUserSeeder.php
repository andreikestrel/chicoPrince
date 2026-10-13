<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dados de demonstração disponíveis somente em local/testing.');
        }
        $password = config('seeding.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Execute scripts/init-env.php para configurar SEED_USER_PASSWORD.');
        }
        User::firstOrCreate(['email' => config('seeding.email')], [
            'name' => 'Usuário de demonstração',
            'password' => Hash::make($password),
        ]);
    }
}
