<?php

namespace Tests\Feature\Explore;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Profile;
use App\Modules\Explore\Models\TourismExperience;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests catalogue + publication + validation des expériences (phase B6.2).
 */
class ExperienceCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Prestataire vérifié (peut publier).
     */
    private function verifiedProvider(): User
    {
        $provider = User::factory()->create();
        $provider->assignRole(UserRole::PRESTATAIRE->value);
        Profile::factory()->prestataire()->verifie()->create(['user_id' => $provider->id]);

        return $provider;
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)

        return $agent;
    }

    public function test_le_catalogue_ne_montre_que_les_experiences_publiees(): void
    {
        TourismExperience::factory()->published()->count(2)->create();
        TourismExperience::factory()->create(); // en attente

        $this->getJson('/api/v1/experiences')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_le_detail_d_une_experience_non_publiee_renvoie_404(): void
    {
        $experience = TourismExperience::factory()->create(); // en attente

        $this->getJson("/api/v1/experiences/{$experience->id}")->assertStatus(404);
    }

    public function test_un_prestataire_verifie_publie_une_experience(): void
    {
        Sanctum::actingAs($this->verifiedProvider());

        $this->postJson('/api/v1/experiences', [
            'title' => 'Désert de Lompoul',
            'destination' => 'Lompoul',
            'duration_days' => 2,
            'price_xof' => 80_000,
            'capacity' => 10,
            'inclusions' => ['restauration' => true, 'guide' => true],
        ])
            ->assertCreated()
            ->assertJsonPath('data.experience.status', 'en_attente_validation');
    }

    public function test_un_prestataire_non_verifie_ne_peut_pas_publier(): void
    {
        $provider = User::factory()->create();
        $provider->assignRole(UserRole::PRESTATAIRE->value);
        Profile::factory()->prestataire()->create(['user_id' => $provider->id]); // non vérifié

        Sanctum::actingAs($provider);

        $this->postJson('/api/v1/experiences', [
            'title' => 'X', 'destination' => 'Y', 'duration_days' => 1,
            'price_xof' => 1000, 'capacity' => 5,
        ])->assertStatus(403);
    }

    public function test_un_simple_client_ne_peut_pas_publier(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/experiences', [
            'title' => 'X', 'destination' => 'Y', 'duration_days' => 1,
            'price_xof' => 1000, 'capacity' => 5,
        ])->assertStatus(403);
    }

    public function test_mine_ne_liste_que_mes_experiences(): void
    {
        $provider = $this->verifiedProvider();
        TourismExperience::factory()->count(2)->create(['provider_id' => $provider->id]);
        TourismExperience::factory()->create(); // autre prestataire

        Sanctum::actingAs($provider);

        $this->getJson('/api/v1/experiences/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_un_agent_valide_puis_rejette_une_experience(): void
    {
        $experience = TourismExperience::factory()->create();
        $agent = $this->agent();
        Sanctum::actingAs($agent);

        $this->patchJson("/api/v1/experiences/{$experience->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.experience.status', 'publie');

        $this->assertDatabaseHas('tourism_experiences', [
            'id' => $experience->id,
            'approved_by' => $agent->id,
        ]);

        $autre = TourismExperience::factory()->create();
        $this->patchJson("/api/v1/experiences/{$autre->id}/reject", ['reason' => 'Incomplet'])
            ->assertOk()
            ->assertJsonPath('data.experience.status', 'rejete');
    }

    public function test_un_non_agent_ne_peut_pas_valider(): void
    {
        $experience = TourismExperience::factory()->create();
        Sanctum::actingAs($this->verifiedProvider());

        $this->patchJson("/api/v1/experiences/{$experience->id}/approve")->assertStatus(403);
    }
}
