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
}
