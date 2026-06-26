<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de la policy d'accès aux comptes (phase B1.5) :
 * chacun n'accède qu'à ses données, sauf admin / super_admin.
 */
class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_un_utilisateur_accede_a_lui_meme_mais_pas_aux_autres(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->assertTrue($a->can('viewProfile', $a));
        $this->assertFalse($a->can('viewProfile', $b));
        $this->assertFalse($a->can('updateProfile', $b));
    }

    public function test_un_admin_accede_aux_autres_comptes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);
        $autre = User::factory()->create();

        $this->assertTrue($admin->can('viewProfile', $autre));
        $this->assertTrue($admin->can('manageDocuments', $autre));
    }

    public function test_le_super_admin_passe_partout(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);
        $autre = User::factory()->create();

        $this->assertTrue($superAdmin->can('viewProfile', $autre));
    }
}
