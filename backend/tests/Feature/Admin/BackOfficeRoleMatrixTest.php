<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests B13.6 : consolidation des policies Agent / Admin / Super Admin.
 *
 * Verrouille la matrice de droits du back-office pour éviter toute dérive :
 *   - l'AGENT est opérationnel (validation, modération, exploitation) mais
 *     n'accède ni aux comptes, ni aux paiements, ni aux paramètres ;
 *   - l'ADMIN dispose de l'ensemble du back-office ;
 *   - le SUPER ADMIN court-circuite toute autorisation (Gate::before).
 */
class BackOfficeRoleMatrixTest extends TestCase
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

    public function test_l_agent_a_l_acces_seul_par_defaut_puis_traite_ce_qu_on_lui_delegue(): void
    {
        // F7.1.b : le rôle agent n'ouvre QUE l'accès au back-office.
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        $this->assertTrue($agent->can('consulter:dashboard-admin'), "L'agent doit accéder au back-office");

        // Aucun droit de traitement tant qu'on ne lui délègue rien.
        foreach (['valider:bien', 'valider:vehicule', 'moderer:avis', 'gerer:nuitees'] as $denied) {
            $this->assertFalse($agent->can($denied), "L'agent ne devrait pas avoir {$denied} par défaut");
        }

        // Le super admin délègue deux dossiers : l'agent ne traite QUE ceux-là.
        $agent->givePermissionTo(['valider:bien', 'gerer:nuitees']);
        $agent = $agent->fresh();
        $this->assertTrue($agent->can('valider:bien'));
        $this->assertTrue($agent->can('gerer:nuitees'));
        $this->assertFalse($agent->can('valider:vehicule'));

        // Le périmètre de gouvernance reste hors de portée d'un agent.
        foreach (['gerer:utilisateurs', 'gerer:paiements', 'gerer:parametres'] as $denied) {
            $this->assertFalse($agent->can($denied), "L'agent ne devrait pas avoir {$denied}");
        }
    }

    public function test_l_admin_a_tout_le_back_office(): void
    {
        $admin = $this->withRole(UserRole::ADMIN->value);

        foreach (['gerer:utilisateurs', 'gerer:paiements', 'gerer:parametres', 'consulter:dashboard-admin', 'gerer:nuitees'] as $permission) {
            $this->assertTrue($admin->can($permission), "L'admin devrait avoir {$permission}");
        }
    }

    public function test_le_super_admin_court_circuite_toute_autorisation(): void
    {
        $superAdmin = $this->withRole(UserRole::SUPER_ADMIN->value);

        // Même une permission jamais déclarée passe (bypass Gate::before).
        $this->assertTrue($superAdmin->can('gerer:utilisateurs'));
        $this->assertTrue($superAdmin->can('permission:totalement-inventee'));
    }

    /*
    |--------------------------------------------------------------------------
    | F7.4.a — Permissions exposées au frontend (cloisonnement du rail)
    |--------------------------------------------------------------------------
    */

    public function test_l_equipe_recoit_ses_permissions_sur_son_propre_compte(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(['valider:bien', 'gerer:nuitees']);

        $response = $this->actingAs($agent->fresh())->getJson('/api/v1/users/me');

        $response->assertOk();
        $permissions = $response->json('data.user.permissions');

        $this->assertContains('consulter:dashboard-admin', $permissions, 'Accès de base porté par le rôle');
        $this->assertContains('valider:bien', $permissions);
        $this->assertContains('gerer:nuitees', $permissions);
        // Ce qui n'est pas délégué ne doit pas apparaître : c'est ce tableau qui
        // décide des rubriques affichées au rail du back-office.
        $this->assertNotContains('gerer:paiements', $permissions);
        $this->assertNotContains('valider:vehicule', $permissions);
    }

    public function test_le_super_admin_recoit_le_catalogue_complet(): void
    {
        // Cas particulier à ne pas perdre : le super_admin n'a aucune permission
        // ASSIGNÉE (il passe par Gate::before). Sans traitement dédié, le compte
        // le plus puissant se retrouverait avec le rail le plus vide.
        $superAdmin = $this->withRole(UserRole::SUPER_ADMIN->value);

        $permissions = $this->actingAs($superAdmin)
            ->getJson('/api/v1/users/me')
            ->assertOk()
            ->json('data.user.permissions');

        foreach (['gerer:paiements', 'gerer:parametres', 'gerer:utilisateurs', 'valider:bien'] as $permission) {
            $this->assertContains($permission, $permissions);
        }
    }

    public function test_un_compte_hors_equipe_n_a_aucune_permission_exposee(): void
    {
        $client = $this->withRole(UserRole::CLIENT->value);

        $this->actingAs($client)
            ->getJson('/api/v1/users/me')
            ->assertOk()
            // Clé ABSENTE (et non pas vide) : rien à exposer hors back-office.
            ->assertJsonMissingPath('data.user.permissions');
    }
}
