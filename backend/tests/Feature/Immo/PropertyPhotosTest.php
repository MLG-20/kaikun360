<?php

namespace Tests\Feature\Immo;

use App\Models\Media;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Photos des biens (F4.3) — exposition au catalogue / aux fiches, et choix de
 * l'image de couverture par le propriétaire.
 *
 * Des annonces illustrées sont déterminantes pour la confiance des clients : on
 * vérifie que les photos remontent jusqu'au **catalogue public** et à la **fiche
 * publique** dans le bon ordre (couverture d'abord), et que seul le propriétaire
 * du bien peut les gérer.
 */
class PropertyPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);
        Storage::fake('public');
    }

    private function proprietaire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PROPRIETAIRE->value);

        return $user;
    }

    /** Rattache une photo à un bien (sans passer par l'upload). */
    private function photo(Property $property, array $overrides = []): Media
    {
        return Media::create(array_merge([
            'reference' => 'MED-'.fake()->unique()->lexify('????????'),
            'mediable_type' => Property::class,
            'mediable_id' => $property->id,
            'type' => 'image',
            'disk' => 'public',
            'path' => 'media/'.fake()->uuid().'.jpg',
            'is_primary' => false,
            'position' => 0,
        ], $overrides));
    }

    public function test_un_proprietaire_televerse_une_photo_sur_son_bien(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/media/upload', [
            'mediable_type' => 'property',
            'mediable_id' => $property->id,
            'file' => UploadedFile::fake()->image('salon.jpg', 1200, 900),
            'is_primary' => true,
        ])->assertCreated()->assertJsonPath('data.media.is_primary', true);

        $this->assertDatabaseHas('media', [
            'mediable_type' => Property::class,
            'mediable_id' => $property->id,
            'is_primary' => true,
        ]);
    }

    public function test_on_ne_peut_pas_illustrer_le_bien_d_un_autre(): void
    {
        $owner = $this->proprietaire();
        $autre = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $autre->id]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/media/upload', [
            'mediable_type' => 'property',
            'mediable_id' => $property->id,
            'file' => UploadedFile::fake()->image('salon.jpg'),
        ])->assertStatus(403);
    }

    public function test_la_fiche_privee_expose_les_photos_couverture_en_tete(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        // Créées dans le désordre : la couverture doit malgré tout sortir en 1re.
        $this->photo($property, ['position' => 2]);
        $couverture = $this->photo($property, ['is_primary' => true, 'position' => 5]);

        Sanctum::actingAs($owner);

        $res = $this->getJson("/api/v1/properties/mine/{$property->id}")->assertOk();

        $this->assertCount(2, $res->json('data.photos'));
        $res->assertJsonPath('data.photos.0.id', $couverture->id);
        $res->assertJsonPath('data.photos.0.is_primary', true);
    }

    public function test_le_catalogue_public_expose_la_photo_de_couverture(): void
    {
        $property = Property::factory()->published()->create();
        $couverture = $this->photo($property, ['is_primary' => true]);

        $res = $this->getJson('/api/v1/properties')->assertOk();

        $bien = collect($res->json('data'))->firstWhere('id', $property->id);

        // `photo_url` alimente la vignette des cartes du catalogue.
        $this->assertNotNull($bien['photo_url']);
        $this->assertSame($couverture->id, $bien['photos'][0]['id']);
    }

    public function test_la_fiche_publique_expose_toutes_les_photos(): void
    {
        // La galerie de la fiche publique consomme `photos` : sans elle, un bien
        // illustré s'affichait sans aucune image (régression corrigée en F4.3).
        $property = Property::factory()->published()->create();
        $couverture = $this->photo($property, ['is_primary' => true]);
        $this->photo($property, ['position' => 1]);
        $this->photo($property, ['position' => 2]);

        $res = $this->getJson("/api/v1/properties/{$property->id}")->assertOk();

        $this->assertCount(3, $res->json('data.photos'));
        $res->assertJsonPath('data.photos.0.id', $couverture->id);
    }

    public function test_un_bien_sans_photo_expose_une_couverture_nulle(): void
    {
        $property = Property::factory()->published()->create();

        $bien = collect($this->getJson('/api/v1/properties')->assertOk()->json('data'))
            ->firstWhere('id', $property->id);

        // Le front retombe alors sur sa vignette de repli (pas d'image cassée).
        $this->assertNull($bien['photo_url']);
        $this->assertSame([], $bien['photos']);
    }

    public function test_le_proprietaire_choisit_la_photo_de_couverture(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $ancienne = $this->photo($property, ['is_primary' => true]);
        $nouvelle = $this->photo($property);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/media/{$nouvelle->id}/primary")
            ->assertOk()
            ->assertJsonPath('data.media.is_primary', true);

        // Une seule couverture : l'ancienne est dépromue.
        $this->assertDatabaseHas('media', ['id' => $nouvelle->id, 'is_primary' => true]);
        $this->assertDatabaseHas('media', ['id' => $ancienne->id, 'is_primary' => false]);
    }

    public function test_on_ne_choisit_pas_la_couverture_du_bien_d_un_autre(): void
    {
        $owner = $this->proprietaire();
        $autre = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $autre->id]);
        $photo = $this->photo($property);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/media/{$photo->id}/primary")->assertStatus(403);
    }

    public function test_le_proprietaire_supprime_une_photo_de_son_bien(): void
    {
        $owner = $this->proprietaire();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $photo = $this->photo($property);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/media/{$photo->id}")->assertOk();

        $this->assertDatabaseMissing('media', ['id' => $photo->id]);
    }
}
