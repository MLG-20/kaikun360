<?php

namespace Tests\Feature\Transversal;

use App\Models\Review;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\Provider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests transversaux B12.3 : modération des avis (publier/rejeter par agents) et
 * report de la note agrégée sur la notation prestataire (`providers`).
 */
class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        return $agent;
    }

    public function test_un_agent_publie_un_avis_et_met_a_jour_la_note_prestataire(): void
    {
        $providerUser = User::factory()->create();
        $provider = Provider::factory()->create(['user_id' => $providerUser->id]);
        $vehicle = Vehicle::factory()->create(['provider_id' => $providerUser->id]);

        $review = Review::factory()->create([
            'reviewable_type' => Vehicle::class,
            'reviewable_id' => $vehicle->id,
            'rating' => 4,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/reviews/{$review->id}/moderate", ['status' => 'publie'])
            ->assertOk()
            ->assertJsonPath('data.review.status', 'publie');

        $provider->refresh();
        $this->assertSame(1, $provider->rating_count);
        $this->assertSame(4.0, (float) $provider->rating_avg);
    }

    public function test_un_utilisateur_ordinaire_ne_modere_pas(): void
    {
        $review = Review::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/reviews/{$review->id}/moderate", ['status' => 'publie'])
            ->assertStatus(403);
    }

    public function test_on_ne_modere_pas_un_avis_deja_modere(): void
    {
        $review = Review::factory()->published()->create();

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/reviews/{$review->id}/moderate", ['status' => 'rejete'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_la_publication_reste_sure_sans_prestataire_enregistre(): void
    {
        // Véhicule dont le user prestataire n'a pas de fiche Provider : la
        // publication ne doit pas échouer, simplement ne rien agréger.
        $providerUser = User::factory()->create();
        $vehicle = Vehicle::factory()->create(['provider_id' => $providerUser->id]);
        $review = Review::factory()->create([
            'reviewable_type' => Vehicle::class,
            'reviewable_id' => $vehicle->id,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/reviews/{$review->id}/moderate", ['status' => 'publie'])
            ->assertOk();
    }
}
