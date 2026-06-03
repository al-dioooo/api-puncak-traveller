<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_database_seeder_publishes_demo_gallery_images(): void
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('galleries', [
            'public_id' => 'summit-push',
            'image_path' => 'gallery/demo/summit-push-at-dawn.jpg',
        ]);
        Storage::disk('public')->assertExists('gallery/demo/summit-push-at-dawn.jpg');
    }
}
