<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Explore\Models\TourismExperience;
use App\Modules\Immo\Models\Property;
use App\Modules\Mobility\Models\Vehicle;
use App\Modules\Pro\Models\Provider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests F8.1 : **revue des médias avant publication**.
 *
 * Jusqu'ici un agent validait une annonce sans jamais voir ses photos — il
 * publiait sur le site vitrine à l'aveugle. Cette tranche ajoute :
 *   - la galerie dans la file de validation (aperçu) ;
 *   - le dossier complet `GET /admin/queue/{type}/{id}` (galerie entière) ;
 *   - la modération photo par photo `PATCH /admin/media/{media}/status`.
 *
 * Couvre aussi la dette B12 rattrapée au passage : véhicules et expériences
 * acceptaient des dépôts de médias qu'aucune relation ne relisait.
 */
class ValidationMediaReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Agent disposant de toutes les permissions de validation. */
    private function agent(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo(AdminPermission::operational());

        return $agent;
    }

    /** Agent d'accès back-office SANS aucune permission de validation fine. */
    private function agentSansMandat(): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(UserRole::AGENT_KAIKUN->value);
        $agent->givePermissionTo('consulter:dashboard-admin');

        return $agent;
    }

    /** Attache des médias à une ressource. */
    private function mediaFor(object $model, int $count = 1, string $state = 'default'): void
    {
        $factory = Media::factory()->count($count);

        $factory = match ($state) {
            'video' => $factory->video(),
            'hidden' => $factory->hidden(),
            default => $factory,
        };

        $factory->create([
            'mediable_type' => $model::class,
            'mediable_id' => $model->getKey(),
        ]);
    }

    // --- La file transporte la galerie ---------------------------------------

    public function test_la_file_expose_la_galerie_de_chaque_element(): void
    {
        $property = Property::factory()->create(['status' => 'en_attente_validation']);
        $this->mediaFor($property, 3);
        $this->mediaFor($property, 1, 'video');

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/queue?type=property')
            ->assertOk()
            ->assertJsonPath('data.0.media.total', 4)
            ->assertJsonPath('data.0.media.images', 3)
            ->assertJsonPath('data.0.media.videos', 1)
            ->assertJsonStructure([
                'data' => [
                    ['media' => ['total', 'images', 'videos', 'hidden', 'items' => [
                        ['id', 'type', 'url', 'status', 'is_hidden', 'is_primary'],
                    ]]],
                ],
            ]);
    }

    public function test_l_apercu_de_la_file_est_borne_mais_le_compteur_reste_complet(): void
    {
        // On ne veut pas charger 40 vignettes par ligne de file : l'aperçu est
        // borné, mais l'agent doit voir qu'il reste des photos à examiner.
        $property = Property::factory()->create(['status' => 'en_attente_validation']);
        $this->mediaFor($property, 9);

        Sanctum::actingAs($this->agent());

        $response = $this->getJson('/api/v1/admin/queue?type=property')->assertOk();

        $this->assertSame(9, $response->json('data.0.media.total'));
        $this->assertCount(4, $response->json('data.0.media.items'));
    }

    public function test_un_prestataire_n_a_pas_de_galerie_mais_garde_la_meme_forme(): void
    {
        // Provider est absent de Media::TYPES : la clé doit rester présente pour
        // que le front n'ait pas à traiter un onglet à part.
        Provider::factory()->create(['status' => 'en_attente']);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/queue?type=provider')
            ->assertOk()
            ->assertJsonPath('data.0.media.total', 0)
            ->assertJsonPath('data.0.media.items', []);
    }

    // --- Dette B12 : véhicules et expériences relisent enfin leurs médias -----

    public function test_les_vehicules_et_experiences_exposent_aussi_leur_galerie(): void
    {
        // Ces deux types acceptaient déjà des dépôts via POST media/upload, mais
        // aucune relation ne les relisait (« sera branchée en B12 »).
        $vehicle = Vehicle::factory()->create(['status' => 'en_attente_validation']);
        $this->mediaFor($vehicle, 2);

        $experience = TourismExperience::factory()->create(['status' => 'en_attente_validation']);
        $this->mediaFor($experience, 5);

        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/queue?type=vehicle')
            ->assertOk()
            ->assertJsonPath('data.0.media.total', 2);

        $this->getJson('/api/v1/admin/queue?type=experience')
            ->assertOk()
            ->assertJsonPath('data.0.media.total', 5);
    }

    public function test_la_galerie_publique_exclut_les_medias_masques(): void
    {
        $property = Property::factory()->create(['status' => 'publie']);
        $this->mediaFor($property, 2);
        $this->mediaFor($property, 1, 'hidden');

        // `media()` = ce que voit le public ; `allMedia()` = ce que voit l'agent.
        $this->assertCount(2, $property->media()->get());
        $this->assertCount(3, $property->allMedia()->get());
    }

    // --- Dossier complet ------------------------------------------------------

    public function test_le_dossier_complet_renvoie_toute_la_galerie_et_les_caracteristiques(): void
    {
        $property = Property::factory()->create([
            'status' => 'en_attente_validation',
            'price_xof' => 45_000_000,
        ]);
        $this->mediaFor($property, 7);

        Sanctum::actingAs($this->agent());

        $response = $this->getJson("/api/v1/admin/queue/property/{$property->id}")
            ->assertOk()
            ->assertJsonPath('data.is_pending', true)
            ->assertJsonPath('data.entry.media.total', 7)
            ->assertJsonPath('data.entry.fields.Prix', 45_000_000);

        // Contrairement à la file, le détail n'est pas borné à 4 vignettes.
        $this->assertCount(7, $response->json('data.entry.media.items'));
    }

    public function test_le_dossier_complet_montre_les_medias_masques_a_l_agent(): void
    {
        // Sans ça, un agent ne pourrait jamais réafficher une photo qu'il a
        // écartée : elle disparaîtrait aussi de son propre écran.
        $property = Property::factory()->create(['status' => 'en_attente_validation']);
        $this->mediaFor($property, 1);
        $this->mediaFor($property, 2, 'hidden');

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/queue/property/{$property->id}")
            ->assertOk()
            ->assertJsonPath('data.entry.media.total', 3)
            ->assertJsonPath('data.entry.media.hidden', 2);
    }

    public function test_le_dossier_reste_consultable_apres_decision(): void
    {
        // Un agent doit pouvoir rouvrir un dossier qu'il vient de trancher.
        $property = Property::factory()->create(['status' => 'publie']);

        Sanctum::actingAs($this->agent());

        $this->getJson("/api/v1/admin/queue/property/{$property->id}")
            ->assertOk()
            ->assertJsonPath('data.is_pending', false);
    }

    public function test_le_dossier_refuse_un_type_inconnu_et_un_id_introuvable(): void
    {
        Sanctum::actingAs($this->agent());

        $this->getJson('/api/v1/admin/queue/licorne/1')->assertStatus(404);
        $this->getJson('/api/v1/admin/queue/property/999999')->assertStatus(404);
    }

    public function test_le_dossier_est_ferme_hors_back_office(): void
    {
        $property = Property::factory()->create(['status' => 'en_attente_validation']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/admin/queue/property/{$property->id}")->assertStatus(403);
    }

    // --- Modération photo par photo ------------------------------------------

    public function test_un_agent_masque_puis_reaffiche_une_photo(): void
    {
        $property = Property::factory()->create(['status' => 'en_attente_validation']);
        $media = Media::factory()->create([
            'mediable_type' => Property::class,
            'mediable_id' => $property->id,
        ]);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/media/{$media->id}/status", ['status' => 'masque'])
            ->assertOk()
            ->assertJsonPath('data.media.status', 'masque')
            ->assertJsonPath('data.media.is_hidden', true);

        $this->assertSame(MediaStatus::MASQUE, $media->refresh()->status);

        // Le média sort des annonces publiques sans être supprimé.
        $this->assertCount(0, $property->media()->get());
        $this->assertDatabaseHas('media', ['id' => $media->id]);

        $this->patchJson("/api/v1/admin/media/{$media->id}/status", ['status' => 'actif'])
            ->assertOk()
            ->assertJsonPath('data.media.is_hidden', false);

        $this->assertCount(1, $property->fresh()->media()->get());
    }

    public function test_masquer_une_photo_laisse_l_annonce_publiable(): void
    {
        // Tout l'intérêt de la modération fine : écarter UNE photo plutôt que de
        // refuser l'annonce entière et renvoyer le déposant à zéro.
        $property = Property::factory()->create(['status' => 'en_attente_validation']);
        $floue = Media::factory()->create([
            'mediable_type' => Property::class,
            'mediable_id' => $property->id,
        ]);
        $this->mediaFor($property, 2);

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/media/{$floue->id}/status", ['status' => 'masque'])
            ->assertOk();

        $this->patchJson("/api/v1/admin/validate/property/{$property->id}", ['decision' => 'approve'])
            ->assertOk();

        $this->assertSame('publie', $property->refresh()->status->value);
        $this->assertCount(2, $property->media()->get());
    }

    public function test_la_moderation_exige_la_permission_du_type_parent(): void
    {
        $property = Property::factory()->create(['status' => 'en_attente_validation']);
        $media = Media::factory()->create([
            'mediable_type' => Property::class,
            'mediable_id' => $property->id,
        ]);

        // Accès back-office mais aucun mandat de validation : pas de modération.
        Sanctum::actingAs($this->agentSansMandat());

        $this->patchJson("/api/v1/admin/media/{$media->id}/status", ['status' => 'masque'])
            ->assertStatus(403);

        $this->assertSame(MediaStatus::ACTIF, $media->refresh()->status);
    }

    public function test_la_moderation_refuse_un_statut_invalide(): void
    {
        $media = Media::factory()->create();

        Sanctum::actingAs($this->agent());

        $this->patchJson("/api/v1/admin/media/{$media->id}/status", ['status' => 'supprime'])
            ->assertStatus(422);
    }

    // --- Compteurs du catalogue de supervision --------------------------------

    public function test_le_catalogue_compte_les_medias_et_les_masques(): void
    {
        // Sert à repérer une annonce PUBLIÉE sans visuel : sur le site vitrine
        // elle s'affiche vide au client, c'est une anomalie à voir de la liste.
        $avecPhotos = Property::factory()->create(['status' => 'publie']);
        $this->mediaFor($avecPhotos, 3);
        $this->mediaFor($avecPhotos, 1, 'hidden');

        Sanctum::actingAs($this->agent());

        $response = $this->getJson('/api/v1/admin/properties')->assertOk();

        $ligne = collect($response->json('data'))->firstWhere('id', $avecPhotos->id);

        $this->assertSame(4, $ligne['media_count']);
        $this->assertSame(1, $ligne['media_hidden_count']);
    }

    public function test_le_catalogue_signale_une_annonce_sans_aucun_media(): void
    {
        $sansPhoto = Property::factory()->create(['status' => 'publie']);
        $vehicule = Vehicle::factory()->create(['status' => 'publie']);
        $experience = TourismExperience::factory()->create(['status' => 'publie']);

        Sanctum::actingAs($this->agent());

        foreach ([
            ['/api/v1/admin/properties', $sansPhoto->id],
            ['/api/v1/admin/vehicles', $vehicule->id],
            ['/api/v1/admin/experiences', $experience->id],
        ] as [$url, $id]) {
            $ligne = collect($this->getJson($url)->assertOk()->json('data'))->firstWhere('id', $id);

            $this->assertSame(0, $ligne['media_count'], $url);
            $this->assertSame(0, $ligne['media_hidden_count'], $url);
        }
    }

    public function test_la_moderation_est_fermee_hors_back_office(): void
    {
        $media = Media::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/admin/media/{$media->id}/status", ['status' => 'masque'])
            ->assertStatus(403);
    }
}
