<?php

namespace Tests\Feature\Pro;

use App\Models\Review;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Enums\MissionStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderMission;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des « Avis reçus » du prestataire (F5.5).
 *
 * Deux volets : (1) l'écran de consultation `GET /providers/reviews` qui réunit
 * les avis sur les ressources du prestataire ET les avis directs, avec la
 * synthèse (moyenne, total, répartition) ; (2) le dépôt d'un **avis direct** sur
 * le prestataire, réservé au client d'une mission terminée, et son report sur la
 * note agrégée à la publication.
 */
class ProviderReviewTest extends TestCase
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

    /** Crée un prestataire validé rattaché à un user donné. */
    private function providerFor(User $user): Provider
    {
        return Provider::factory()->validated()->create(['user_id' => $user->id]);
    }

    public function test_la_liste_des_avis_recus_reunit_ressources_et_avis_directs(): void
    {
        $providerUser = User::factory()->create();
        $provider = $this->providerFor($providerUser);
        $vehicle = Vehicle::factory()->create(['provider_id' => $providerUser->id]);
        $experience = TourismExperience::factory()->create(['provider_id' => $providerUser->id]);

        // Un avis publié par source : véhicule (5), expérience (4), direct (4).
        Review::factory()->published()->create([
            'reviewable_type' => Vehicle::class, 'reviewable_id' => $vehicle->id, 'rating' => 5,
        ]);
        Review::factory()->published()->create([
            'reviewable_type' => TourismExperience::class, 'reviewable_id' => $experience->id, 'rating' => 4,
        ]);
        Review::factory()->published()->create([
            'reviewable_type' => Provider::class, 'reviewable_id' => $provider->id, 'rating' => 4,
        ]);
        // Un avis encore en attente sur le véhicule → exclu.
        Review::factory()->create([
            'reviewable_type' => Vehicle::class, 'reviewable_id' => $vehicle->id, 'rating' => 1,
        ]);

        Sanctum::actingAs($providerUser);

        $this->getJson('/api/v1/providers/reviews')
            ->assertOk()
            ->assertJsonPath('data.summary.count', 3)
            ->assertJsonPath('data.summary.average', 4.33) // (5+4+4)/3 arrondi
            ->assertJsonPath('data.summary.distribution.5', 1)
            ->assertJsonPath('data.summary.distribution.4', 2)
            ->assertJsonPath('data.summary.distribution.3', 0)
            ->assertJsonPath('data.summary.distribution.2', 0)
            ->assertJsonPath('data.summary.distribution.1', 0)
            ->assertJsonCount(3, 'data.reviews');
    }

    public function test_l_avis_direct_porte_le_libelle_prestation_directe(): void
    {
        $providerUser = User::factory()->create();
        $provider = $this->providerFor($providerUser);
        Review::factory()->published()->create([
            'reviewable_type' => Provider::class, 'reviewable_id' => $provider->id, 'rating' => 5,
        ]);

        Sanctum::actingAs($providerUser);

        $this->getJson('/api/v1/providers/reviews')
            ->assertOk()
            ->assertJsonPath('data.reviews.0.source', 'Prestation directe');
    }

    public function test_les_avis_d_un_autre_prestataire_sont_exclus(): void
    {
        $providerUser = User::factory()->create();
        $this->providerFor($providerUser);

        // Un autre prestataire avec un avis publié sur son véhicule.
        $otherUser = User::factory()->create();
        $this->providerFor($otherUser);
        $otherVehicle = Vehicle::factory()->create(['provider_id' => $otherUser->id]);
        Review::factory()->published()->create([
            'reviewable_type' => Vehicle::class, 'reviewable_id' => $otherVehicle->id, 'rating' => 5,
        ]);

        Sanctum::actingAs($providerUser);

        $this->getJson('/api/v1/providers/reviews')
            ->assertOk()
            ->assertJsonPath('data.summary.count', 0)
            ->assertJsonPath('data.summary.average', null)
            ->assertJsonCount(0, 'data.reviews');
    }

    public function test_un_compte_sans_profil_prestataire_recoit_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/providers/reviews')->assertNotFound();
    }

    public function test_le_client_d_une_mission_terminee_note_le_prestataire(): void
    {
        $providerUser = User::factory()->create();
        $provider = $this->providerFor($providerUser);
        $client = User::factory()->create();

        ProviderMission::factory()->create([
            'provider_id' => $provider->id,
            'client_id' => $client->id,
            'status' => MissionStatus::TERMINEE->value,
        ]);

        Sanctum::actingAs($client);

        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'provider',
            'reviewable_id' => $provider->id,
            'rating' => 5,
            'comment' => 'Prestation impeccable.',
        ])->assertCreated()->assertJsonPath('data.review.status', 'en_attente');
    }

    public function test_sans_mission_terminee_pas_d_avis_direct(): void
    {
        $providerUser = User::factory()->create();
        $provider = $this->providerFor($providerUser);
        $client = User::factory()->create();

        // Mission encore en cours : pas de prestation avérée.
        ProviderMission::factory()->create([
            'provider_id' => $provider->id,
            'client_id' => $client->id,
            'status' => MissionStatus::EN_COURS->value,
        ]);

        Sanctum::actingAs($client);

        $this->postJson('/api/v1/reviews', [
            'reviewable_type' => 'provider',
            'reviewable_id' => $provider->id,
            'rating' => 4,
        ])->assertStatus(403);
    }

    public function test_publier_un_avis_direct_met_a_jour_la_note_du_prestataire(): void
    {
        $providerUser = User::factory()->create();
        $provider = $this->providerFor($providerUser);

        $review = Review::factory()->create([
            'reviewable_type' => Provider::class,
            'reviewable_id' => $provider->id,
            'rating' => 4,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/reviews/{$review->id}/moderate", ['status' => 'publie'])
            ->assertOk();

        $provider->refresh();
        $this->assertSame(1, $provider->rating_count);
        $this->assertSame(4.0, (float) $provider->rating_avg);
    }
}
