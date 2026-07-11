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

    public function test_l_agent_est_operationnel_mais_pas_administrateur(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        // Périmètre opérationnel autorisé.
        foreach (['consulter:dashboard-admin', 'valider:bien', 'valider:vehicule', 'moderer:avis', 'gerer:nuitees'] as $allowed) {
            $this->assertTrue($agent->can($allowed), "L'agent devrait avoir {$allowed}");
        }

        // Périmètre d'administration interdit.
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
