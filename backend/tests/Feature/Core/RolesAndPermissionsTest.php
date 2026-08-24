<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\ProfileType;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests des rôles & permissions (phase B1.2).
 *
 * Valident : la création des 9 rôles, la matrice de permissions (agent vs admin),
 * le bypass total du super_admin (Gate::before) et le mapping
 * "type de profil → rôle par défaut".
 */
class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // La base de test est vidée à chaque test : on rejoue le seeder.
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_les_neuf_roles_sont_crees(): void
    {
        $this->assertCount(9, UserRole::cases());

        foreach (UserRole::values() as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_le_super_admin_peut_tout_faire(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

        // Grâce à Gate::before, le super_admin passe toutes les vérifications,
        // y compris une capacité totalement arbitraire.
        $this->assertTrue($superAdmin->can('gerer:utilisateurs'));
        $this->assertTrue($superAdmin->can('capacite-inexistante-quelconque'));
    }

    public function test_agent_a_l_acces_mais_aucun_droit_de_traitement_par_defaut(): void
    {
        // Depuis F7.1.b (« grant pur par personne »), le rôle agent n'ouvre QUE
        // l'accès au back-office : les dossiers à traiter sont délégués un par un.
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);

        $this->assertTrue($agent->can('consulter:dashboard-admin'));
        $this->assertFalse($agent->can('valider:bien'));
        $this->assertFalse($agent->can('gerer:utilisateurs'));

        // Une fois un dossier délégué, l'agent le traite.
        $agent->givePermissionTo('valider:bien');
        $this->assertTrue($agent->fresh()->can('valider:bien'));
    }

    public function test_client_n_a_aucune_permission_d_administration(): void
    {
        $client = User::factory()->create();
        $client->assignRole(UserRole::CLIENT->value);

        $this->assertFalse($client->can('valider:bien'));
        $this->assertFalse($client->can('consulter:dashboard-admin'));
    }

    public function test_mapping_type_profil_vers_role_par_defaut(): void
    {
        $this->assertSame(UserRole::CLIENT, UserRole::defaultForProfileType(ProfileType::CLIENT));
        // Depuis la séparation de l'espace diaspora, le profil diaspora reçoit son PROPRE rôle.
        $this->assertSame(UserRole::DIASPORA, UserRole::defaultForProfileType(ProfileType::DIASPORA));
        $this->assertSame(UserRole::PROPRIETAIRE, UserRole::defaultForProfileType(ProfileType::PROPRIETAIRE));
        $this->assertSame(UserRole::PRESTATAIRE, UserRole::defaultForProfileType(ProfileType::PRESTATAIRE));
        $this->assertSame(UserRole::ENTREPRISE, UserRole::defaultForProfileType(ProfileType::ENTREPRISE));
    }
}
