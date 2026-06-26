<?php

namespace Tests\Feature\Immo;

use App\Models\User;
use App\Modules\Immo\Models\Property;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des favoris et de la comparaison de biens (phase B2.5).
 */
class FavoriteAndCompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    public function test_un_utilisateur_peut_ajouter_un_bien_publie_en_favori(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->published()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/properties/{$property->id}/favorite")->assertOk();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'property_id' => $property->id,
        ]);
    }

    public function test_impossible_de_mettre_en_favori_un_bien_non_publie(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->pending()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/properties/{$property->id}/favorite")->assertNotFound();
    }

    public function test_favoriser_est_idempotent(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->published()->create();

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/properties/{$property->id}/favorite")->assertOk();
        $this->postJson("/api/v1/properties/{$property->id}/favorite")->assertOk();

        $this->assertSame(1, $user->favoriteProperties()->count());
    }

    public function test_un_utilisateur_peut_retirer_un_favori(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->published()->create();
        $user->favoriteProperties()->attach($property->id);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/v1/properties/{$property->id}/favorite")->assertOk();

        $this->assertSame(0, $user->favoriteProperties()->count());
    }

    public function test_la_liste_des_favoris(): void
    {
        $user = User::factory()->create();
        $user->favoriteProperties()->attach(Property::factory()->published()->create()->id);
        $user->favoriteProperties()->attach(Property::factory()->published()->create()->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/favorites')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_les_favoris_exigent_une_authentification(): void
    {
        $this->getJson('/api/v1/favorites')->assertStatus(401);
    }

    public function test_la_comparaison_ne_renvoie_que_les_biens_publies(): void
    {
        $p1 = Property::factory()->published()->create();
        $p2 = Property::factory()->published()->create();
        $p3 = Property::factory()->pending()->create();

        $this->getJson("/api/v1/properties/compare?ids={$p1->id},{$p2->id},{$p3->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // le bien non publié est exclu
    }
}
