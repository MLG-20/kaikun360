<?php

namespace Tests\Feature\Admin;

use App\Models\Review;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\Provider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F7.2.g : les deux points d'accès de supervision « Avis & qualité » du
 * back-office — file de modération des avis (`GET /admin/reviews`, garde
 * `moderer:avis`) et liste des prestataires (`GET /admin/providers`, garde
 * `valider:prestataire`). Les actions (moderate / warn / suspend) sont testées
 * ailleurs (ReviewModerationTest, module Pro).
 */
class AdminQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Un agent pleinement outillé (droits opérationnels délégués). */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /** Un agent au socle minimal (dashboard seul). */
    private function bareAgent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        return $agent;
    }

    public function test_la_file_de_moderation_liste_les_avis_en_attente_par_defaut(): void
    {
        $vehicle = Vehicle::factory()->create();
        Review::factory()->create(['reviewable_type' => Vehicle::class, 'reviewable_id' => $vehicle->id]);
        Review::factory()->published()->create(['reviewable_type' => Vehicle::class, 'reviewable_id' => $vehicle->id]);

        Sanctum::actingAs($this->agent());

        // Sans filtre : seuls les avis en attente.
        $this->getJson('/api/v1/admin/reviews')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'en_attente')
            ->assertJsonStructure(['data' => [['id', 'rating', 'author', 'resource_type', 'resource_label']]]);

        // Filtre explicite sur les publiés.
        $this->getJson('/api/v1/admin/reviews?status=publie')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'publie');
    }

    public function test_un_agent_sans_moderer_avis_est_refuse_sur_la_file(): void
    {
        Sanctum::actingAs($this->bareAgent());

        $this->getJson('/api/v1/admin/reviews')->assertStatus(403);
    }

    public function test_la_liste_des_prestataires_filtre_par_statut(): void
    {
        Provider::factory()->count(2)->create(['status' => 'valide']);
        Provider::factory()->create(['status' => 'suspendu']);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/providers')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'business_name', 'status', 'rating_avg', 'warnings_count', 'sanction_note']]]);

        $this->getJson('/api/v1/admin/providers?status=suspendu')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'suspendu');
    }

    public function test_un_agent_sans_valider_prestataire_est_refuse_sur_la_liste(): void
    {
        Sanctum::actingAs($this->bareAgent());

        $this->getJson('/api/v1/admin/providers')->assertStatus(403);
    }
}
