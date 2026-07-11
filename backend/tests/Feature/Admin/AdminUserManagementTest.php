<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests B13.3 : gestion des comptes par le back-office (GET /admin/users,
 * PATCH /admin/users/{id}).
 *
 * Vérifie l'accès de niveau admin, le filtrage, la mise à jour rôle/statut et
 * les garde-fous de hiérarchie (escalade de privilèges, non-modification de
 * soi-même, protection des comptes super_admin).
 */
class AdminUserManagementTest extends TestCase
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

    public function test_un_agent_sans_gerer_utilisateurs_est_refuse(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/users')->assertStatus(403);
    }

    public function test_l_admin_liste_et_filtre_les_comptes(): void
    {
        $this->withRole(UserRole::CLIENT->value);
        $this->withRole(UserRole::CLIENT->value);
        $this->withRole(UserRole::PROPRIETAIRE->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Sans filtre : les 4 comptes créés (3 + l'admin courant).
        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);

        // Filtre par rôle.
        $this->getJson('/api/v1/admin/users?role=client')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_l_admin_change_le_role_et_le_statut(): void
    {
        $target = $this->withRole(UserRole::CLIENT->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson("/api/v1/admin/users/{$target->id}", [
            'role' => UserRole::AGENT_KAIKUN->value,
            'status' => 'desactive',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.status', 'desactive')
            ->assertJsonPath('data.user.roles.0', 'agent_kaikun');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'desactive']);
        $this->assertTrue($target->fresh()->hasRole(UserRole::AGENT_KAIKUN->value));
    }

    public function test_un_admin_ne_peut_pas_attribuer_un_role_d_administration(): void
    {
        $target = $this->withRole(UserRole::CLIENT->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson("/api/v1/admin/users/{$target->id}", ['role' => UserRole::ADMIN->value])
            ->assertStatus(403);

        $this->assertFalse($target->fresh()->hasRole(UserRole::ADMIN->value));
    }

    public function test_un_super_admin_peut_promouvoir_un_admin(): void
    {
        $target = $this->withRole(UserRole::CLIENT->value);

        Sanctum::actingAs($this->withRole(UserRole::SUPER_ADMIN->value));

        $this->patchJson("/api/v1/admin/users/{$target->id}", ['role' => UserRole::ADMIN->value])
            ->assertOk();

        $this->assertTrue($target->fresh()->hasRole(UserRole::ADMIN->value));
    }

    public function test_on_ne_modifie_pas_son_propre_compte(): void
    {
        $admin = $this->withRole(UserRole::ADMIN->value);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$admin->id}", ['status' => 'desactive'])
            ->assertStatus(403);
    }

    public function test_un_admin_ne_modifie_pas_un_super_admin(): void
    {
        $superAdmin = $this->withRole(UserRole::SUPER_ADMIN->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson("/api/v1/admin/users/{$superAdmin->id}", ['status' => 'suspendu'])
            ->assertStatus(403);
    }

    public function test_role_et_statut_sont_valides(): void
    {
        $target = $this->withRole(UserRole::CLIENT->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // Aucun champ fourni.
        $this->patchJson("/api/v1/admin/users/{$target->id}", [])
            ->assertStatus(422);

        // Valeurs invalides.
        $this->patchJson("/api/v1/admin/users/{$target->id}", ['role' => 'sorcier', 'status' => 'zombie'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role', 'status']);
    }
}
