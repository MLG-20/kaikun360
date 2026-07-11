<?php

namespace Tests\Feature\Admin;

use App\Models\Region;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Mobility\Services\CommissionCalculator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.4 : paramétrage global (GET/PATCH /admin/settings) et nomenclatures
 * de référence (GET /admin/reference).
 *
 * Vérifie l'accès (gerer:parametres), les valeurs par défaut, la surcharge d'un
 * réglage et sa RÉPERCUSSION sur le calcul de commission, ainsi que les
 * garde-fous (clé inconnue, valeur non numérique).
 */
class SettingsTest extends TestCase
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

    public function test_l_acces_est_reserve_a_gerer_parametres(): void
    {
        // L'agent a consulter:dashboard-admin mais PAS gerer:parametres.
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/settings')->assertStatus(403);
    }

    public function test_liste_les_reglages_avec_leurs_valeurs_par_defaut(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'commission.default_rate',
                'value' => 12.0,
                'overridden' => false,
            ]);
    }

    public function test_surcharge_un_reglage_et_impacte_le_calcul_de_commission(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Avant : taux par défaut 12 % → 120 sur 1000.
        $this->assertSame(120, app(CommissionCalculator::class)->commissionFor(1000));

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['commission.default_rate' => 20],
        ])
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'commission.default_rate',
                'value' => 20.0,
                'overridden' => true,
            ]);

        // Après : le calculateur lit le nouveau taux 20 % → 200 sur 1000.
        $this->assertSame(200, app(CommissionCalculator::class)->commissionFor(1000));
    }

    public function test_refuse_une_cle_inconnue(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['fantome.inconnu' => 42],
        ])->assertStatus(422);
    }

    public function test_refuse_une_valeur_non_numerique_pour_un_taux(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson('/api/v1/admin/settings', [
            'settings' => ['commission.default_rate' => 'beaucoup'],
        ])->assertStatus(422);
    }

    public function test_la_reference_expose_categories_et_regions(): void
    {
        Region::create(['name' => 'Dakar']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson('/api/v1/admin/reference')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'categories' => ['provider', 'property_type', 'service_type', 'vehicle_type'],
                    'geography' => ['regions'],
                ],
            ])
            ->assertJsonPath('data.geography.regions.0.name', 'Dakar');
    }
}
