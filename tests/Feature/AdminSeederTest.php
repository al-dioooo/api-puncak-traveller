<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_user_seeder_is_idempotent(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'alice@puncaktraveller.id')->count());
        $this->assertSame(User::ROLE_ADMIN, User::query()->where('email', 'alice@puncaktraveller.id')->firstOrFail()->role);
    }
}
