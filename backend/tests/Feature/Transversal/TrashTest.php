<?php

namespace Tests\Feature\Transversal;

use App\Models\Media;
use App\Models\User;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Immo\Enums\PropertyStatus;
use App\Modules\Immo\Models\Property;
use App\Modules\Manage\Enums\MandateStatus;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Mobility\Models\Vehicle;
use App\Support\Trash\ListingTrash;
use Database\Seeders\CommunesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SenegalGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Corbeille des espaces utilisateurs (F11.4).
 *
 * Ce que ces tests protègent, dans l'ordre d'importance :
 *   1. une annonce restaurée ne se remet JAMAIS en ligne toute seule ;
 *   2. une annonce à la corbeille garde ses photos (sinon « restaurer » ment) ;
 *   3. la corbeille est cloisonnée — on ne voit ni ne restaure celle d'un autre ;
 *   4. la purge n'efface qu'après le délai, jamais avant.
 */
class TrashTest extends TestCase
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

    private function prestataire(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::PRESTATAIRE->value);

        return $user;
    }

    // =========================================================================
    // 1. Le geste : ranger sans détruire
    // =========================================================================

    public function test_un_bien_mis_a_la_corbeille_quitte_la_liste_et_y_apparait(): void
    {
        $owner = $this->proprietaire();
        $bien = Property::factory()->create([
            'owner_id' => $owner->id,
            'status' => PropertyStatus::PUBLIE->value,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/properties/{$bien->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        // La ligne existe encore, datée : c'est la corbeille, pas un effacement.
        $this->assertSoftDeleted('properties', ['id' => $bien->id]);

        // Elle a quitté la liste du propriétaire…
        $this->getJson('/api/v1/properties/mine')
            ->assertOk()
            ->assertJsonMissing(['id' => $bien->id]);

        // …et se retrouve dans la corbeille, avec son compte à rebours.
        $this->getJson('/api/v1/me/trash')
            ->assertOk()
            ->assertJsonPath('data.retention_days', ListingTrash::JOURS_DE_CONSERVATION)
            ->assertJsonPath('data.items.0.type', 'property')
            ->assertJsonPath('data.items.0.id', $bien->id)
            ->assertJsonPath('data.items.0.days_left', ListingTrash::JOURS_DE_CONSERVATION);
    }

    /**
     * ⚠️ Le bien à la corbeille doit aussi DISPARAÎTRE DU CATALOGUE PUBLIC.
     * `SoftDeletes` s'en charge tout seul, mais c'est précisément le genre de
     * garantie qu'on ne veut pas découvrir cassée : une annonce rangée qui
     * resterait réservable est le pire des deux mondes.
     */
    public function test_un_bien_a_la_corbeille_sort_du_catalogue_public(): void
    {
        $owner = $this->proprietaire();
        $bien = Property::factory()->create([
            'owner_id' => $owner->id,
            'status' => PropertyStatus::PUBLIE->value,
        ]);

        $this->getJson('/api/v1/properties')->assertOk()->assertJsonFragment(['id' => $bien->id]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/properties/{$bien->id}")->assertOk();

        $this->getJson('/api/v1/properties')->assertOk()->assertJsonMissing(['id' => $bien->id]);
        $this->getJson("/api/v1/properties/{$bien->id}")->assertNotFound();
    }

    // =========================================================================
    // 2. LA RÈGLE DE SÉCURITÉ : ce qui revient, revient ÉTEINT
    // =========================================================================

    public function test_un_bien_restaure_revient_archive_et_non_publie(): void
    {
        $owner = $this->proprietaire();
        $bien = Property::factory()->create([
            'owner_id' => $owner->id,
            'status' => PropertyStatus::PUBLIE->value,
        ]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/properties/{$bien->id}")->assertOk();

        $this->postJson("/api/v1/me/trash/property/{$bien->id}/restore")->assertOk();

        $bien->refresh();

        $this->assertNull($bien->deleted_at, 'Le bien doit être sorti de la corbeille.');

        // ⚠️ LE point du test : il était PUBLIÉ avant d'être rangé, il ne doit
        // PAS l'être en revenant. Entre-temps il a pu être vendu.
        $this->assertSame(PropertyStatus::ARCHIVE->value, $bien->status->value ?? $bien->status);

        // Et il reste donc invisible du public.
        $this->getJson('/api/v1/properties')->assertOk()->assertJsonMissing(['id' => $bien->id]);
    }

    // =========================================================================
    // 3. LES PHOTOS SURVIVENT — sinon « restaurer » est un mensonge
    // =========================================================================

    /**
     * ⚠️ Le défaut que ce test verrouille est réel et a bien failli passer :
     * `OfferRetirementService` effaçait les fichiers AVANT `delete()`. Le jour
     * où `delete()` est devenu un effacement différé, l'offre serait partie à
     * la corbeille sans une seule image — et serait revenue vide, sans recours.
     */
    public function test_une_offre_a_la_corbeille_garde_ses_photos(): void
    {
        $prestataire = $this->prestataire();
        $vehicule = Vehicle::factory()->published()->create(['provider_id' => $prestataire->id]);

        // ⚠️ Par la fabrique, pas par un `create()` à la main : la table porte
        // une `reference` obligatoire sans valeur par défaut, qu'un tableau
        // écrit à la main oublie — le test échouait sur une erreur SQL avant
        // même d'avoir testé quoi que ce soit.
        $media = Media::factory()->create([
            'mediable_type' => $vehicule->getMorphClass(),
            'mediable_id' => $vehicule->id,
            'disk' => 'public',
            'path' => 'vehicles/photo-test.jpg',
        ]);

        Sanctum::actingAs($prestataire);
        $this->deleteJson("/api/v1/vehicles/{$vehicule->id}")->assertOk();

        $this->assertSoftDeleted('vehicles', ['id' => $vehicule->id]);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    // =========================================================================
    // 4. UN OBJET ENGAGÉ NE PART PAS
    // =========================================================================

    public function test_un_bien_sous_mandat_actif_ne_part_pas_a_la_corbeille(): void
    {
        $owner = $this->proprietaire();
        $bien = Property::factory()->create([
            'owner_id' => $owner->id,
            'status' => PropertyStatus::PUBLIE->value,
        ]);

        ManagementMandate::factory()->create([
            'property_id' => $bien->id,
            'status' => MandateStatus::ACTIF->value,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/properties/{$bien->id}")
            ->assertStatus(422);

        // Rien n'a bougé : le bien est toujours bien vivant.
        $this->assertDatabaseHas('properties', ['id' => $bien->id, 'deleted_at' => null]);
    }

    // =========================================================================
    // 5. CLOISONNEMENT — bâti en créant l'annonce d'un TIERS
    // =========================================================================

    /**
     * ⚠️ Bâti avec DEUX propriétaires : un test qui ne vérifierait que « je vois
     * ma corbeille » passerait au vert sur un code qui montre aussi celle du
     * voisin.
     */
    public function test_la_corbeille_ne_montre_que_ses_propres_elements(): void
    {
        $moi = $this->proprietaire();
        $autre = $this->proprietaire();

        $leMien = Property::factory()->create(['owner_id' => $moi->id]);
        $leSien = Property::factory()->create(['owner_id' => $autre->id]);

        Sanctum::actingAs($autre);
        $this->deleteJson("/api/v1/properties/{$leSien->id}")->assertOk();

        Sanctum::actingAs($moi);
        $this->deleteJson("/api/v1/properties/{$leMien->id}")->assertOk();

        $this->getJson('/api/v1/me/trash')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $leMien->id);
    }

    public function test_on_ne_restaure_pas_l_element_d_un_autre(): void
    {
        $moi = $this->proprietaire();
        $autre = $this->proprietaire();
        $leSien = Property::factory()->create(['owner_id' => $autre->id]);

        Sanctum::actingAs($autre);
        $this->deleteJson("/api/v1/properties/{$leSien->id}")->assertOk();

        Sanctum::actingAs($moi);
        $this->postJson("/api/v1/me/trash/property/{$leSien->id}/restore")
            ->assertNotFound();

        // Il est toujours à la corbeille de son propriétaire.
        $this->assertSoftDeleted('properties', ['id' => $leSien->id]);
    }

    // =========================================================================
    // 6. LA PURGE — après le délai, jamais avant
    // =========================================================================

    public function test_la_purge_epargne_ce_qui_est_encore_dans_le_delai(): void
    {
        $owner = $this->proprietaire();
        $bien = Property::factory()->create(['owner_id' => $owner->id]);
        $bien->delete();

        // Jeté hier : bien à l'abri.
        $bien->forceFill(['deleted_at' => now()->subDay()])->saveQuietly();

        $this->artisan('corbeille:purger')->assertExitCode(0);

        $this->assertSoftDeleted('properties', ['id' => $bien->id]);
    }

    public function test_la_purge_efface_definitivement_au_dela_du_delai(): void
    {
        $owner = $this->proprietaire();
        $bien = Property::factory()->create(['owner_id' => $owner->id]);
        $bien->delete();

        $bien->forceFill([
            'deleted_at' => now()->subDays(ListingTrash::JOURS_DE_CONSERVATION + 1),
        ])->saveQuietly();

        $this->artisan('corbeille:purger')->assertExitCode(0);

        $this->assertDatabaseMissing('properties', ['id' => $bien->id]);
    }
}
