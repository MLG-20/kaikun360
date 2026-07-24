<?php

namespace Tests\Feature\Pro;

use App\Models\User;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCertification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de « Mes services » (F5) : édition du profil prestataire et gestion des
 * certifications, scopées au profil du compte connecté.
 */
class ProviderProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Crée un utilisateur prestataire (avec profil validé) et l'authentifie. */
    private function actingAsProvider(): Provider
    {
        $user = User::factory()->create();
        $provider = Provider::factory()->validated()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        return $provider;
    }

    public function test_le_prestataire_met_a_jour_son_profil(): void
    {
        $this->actingAsProvider();

        $this->putJson('/api/v1/providers/mine', [
            'business_name' => 'Teranga Events (nouveau nom)',
            'category' => 'transport',
            'bio' => 'Navettes et logistique événementielle.',
        ])
            ->assertOk()
            ->assertJsonPath('data.provider.business_name', 'Teranga Events (nouveau nom)')
            ->assertJsonPath('data.provider.category', 'transport');

        $this->assertDatabaseHas('providers', [
            'business_name' => 'Teranga Events (nouveau nom)',
            'category' => 'transport',
        ]);
    }

    public function test_l_edition_ne_change_pas_le_statut_de_validation(): void
    {
        $provider = $this->actingAsProvider();

        $this->putJson('/api/v1/providers/mine', [
            'business_name' => 'Nom mis à jour',
            'category' => 'evenementiel',
        ])->assertOk()->assertJsonPath('data.provider.status', 'valide');

        $this->assertDatabaseHas('providers', [
            'id' => $provider->id,
            'status' => 'valide',
        ]);
    }

    public function test_une_categorie_invalide_est_refusee(): void
    {
        $this->actingAsProvider();

        $this->putJson('/api/v1/providers/mine', [
            'business_name' => 'Peu importe',
            'category' => 'categorie_inconnue',
        ])->assertStatus(422)->assertJsonValidationErrors('category');
    }

    public function test_le_prestataire_ajoute_une_certification_non_verifiee(): void
    {
        $provider = $this->actingAsProvider();

        $this->postJson('/api/v1/providers/certifications', [
            'name' => 'Licence de transport',
            'issuer' => 'Ministère des Transports',
        ])
            ->assertCreated()
            ->assertJsonPath('data.certification.name', 'Licence de transport')
            ->assertJsonPath('data.certification.verified', false);

        $this->assertDatabaseHas('provider_certifications', [
            'provider_id' => $provider->id,
            'name' => 'Licence de transport',
            'verified' => false,
        ]);
    }

    public function test_le_prestataire_supprime_sa_certification(): void
    {
        $provider = $this->actingAsProvider();
        $certification = ProviderCertification::create([
            'provider_id' => $provider->id,
            'name' => 'Assurance responsabilité civile',
        ]);

        $this->deleteJson("/api/v1/providers/certifications/{$certification->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('provider_certifications', ['id' => $certification->id]);
    }

    public function test_on_ne_supprime_pas_la_certification_d_un_autre(): void
    {
        $this->actingAsProvider();
        // Certification appartenant à un AUTRE prestataire.
        $other = ProviderCertification::create([
            'provider_id' => Provider::factory()->create()->id,
            'name' => 'Certif d’un tiers',
        ]);

        $this->deleteJson("/api/v1/providers/certifications/{$other->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('provider_certifications', ['id' => $other->id]);
    }

    public function test_un_compte_sans_profil_prestataire_recoit_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/providers/mine', [
            'business_name' => 'X',
            'category' => 'autre',
        ])->assertStatus(404);
    }
}
