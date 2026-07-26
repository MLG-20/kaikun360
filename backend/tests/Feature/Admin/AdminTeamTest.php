<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Services\VerificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F7.1.a : gestion de l'équipe back-office (« poste de commandement »).
 *
 * Couvre : l'accès de niveau admin (`gerer:utilisateurs`), l'annuaire limité aux
 * rôles d'équipe, l'enrôlement d'un employé (avec code d'invitation par e-mail),
 * et les garde-fous de hiérarchie (escalade, auto-modification, protection des
 * super_admin, cible hors équipe).
 */
class AdminTeamTest extends TestCase
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

    public function test_un_agent_ne_peut_pas_acceder_a_l_equipe(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::AGENT_KAIKUN->value));

        $this->getJson('/api/v1/admin/team')->assertStatus(403);
    }

    public function test_l_annuaire_ne_liste_que_les_membres_de_l_equipe(): void
    {
        // Comptes publics : ne doivent PAS apparaître.
        $this->withRole(UserRole::CLIENT->value);
        $this->withRole(UserRole::PROPRIETAIRE->value);
        // Membres d'équipe.
        $this->withRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // L'agent + l'admin courant = 2 membres d'équipe (les 2 comptes publics exclus).
        $this->getJson('/api/v1/admin/team')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        // Filtre par rôle.
        $this->getJson('/api/v1/admin/team?role=agent_kaikun')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_un_admin_enrole_un_agent_et_declenche_le_code_d_invitation(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson('/api/v1/admin/team', [
            'name' => 'Awa Diop',
            'email' => 'awa.diop@kaikun.test',
            'role' => UserRole::AGENT_KAIKUN->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.member.role', 'agent_kaikun')
            ->assertJsonPath('data.member.status', UserStatus::EN_ATTENTE_VERIFICATION->value);

        $member = User::where('email', 'awa.diop@kaikun.test')->first();
        $this->assertNotNull($member);
        $this->assertTrue($member->hasRole(UserRole::AGENT_KAIKUN->value));

        // Un code d'invitation (réinitialisation e-mail) a bien été émis.
        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $member->id,
            'purpose' => VerificationService::PURPOSE_PASSWORD_RESET,
            'channel' => VerificationService::CHANNEL_EMAIL,
        ]);
    }

    public function test_un_admin_ne_peut_pas_creer_un_administrateur(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->postJson('/api/v1/admin/team', [
            'name' => 'Nouveau Admin',
            'email' => 'admin2@kaikun.test',
            'role' => UserRole::ADMIN->value,
        ])->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'admin2@kaikun.test']);
    }

    public function test_un_super_admin_peut_creer_un_administrateur(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::SUPER_ADMIN->value));

        $this->postJson('/api/v1/admin/team', [
            'name' => 'Nouveau Admin',
            'email' => 'admin2@kaikun.test',
            'role' => UserRole::ADMIN->value,
        ])->assertCreated();

        $this->assertTrue(
            User::where('email', 'admin2@kaikun.test')->first()->hasRole(UserRole::ADMIN->value)
        );
    }

    public function test_le_role_super_admin_n_est_pas_attribuable(): void
    {
        Sanctum::actingAs($this->withRole(UserRole::SUPER_ADMIN->value));

        $this->postJson('/api/v1/admin/team', [
            'name' => 'Tentative',
            'email' => 'root@kaikun.test',
            'role' => UserRole::SUPER_ADMIN->value,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_un_admin_suspend_un_agent(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson("/api/v1/admin/team/{$agent->id}", ['status' => UserStatus::SUSPENDU->value])
            ->assertOk()
            ->assertJsonPath('data.member.status', UserStatus::SUSPENDU->value);

        $this->assertDatabaseHas('users', ['id' => $agent->id, 'status' => UserStatus::SUSPENDU->value]);
    }

    public function test_on_ne_modifie_pas_un_compte_hors_equipe(): void
    {
        $client = $this->withRole(UserRole::CLIENT->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson("/api/v1/admin/team/{$client->id}", ['status' => UserStatus::SUSPENDU->value])
            ->assertStatus(404);
    }

    public function test_on_ne_modifie_pas_son_propre_compte(): void
    {
        $admin = $this->withRole(UserRole::ADMIN->value);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/team/{$admin->id}", ['status' => UserStatus::SUSPENDU->value])
            ->assertStatus(403);
    }

    public function test_un_admin_ne_touche_pas_un_super_admin(): void
    {
        $superAdmin = $this->withRole(UserRole::SUPER_ADMIN->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson("/api/v1/admin/team/{$superAdmin->id}", ['status' => UserStatus::SUSPENDU->value])
            ->assertStatus(403);
    }

    public function test_un_admin_ne_promeut_pas_un_agent_en_administrateur(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->patchJson("/api/v1/admin/team/{$agent->id}", ['role' => UserRole::ADMIN->value])
            ->assertStatus(403);

        $this->assertFalse($agent->fresh()->hasRole(UserRole::ADMIN->value));
    }

    /*
    |--------------------------------------------------------------------------
    | F7.1.b — Délégation des dossiers (« grant pur par personne »)
    |--------------------------------------------------------------------------
    */

    public function test_un_agent_neuf_n_a_aucun_dossier_delegue(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->getJson("/api/v1/admin/team/{$agent->id}/permissions")
            ->assertOk()
            // Les 12 permissions délégables composent le catalogue.
            ->assertJsonCount(count(AdminPermission::delegable()), 'data.catalog')
            // Aucune n'est cochée au départ.
            ->assertJsonPath('data.granted', []);
    }

    public function test_un_admin_delegue_des_dossiers_operationnels_a_un_agent(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->putJson("/api/v1/admin/team/{$agent->id}/permissions", [
            'permissions' => ['valider:bien', 'gerer:nuitees'],
        ])
            ->assertOk()
            ->assertJsonPath('data.member.direct_permissions', ['gerer:nuitees', 'valider:bien']);

        $agent = $agent->fresh();
        $this->assertTrue($agent->can('valider:bien'));
        $this->assertTrue($agent->can('gerer:nuitees'));
        $this->assertFalse($agent->can('valider:vehicule'));
    }

    public function test_la_delegation_remplace_l_ensemble_des_droits(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(['valider:bien', 'valider:vehicule']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        // On n'envoie plus que 'gerer:nuitees' : les deux droits précédents sautent.
        $this->putJson("/api/v1/admin/team/{$agent->id}/permissions", [
            'permissions' => ['gerer:nuitees'],
        ])->assertOk();

        $agent = $agent->fresh();
        $this->assertTrue($agent->can('gerer:nuitees'));
        $this->assertFalse($agent->can('valider:bien'));
        $this->assertFalse($agent->can('valider:vehicule'));
    }

    public function test_une_liste_vide_retire_tous_les_dossiers(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(['valider:bien']);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->putJson("/api/v1/admin/team/{$agent->id}/permissions", ['permissions' => []])
            ->assertOk()
            ->assertJsonPath('data.member.direct_permissions', []);

        $this->assertFalse($agent->fresh()->can('valider:bien'));
    }

    public function test_un_admin_ne_delegue_pas_une_permission_de_gouvernance(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($this->withRole(UserRole::ADMIN->value));

        $this->putJson("/api/v1/admin/team/{$agent->id}/permissions", [
            'permissions' => ['gerer:paiements'],
        ])->assertStatus(403);

        $this->assertFalse($agent->fresh()->can('gerer:paiements'));
    }

    public function test_un_super_admin_peut_deleguer_une_permission_de_gouvernance(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($this->withRole(UserRole::SUPER_ADMIN->value));

        $this->putJson("/api/v1/admin/team/{$agent->id}/permissions", [
            'permissions' => ['gerer:paiements'],
        ])->assertOk();

        $this->assertTrue($agent->fresh()->can('gerer:paiements'));
    }

    public function test_on_ne_delegue_pas_de_dossiers_a_un_administrateur(): void
    {
        // Un admin a déjà tout : la délégation ne le concerne pas (422).
        $target = $this->withRole(UserRole::ADMIN->value);

        Sanctum::actingAs($this->withRole(UserRole::SUPER_ADMIN->value));

        $this->putJson("/api/v1/admin/team/{$target->id}/permissions", [
            'permissions' => ['valider:bien'],
        ])->assertStatus(422);
    }

    public function test_une_permission_hors_catalogue_est_refusee(): void
    {
        $agent = $this->withRole(UserRole::AGENT_KAIKUN->value);

        Sanctum::actingAs($this->withRole(UserRole::SUPER_ADMIN->value));

        $this->putJson("/api/v1/admin/team/{$agent->id}/permissions", [
            'permissions' => ['consulter:dashboard-admin'], // accès de base, non délégable
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);
    }
}
