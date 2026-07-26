<?php

namespace Tests\Feature\Pro;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Pro\Enums\ProviderStatus;
use App\Modules\Pro\Models\Provider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests inscription + validation + charte qualité des prestataires (phase B10.2),
 * dont l'intégration « validation débloque la publication » (Explore).
 */
class ProviderRegistrationTest extends TestCase
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
        $agent->givePermissionTo(AdminPermission::operational()); // F7.1.b : agent pleinement outillé (droits désormais délégués, plus portés par le rôle)

        return $agent;
    }

    /**
     * Inscrit un prestataire via l'API et renvoie [utilisateur, provider_id].
     *
     * @return array{0: User, 1: int}
     */
    private function registerProvider(): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/providers', [
            'business_name' => 'Sunu Events',
            'category' => 'animation',
            'certifications' => [['name' => 'Agrément', 'issuer' => 'Ministère']],
        ])->assertCreated()->json('data.provider.id');

        return [$user, $id];
    }

    public function test_un_utilisateur_s_inscrit_comme_prestataire(): void
    {
        [$user, $id] = $this->registerProvider();

        $this->assertDatabaseHas('providers', [
            'id' => $id, 'user_id' => $user->id, 'status' => 'en_attente',
        ]);
        $this->assertTrue($user->fresh()->hasRole(UserRole::PRESTATAIRE->value));
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'type' => 'prestataire']);
        $this->assertDatabaseHas('provider_certifications', ['provider_id' => $id, 'name' => 'Agrément']);
    }

    public function test_inscription_en_double_refusee(): void
    {
        $user = User::factory()->create();
        Provider::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/providers', [
            'business_name' => 'X', 'category' => 'autre',
        ])->assertStatus(422);
    }

    public function test_la_validation_debloque_la_publication_d_un_service(): void
    {
        [$user, $id] = $this->registerProvider();

        // Avant validation : publication d'une expérience refusée.
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/experiences', $this->experiencePayload())->assertStatus(403);

        // Un agent valide le prestataire.
        Sanctum::actingAs($this->agent());
        $this->patchJson("/api/v1/providers/{$id}/validate")
            ->assertOk()
            ->assertJsonPath('data.provider.status', 'valide');

        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'verification_status' => 'verifie']);

        // Après validation : la publication passe (instance rechargée, comme en HTTP réel).
        Sanctum::actingAs($user->fresh());
        $this->postJson('/api/v1/experiences', $this->experiencePayload())->assertCreated();
    }

    public function test_la_suspension_bloque_de_nouveau_la_publication(): void
    {
        [$user, $id] = $this->registerProvider();

        Sanctum::actingAs($this->agent());
        $this->patchJson("/api/v1/providers/{$id}/validate")->assertOk();
        $this->patchJson("/api/v1/providers/{$id}/suspend", ['reason' => 'Réclamations clients'])
            ->assertOk()
            ->assertJsonPath('data.provider.status', 'suspendu');

        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'verification_status' => 'non_verifie']);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/experiences', $this->experiencePayload())->assertStatus(403);
    }

    public function test_un_non_agent_ne_peut_pas_valider(): void
    {
        $provider = Provider::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/providers/{$provider->id}/validate")->assertStatus(403);
    }

    public function test_trois_avertissements_suspendent_le_prestataire(): void
    {
        $provider = Provider::factory()->validated()->create();
        Sanctum::actingAs($this->agent());

        for ($i = 0; $i < 3; $i++) {
            $this->patchJson("/api/v1/providers/{$provider->id}/warn", ['reason' => 'Retard'])->assertOk();
        }

        $this->assertDatabaseHas('providers', [
            'id' => $provider->id,
            'status' => ProviderStatus::SUSPENDU->value,
            'warnings_count' => 3,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function experiencePayload(): array
    {
        return [
            'title' => 'Balade',
            'destination' => 'Saly',
            'duration_days' => 1,
            'price_xof' => 30_000,
            'capacity' => 10,
        ];
    }
}
