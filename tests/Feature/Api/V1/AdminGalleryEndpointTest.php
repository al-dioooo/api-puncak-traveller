<?php

namespace Tests\Feature\Api\V1;

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGalleryEndpointTest extends TestCase
{
    public function test_admin_can_upload_and_bulk_delete_gallery_photos_by_public_slug(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $upload = $this->post(route('api.v1.galleries.store'), [
            'image' => UploadedFile::fake()->image('summit.jpg', 1200, 800),
            'title' => 'Summit push',
            'category' => 'hike',
        ], ['Accept' => 'application/json']);

        $upload
            ->assertCreated()
            ->assertJsonPath('data.id', 'summit-push');

        $gallery = Gallery::query()->where('public_id', 'summit-push')->firstOrFail();
        Storage::disk('public')->assertExists($gallery->image_path);

        $this->postJson(route('api.v1.galleries.bulk-destroy'), [
            'ids' => ['summit-push', 'missing-photo'],
        ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 1)
            ->assertJsonPath('data.missing', 1);

        Storage::disk('public')->assertMissing($gallery->image_path);
    }

    public function test_gallery_upload_rejects_invalid_files(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->post(route('api.v1.galleries.store'), [
            'image' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            'title' => 'Invalid upload',
            'category' => 'hike',
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_admin_can_download_stored_gallery_photo(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        Storage::disk('public')->put('gallery/demo/summit-push-at-dawn.jpg', 'demo-image-bytes');
        $gallery = Gallery::factory()->create([
            'public_id' => 'summit-push',
            'title' => 'Summit push at dawn',
            'image_path' => 'gallery/demo/summit-push-at-dawn.jpg',
        ]);

        $this->get(route('api.v1.galleries.download', $gallery))
            ->assertOk()
            ->assertHeader('Content-Disposition');
    }
}
