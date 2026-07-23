<?php

namespace Tests\Feature\Pro;

use App\Models\User;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderUnavailability;
use App\Modules\Pro\Models\ProviderWeeklyAvailability;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests des disponibilités prestataire (F5.4) : planning hebdomadaire récurrent
 * et périodes d'indisponibilité, scopés au profil du compte connecté.
 */
class ProviderAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Crée un utilisateur prestataire (avec profil) et l'authentifie. */
    private function actingAsProvider(): Provider
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        return $provider;
    }

    public function test_le_planning_renvoie_toujours_les_sept_jours(): void
    {
        $provider = $this->actingAsProvider();
        // Un seul jour enregistré : les 6 autres doivent apparaître « fermés ».
        ProviderWeeklyAvailability::create([
            'provider_id' => $provider->id, 'weekday' => 0,
            'is_open' => true, 'start_time' => '09:00', 'end_time' => '18:00',
        ]);

        $this->getJson('/api/v1/providers/availability')
            ->assertOk()
            ->assertJsonCount(7, 'data.weekly')
            ->assertJsonPath('data.weekly.0.is_open', true)
            ->assertJsonPath('data.weekly.0.start_time', '09:00')
            ->assertJsonPath('data.weekly.1.is_open', false);
    }

    public function test_le_prestataire_enregistre_son_planning(): void
    {
        $this->actingAsProvider();

        $this->putJson('/api/v1/providers/availability/weekly', [
            'days' => [
                ['weekday' => 0, 'is_open' => true, 'start_time' => '08:30', 'end_time' => '17:00'],
                ['weekday' => 1, 'is_open' => false, 'start_time' => null, 'end_time' => null],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.weekly.0.start_time', '08:30');

        $this->assertDatabaseHas('provider_weekly_availabilities', [
            'weekday' => 0, 'is_open' => true,
        ]);
    }

    public function test_un_creneau_avec_fin_avant_debut_est_refuse(): void
    {
        $this->actingAsProvider();

        $this->putJson('/api/v1/providers/availability/weekly', [
            'days' => [
                ['weekday' => 0, 'is_open' => true, 'start_time' => '18:00', 'end_time' => '09:00'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('days.0.end_time');
    }

    public function test_le_prestataire_ajoute_une_indisponibilite(): void
    {
        $this->actingAsProvider();

        $this->postJson('/api/v1/providers/availability/unavailability', [
            'start_date' => '2026-08-12', 'end_date' => '2026-08-15', 'reason' => 'Congés',
        ])
            ->assertCreated()
            ->assertJsonPath('data.unavailability.reason', 'Congés');
    }

    public function test_une_indisponibilite_avec_fin_avant_debut_est_refusee(): void
    {
        $this->actingAsProvider();

        $this->postJson('/api/v1/providers/availability/unavailability', [
            'start_date' => '2026-08-15', 'end_date' => '2026-08-12',
        ])->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_les_indisponibilites_passees_sont_masquees(): void
    {
        $provider = $this->actingAsProvider();
        ProviderUnavailability::create([
            'provider_id' => $provider->id,
            'start_date' => now()->subMonth()->subDays(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
        ]);
        ProviderUnavailability::create([
            'provider_id' => $provider->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $this->getJson('/api/v1/providers/availability')
            ->assertOk()
            ->assertJsonCount(1, 'data.unavailabilities');
    }

    public function test_on_ne_supprime_pas_l_indisponibilite_d_un_autre(): void
    {
        $this->actingAsProvider();
        // Période appartenant à un AUTRE prestataire.
        $other = ProviderUnavailability::create([
            'provider_id' => Provider::factory()->create()->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $this->deleteJson("/api/v1/providers/availability/unavailability/{$other->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('provider_unavailabilities', ['id' => $other->id]);
    }

    public function test_un_compte_sans_profil_prestataire_recoit_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/providers/availability')->assertStatus(404);
    }
}
