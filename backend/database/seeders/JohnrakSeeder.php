<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JohnrakSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'admin@johnrak.online');
        $password = env('SUPER_ADMIN_PASSWORD', 'ChangeThisPassword!');
        $name = env('SUPER_ADMIN_NAME', 'Johnrak Owner');

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'role' => 'owner']
        );
    }
}
