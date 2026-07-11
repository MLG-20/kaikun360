<?php

namespace Tests\Feature\Transversal;

use App\Models\Media;
use App\Models\User;
use App\Modules\Mobility\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests transversaux B12.1 : médias polymorphes (upload d'image compressée,
 * vidéo par URL, image principale vs galerie, autorisation propriétaire,
 * suppression). La cible de test est un véhicule (policy `update` = provider).
 */
class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    /** Véhicule appartenant à l'utilisateur donné (provider_id = user id). */
    private function vehicleOf(User $user): Vehicle
    {
        return Vehicle::factory()->create(['provider_id' => $user->id]);
    }

    public function test_le_proprietaire_televerse_une_image_compressee(): void
    {
        $owner = User::factory()->create();
        $vehicle = $this->vehicleOf($owner);

        Sanctum::actingAs($owner);

        $response = $this->post('/api/v1/media/upload', [
            'mediable_type' => 'vehicle',
            'mediable_id' => $vehicle->id,
            'file' => UploadedFile::fake()->image('photo.jpg', 2400, 1800),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.media.type', 'image');

        $media = Media::first();
        $this->assertNotNull($media->path);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_un_tiers_ne_peut_pas_deposer_de_media(): void
    {
        $vehicle = $this->vehicleOf(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/media/upload', [
            'mediable_type' => 'vehicle',
            'mediable_id' => $vehicle->id,
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(403);
    }

    public function test_une_seule_image_principale_par_ressource(): void
    {
        $owner = User::factory()->create();
        $vehicle = $this->vehicleOf($owner);
        $ancienne = Media::factory()->primary()->create([
            'mediable_type' => Vehicle::class,
            'mediable_id' => $vehicle->id,
        ]);

        Sanctum::actingAs($owner);

        $this->post('/api/v1/media/upload', [
            'mediable_type' => 'vehicle',
            'mediable_id' => $vehicle->id,
            'file' => UploadedFile::fake()->image('cover.jpg'),
            'is_primary' => true,
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertFalse($ancienne->fresh()->is_primary);
        $this->assertSame(1, Media::where('is_primary', true)->count());
    }

    public function test_une_video_est_deposee_par_url(): void
    {
        $owner = User::factory()->create();
        $vehicle = $this->vehicleOf($owner);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/media/upload', [
            'mediable_type' => 'vehicle',
            'mediable_id' => $vehicle->id,
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ])->assertCreated()->assertJsonPath('data.media.type', 'video');
    }

    public function test_il_faut_un_fichier_ou_une_url(): void
    {
        $owner = User::factory()->create();
        $vehicle = $this->vehicleOf($owner);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/media/upload', [
            'mediable_type' => 'vehicle',
            'mediable_id' => $vehicle->id,
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_le_proprietaire_supprime_un_media(): void
    {
        $owner = User::factory()->create();
        $vehicle = $this->vehicleOf($owner);
        Storage::disk('public')->put('media/x.jpg', 'data');
        $media = Media::factory()->create([
            'mediable_type' => Vehicle::class,
            'mediable_id' => $vehicle->id,
            'path' => 'media/x.jpg',
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/media/{$media->id}")->assertOk();

        Storage::disk('public')->assertMissing('media/x.jpg');
        $this->assertNull($media->fresh());
    }
}
