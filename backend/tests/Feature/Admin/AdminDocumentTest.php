<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Pro\Models\ProviderCertification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.7.3 : gestion documentaire transverse (GET /admin/documents).
 *
 * Vue centralisée et sensible (KYC, contrats) réservée à `gerer:utilisateurs`.
 */
class AdminDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_l_agent_sans_gerer_utilisateurs_est_refuse(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/documents')->assertStatus(403);
    }

    public function test_la_vue_d_ensemble_compte_les_pieces_par_type(): void
    {
        ProviderCertification::factory()->count(2)->create();
        OwnerPayout::factory()->create(['proof_path' => 'proofs/recu.pdf']);
        OwnerPayout::factory()->create(['proof_path' => null]); // sans preuve → non comptée

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/documents')
            ->assertOk()
            ->assertJsonPath('data.documents.certification', 2)
            ->assertJsonPath('data.documents.payout_proof', 1)
            ->assertJsonPath('data.documents.kyc', 0);
    }

    public function test_liste_normalisee_paginee_par_type(): void
    {
        ProviderCertification::factory()->count(3)->create();

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/documents?type=certification&per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.doc_type', 'certification');
    }

    public function test_type_documentaire_inconnu_donne_404(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/documents?type=inexistant')->assertStatus(404);
    }
}
