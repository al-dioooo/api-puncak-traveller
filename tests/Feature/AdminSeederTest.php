<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    public function test_admin_user_seeder_is_idempotent(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@puncaktraveller.id')->firstOrFail();

        $this->assertSame(1, User::query()->where('email', 'admin@puncaktraveller.id')->count());
        $this->assertSame('Administrator', $admin->name);
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue(Hash::check('aldio1234', $admin->password));
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

    public function test_database_seeder_creates_mobile_community_resources(): void
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);

        $menginap = Community::query()->where('slug', 'puncak-menginap')->firstOrFail();
        $runners = Community::query()->where('slug', 'puncak-runners')->firstOrFail();
        $puncakIn = Community::query()->where('slug', 'puncak-in')->firstOrFail();

        $this->assertSame(7, $menginap->places()->count());
        $this->assertSame(2, $menginap->events()->count());
        $this->assertSame(0, $runners->places()->count());
        $this->assertSame(7, $runners->events()->count());
        $this->assertSame(0, $puncakIn->places()->count());
        $this->assertSame(1, $puncakIn->events()->count());
    }

    public function test_database_seeder_publishes_mobile_demo_assets(): void
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('communities', [
            'slug' => 'puncak-menginap',
            'image_path' => 'communities/demo/puncak-menginap.jpg',
        ]);
        $this->assertDatabaseHas('places', [
            'name' => 'Lembah Pinang Villa',
            'image_path' => 'places/demo/lembah-pinang-villa.jpg',
        ]);
        $this->assertDatabaseHas('events', [
            'slug' => 'puncak-trail-run-2026',
            'cover_image' => 'events/demo/puncak-trail-run-2026.jpg',
        ]);

        Storage::disk('public')->assertExists('communities/demo/puncak-menginap.jpg');
        Storage::disk('public')->assertExists('places/demo/lembah-pinang-villa.jpg');
        Storage::disk('public')->assertExists('events/demo/puncak-trail-run-2026.jpg');

        $this->assertTrue(Event::query()->where('slug', 'puncak-trail-run-2026')->exists());
    }
}
