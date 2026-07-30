<?php

namespace Tests\Feature\Admin;

use App\Models\Report;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Manage\Models\ManagementMandate;
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

    /*
    |--------------------------------------------------------------------------
    | F7.4.c — Les six familles du CDC §6 (module Documents)
    |--------------------------------------------------------------------------
    */

    public function test_les_mandats_et_les_rapports_figurent_dans_la_vue_documentaire(): void
    {
        // « Mandats, contrats, preuves, pièces, rapports, pièces prestataires » :
        // les deux dernières familles manquaient alors qu'elles existaient en base.
        $mandate = ManagementMandate::factory()->create();
        Report::factory()->count(2)->create();

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/documents')
            ->assertOk()
            ->assertJsonPath('data.documents.mandate', 1)
            ->assertJsonPath('data.documents.report', 2);

        // Le mandat s'intitule par sa référence : une ligne « MND-0007 » nue
        // serait inutilisable dans un écran qui sert à retrouver un contrat.
        $this->getJson('/api/v1/admin/documents?type=mandate')
            ->assertOk()
            ->assertJsonPath('data.0.doc_type', 'mandate')
            ->assertJsonPath('data.0.subject_id', $mandate->id)
            // Pas de fichier joint : les clauses vivent dans la fiche du mandat.
            ->assertJsonPath('data.0.original_name', null);

        $this->getJson('/api/v1/admin/documents?type=report')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.doc_type', 'report');
    }
}
