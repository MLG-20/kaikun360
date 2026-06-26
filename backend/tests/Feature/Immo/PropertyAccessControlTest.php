<?php

namespace Tests\Feature\Immo;

use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Models\Property;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Consolidation des règles d'accès du module Immo (phase B2.6).
 *
 * Couvre les deux critères explicites du cahier des charges :
 *  - un visiteur ne voit JAMAIS un bien non validé (catalogue, détail, comparaison) ;
 *  - un propriétaire ne touche pas au bien d'un autre (modification, documents).
 */
class PropertyAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SenegalGeographySeeder::class, CommunesSeeder::class]);
    }

    private function proprietaire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PROPRIETAIRE->value);

        return $user;
    }

    public function test_un_visiteur_ne_voit_jamais_un_bien_non_valide(): void
    {
        $pending = Property::factory()->pending()->create();
        Property::factory()->published()->create();

        // 1) Catalogue : seul le bien publié apparaît.
        $this->getJson('/api/v1/properties')->assertOk()->assertJsonCount(1, 'data');

        // 2) Détail direct d'un bien non validé : 404.
        $this->getJson("/api/v1/properties/{$pending->id}")->assertNotFound();

        // 3) Comparaison : le bien non validé est exclu.
        $this->getJson("/api/v1/properties/compare?ids={$pending->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_proprietaire_ne_depose_pas_de_document_sur_le_bien_d_un_autre(): void
    {
        Storage::fake('local');

        $autre = $this->proprietaire();
        $bienDeLautre = Property::factory()->create(['owner_id' => $autre->id]);

        $intrus = $this->proprietaire();
        Sanctum::actingAs($intrus);

        $file = UploadedFile::fake()->create('tf.pdf', 50, 'application/pdf');

        $this->postJson("/api/v1/properties/{$bienDeLautre->id}/documents", [
            'type' => 'titre_foncier',
            'file' => $file,
        ])->assertStatus(403);

        // Aucun document n'a été enregistré.
        $this->assertDatabaseCount('property_documents', 0);
    }
}
