<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('PUNCAK_ADMIN_EMAIL', 'alice@puncaktraveller.id');
        $password = env('PUNCAK_ADMIN_PASSWORD', 'aldio1234');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('PUNCAK_ADMIN_NAME', 'Puncak Administrator'),
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );
    }
}
