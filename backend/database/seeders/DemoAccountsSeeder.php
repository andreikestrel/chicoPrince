<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Contas públicas de demonstração disponíveis somente em local/testing.');
        }
        foreach ([
            ['admin@chicoprince.com', '123456', 'Administrador demo', true],
            ['teste@chicoprince.com', '654321', 'Cliente demo', false],
        ] as [$email, $password, $name, $admin]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill(['name' => $name, 'password' => Hash::make($password), 'is_admin' => $admin])->save();
        }
    }
}
