<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Models\Property;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests de la correction et de l'archivage d'un bien au back-office (F7.3.g).
 *
 * Solde la dette CDC §15 « un admin peut modifier » : valider et publier
 * existaient depuis B2.4, modifier et archiver n'avaient aucune route côté
 * back-office.
 *
 * Périmètre arbitré : corriger et archiver, tout étant tracé. Ni création à la
 * place d'un propriétaire, ni réattribution à un autre compte.
 */
class AdminPropertyEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    public function test_un_agent_corrige_le_titre_et_le_prix_d_un_bien(): void
    {
        $property = Property::factory()->create([
            'title' => 'vila a vendr dakar',
            'price_xof' => 50_000_000,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/properties/{$property->id}", [
            'title' => 'Villa à vendre — Dakar',
            'price_xof' => 55_000_000,
        ])->assertOk()
            ->assertJsonPath('data.property.title', 'Villa à vendre — Dakar');

        $this->assertSame(55_000_000, $property->fresh()->price_xof);
    }

    public function test_la_correction_est_tracee_avec_l_avant_apres(): void
    {
        $property = Property::factory()->create(['title' => 'Ancien titre']);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/properties/{$property->id}", [
            'title' => 'Nouveau titre',
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Property::class,
            'subject_id' => $property->id,
            'description' => 'Correction de bien (back-office)',
        ]);
    }

    public function test_le_bien_reste_a_son_proprietaire(): void
    {
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);

        Sanctum::actingAs($this->agent());

        // Une tentative de réattribution passe par un champ non autorisé : il est
        // simplement ignoré (les règles ne l'acceptent pas).
        $this->patchJson("/api/v1/admin/properties/{$property->id}", [
            'title' => 'Titre corrigé',
            'owner_id' => User::factory()->create()->id,
        ])->assertOk();

        $this->assertSame($owner->id, $property->fresh()->owner_id);
    }

    public function test_le_statut_ne_se_change_pas_par_la_correction(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::PUBLIE->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/properties/{$property->id}", [
            'title' => 'Titre corrigé',
            'status' => PropertyStatus::ARCHIVE->value,
        ])->assertOk();

        // Le statut relève de la file de validation et de l'archivage, qui tracent
        // chacun leur décision.
        $this->assertSame(PropertyStatus::PUBLIE, $property->fresh()->status);
    }

    public function test_un_agent_archive_un_bien_avec_motif(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::PUBLIE->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/properties/{$property->id}/archive", [
            'reason' => 'Annonce en doublon.',
        ])->assertOk()
            ->assertJsonPath('data.property.status', PropertyStatus::ARCHIVE->value);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Property::class,
            'subject_id' => $property->id,
            'description' => 'Archivage de bien',
        ]);
    }

    public function test_un_bien_deja_archive_ne_se_rearchive_pas(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::ARCHIVE->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/properties/{$property->id}/archive")
            ->assertStatus(422);
    }

    public function test_sortir_de_l_archive_renvoie_en_file_de_validation(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::ARCHIVE->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/properties/{$property->id}/restore")
            ->assertOk()
            // JAMAIS directement publié : le bien a pu être archivé pour un contenu
            // problématique, le republier d'un clic le remettrait en ligne.
            ->assertJsonPath('data.property.status', PropertyStatus::EN_ATTENTE_VALIDATION->value);
    }

    public function test_un_bien_non_archive_ne_se_restaure_pas(): void
    {
        $property = Property::factory()->create([
            'status' => PropertyStatus::PUBLIE->value,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/properties/{$property->id}/restore")
            ->assertStatus(422);
    }

    public function test_un_compte_sans_valider_bien_est_refuse(): void
    {
        $property = Property::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/admin/properties/{$property->id}", ['title' => 'Pirate'])
            ->assertStatus(403);
        $this->patchJson("/api/v1/admin/properties/{$property->id}/archive")
            ->assertStatus(403);
    }

    public function test_la_coherence_geographique_reste_verifiee(): void
    {
        $property = Property::factory()->create();

        Sanctum::actingAs($this->agent());

        // Département inexistant : les règles du propriétaire s'appliquent telles
        // quelles côté back-office.
        $this->patchJson("/api/v1/admin/properties/{$property->id}", [
            'department_id' => 999_999,
        ])->assertStatus(422);
    }
}
